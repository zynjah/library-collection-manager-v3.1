<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/database.php';

if (isset($_SESSION['user'])) {
    header('Location: ../index.php?welcome=1');
    exit;
}

// Validate credentials here so the browser only receives a generic login error.
$errors = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Enter a valid email address and password.';
    } else {
        $statement = $database->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute([':email' => $oldEmail]);
        $user = $statement->fetch();

        if (!$user || !(bool) $user['is_active'] || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'The email or password is incorrect.';
        } else {
            // Regenerate the session ID after login to prevent session fixation.
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'user_id' => $user['user_id'],
            ];
            header('Location: ../index.php');
            exit;
        }
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Library System - Log in</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
  <main class="auth-shell">
    <section class="auth-intro">
      <a class="brand" href="../index.php" aria-label="Library Manager Collection">Library Manager Collection</a>
      <p class="eyebrow">ACCOUNT ACCESS</p>
      <h1>One account for your library role.</h1>
      <p class="intro-copy">Sign in to manage your books, requests, borrowing activity, and account information.</p>
      <div class="role-list">
        <div><span class="role-dot student"></span>Student access</div>
        <div><span class="role-dot librarian"></span>Librarian access</div>
        <div><span class="role-dot admin"></span>Admin access</div>
      </div>
    </section>

    <section class="auth-card" aria-labelledby="authTitle">
      <div class="auth-choice" role="tablist" aria-label="Account access">
        <button class="choice-button active" type="button" role="tab" aria-selected="true" data-auth-mode="login">Log in</button>
        <button class="choice-button" type="button" role="tab" aria-selected="false" data-auth-mode="signup">Sign up</button>
      </div>

      <div id="loginPanel" class="auth-mode-panel">
        <h2 id="authTitle">Welcome back</h2>
        <p class="muted">Use your registered account credentials to continue.</p>

        <?php if ($errors): ?>
          <div class="notice error" role="alert">
            <?= escape($errors[0]) ?>
          </div>
        <?php endif; ?>

        <form class="auth-panel" method="post" action="index.php">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" value="<?= escape($oldEmail) ?>" autocomplete="username" required>

          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required>

          <button class="primary-button" type="submit">Log in</button>
        </form>
      </div>

      <div id="signupPanel" class="auth-mode-panel signup-panel" hidden>
        <h2>Create your account</h2>
        <p class="muted">Library accounts are created and approved by an administrator so every role has the right access.</p>
        <div class="signup-note">
          <span class="signup-note-mark">+</span>
          <div>
            <strong>Need access?</strong>
            <p>Ask a librarian or administrator to create your account. They will provide your login credentials.</p>
          </div>
        </div>
        <button class="secondary-button" type="button" data-auth-mode="login">Return to log in</button>
      </div>
    </section>
  </main>
  <div class="auth-loading" id="authLoading" aria-live="polite" aria-hidden="true">
    <div class="loading-rule"></div>
    <p>Preparing your library workspace</p>
  </div>
  <script src="app.js?v=1"></script>
</body>
</html>
