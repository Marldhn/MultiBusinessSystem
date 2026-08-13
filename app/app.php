<?php
// Start session globally for the entire application
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database connection configuration securely from the parent folder
require_once __DIR__ . '/../config/db.php';

// Define global project base URL
define('BASE_URL', 'http://localhost/MultiBusinessSystem/public/');
?>