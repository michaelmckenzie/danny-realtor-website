<?php
/**
 * admin/login.php — Admin login page
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// Already logged in → redirect to dashboard
if (isAdminLoggedIn()) {
    header('Location: /v2/admin/');
    exit;
}

$error    = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '/admin/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid form token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (adminLogin($username, $password)) {
            header('Location: ' . $redirect);
            exit;
        }
        // Slow down brute force attempts
        sleep(1);
        $error = 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agent Login | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="/v2/assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="login-page">
  <div class="login-card">
    <a href="/v2/" class="site-logo" aria-label="Home">
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
        <path d="M16 2L2 14H6V28H13V20H19V28H26V14H30L16 2Z" fill="currentColor"/>
      </svg>
      <span class="logo-text">Danny<strong>Homes</strong></span>
    </a>

    <h1 class="login-title">Agent Portal</h1>
    <p class="login-sub">Sign in to manage your listings</p>

    <?php if ($error): ?>
      <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="login-form">
      <?= csrfField() ?>
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input
          type="text"
          id="username"
          name="username"
          class="form-control"
          value="<?= e($_POST['username'] ?? '') ?>"
          autocomplete="username"
          autofocus
          required
        >
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          autocomplete="current-password"
          required
        >
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">Sign In</button>
    </form>

    <p style="text-align:center; margin-top:20px;">
      <a href="/v2/" style="font-size:13px; color:var(--clr-text-muted);">&larr; Back to site</a>
    </p>
  </div>
</body>
</html>
