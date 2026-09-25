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
$role = $_SESSION['user']['role'];
$canManage = in_array($role, ['librarian', 'admin'], true);

// Decode JSON requests while still supporting standard form submissions.
function requestPayload(): array
{
    $payload = json_decode(file_get_contents('php://input'), true);
    return is_array($payload) ? $payload : $_POST;
}

function bookResponse(array $book): array
{
    return [
        'id' => (string) $book['id'],
        'title' => $book['title'],
        'author' => $book['author'],
        'category' => $book['category'],
        'total' => (int) $book['total_copies'],
        'available' => (int) $book['available_copies'],
    ];
}

if ($method === 'GET') {
    $books = $database->query('SELECT * FROM books ORDER BY title COLLATE NOCASE')->fetchAll();
    echo json_encode(array_map('bookResponse', $books));
    exit;
}

if (!$canManage) {
    http_response_code(403);
    echo json_encode(['error' => 'Only librarians and admins can manage books.']);
    exit;
}

$payload = requestPayload();

if ($method === 'DELETE') {
    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false) {
        http_response_code(422);
        echo json_encode(['error' => 'A valid book ID is required.']);
        exit;
    }

    $statement = $database->prepare('DELETE FROM books WHERE id = :id');
    $statement->execute([':id' => $id]);
    echo json_encode(['success' => true]);
    exit;
}

$title = trim((string) ($payload['title'] ?? ''));
$author = trim((string) ($payload['author'] ?? ''));
$category = trim((string) ($payload['category'] ?? ''));
$total = filter_var($payload['total'] ?? null, FILTER_VALIDATE_INT);
$available = filter_var($payload['available'] ?? null, FILTER_VALIDATE_INT);

if ($title === '' || $author === '' || $category === '' || $total === false || $available === false || $total < 1 || $available < 0 || $available > $total) {
    http_response_code(422);
    echo json_encode(['error' => 'Book details are invalid.']);
    exit;
}

if ($method === 'POST') {
    // Book creation and student notifications must succeed together.
    $database->beginTransaction();
    try {
        $statement = $database->prepare(
            'INSERT INTO books (title, author, category, total_copies, available_copies)
             VALUES (:title, :author, :category, :total, :available)'
        );
        $statement->execute([
            ':title' => $title,
            ':author' => $author,
            ':category' => $category,
            ':total' => $total,
            ':available' => $available,
        ]);

        $book = $database->query('SELECT * FROM books WHERE id = last_insert_rowid()')->fetch();
        $notification = $database->prepare(
            'INSERT INTO notifications (user_id, message, type)
             SELECT id, :message, "info"
             FROM users
             WHERE role = "student"'
        );
        $notification->execute([
            ':message' => 'New book added: "' . $book['title'] . '" by ' . $book['author']
                . '. Total copies: ' . $book['total_copies']
                . '. Available copies: ' . $book['available_copies'] . '.',
        ]);

        $database->commit();
        echo json_encode(bookResponse($book));
    } catch (Throwable $error) {
        $database->rollBack();
        http_response_code(422);
        echo json_encode(['error' => $error->getMessage()]);
    }
    exit;
}

$id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false) {
    http_response_code(422);
    echo json_encode(['error' => 'A valid book ID is required.']);
    exit;
}

if ($method === 'PUT') {
    $statement = $database->prepare(
        'UPDATE books
         SET title = :title, author = :author, category = :category,
             total_copies = :total, available_copies = :available
         WHERE id = :id'
    );
    $statement->execute([
        ':title' => $title,
        ':author' => $author,
        ':category' => $category,
        ':total' => $total,
        ':available' => $available,
        ':id' => $id,
    ]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.']);
