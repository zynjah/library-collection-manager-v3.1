<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Profile and password actions are available to every authenticated role.
if ($method === 'POST' && $action === 'profile-update') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $payload = is_array($payload) ? $payload : $_POST;
    $name = trim((string) ($payload['name'] ?? ''));

    if ($name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Name cannot be empty.']);
        exit;
    }

    $statement = $database->prepare('UPDATE users SET name = :name WHERE id = :id');
    $statement->execute([':name' => $name, ':id' => (int) $_SESSION['user']['id']]);
    $_SESSION['user']['name'] = $name;
    echo json_encode(['success' => true, 'name' => $name]);
    exit;
}

if ($method === 'POST' && $action === 'password-update') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $payload = is_array($payload) ? $payload : $_POST;
    $currentPassword = (string) ($payload['currentPassword'] ?? '');
    $newPassword = (string) ($payload['newPassword'] ?? '');
    $confirmPassword = (string) ($payload['confirmPassword'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Complete all password fields.']);
        exit;
    }

    if (strlen($newPassword) < 8) {
        http_response_code(422);
        echo json_encode(['error' => 'New password must be at least 8 characters.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(422);
        echo json_encode(['error' => 'New passwords do not match.']);
        exit;
    }

    $statement = $database->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $statement->execute([':id' => (int) $_SESSION['user']['id']]);
    $user = $statement->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Current password is incorrect.']);
        exit;
    }

    $statement = $database->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $statement->execute([
        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => (int) $_SESSION['user']['id'],
    ]);

    echo json_encode(['success' => true]);
    exit;
}

function userRow(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'userId' => $user['user_id'],
        'isActive' => (bool) $user['is_active'],
        'createdAt' => $user['created_at'],
    ];
}

if ($method === 'GET' && $action === 'students') {
    // Staff can inspect students, but account administration remains admin-only below.
    if (!in_array($_SESSION['user']['role'], ['librarian', 'admin'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Staff access required.']);
        exit;
    }

    $search = trim((string) ($_GET['search'] ?? ''));
    $statement = $database->prepare(
        'SELECT id, name, email, role, user_id, is_active, created_at
         FROM users
         WHERE role = "student"
           AND (name LIKE :search OR email LIKE :search OR user_id LIKE :search)
         ORDER BY name COLLATE NOCASE'
    );
    $statement->execute([':search' => '%' . $search . '%']);
    echo json_encode(array_map('userRow', $statement->fetchAll()));
    exit;
}

if ($_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required.']);
    exit;
}

// The remaining account-management actions require administrator privileges.
$payload = json_decode(file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
$action = $_GET['action'] ?? ($payload['action'] ?? '');

function adminError(string $message, int $status = 422): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

if ($method === 'GET' && $action === 'users') {
    $search = trim((string) ($_GET['search'] ?? ''));
    $role = trim((string) ($_GET['role'] ?? ''));
    $query = 'SELECT id, name, email, role, user_id, is_active, created_at FROM users WHERE 1 = 1';
    $parameters = [];

    if ($search !== '') {
        $query .= ' AND (name LIKE :search OR email LIKE :search OR user_id LIKE :search)';
        $parameters[':search'] = '%' . $search . '%';
    }
    if (in_array($role, ['student', 'librarian', 'admin'], true)) {
        $query .= ' AND role = :role';
        $parameters[':role'] = $role;
    }
    $query .= ' ORDER BY role, name COLLATE NOCASE';
    $statement = $database->prepare($query);
    $statement->execute($parameters);
    echo json_encode(array_map('userRow', $statement->fetchAll()));
    exit;
}

if ($method === 'POST' && $action === 'users-create') {
    $name = trim((string) ($payload['name'] ?? ''));
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $password = (string) ($payload['password'] ?? '');
    $role = (string) ($payload['role'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8
        || !in_array($role, ['student', 'librarian', 'admin'], true)) {
        adminError('Enter a name, valid email, role, and password of at least 8 characters.');
    }

    do {
        $userId = (string) random_int(10000, 99999);
        $check = $database->prepare('SELECT COUNT(*) FROM users WHERE user_id = :user_id');
        $check->execute([':user_id' => $userId]);
    } while ((int) $check->fetchColumn() > 0);

    try {
        $statement = $database->prepare(
            'INSERT INTO users (name, email, password_hash, role, user_id)
             VALUES (:name, :email, :password_hash, :role, :user_id)'
        );
        $statement->execute([
            ':name' => $name,
            ':email' => $email,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => $role,
            ':user_id' => $userId,
        ]);
    } catch (PDOException $error) {
        adminError($error->getCode() === '23000' ? 'That email is already in use.' : 'Unable to create account.');
    }

    $user = $database->query('SELECT * FROM users WHERE id = last_insert_rowid()')->fetch();
    echo json_encode(userRow($user));
    exit;
}

if ($method === 'POST' && $action === 'users-update') {
    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim((string) ($payload['name'] ?? ''));
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $role = (string) ($payload['role'] ?? '');
    $password = (string) ($payload['password'] ?? '');

    if ($id === false || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !in_array($role, ['student', 'librarian', 'admin'], true)) {
        adminError('Account details are invalid.');
    }

    $fields = 'name = :name, email = :email, role = :role';
    $parameters = [':name' => $name, ':email' => $email, ':role' => $role, ':id' => $id];
    if ($password !== '') {
        if (strlen($password) < 8) adminError('Password must be at least 8 characters.');
        $fields .= ', password_hash = :password_hash';
        $parameters[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    try {
        $statement = $database->prepare("UPDATE users SET $fields WHERE id = :id");
        $statement->execute($parameters);
    } catch (PDOException $error) {
        adminError($error->getCode() === '23000' ? 'That email is already in use.' : 'Unable to update account.');
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'POST' && $action === 'users-status') {
    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
    $activeValue = $payload['active'] ?? null;
    $active = is_bool($activeValue)
        ? $activeValue
        : filter_var($activeValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($id === false || $active === null) adminError('Invalid account status.');
    if ($id === (int) $_SESSION['user']['id'] && !$active) adminError('You cannot deactivate your own account.');

    $statement = $database->prepare('UPDATE users SET is_active = :active WHERE id = :id');
    $statement->execute([':active' => $active ? 1 : 0, ':id' => $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'POST' && $action === 'users-delete') {
    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false) adminError('Invalid account id.');
    if ($id === (int) $_SESSION['user']['id']) adminError('You cannot delete your own account.');

    $statement = $database->prepare('DELETE FROM users WHERE id = :id');
    $statement->execute([':id' => $id]);
    echo json_encode(['success' => true]);
    exit;
}

adminError('Unsupported admin action.', 405);
