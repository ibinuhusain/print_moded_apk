<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Initialize variables
$agents = [];
$success_message = '';
$error_message = '';

// Fetch all agents from database
try {
    $stmt = $pdo->prepare("SELECT id, username, name, phone, role, created_at FROM users WHERE role = 'agent' ORDER BY id ASC");
    $stmt->execute();
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching agents: " . $e->getMessage();
}

// Handle Excel import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_agents'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        
        try {
            // Simple CSV parsing (no PhpSpreadsheet dependency)
            $file = fopen($_FILES['excel_file']['tmp_name'], 'r');
            if (!$file) {
                throw new Exception('Cannot open uploaded file.');
            }
            
            $rows = [];
            while (($row = fgetcsv($file)) !== false) {
                $rows[] = $row;
            }
            fclose($file);
            
            // Check if we have any data
            if (empty($rows)) {
                throw new Exception('The uploaded file is empty.');
            }
            
            // Import counters
            $importedCount = 0;
            $skippedCount = 0;
            $importErrors = [];
            
            // Start transaction
            $pdo->beginTransaction();
            
            foreach ($rows as $rowIndex => $row) {
                // Skip empty rows
                if (empty(array_filter($row, function($value) { 
                    return $value !== null && trim($value) !== ''; 
                }))) {
                    continue;
                }
                
                // Check if first row is header (skip if contains header keywords)
                if ($rowIndex === 0) {
                    $firstCell = strtolower(trim($row[0] ?? ''));
                    if (strpos($firstCell, 'agent') !== false || 
                        strpos($firstCell, 'name') !== false ||
                        strpos($firstCell, 'username') !== false ||
                        strpos($firstCell, 'userid') !== false) {
                        continue;
                    }
                }
                
                // Extract data - assuming columns: Agent_Name, Username, Userid, Phone, Password
                $agent_name = isset($row[0]) ? trim($row[0]) : '';
                $username = isset($row[1]) ? trim($row[1]) : '';
                $manual_id = isset($row[2]) ? intval(trim($row[2])) : 0;
                $phone = isset($row[3]) ? trim($row[3]) : '';
                $raw_password = isset($row[4]) ? trim($row[4]) : '';
                
                // Validate required fields
                if (empty($agent_name)) {
                    $importErrors[] = "Row " . ($rowIndex + 1) . ": Agent Name is required";
                    $skippedCount++;
                    continue;
                }
                
                if (empty($username)) {
                    $importErrors[] = "Row " . ($rowIndex + 1) . ": Username is required";
                    $skippedCount++;
                    continue;
                }
                
                // Check if username already exists
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $checkStmt->execute([$username]);
                
                if ($checkStmt->rowCount() > 0) {
                    $importErrors[] = "Row " . ($rowIndex + 1) . ": Username '$username' already exists";
                    $skippedCount++;
                    continue;
                }
                
                // Determine which ID to use
                $agent_id = null;
                $useManualId = false;
                
                if ($manual_id > 0) {
                    // Check if manual ID already exists
                    $checkIdStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                    $checkIdStmt->execute([$manual_id]);
                    
                    if ($checkIdStmt->rowCount() === 0) {
                        $agent_id = $manual_id;
                        $useManualId = true;
                    }
                }
                
                // Set password
                if (!empty($raw_password)) {
                    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
                } else {
                    $hashed_password = password_hash('agent123', PASSWORD_DEFAULT);
                }
                
                // Insert the agent
                try {
                    if ($useManualId && $agent_id > 0) {
                        $stmt = $pdo->prepare("INSERT INTO users (id, username, password, name, phone, role, created_at) VALUES (?, ?, ?, ?, ?, 'agent', NOW())");
                        $stmt->execute([$agent_id, $username, $hashed_password, $agent_name, $phone]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, phone, role, created_at) VALUES (?, ?, ?, ?, 'agent', NOW())");
                        $stmt->execute([$username, $hashed_password, $agent_name, $phone]);
                        $agent_id = $pdo->lastInsertId();
                    }
                    
                    if ($stmt->rowCount() > 0) {
                        $importedCount++;
                    } else {
                        $importErrors[] = "Row " . ($rowIndex + 1) . ": Failed to insert agent '$agent_name'";
                        $skippedCount++;
                    }
                    
                } catch (PDOException $e) {
                    $importErrors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
                    $skippedCount++;
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Refresh agents list
            $stmt = $pdo->prepare("SELECT id, username, name, phone, role, created_at FROM users WHERE role = 'agent' ORDER BY id ASC");
            $stmt->execute();
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare messages
            if ($importedCount > 0) {
                $success_message = "✅ Successfully imported $importedCount agents.";
                if ($skippedCount > 0) {
                    $success_message .= " $skippedCount rows were skipped.";
                    if (!empty($importErrors)) {
                        $error_message = "Some rows had issues:<br>" . implode("<br>", array_slice($importErrors, 0, 3));
                        if (count($importErrors) > 3) {
                            $error_message .= "<br>... and " . (count($importErrors) - 3) . " more";
                        }
                    }
                }
            } else {
                $error_message = "❌ No agents were imported. $skippedCount rows were skipped.";
                if (!empty($importErrors)) {
                    $error_message .= "<br>Errors:<br>" . implode("<br>", array_slice($importErrors, 0, 5));
                }
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Error importing file: " . $e->getMessage();
        }
    } else {
        $error_message = "Please select a valid file.";
        if (isset($_FILES['excel_file']['error'])) {
            switch ($_FILES['excel_file']['error']) {
                case 1:
                case 2:
                    $error_message .= " File is too large.";
                    break;
                case 3:
                    $error_message .= " File was only partially uploaded.";
                    break;
                case 4:
                    $error_message .= " No file was uploaded.";
                    break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents - Apparels Collection</title>
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    <link rel="stylesheet" href="../css/agents.css">
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="/images/icon-192x192.png">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="../js/app.js" defer></script>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo" align="center">
                    <img src="../images/logo.png" alt="Apparel Collection Logo" width="auto" height="80px" style="object-fit: contain;" loading="lazy">
                    <div class="sidebar-logo-text"></div>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="material-symbols-outlined">home</span>
                    <span>Dashboard</span>
                </a>
                <a href="assignments.php" class="nav-item">
                    <span class="material-symbols-outlined">assignment</span>
                    <span>Assignments</span>
                </a>
                <a href="agents.php" class="nav-item active">
                    <span class="material-symbols-outlined">people</span>
                    <span>Agents</span>
                </a>
                <a href="management.php" class="nav-item">
                    <span class="material-symbols-outlined">settings</span>
                    <span>Management</span>
                </a>
                <a href="store_data.php" class="nav-item">
                    <span class="material-symbols-outlined">storefront</span>
                    <span>Store Data</span>
                </a>
                <a href="bank_approvals.php" class="nav-item">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <span>Bank Approvals</span>
                </a>
            </div>
        </div>
        
        <!-- Overlay for mobile sidebar -->
        <div class="overlay"></div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="header-title">Agents</div>
                </div>
                <div class="header-right">
                    <a href="../logout.php" class="logout-btn">
                        <span class="material-symbols-outlined">logout</span>
                        Logout
                    </a>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="content">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <span class="material-symbols-outlined">check_circle</span>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <span class="material-symbols-outlined">error</span>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <h2>
                    <span class="material-symbols-outlined">upload</span>
                    Import Agents via CSV/Excel
                </h2>
                
                <form method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="import_agents" value="1">
                    
                    <div class="form-group">
                        <label for="excel_file">Upload CSV/Excel File:</label>
                        <input type="file" id="excel_file" name="excel_file" accept=".csv,.xlsx,.xls" required>
                        <small>File should have columns: Agent_Name, Username, Userid (optional ID), Phone, Password (optional)</small>
                    </div>
                    
                    <button type="submit" class="btn">
                        <span class="material-symbols-outlined">file_upload</span>
                        Import from File
                    </button>
                </form>
                
                <h2 style="margin-top: 40px;">
                    <span class="material-symbols-outlined">group</span>
                    All Agents (<?php echo count($agents); ?>)
                </h2>
                
                <div class="table-responsive">
                    <?php if (empty($agents)): ?>
                        <div class="alert alert-info">
                            <span class="material-symbols-outlined">info</span>
                            No agents found. Import agents using the file import above.
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Agent Name</th>
                                    <th>Username</th>
                                    <th>Phone</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agents as $agent): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($agent['id']); ?></td>
                                        <td><?php echo htmlspecialchars($agent['name']); ?></td>
                                        <td><?php echo htmlspecialchars($agent['username']); ?></td>
                                        <td><?php echo htmlspecialchars($agent['phone']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($agent['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        
        if (hamburger) {
            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.classList.toggle('menu-open');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.classList.remove('menu-open');
            });
        }
    });
    </script>
</body>
</html>