<?php
// Session initialization - include at top of every page
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

require_once __DIR__ . '/config.php';

// Auto-logout after session lifetime
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        redirect(SITE_URL . '/index.php?msg=Session+expired+please+login+again');
    }
}
if (isLoggedIn()) {
    $_SESSION['last_activity'] = time();
}