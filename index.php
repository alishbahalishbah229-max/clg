<?php
// 1. Errors dekhne ke liye settings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Sahi aur direct relative paths
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// 3. Login page par redirection
if (file_exists(__DIR__ . '/login.php')) {
    header("Location: login.php");
    exit();
} else {
    echo "<h1>Welcome to Campus360!</h1>";
    echo "<p>Aapki index.php aur database successfully linked hain.</p>";
}
?>
