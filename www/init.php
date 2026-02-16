<?php
/**
 * Initialization script to set up the database
 */

require_once 'config.php';

try {
    initializeDatabase();
    echo "Database initialized successfully!";
    
    // Check if default admin user was created
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT username, name FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "\nDefault admin user created:";
        echo "\n  Username: " . $admin['username'];
        echo "\n  Name: " . $admin['name'];
        echo "\n  Default password: admin123";
    }
} catch (Exception $e) {
    echo "Error initializing database: " . $e->getMessage();
}
?>