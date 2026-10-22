<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Logout
 *
 * Implements session destruction, cookie cleanup, audit logging,
 * and redirection with confirmation.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Log out user and destroy session
logout_user();

// Flash message stored in new fresh session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

set_flash('info', 'You have been logged out securely.');
redirect('auth/login.php');
