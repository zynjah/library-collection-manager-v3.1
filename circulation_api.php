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

$currentUser = $_SESSION['user'];
$role = $currentUser['role'];
$method = $_SERVER['REQUEST_METHOD'];
$payload = json_decode(file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
$action = $_GET['action'] ?? ($payload['action'] ?? '');

// Keep errors in one JSON format so every frontend action can display them.
function jsonError(string $message, int $status): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function requestRow(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'bookId' => (string) $row['book_id'],
        'bookTitle' => $row['title'],
        'bookAuthor' => $row['author'],
        'borrowerName' => $row['name'],
        'studentId' => $row['user_id'],
        'purpose' => $row['purpose'] ?? 'Coursework',
        'borrowDate' => $row['borrow_date'],
        'dueDate' => $row['due_date'],
        'status' => $row['status'],
        'declineReason' => $row['decline_reason'],
        'createdAt' => $row['created_at'],
        'reviewedAt' => $row['reviewed_at'] ?? null,
        'returnedAt' => $row['returned_at'] ?? null,
    ];
}

function validBorrowingDates(string $borrowDate, string $dueDate): bool
{
    $borrow = DateTimeImmutable::createFromFormat('!Y-m-d', $borrowDate);
    $due = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);

    return $borrow !== false
        && $due !== false
        && $borrow->format('Y-m-d') === $borrowDate
        && $due->format('Y-m-d') === $dueDate
        && $due >= $borrow;
}

if ($method === 'GET' && $action === 'notifications') {
    // Notifications are scoped to the signed-in account.
    $statement = $database->prepare(
        'SELECT id, message, type, is_read, created_at
         FROM notifications WHERE user_id = :user_id
         ORDER BY id DESC'
    );
    $statement->execute([':user_id' => $currentUser['id']]);
    echo json_encode($statement->fetchAll());
    exit;
}

if ($method === 'POST' && $action === 'notifications-read') {
    $notificationId = filter_var($payload['notificationId'] ?? null, FILTER_VALIDATE_INT);
    if ($notificationId === false) {
        jsonError('A valid notification ID is required.', 422);
    }

    $statement = $database->prepare(
        'UPDATE notifications SET is_read = 1
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        ':id' => $notificationId,
        ':user_id' => $currentUser['id'],
    ]);
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'POST' && $action === 'notifications-delete') {
    $notificationId = filter_var($payload['notificationId'] ?? null, FILTER_VALIDATE_INT);
    if ($notificationId === false) {
        jsonError('A valid notification ID is required.', 422);
    }

    $statement = $database->prepare(
        'DELETE FROM notifications WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        ':id' => $notificationId,
        ':user_id' => $currentUser['id'],
    ]);
    echo json_encode(['success' => true]);
    exit;
}
if ($method === 'GET' && $action === 'requests') {
    $query = '
        SELECT br.*, b.title, b.author, u.name, u.user_id
        FROM borrow_requests br
        JOIN books b ON b.id = br.book_id
        JOIN users u ON u.id = br.user_id
    ';

    if ($role === 'student') {
        $query .= ' WHERE br.user_id = :user_id';
    } elseif (in_array($role, ['librarian', 'admin'], true)) {
        $query .= ' WHERE br.status IN ("pending", "approved")';
    } else {
        jsonError('Forbidden.', 403);
    }

    if (in_array($role, ['librarian', 'admin'], true)) {
        // Notify staff once for each pending request.
        $database->exec(
            'INSERT INTO notifications (user_id, message, type)
             SELECT staff.id,
                    requester.name || " requested " || char(34) || book.title || char(34) || ".",
                    "info"
             FROM borrow_requests request
             JOIN books book ON book.id = request.book_id
             JOIN users requester ON requester.id = request.user_id
             CROSS JOIN users staff
             WHERE request.status = "pending"
               AND staff.role IN ("librarian", "admin")
               AND NOT EXISTS (
                   SELECT 1 FROM notifications existing
                   WHERE existing.user_id = staff.id
                     AND existing.message = requester.name || " requested " || char(34) || book.title || char(34) || "."
               )'
        );
    }

    $query .= ' ORDER BY br.id DESC';
    $statement = $database->prepare($query);
    if ($role === 'student') {
        $statement->execute([':user_id' => $currentUser['id']]);
    } else {
        $statement->execute();
    }
    echo json_encode(array_map('requestRow', $statement->fetchAll()));
    exit;
}

