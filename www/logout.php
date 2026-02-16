<?php
// logout.php - FIXED VERSION

// Don't include auth.php if it has the logout function conflict
// Instead, handle session directly

session_start();

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login
header("Location: " . '/../login.php');
exit();
?>


<?php
/*
require_once __DIR__ . '/../includes/auth.php';
session_start();
session_destroy();
header("Location: login.php");
exit();
*/
?>