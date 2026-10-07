<?php
/**
 * FOODIIE api/csrf.php — issues a CSRF token (JSON) for public forms
 * (newsletter, contact) on the static site. The static pages fetch this
 * with JavaScript and attach the token to the form POST.
 */
require_once __DIR__ . '/../cms/functions/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['token' => csrf_token()]);
