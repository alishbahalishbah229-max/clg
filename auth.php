<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

function login($email, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && verifyPassword($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    return true;
}

function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && ($_SESSION['role'] === $role || $_SESSION['role'] === 'admin');
}

function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        header('Location: /unauthorized.php');
        exit;
    }
}

function getCurrentUser() {
    global $pdo;
    
    if (!isAuthenticated()) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>