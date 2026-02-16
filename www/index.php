<?php
// Simple version - direct authentication check
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Redirect based on role
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: /admin/dashboard.php");
    } else {
        header("Location: /agent/dashboard.php");
    }
    exit();
} else {
    // No role set - go to login
    header("Location: /login.php");
    exit();
}
?>