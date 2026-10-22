<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Application & Database Configuration Template
 * 
 * Instructions:
 * 1. Copy this file to `config/config.local.php` (which is git-ignored).
 * 2. Update the credentials below according to your local MySQL / XAMPP setup.
 */

// Database connection parameters
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'urbaneats_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // In XAMPP, default root password is blank
define('DB_CHARSET', 'utf8mb4');

// Application Environment: 'development' or 'production'
define('APP_ENV', 'development');
define('APP_NAME', 'UrbanEats Cloud Kitchen');

// Security & Session Settings
define('SESSION_LIFETIME', 1800); // 30 minutes inactivity timeout (in seconds)
define('MAX_LOGIN_ATTEMPTS', 5);  // Max failed attempts before throttle lock
define('LOCKOUT_TIME_MINUTES', 15); // Duration of lock after 5 failed attempts
define('RESET_TOKEN_EXPIRY_MINUTES', 60); // Password reset token valid for 1 hour

// Default Currency
define('CURRENCY_SYMBOL', 'Rs. ');
define('CURRENCY_CODE', 'LKR');
