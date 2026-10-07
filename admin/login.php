<?php
/** FOODIIE admin login. */
require_once __DIR__ . '/../cms/functions/auth.php';
require_once __DIR__ . '/../cms/functions/csrf.php';

if (current_user() !== null) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    [$ok, $msg] = attempt_login($email, $password);
    if ($ok) {
        // Honor ?next= only when it is a same-app relative path inside this admin dir.
        $target = 'dashboard.php';
        $next = $_GET['next'] ?? '';
        $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        if (is_string($next) && $next !== ''
            && strpos($next, '//') !== 0
            && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $next) // no scheme:
            && strpos($next, '..') === false
            && strpos($next, $script_dir . '/') === 0
        ) {
            $target = $next;
        }
        header('Location: ' . $target);
        exit;
    }
    $error = $msg;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — FOODIIE Admin</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <h1>FOODIIE Admin</h1>
    <div class="sub">Sign in to manage your site.</div>
    <?php if ($error !== ''): ?>
      <div class="alert alert-error"><?php echo esc($error); ?></div>
    <?php endif; ?>
    <form method="post" action="login.php<?php echo isset($_GET['next']) ? '?next=' . esc(urlencode((string) $_GET['next'])) : ''; ?>">
      <?php echo csrf_field(); ?>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?php echo esc($email); ?>" required autocomplete="username" autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">Log in</button>
    </form>
  </div>
</div>
</body>
</html>
