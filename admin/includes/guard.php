<?php
/**
 * FOODIIE admin — page guard.
 * Include at the top of every admin page (except login/index/logout).
 * Redirects to login when not authenticated. Exposes $current_user.
 */
require_once __DIR__ . '/../../cms/functions/auth.php';
require_once __DIR__ . '/../../cms/functions/csrf.php';
require_once __DIR__ . '/../../cms/functions/helpers.php';
require_once __DIR__ . '/../../cms/functions/sanitize.php';
require_once __DIR__ . '/../../cms/functions/recipe_categories.php';
require_once __DIR__ . '/functions.php';

$current_user = require_login();

