<?php

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only include config.php if it hasn't been included yet
if (!function_exists('getConnection')) {
    $configPath = dirname(__DIR__) . '/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        // Try alternative paths
        $altPaths = [
            __DIR__ . '/../config.php',
            $_SERVER['DOCUMENT_ROOT'] . '/config.php',
            dirname(dirname(__FILE__)) . '/config.php'
        ];
        
        $found = false;
        foreach ($altPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            die("Config file not found!");
        }
    }
}

// Check if initialization worked
if (!function_exists('getConnection')) {
    die("Database connection function not found in config.php!");
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Login function
function login($username, $password) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        return true;
    }
    return false;
}


function requireAgent() {
    requireLogin();
    if (!hasRole('agent')) {
        header("Location: ../login.php");
        exit();
    }
}

// Logout function
function logout() {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Check if user is super admin
function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is admin or higher
function isAdminOrHigher() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin');
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        // Determine correct redirect based on current location
        $isInAgentFolder = strpos($_SERVER['PHP_SELF'], '/agent/') !== false;
        
        if ($isInAgentFolder) {
            header("Location: login.php");
        } else {
            header("Location: ../agent/login.php");
        }
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    // REMOVE THESE DEBUG ECHO STATEMENTS FOR PRODUCTION
    // echo "Debug - Session role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
    // echo "Debug - isAdminOrHigher: " . (isAdminOrHigher() ? 'YES' : 'NO') . "<br>";
    
    if (!isAdminOrHigher()) {
        // REMOVE THIS DEBUG ECHO
        // echo "Debug - Redirecting because not admin<br>";
        
        // Check where we're coming from to determine the correct redirect
        $isInAdminFolder = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
        
        if ($isInAdminFolder) {
            // If in admin folder, redirect to agent dashboard
            header("Location: ../agent/dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    }
}

// Redirect if not super admin
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        header("Location: ../index.php");
        exit();
    }
}

// Don't call initializeDatabase() here - let the individual pages call it if needed
// initializeDatabase();
?>