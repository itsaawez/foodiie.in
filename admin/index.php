<?php
/** FOODIIE admin entry: send logged-in users to the dashboard, others to login. */
require_once __DIR__ . '/../cms/functions/auth.php';

header('Location: ' . (current_user() !== null ? 'dashboard.php' : 'login.php'));
exit;
