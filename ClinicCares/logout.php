<?php
require_once __DIR__ . '/includes/session.php';
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'LOGOUT', 'User logged out');
}
session_unset();
session_destroy();
redirect(SITE_URL . '/index.php?msg=You+have+been+logged+out+successfully');