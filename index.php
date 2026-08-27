<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Step 1: index.php chal rahi hai!</h1>";

require_once __DIR__ . '/database.php';
echo "<p>Step 2: database.php load ho gayi!</p>";

require_once __DIR__ . '/functions.php';
echo "<p>Step 3: functions.php load ho gayi!</p>";

require_once __DIR__ . '/auth.php';
echo "<p>Step 4: auth.php load ho gayi!</p>";

echo "<p>Saari files perfectly connect ho chuki hain!</p>";
?>
