<?php
// login.php - Handle includes manually

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config.php from root
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    die("Config file not found at: $configPath");
}

// Include auth.php from includes folder
$authPath = __DIR__ . '/includes/auth.php';
if (file_exists($authPath)) {
    require_once $authPath;
} else {
    die("Auth file not found at: $authPath");
}

// Now check if already logged in
if (isLoggedIn()) {
    if (hasRole('admin')) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: agent/dashboard_agent.php");
    }
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (login($username, $password)) {
            if (hasRole('admin')) {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: agent/dashboard_agent.php");
            }
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apparels Group KSA - Login</title>
    <link rel="stylesheet" href="css/material-style.css">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" href="/images/icon-192x192.png">
    <meta name="theme-color" content="#1976d2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Apparels Collection">
    <link rel="apple-touch-icon" href="/images/icon-192x192.png">
    
    <!-- Preload fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }
        
        .login-logo-icon {
            width: 48px;
            height: 48px;
            background-color: #1976d2;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .login-title {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #212121;
            font-size: 24px;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #5f6368;
            font-weight: 500;
        }
        
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.2);
        }
        
        button {
            width: 100%;
            padding: 12px;
            background-color: #1976d2;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            font-family: 'Roboto', sans-serif;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #1565c0;
        }
        
        .error {
            color: #d32f2f;
            text-align: center;
            margin-bottom: 1rem;
            padding: 12px;
            background-color: #ffebee;
            border-radius: 4px;
            border: 1px solid #ffcdd2;
            font-size: 14px;
        }
        
        .default-credentials {
            margin-top: 1rem;
            padding: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #666;
        }
        
        /* Install Button Styles */
        #installPWA {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1976d2;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            cursor: pointer;
            z-index: 1000;
            display: none;
            font-size: 14px;
            box-shadow: 0 2px 4px -1px rgba(0,0,0,0.2), 
                        0 4px 5px 0 rgba(0,0,0,0.14), 
                        0 1px 10px 0 rgba(0,0,0,0.12);
            font-family: 'Roboto', sans-serif;
            font-weight: 500;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-logo">
            <div class="login-logo-icon">
                <span class="material-symbols-outlined">store</span>
            </div>
        </div>
        <div class="login-title">Apparel Group KSA</div>
        <form method="post">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>
    
    <button id="installPWA">
        <span class="material-symbols-outlined">download</span> Install App
    </button>
    
    <script>
    // PWA Installation Handler
    let pwaDeferredPrompt;
    
    document.addEventListener('DOMContentLoaded', function() {
        const installButton = document.getElementById('installPWA');
        
        // Listen for beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            pwaDeferredPrompt = e;
            
            // Show install button after a delay
            setTimeout(() => {
                if (!localStorage.getItem('pwaInstallDismissed') && installButton) {
                    installButton.style.display = 'flex';
                    
                    // Auto-hide after 10 seconds
                    setTimeout(() => {
                        if (installButton.style.display === 'flex') {
                            installButton.style.display = 'none';
                            localStorage.setItem('pwaInstallDismissed', 'true');
                        }
                    }, 10000);
                }
            }, 3000);
        });
        
        // Handle install button click
        if (installButton) {
            installButton.addEventListener('click', async () => {
                if (!pwaDeferredPrompt) return;
                
                installButton.style.display = 'none';
                pwaDeferredPrompt.prompt();
                
                const { outcome } = await pwaDeferredPrompt.userChoice;
                
                if (outcome === 'accepted') {
                    localStorage.setItem('pwaInstalled', 'true');
                } else {
                    localStorage.setItem('pwaInstallDismissed', 'true');
                }
                
                pwaDeferredPrompt = null;
            });
        }
        
        // Check if already installed
        window.addEventListener('appinstalled', () => {
            localStorage.setItem('pwaInstalled', 'true');
            if (installButton) {
                installButton.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>