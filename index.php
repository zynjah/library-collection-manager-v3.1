<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: auth/index.php');
    exit;
}

$currentUser = $_SESSION['user'];
$showWelcome = isset($_GET['welcome']) && $_GET['welcome'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Library Collection Manager</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="stylesheet" href="style.css?v=4">
</head>

<body
    data-role="<?= htmlspecialchars($currentUser['role'], ENT_QUOTES, 'UTF-8') ?>"
    data-user-name="<?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>"
    data-user-email="<?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?>"
    data-user-id="<?= htmlspecialchars($currentUser['user_id'], ENT_QUOTES, 'UTF-8') ?>"
    data-show-welcome="<?= $showWelcome ? 'true' : 'false' ?>"
>

<div class="app">

    <?php if ($showWelcome): ?>
        <div class="app-loading" id="appLoading" aria-live="polite">
            <div class="app-loading-mark"></div>
            <p>Loading your library workspace</p>
        </div>
    <?php endif; ?>

    <header class="header">

        <div class="brand">
            <div class="brand-icon">
                <i data-lucide="library-big"></i>
            </div>

            <div>
                <span class="brand-name">LIBRARY</span>
                <span class="brand-subtitle">Collection Manager</span>
            </div>
        </div>

        <nav class="navigation">
            <button class="nav-button" data-view="collection">
                <i data-lucide="library"></i>
                Collection
            </button>

            <button class="nav-button" data-view="borrowed">
                <i data-lucide="book-open-check"></i>
                <?= $currentUser['role'] === 'student' ? 'My Books' : (in_array($currentUser['role'], ['librarian', 'admin'], true) ? 'Requests' : 'Borrowed') ?>
                <span class="nav-badge" id="borrowedBadge">0</span>
            </button>

            <button class="nav-button" data-view="history">
                <i data-lucide="history"></i>
                History
                <span class="nav-badge" id="historyBadge">0</span>
            </button>

            <button class="nav-button" data-view="notifications">
                <i data-lucide="bell"></i>
                Notifications
                <span class="nav-badge" id="notificationBadge">0</span>
            </button>
            <?php if ($currentUser['role'] === 'admin'): ?>
                <button class="nav-button" data-view="accounts">
                    <i data-lucide="users"></i>
                    Accounts
                </button>
            <?php endif; ?>
            <?php if (in_array($currentUser['role'], ['librarian', 'admin'], true)): ?>
                <button class="nav-button" data-view="students">
                    <i data-lucide="graduation-cap"></i>
                    Students
                </button>
            <?php endif; ?>
            <button class="nav-button active" data-view="profile">
                <i data-lucide="user-round"></i>
                Profile
            </button>
            <button class="nav-button" data-view="settings">
                <i data-lucide="settings"></i>
                Settings
            </button>
        </nav>

    </header>


    <main class="main-content">

        <!-- Profile view -->
        <section class="view active-view" id="profileView">
            <section class="page-header profile-header">
                <div>
                    <p class="eyebrow">PROFILE</p>
                    <h1>Account Overview</h1>
                    <p>Manage your personal library details and quick access to your activity.</p>
                </div>
                <a class="session-link logout-button" href="auth/logout.php">
                    <i data-lucide="log-out"></i>
                    Log out
                </a>
            </section>

            <section class="profile-layout">
                <div class="profile-card profile-overview-card">
                    <div class="profile-avatar">
                        <i data-lucide="user"></i>
                    </div>

                    <div class="profile-info">
                        <div class="profile-header-row">
                            <div>
                                <h2 id="profileName"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <p id="profileEmail"><?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <button class="profile-toggle" id="toggleProfileEdit" type="button">Edit Profile</button>
                        </div>

                        <div class="profile-grid">
                            <div class="profile-field">
                                <span>Role</span>
                                <strong id="profileRole"><?= htmlspecialchars(ucfirst($currentUser['role']), ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                            <div class="profile-field">
                                <span>User ID</span>
                                <strong id="profileId"><?= htmlspecialchars($currentUser['user_id'], ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                        </div>

                        <form class="profile-editor hidden" id="profileEditor" method="post">
                            <label for="profileNameInput">Display name</label>
                            <div class="profile-editor-row">
                                <input id="profileNameInput" type="text" value="<?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>" maxlength="80" required>
                                <button class="save-profile-button" type="submit">Save</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="profile-panel-wrap">
                    <section class="profile-panel">
                        <h3>Account Information</h3>
                        <div class="panel-divider"></div>
                        <div class="profile-detail-grid">
                            <div class="detail-row"><span>Username</span><strong><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div class="detail-row"><span>Email</span><strong><?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div class="detail-row"><span>Account Created</span><strong><?= htmlspecialchars(date('M j, Y', strtotime($currentUser['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div class="detail-row"><span>Last Login</span><strong><?= htmlspecialchars(date('M j, Y', time()), ENT_QUOTES, 'UTF-8') ?></strong></div>
                        </div>
                    </section>

                    <section class="profile-panel">
                        <h3>Security</h3>
                        <div class="panel-divider"></div>
                        <div class="security-row">
                            <span>Password</span>
                            <button class="profile-action-button" id="openPasswordModal" type="button">
                                <i data-lucide="lock-keyhole"></i>
                                Change Password
                            </button>
                        </div>
                    </section>

                    <?php if ($currentUser['role'] === 'student'): ?>
                        <section class="profile-panel">
                            <h3>Library Activity</h3>
                            <div class="panel-divider"></div>
                            <div class="mini-metrics">
                                <div class="mini-metric"><span>Borrowed</span><strong id="studentBorrowedCount">0</strong></div>
                                <div class="mini-metric"><span>Pending</span><strong id="studentPendingCount">0</strong></div>
                                <div class="mini-metric"><span>Returned</span><strong id="studentReturnedCount">0</strong></div>
                                <div class="mini-metric"><span>Overdue</span><strong id="studentOverdueCount">0</strong></div>
                            </div>
                        </section>
                    <?php elseif ($currentUser['role'] === 'admin'): ?>
                        <section class="profile-panel">
                            <h3>System Activity</h3>
                            <div class="panel-divider"></div>
                            <div class="mini-metrics admin-mini-metrics">
                                <div class="mini-metric"><span>Students</span><strong id="adminStudentCount">0</strong></div>
                                <div class="mini-metric"><span>Librarians</span><strong id="adminLibrarianCount">0</strong></div>
                                <div class="mini-metric"><span>Books</span><strong id="adminBookCount">0</strong></div>
                                <div class="mini-metric"><span>Pending</span><strong id="adminPendingCount">0</strong></div>
                                <div class="mini-metric"><span>Overdue</span><strong id="adminOverdueCount">0</strong></div>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            </section>
        </section>

        <!-- Settings view -->
        <section class="view" id="settingsView">
            <section class="page-header profile-header">
                <div>
                    <p class="eyebrow">SETTINGS</p>
                    <h1>Notification Preferences</h1>
                    <p>Control the updates you receive from the library system.</p>
                </div>
            </section>

            <section class="settings-list">
                <div class="setting-row">
                    <span>Email Notifications</span>
                    <button class="toggle-pill on" type="button">ON</button>
                </div>
                <div class="setting-row">
                    <span>Borrowing Updates</span>
                    <button class="toggle-pill on" type="button">ON</button>
                </div>
                <div class="setting-row">
                    <span>Due Date Reminders</span>
                    <button class="toggle-pill on" type="button">ON</button>
                </div>
                <div class="setting-row">
                    <span>System Announcements</span>
                    <button class="toggle-pill on" type="button">ON</button>
                </div>
            </section>
        </section>

        <!-- Collection view -->
        <section class="view" id="collectionView">

            <section class="hero">

                <div class="hero-content">
                    <p class="eyebrow" id="roleEyebrow">YOUR COLLECTION</p>

                    <h1 id="roleHeroTitle">
                        Every book has
                        <em>its place.</em>
                    </h1>

                    <p class="hero-description" id="roleHeroDescription">
                        Manage your library collection, track available copies,
                        and monitor every borrowed book.
                    </p>
                </div>

                <div class="summary-card">

                    <div class="summary-top">
                        <span id="roleSummaryLabel">COLLECTION OVERVIEW</span>
                        <i data-lucide="book-open"></i>
                    </div>

                    <div class="summary-number">
                        <span id="roleSummaryValue">0</span>
                    </div>

                    <p id="roleSummaryHint">Total unique books in your collection</p>

                </div>

            </section>

            <section class="role-board" id="roleBoard"></section>

            <section class="collection-header">

                <div>
                    <p class="eyebrow">CATALOG</p>
                    <h2>Books</h2>
                </div>

                <div class="book-count">
                    <span id="bookCount">0</span> books
                </div>

            </section>


            <section class="controls">

                <div class="search-container">
                    <i data-lucide="search"></i>

                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Search by title or author..."
                    >
                </div>

                <select id="categoryFilter">
                    <option value="all">All Categories</option>
                </select>

                <select id="availabilityFilter">
                    <option value="all">All Availability</option>
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                </select>

                <?php if (in_array($currentUser['role'], ['librarian', 'admin'], true)): ?>
                    <button class="add-book-button collection-add-button" id="openAddModal">
                        <i data-lucide="plus"></i>
                        <span>Add Book</span>
                    </button>
                <?php endif; ?>

            </section>


            <section class="book-list" id="bookList"></section>


            <section class="empty-state" id="emptyState">

                <div class="empty-icon">
                    <i data-lucide="book-open"></i>
                </div>

                <h3>Your library is empty</h3>

                <p>
                    Start building your collection by adding your first book.
                </p>

                <?php if (in_array($currentUser['role'], ['librarian', 'admin'], true)): ?>
                    <button class="add-book-button" id="emptyAddButton">
                        <i data-lucide="plus"></i>
                        Add your first book
                    </button>
                <?php endif; ?>

            </section>

        </section>

        <?php if ($currentUser['role'] === 'admin'): ?>
            <section class="view" id="accountsView">
                <section class="page-header">
                    <div>
                        <p class="eyebrow">ADMINISTRATION</p>
                        <h1>Accounts</h1>
                        <p>Manage student, librarian, and admin accounts.</p>
                    </div>
                    <button class="add-book-button" id="openAccountModal">
                        <i data-lucide="user-plus"></i>
                        Add account
                    </button>
                </section>
                <section class="controls">
                    <div class="search-container">
                        <i data-lucide="search"></i>
                        <input type="text" id="accountSearch" placeholder="Search name, email, or user ID...">
                    </div>
                    <select id="accountRoleFilter">
                        <option value="">All roles</option>
                        <option value="student">Students</option>
                        <option value="librarian">Librarians</option>
                        <option value="admin">Admins</option>
                    </select>
                </section>
                <section class="transaction-list" id="accountList"></section>
            </section>
        <?php endif; ?>

        <?php if (in_array($currentUser['role'], ['librarian', 'admin'], true)): ?>
            <section class="view" id="studentsView">
                <section class="page-header">
                    <div>
                        <p class="eyebrow">STUDENT DIRECTORY</p>
                        <h1>Students</h1>
                        <p>View each student's borrowing activity and circulation status.</p>
                    </div>
                </section>
                <section class="controls">
                    <div class="search-container">
                        <i data-lucide="search"></i>
                        <input type="text" id="studentSearch" placeholder="Search student name, email, or ID...">
                    </div>
                </section>
                <section class="transaction-list" id="studentList"></section>
                <section class="empty-state" id="noStudents">
                    <div class="empty-icon"><i data-lucide="graduation-cap"></i></div>
                    <h3>No students found</h3>
                    <p>Student accounts will appear here.</p>
                </section>
            </section>
        <?php endif; ?>

        <!-- BORROWED VIEW -->
        <section class="view" id="borrowedView">

            <section class="page-header">

                <div>
                    <p class="eyebrow">CIRCULATION</p>
                    <h1><?= $currentUser['role'] === 'student' ? 'My Books' : (in_array($currentUser['role'], ['librarian', 'admin'], true) ? 'Borrow Requests' : 'Active Borrowings') ?></h1>

                    <p><?= $currentUser['role'] === 'student' ? 'Track your requests, active borrowings, and returned books.' : (in_array($currentUser['role'], ['librarian', 'admin'], true) ? 'Review student requests and monitor active circulation.' : 'Books currently borrowed from your library.') ?></p>
                </div>

                <?php if ($currentUser['role'] !== 'student'): ?>
                    <div class="page-number">
                        <span id="activeBorrowingCount">0</span>
                        active
                    </div>
                <?php endif; ?>

            </section>

            <?php if ($currentUser['role'] === 'student'): ?>
                <section class="my-books-panel">
                    <div class="my-books-tabs" role="tablist" aria-label="My Books Filters">
                        <button class="my-books-tab active" type="button" data-my-books-tab="pending">
                            <span>PENDING</span>
                            <strong id="pendingTabCount">0</strong>
                        </button>
                        <button class="my-books-tab" type="button" data-my-books-tab="borrowed">
                            <span>BORROWED</span>
                            <strong id="borrowedTabCount">0</strong>
                        </button>
                        <button class="my-books-tab" type="button" data-my-books-tab="returned">
                            <span>RETURNED</span>
                            <strong id="returnedTabCount">0</strong>
                        </button>
                    </div>

                    <div class="my-books-content">
                        <section class="my-books-panel-section active" data-my-books-panel="pending">
                            <div class="my-books-section-header">
                                <h2>Pending Requests</h2>
                            </div>
                            <div class="student-my-books-list" id="studentPendingList"></div>
                            <div class="empty-state small-empty" id="studentPendingEmpty">
                                <div class="empty-icon"><i data-lucide="clock-3"></i></div>
                                <h3>No pending requests</h3>
                                <p>You have no books waiting for approval.</p>
                            </div>
                        </section>

                        <section class="my-books-panel-section" data-my-books-panel="borrowed">
                            <div class="my-books-section-header">
                                <h2>Currently Borrowed</h2>
                            </div>
                            <div class="student-my-books-list" id="studentBorrowedList"></div>
                            <div class="empty-state small-empty" id="studentBorrowedEmpty">
                                <div class="empty-icon"><i data-lucide="book-check"></i></div>
                                <h3>No active borrowings</h3>
                                <p>You currently have no borrowed books.</p>
                            </div>
                        </section>

                        <section class="my-books-panel-section" data-my-books-panel="returned">
                            <div class="my-books-section-header">
                                <h2>Returned Books</h2>
                            </div>
                            <div class="student-my-books-list" id="studentReturnedList"></div>
                            <div class="empty-state small-empty" id="studentReturnedEmpty">
                                <div class="empty-icon"><i data-lucide="history"></i></div>
                                <h3>No returned books</h3>
                                <p>Your returned titles will appear here.</p>
                            </div>
                        </section>
                    </div>
                </section>
            <?php else: ?>
                <?php if (in_array($currentUser['role'], ['librarian', 'admin'], true)): ?>
                    <section class="request-section">
                        <div class="section-heading">
                            <div>
                                <p class="eyebrow">REVIEW</p>
                                <h2>Borrow Requests</h2>
                            </div>
                        </div>
                        <section class="transaction-list" id="pendingRequestList"></section>
                        <section class="empty-state" id="noPendingRequests">
                            <div class="empty-icon"><i data-lucide="inbox"></i></div>
                            <h3>No pending requests</h3>
                            <p>New student requests will appear here.</p>
                        </section>
                    </section>
                <?php endif; ?>

                <section class="transaction-list" id="activeBorrowingList"></section>

                <section class="empty-state" id="noBorrowings">
                    <div class="empty-icon">
                        <i data-lucide="book-check"></i>
                    </div>

                    <h3>No active borrowings</h3>

                    <p>
                        All books are currently in the library.
                    </p>
                </section>
            <?php endif; ?>

        </section>


        <!-- History view -->
        <section class="view" id="historyView">

            <section class="page-header">

                <div>
                    <p class="eyebrow">RECORDS</p>
                    <h1>Borrowing History</h1>

                    <p>
                        A complete record of library circulation.
                    </p>
                </div>

            </section>

            <section class="history-controls">

                <div class="search-container">
                    <i data-lucide="search"></i>

                    <input
                        type="text"
                        id="historySearch"
                        placeholder="<?= in_array($currentUser['role'], ['librarian', 'admin'], true) ? 'Search books, student names, or IDs...' : 'Search your books...' ?>"
                    >
                </div>

                <select id="historyFilter">
                    <option value="all">All Transactions</option>
                    <option value="borrowed">Currently Borrowed</option>
                    <option value="returned">Returned</option>
                    <option value="overdue">Overdue</option>
                </select>

            </section>


            <section class="history-list" id="historyList"></section>


            <section class="empty-state" id="noHistory">

                <div class="empty-icon">
                    <i data-lucide="history"></i>
                </div>

                <h3>No transaction history</h3>

                <p>
                    Borrowing activity will appear here.
                </p>

            </section>

        </section>

        <!-- Notifications view -->
        <section class="view" id="notificationsView">

            <section class="page-header">
                <div>
                    <p class="eyebrow">UPDATES</p>
                    <h1>Notifications</h1>
                    <p>Messages related to your account and borrowing activity.</p>
                </div>
            </section>

            <section class="notification-list" id="notificationList"></section>

            <section class="empty-state" id="noNotifications">
                <div class="empty-icon"><i data-lucide="bell-off"></i></div>
                <h3>No notifications</h3>
                <p>You are all caught up.</p>
            </section>

        </section>

    </main>

</div>


<!-- Add or edit book modal -->
<div class="modal-overlay" id="bookModal">

    <div class="modal">

        <div class="modal-header">

            <div>
                <p class="eyebrow">COLLECTION</p>
                <h2 id="modalTitle">Add a Book</h2>
            </div>

            <button class="close-modal" id="closeModal">
                <i data-lucide="x"></i>
            </button>

        </div>


        <form id="bookForm">

            <input type="hidden" id="bookId">

            <div class="form-group">
                <label>Book Title</label>

                <input
                    type="text"
                    id="bookTitle"
                    placeholder="e.g. Clean Code"
                    required
                >
            </div>


            <div class="form-row">

                <div class="form-group">
                    <label>Author</label>

                    <input
                        type="text"
                        id="bookAuthor"
                        required
                    >
                </div>


                <div class="form-group">
                    <label>Category</label>

                    <input
                        type="text"
                        id="bookCategory"
                        placeholder="e.g. Programming"
                        required
                    >
                </div>

            </div>


            <div class="form-row">

                <div class="form-group">
                    <label>Total Copies</label>

                    <input
                        type="number"
                        id="bookTotal"
                        min="1"
                        value="1"
                        required
                    >
                </div>


                <div class="form-group">
                    <label>Available Copies</label>

                    <input
                        type="number"
                        id="bookAvailable"
                        min="0"
                        value="1"
                        required
                    >
                </div>

            </div>


            <div class="form-actions">

                <button
                    type="button"
                    class="cancel-button"
                    id="cancelModal"
                >
                    Cancel
                </button>

                <button type="submit" class="save-button">
                    <span id="saveButtonText">Add Book</span>
                    <i data-lucide="arrow-right"></i>
                </button>

            </div>

        </form>

    </div>

</div>

<?php if ($currentUser['role'] === 'admin'): ?>
<div class="modal-overlay" id="accountModal">
    <div class="modal">
        <div class="modal-header">
            <div><p class="eyebrow">ACCOUNT MANAGEMENT</p><h2 id="accountModalTitle">Add an Account</h2></div>
            <button class="close-modal" id="closeAccountModal"><i data-lucide="x"></i></button>
        </div>
        <form id="accountForm">
            <input type="hidden" id="accountId">
            <div class="form-group"><label for="accountName">Full name</label><input id="accountName" required></div>
            <div class="form-group"><label for="accountEmail">Email</label><input id="accountEmail" type="email" required></div>
            <div class="form-row">
                <div class="form-group"><label for="accountRole">Role</label><select id="accountRole" required><option value="student">Student</option><option value="librarian">Librarian</option><option value="admin">Admin</option></select></div>
                <div class="form-group"><label for="accountPassword">Password</label><input id="accountPassword" type="password" minlength="8" placeholder="Required for new accounts"></div>
            </div>
            <div class="form-actions"><button type="button" class="cancel-button" id="cancelAccountModal">Cancel</button><button type="submit" class="save-button">Save account</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (in_array($currentUser['role'], ['librarian', 'admin'], true)): ?>
<div class="modal-overlay" id="studentActivityModal">
    <div class="modal student-activity-modal">
        <div class="modal-header">
            <div>
                <p class="eyebrow">STUDENT ACTIVITY</p>
                <h2 id="studentActivityTitle">Borrowing Activity</h2>
            </div>
            <button class="close-modal" id="closeStudentActivity" type="button"><i data-lucide="x"></i></button>
        </div>
        <div class="student-activity-tabs" role="tablist" aria-label="Student borrowing activity">
            <button class="student-activity-tab active" type="button" data-student-activity-tab="borrowed">Borrowed <strong id="studentActivityBorrowedCount">0</strong></button>
            <button class="student-activity-tab" type="button" data-student-activity-tab="returned">Returned <strong id="studentActivityReturnedCount">0</strong></button>
            <button class="student-activity-tab" type="button" data-student-activity-tab="overdue">Overdue <strong id="studentActivityOverdueCount">0</strong></button>
        </div>
        <section class="student-activity-list" id="studentActivityList"></section>
        <section class="empty-state small-empty" id="noStudentActivity">
            <div class="empty-icon"><i data-lucide="book-open"></i></div>
            <h3>No records in this section</h3>
            <p>This student's activity will appear here.</p>
        </section>
    </div>
</div>
<?php endif; ?>

<!-- Delete account modal -->
<div class="modal-overlay" id="accountDeleteModal">
    <div class="modal small-modal">
        <div class="delete-icon">
            <i data-lucide="user-round-x"></i>
        </div>

        <h2>Delete this account?</h2>

        <p>
            <strong id="accountDeleteName">This account</strong> will be permanently deleted.
            This action cannot be undone.
        </p>

        <div class="form-actions center-actions">
            <button class="cancel-button" id="cancelAccountDelete" type="button">Cancel</button>
            <button class="delete-button" id="confirmAccountDelete" type="button">Delete Account</button>
        </div>
    </div>
</div>

<!-- Borrow modal -->
<div class="modal-overlay" id="borrowModal">

    <div class="modal">

        <div class="modal-header">

            <div>
                <p class="eyebrow">BORROW BOOK</p>
                <h2 id="borrowBookTitle">Book Title</h2>
            </div>

            <button class="close-modal" id="closeBorrowModal">
                <i data-lucide="x"></i>
            </button>

        </div>


        <div class="selected-book-info" id="selectedBookInfo"></div>


        <form id="borrowForm">

            <input type="hidden" id="borrowBookId">

            <div class="form-row">

                <div class="form-group">
                    <label>Borrower Name</label>

                    <input
                        type="text"
                        id="borrowerName"
                        placeholder="Full name"
                        required
                    >
                </div>


                <div class="form-group">
                    <label>Purpose</label>

                    <select id="borrowPurpose" required>
                        <option value="Coursework">Coursework</option>
                        <option value="Research">Research</option>
                        <option value="Reading">Reading</option>
                        <option value="Reference">Reference</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

            </div>


            <div class="form-row">

                <div class="form-group">
                    <label>Borrow Date</label>

                    <input
                        type="date"
                        id="borrowDate"
                        required
                    >
                </div>


                <div class="form-group">
                    <label>Due Date</label>

                    <input
                        type="date"
                        id="dueDate"
                        required
                    >
                </div>

            </div>


            <div class="form-actions">

                <button
                    type="button"
                    class="cancel-button"
                    id="cancelBorrow"
                >
                    Cancel
                </button>

                <button type="submit" class="save-button">
                    <i data-lucide="book-open"></i>
                    Confirm Borrow
                </button>

            </div>

        </form>

    </div>

</div>


<!-- Return modal -->
<div class="modal-overlay" id="returnModal">

    <div class="modal small-modal">

        <div class="return-icon">
            <i data-lucide="book-check"></i>
        </div>

        <h2>Return this book?</h2>

        <p id="returnBookInfo">
            Confirm that this book has been returned.
        </p>

        <div class="form-actions center-actions">

            <button class="cancel-button" id="cancelReturn">
                Cancel
            </button>

            <button class="return-button" id="confirmReturn">
                Return Book
            </button>

        </div>

    </div>

</div>


<!-- Delete book modal -->
<div class="modal-overlay" id="deleteModal">

    <div class="modal small-modal">

        <div class="delete-icon">
            <i data-lucide="trash-2"></i>
        </div>

        <h2>Remove this book?</h2>

        <p>
            This book will be permanently removed from your collection.
        </p>

        <div class="form-actions center-actions">

            <button class="cancel-button" id="cancelDelete">
                Cancel
            </button>

            <button class="delete-button" id="confirmDelete">
                Remove Book
            </button>

        </div>

    </div>

</div>

<!-- Decline request modal -->
<div class="modal-overlay" id="declineModal">

    <div class="modal small-modal">

        <div class="delete-icon">
            <i data-lucide="message-square-warning"></i>
        </div>

        <h2>Decline this request?</h2>

        <p>Provide a reason for the student.</p>

        <form id="declineForm">
            <div class="form-group">
                <label for="declineReason">Reason</label>
                <textarea id="declineReason" rows="3" placeholder="Enter a reason"></textarea>
            </div>

            <div class="form-actions center-actions">
                <button type="button" class="cancel-button" id="cancelDecline">Cancel</button>
                <button type="submit" class="delete-button">Decline Request</button>
            </div>
        </form>

    </div>

</div>

<!-- Change password modal -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal small-modal password-modal">
        <div class="modal-header">
            <div>
                <p class="eyebrow">SECURITY</p>
                <h2>Change Password</h2>
            </div>
            <button class="close-modal" id="closePasswordModal" type="button">
                <i data-lucide="x"></i>
            </button>
        </div>

        <form id="passwordForm">
            <div class="form-group">
                <label for="currentPassword">Current Password</label>
                <input id="currentPassword" type="password" autocomplete="current-password" required>
            </div>
            <div class="form-group">
                <label for="newPassword">New Password</label>
                <input id="newPassword" type="password" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirm New Password</label>
                <input id="confirmPassword" type="password" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="form-actions center-actions">
                <button class="cancel-button" id="cancelPasswordModal" type="button">Cancel</button>
                <button class="save-button" type="submit">
                    <i data-lucide="check"></i>
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast message -->
<div class="toast" id="toast">

    <i data-lucide="check-circle-2"></i>

    <span id="toastMessage">
        Success
    </span>

</div>


<script src="script.js?v=35"></script>

</body>
</html>