if ($method === 'GET' && $action === 'history') {
    $search = trim((string) ($_GET['search'] ?? ''));
    $query = '
        SELECT br.*, b.title, b.author, u.name, u.user_id
        FROM borrow_requests br
        JOIN books b ON b.id = br.book_id
        JOIN users u ON u.id = br.user_id
        WHERE 1 = 1
    ';
    $parameters = [];

    if ($role === 'student') {
        $query .= ' AND br.user_id = :user_id';
        $parameters[':user_id'] = $currentUser['id'];
    } elseif (!in_array($role, ['librarian', 'admin'], true)) {
        jsonError('Forbidden.', 403);
    }

    if ($search !== '') {
        $query .= ' AND (b.title LIKE :search OR u.name LIKE :search OR u.user_id LIKE :search)';
        $parameters[':search'] = '%' . $search . '%';
    }

    $query .= ' ORDER BY COALESCE(br.returned_at, br.reviewed_at, br.created_at) DESC, br.id DESC';
    $statement = $database->prepare($query);
    $statement->execute($parameters);
    echo json_encode(array_map('requestRow', $statement->fetchAll()));
    exit;
}

if ($method === 'GET' && $action === 'student-history') {
    if (!in_array($role, ['librarian', 'admin'], true)) {
        jsonError('Staff access required.', 403);
    }

    $studentId = filter_var($_GET['studentId'] ?? null, FILTER_VALIDATE_INT);
    if ($studentId === false) {
        jsonError('A valid student is required.', 422);
    }

    $statement = $database->prepare(
        'SELECT br.*, b.title, b.author, u.name, u.user_id
         FROM borrow_requests br
         JOIN books b ON b.id = br.book_id
         JOIN users u ON u.id = br.user_id
         WHERE br.user_id = :student_id
         ORDER BY COALESCE(br.returned_at, br.due_date, br.created_at) DESC, br.id DESC'
    );
    $statement->execute([':student_id' => $studentId]);
    echo json_encode(array_map('requestRow', $statement->fetchAll()));
    exit;
}

