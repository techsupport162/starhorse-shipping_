<?php
/**
 * login.php
 * -----------------------------------------------------------------------
 * Admin login form + handler. Uses password_verify() against the bcrypt
 * hash stored in the `users` table, a prepared statement lookup, and a
 * CSRF token to protect the POST. Blocks sign-in for inactive accounts
 * with a clear message (per the User Management module's status rules),
 * and temporarily locks out repeated failures (login_throttle_functions.php).
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/login_throttle_functions.php';
require_once __DIR__ . '/../config/database.php';

// Already logged in? Skip straight to the dashboard.
if (is_logged_in()) {
    header('Location: ' . admin_base_url() . '/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $username = clean_str($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errors[] = 'Please enter both your username and password.';
        } elseif (($lockedFor = login_throttle_seconds_remaining($username)) > 0) {
            // Checked before the password, so a correct guess during the
            // lockout still gets no confirmation that it was correct.
            $minutes = (int) ceil($lockedFor / 60);
            $errors[] = 'Too many failed sign-in attempts. Please try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
        } else {
            $pdo = get_db();
            $stmt = $pdo->prepare('SELECT id, username, password, full_name, user_access, status, profile_image FROM users WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                login_throttle_record_failure($username);
                $errors[] = 'Invalid username or password.';
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'This account has been deactivated. Please contact the administrator.';
            } else {
                login_throttle_clear($username);
                session_regenerate_id(true);
                $_SESSION['admin_id']      = $user['id'];
                $_SESSION['admin_name']    = $user['full_name'] ?: $user['username'];
                $_SESSION['admin_role']    = $user['user_access'];
                $_SESSION['admin_avatar']  = $user['profile_image'];

                $upd = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $upd->execute([':id' => $user['id']]);

                header('Location: ' . admin_base_url() . '/dashboard.php');
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Admin Login · Starhorse Shipping Line</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/admin.css" rel="stylesheet">
</head>
<body class="sh-admin-body">
<div class="sh-auth-wrap">
  <div class="sh-auth-card">
    <div class="sh-mark"><i class="bi bi-water"></i></div>
    <h1>Starhorse Admin</h1>
    <p class="sh-sub">Sign in to manage the website</p>

    <?php if ($errors): ?>
    <div class="alert alert-danger py-2">
      <?php foreach ($errors as $error): ?>
        <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?php csrf_field(); ?>
      <div class="mb-3">
        <label class="form-label small fw-semibold" for="username">Username</label>
        <input type="text" class="form-control" id="username" name="username" autocomplete="username" required autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-sh-primary w-100 mt-2 py-2">Sign In</button>
    </form>
  </div>
</div>
</body>
</html>
