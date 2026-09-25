<?php

declare(strict_types=1);

$databaseDirectory = __DIR__ . '/../database';
$databasePath = $databaseDirectory . '/library.sqlite';

// Create the database folder when the app is installed on a new machine. 
if (!is_dir($databaseDirectory)) {
    mkdir($databaseDirectory, 0775, true);
}

$database = new PDO('sqlite:' . $databasePath);
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// These tables are created here so the application can start without a separate migration command.
$database->exec(
    'CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK (role IN ("student", "librarian", "admin")),
        user_id TEXT NOT NULL UNIQUE,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

$userColumns = $database->query('PRAGMA table_info(users)')->fetchAll();
// Add columns introduced after the first version without deleting existing accounts.
if (!in_array('is_active', array_column($userColumns, 'name'), true)) {
    $database->exec('ALTER TABLE users ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
}

$database->exec(
    'CREATE TABLE IF NOT EXISTS borrow_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        book_id INTEGER NOT NULL,
        borrow_date TEXT NOT NULL,
        due_date TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "pending" CHECK (status IN ("pending", "approved", "declined", "returned")),
        decline_reason TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (book_id) REFERENCES books(id)
    )'
);

$database->exec(
    'CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        message TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT "info",
        is_read INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )'
);

$database->exec(
    'CREATE TABLE IF NOT EXISTS books (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        author TEXT NOT NULL,
        category TEXT NOT NULL,
        total_copies INTEGER NOT NULL CHECK (total_copies > 0),
        available_copies INTEGER NOT NULL CHECK (available_copies >= 0),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

$borrowRequestColumns = $database->query('PRAGMA table_info(borrow_requests)')->fetchAll();
$borrowRequestColumnNames = array_column($borrowRequestColumns, 'name');

// Keep older local databases compatible with newer circulation features.
if (!in_array('reviewed_at', $borrowRequestColumnNames, true)) {
    $database->exec('ALTER TABLE borrow_requests ADD COLUMN reviewed_at TEXT');
}
if (!in_array('returned_at', $borrowRequestColumnNames, true)) {
    $database->exec('ALTER TABLE borrow_requests ADD COLUMN returned_at TEXT');
}
if (!in_array('purpose', $borrowRequestColumnNames, true)) {
    $database->exec('ALTER TABLE borrow_requests ADD COLUMN purpose TEXT NOT NULL DEFAULT "Coursework"');
}