if ($method === 'POST' && $action === 'request') {
    if ($role !== 'student') {
        jsonError('Only students can request books.', 403);
    }

    $bookId = filter_var($payload['bookId'] ?? null, FILTER_VALIDATE_INT);
    $borrowDate = trim((string) ($payload['borrowDate'] ?? ''));
    $dueDate = trim((string) ($payload['dueDate'] ?? ''));
    $purpose = trim((string) ($payload['purpose'] ?? ''));
    $allowedPurposes = ['Coursework', 'Research', 'Reading', 'Reference', 'Other'];

    if ($bookId === false || !validBorrowingDates($borrowDate, $dueDate) || $purpose === '' || !in_array($purpose, $allowedPurposes, true)) {
        jsonError('A valid purpose is required.', 422);
    }

    $bookStatement = $database->prepare('SELECT * FROM books WHERE id = :id');
    $bookStatement->execute([':id' => $bookId]);
    $book = $bookStatement->fetch();
    if (!$book || (int) $book['available_copies'] < 1) {
        jsonError('This book is no longer available.', 422);
    }

    // Prevent a student from creating another active request for the same book.
    $duplicate = $database->prepare(
        'SELECT COUNT(*) FROM borrow_requests
         WHERE user_id = :user_id AND book_id = :book_id AND status IN ("pending", "approved")'
    );
    $duplicate->execute([':user_id' => $currentUser['id'], ':book_id' => $bookId]);
    if ((int) $duplicate->fetchColumn() > 0) {
        jsonError('You already have an active request for this book.', 422);
    }

    $statement = $database->prepare(
        'INSERT INTO borrow_requests (user_id, book_id, borrow_date, due_date, purpose)
         VALUES (:user_id, :book_id, :borrow_date, :due_date, :purpose)'
    );
    $statement->execute([
        ':user_id' => $currentUser['id'],
        ':book_id' => $bookId,
        ':borrow_date' => $borrowDate,
        ':due_date' => $dueDate,
        ':purpose' => $purpose,
    ]);

    $notificationStatement = $database->prepare(
        'INSERT INTO notifications (user_id, message, type)
         SELECT id, :message, "info"
         FROM users
         WHERE role IN ("librarian", "admin")'
    );
    $notificationStatement->execute([
        ':message' => $currentUser['name'] . ' requested "' . $book['title'] . '" for ' . $purpose . '.',
    ]);

    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'POST' && $action === 'borrow') {
    if (!in_array($role, ['librarian', 'admin'], true)) {
        jsonError('Only librarians and admins can borrow books directly.', 403);
    }

    $bookId = filter_var($payload['bookId'] ?? null, FILTER_VALIDATE_INT);
    $borrowDate = trim((string) ($payload['borrowDate'] ?? ''));
    $dueDate = trim((string) ($payload['dueDate'] ?? ''));
    $purpose = trim((string) ($payload['purpose'] ?? ''));
    $allowedPurposes = ['Coursework', 'Research', 'Reading', 'Reference', 'Other'];

    if ($bookId === false || !validBorrowingDates($borrowDate, $dueDate) || $purpose === '' || !in_array($purpose, $allowedPurposes, true)) {
        jsonError('A valid purpose is required.', 422);
    }

    // Inventory and the approved borrowing record must stay synchronized.
    $database->beginTransaction();
    try {
        $bookStatement = $database->prepare('SELECT * FROM books WHERE id = :id');
        $bookStatement->execute([':id' => $bookId]);
        $book = $bookStatement->fetch();

        if (!$book || (int) $book['available_copies'] < 1) {
            throw new RuntimeException('This book is no longer available.');
        }

        $duplicate = $database->prepare(
            'SELECT COUNT(*) FROM borrow_requests
             WHERE user_id = :user_id AND book_id = :book_id AND status IN ("pending", "approved")'
        );
        $duplicate->execute([
            ':user_id' => $currentUser['id'],
            ':book_id' => $bookId,
        ]);
        if ((int) $duplicate->fetchColumn() > 0) {
            throw new RuntimeException('You already have an active borrowing for this book.');
        }

        $database->prepare(
            'UPDATE books SET available_copies = available_copies - 1
             WHERE id = :id AND available_copies > 0'
        )->execute([':id' => $bookId]);

        $database->prepare(
            'INSERT INTO borrow_requests (user_id, book_id, borrow_date, due_date, status, purpose)
             VALUES (:user_id, :book_id, :borrow_date, :due_date, "approved", :purpose)'
        )->execute([
            ':user_id' => $currentUser['id'],
            ':book_id' => $bookId,
            ':borrow_date' => $borrowDate,
            ':due_date' => $dueDate,
            ':purpose' => $purpose,
        ]);

        $database->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $error) {
        $database->rollBack();
        jsonError($error->getMessage(), 422);
    }
    exit;
}

