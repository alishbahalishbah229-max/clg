<?php
// 1. Errors dekhne ke liye settings (Taki blank screen na aaye)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Sahi relative paths (Kyunki files direct root folder mein hain)
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// 3. CAMPUS360 MAIN HOMEPAGE REDIRECTION
// Agar aapke paas 'login.php' ya koi aur main file hai toh user direct wahan chala jaye
if (file_exists(__DIR__ . '/login.php')) {
    header("Location: login.php");
    exit();
} else {
    echo "<h1>Welcome to Campus360!</h1>";
    echo "<p>Aapki index.php aur database successfully linked hain.</p>";
}
?>
