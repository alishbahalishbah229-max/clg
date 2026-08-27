<?php
echo "<h1>PHP is working!</h1>";
echo "<p>Server time: " . date('Y-m-d H:i:s') . "</p>";

// Test database
require_once 'config/database.php';

echo "<h2>Database Test</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $result = $stmt->fetch();
    echo "<p>Total users in database: " . $result['total'] . "</p>";
} catch(PDOException $e) {
    echo "<p style='color:red'>Database error: " . $e->getMessage() . "</p>";
}

phpinfo();
?>