if ($method === 'POST' && $action === 'return') {
    $requestId = filter_var($payload['requestId'] ?? null, FILTER_VALIDATE_INT);
    if ($requestId === false) {
        jsonError('A valid borrowing ID is required.', 422);
    }

    // Returning a book restores one available copy and records the return atomically.
    $database->beginTransaction();
    try {
        $isStaff = in_array($role, ['librarian', 'admin'], true);
        $statement = $database->prepare(
            'SELECT br.*, b.title
             FROM borrow_requests br
             JOIN books b ON b.id = br.book_id
             WHERE br.id = :id
               AND br.status = "approved"'
             . ($isStaff ? '' : ' AND br.user_id = :user_id')
        );
        $parameters = [':id' => $requestId];
        if (!$isStaff) {
            $parameters[':user_id'] = $currentUser['id'];
        }
        $statement->execute($parameters);
        $request = $statement->fetch();
        if (!$request) {
            throw new RuntimeException('This borrowing is no longer active.');
        }

        $database->prepare(
            'UPDATE borrow_requests SET status = "returned", returned_at = CURRENT_TIMESTAMP WHERE id = :id'
        )->execute([':id' => $requestId]);
        $database->prepare(
            'UPDATE books SET available_copies = MIN(total_copies, available_copies + 1)
             WHERE id = :id'
        )->execute([':id' => $request['book_id']]);

        if ($isStaff) {
            $notificationStatement = $database->prepare(
                'INSERT INTO notifications (user_id, message, type)
                 VALUES (:user_id, :message, "success")'
            );
            $notificationStatement->execute([
                ':user_id' => $request['user_id'],
                ':message' => 'Your return of "' . $request['title'] . '" was recorded.',
            ]);
        } else {
            $notificationStatement = $database->prepare(
                'INSERT INTO notifications (user_id, message, type)
                 SELECT id, :message, "info"
                 FROM users
                 WHERE role IN ("librarian", "admin")'
            );
            $notificationStatement->execute([
                ':message' => $currentUser['name'] . ' returned "' . $request['title'] . '".',
            ]);
        }

        $database->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $error) {
        $database->rollBack();
        jsonError($error->getMessage(), 422);
    }
    exit;
}

if ($method === 'POST' && $action === 'review') {
    if (!in_array($role, ['librarian', 'admin'], true)) {
        jsonError('Only librarians and admins can review requests.', 403);
    }

    $requestId = filter_var($payload['requestId'] ?? null, FILTER_VALIDATE_INT);
    $decision = $payload['decision'] ?? '';
    $reason = trim((string) ($payload['reason'] ?? ''));

    if ($requestId === false || !in_array($decision, ['approve', 'decline'], true)) {
        jsonError('Invalid request review.', 422);
    }

    // Approval changes inventory; declining a request does not.
    $database->beginTransaction();
    try {
        $statement = $database->prepare(
            'SELECT br.*, b.title, b.available_copies
             FROM borrow_requests br JOIN books b ON b.id = br.book_id
             WHERE br.id = :id AND br.status = "pending"'
        );
        $statement->execute([':id' => $requestId]);
        $request = $statement->fetch();
        if (!$request) {
            throw new RuntimeException('Request is no longer pending.');
        }

        if ($decision === 'approve') {
            if ((int) $request['available_copies'] < 1) {
                throw new RuntimeException('No copies are available.');
            }
            $database->prepare(
                'UPDATE books SET available_copies = available_copies - 1 WHERE id = :id'
            )->execute([':id' => $request['book_id']]);
            $database->prepare(
                'UPDATE borrow_requests SET status = "approved", reviewed_at = CURRENT_TIMESTAMP WHERE id = :id'
            )->execute([':id' => $requestId]);
            $message = 'Your request for "' . $request['title'] . '" was approved.';
        } else {
            $database->prepare(
                'UPDATE borrow_requests
                 SET status = "declined", decline_reason = :reason, reviewed_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute([':reason' => $reason !== '' ? $reason : 'The request was declined.', ':id' => $requestId]);
            $message = 'Your request for "' . $request['title'] . '" was declined.';
        }

        $database->prepare(
            'INSERT INTO notifications (user_id, message, type) VALUES (:user_id, :message, :type)'
        )->execute([
            ':user_id' => $request['user_id'],
            ':message' => $message,
            ':type' => $decision === 'approve' ? 'success' : 'warning',
        ]);
        $database->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $error) {
        $database->rollBack();
        jsonError($error->getMessage(), 422);
    }
    exit;
}

jsonError('Unsupported circulation action.', 405);
