<?php
// Force clear everything
session_start();
session_unset();
session_destroy();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear all session files in temp directory
$session_files = glob(session_save_path() . '/*');
foreach ($session_files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

echo "ALL sessions cleared! <a href='/login.php'>Go to Login</a>";
?>