# Library Management System

The active browser-testable application is the PHP/SQLite app in this project root.
The live database is `database/library.sqlite`.

## Main files and folders

- `index.php` - authenticated application entry page
- `script.js` and `style.css` - active application frontend
- `auth/` - local session login and logout
- `config/database.php` - SQLite connection and schema initialization
- `database/library.sqlite` - live local database
- `admin_api.php` - profile and account administration API
- `books_api.php` - book catalog API
- `circulation_api.php` - requests, approvals, returns, history, and notifications API
- The current Apache entry point is the project-root `index.php`.

## Module areas

- Student
- Librarian
- Admin
