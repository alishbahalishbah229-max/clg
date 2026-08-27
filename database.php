<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'campus_event_360');
define('DB_USER', 'root');
define('DB_PASS', '12345678'); // ← Yahan apna sahi password daalo

// Application configuration
define('APP_NAME', 'CampusEvent360');
define('APP_URL', 'http://localhost:8000');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('QR_SECRET_KEY', 'your-secure-qr-key-2024');

// ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("<h1>Database Connection Error</h1>
         <p>Error: " . $e->getMessage() . "</p>
         <p>Please check your database credentials in config/database.php</p>");
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Test connection (silent)
try {
    $pdo->query("SELECT 1");
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>