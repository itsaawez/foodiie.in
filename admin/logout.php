<?php
/** FOODIIE admin logout. */
require_once __DIR__ . '/../cms/functions/auth.php';

logout();
header('Location: login.php');
exit;
