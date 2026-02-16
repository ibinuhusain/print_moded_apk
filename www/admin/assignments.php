<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Handle form submission for assigning shops to agents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_shops'])) {
    $agent_id = $_POST['agent_id'];
    $selected_stores = $_POST['stores'];
    $assignment_date = $_POST['assignment_date'] ?? date('Y-m-d');
    
    foreach ($selected_stores as $store_id) {
        $stmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned) VALUES (?, ?, ?)");
        $stmt->execute([$agent_id, $store_id, $assignment_date]);
    }
    
    $success_message = "Shops assigned successfully!";
}

// Handle Excel import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['excel_file']['tmp_name'];
        $file_type = $_FILES['excel_file']['type'];
        
        // Check if it's a valid Excel file
        $valid_types = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
        if (in_array($file_type, $valid_types)) {
            // First, try to use PhpOffice\PhpSpreadsheet if available
            $importedCount = 0;
            $hasPhpSpreadsheet = class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory');
            
            if ($hasPhpSpreadsheet) {
                try {
                    // Load the spreadsheet file using PhpSpreadsheet
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_tmp);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                    
                    // Skip header row
                    array_shift($rows);
                    
                    foreach ($rows as $row) {
                        if (!empty($row[0]) && !empty($row[1])) { // Agent_Name and Region are required
                            $agent_name = trim($row[0]);
                            $region = trim($row[1]);
                            $mall = trim($row[2] ?? '');
                            $entity = trim($row[3] ?? '');
                            $brand = trim($row[4] ?? '');
                            
                            // Find agent by name
                            $agentStmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND role = 'agent'");
                            $agentStmt->execute([$agent_name]);
                            $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($agent) {
                                // Find region by name
                                $regionStmt = $pdo->prepare("SELECT id FROM regions WHERE name = ?");
                                $regionStmt->execute([$region]);
                                $regionResult = $regionStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($regionResult) {
                                    $region_id = $regionResult['id'];
                                    
                                    // Find stores matching the criteria
                                    $storeStmt = $pdo->prepare("SELECT id FROM stores WHERE region_id = ? AND mall LIKE ? AND entity LIKE ?");
                                    $storeStmt->execute([$region_id, "%$mall%", "%$entity%"]);
                                    $stores = $storeStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($stores as $store) {
                                        // Create assignment
                                        $assignmentStmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, status) VALUES (?, ?, ?, 'pending')");
                                        $assignmentStmt->execute([$agent['id'], $store['id'], date('Y-m-d')]);
                                    }
                                    
                                    $importedCount++;
                                } else {
                                    // If region doesn't exist, create it
                                    $createRegionStmt = $pdo->prepare("INSERT INTO regions (name) VALUES (?)");
                                    $createRegionStmt->execute([$region]);
                                    $region_id = $pdo->lastInsertId();
                                    
                                    // Find stores matching the criteria
                                    $storeStmt = $pdo->prepare("SELECT id FROM stores WHERE region_id = ? AND mall LIKE ? AND entity LIKE ?");
                                    $storeStmt->execute([$region_id, "%$mall%", "%$entity%"]);
                                    $stores = $storeStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($stores as $store) {
                                        // Create assignment
                                        $assignmentStmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, status) VALUES (?, ?, ?, 'pending')");
                                        $assignmentStmt->execute([$agent['id'], $store['id'], date('Y-m-d')]);
                                    }
                                    
                                    $importedCount++;
                                }
                            } else {
                                $error_message = "Agent '$agent_name' does not exist.";
                                break;
                            }
                        }
                    }
                    
                    if (!$error_message) {
                        $success_message = "Successfully imported $importedCount assignments from Excel file.";
                    }
                } catch (Exception $e) {
                    // If PhpSpreadsheet fails, fall back to basic CSV processing
                    $error_message = "";
                    $hasPhpSpreadsheet = false;
                }
            }
            
            // If PhpSpreadsheet is not available or failed, use basic CSV processing
            if (!$hasPhpSpreadsheet && !$error_message) {
                if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                    // Skip header row
                    fgetcsv($handle);
                    
                    $added_count = 0;
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 5) {
                            $agent_name = trim($data[0]);
                            $region = trim($data[1]);
                            $mall = trim($data[2]);
                            $entity = trim($data[3]);
                            $brand = trim($data[4]);
                            
                            // Find agent by name
                            $agent_stmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND role = 'agent'");
                            $agent_stmt->execute([$agent_name]);
                            $agent = $agent_stmt->fetch();
                            
                            if ($agent) {
                                $agent_id = $agent['id'];
                                
                                // Find store by entity/mall details
                                $store_stmt = $pdo->prepare("SELECT id FROM stores WHERE entity = ? AND mall = ?");
                                $store_stmt->execute([$entity, $mall]);
                                $store = $store_stmt->fetch();
                                
                                if ($store) {
                                    $store_id = $store['id'];
                                    
                                    // Create assignment
                                    $assignment_stmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned) VALUES (?, ?, ?)");
                                    $assignment_stmt->execute([$agent_id, $store_id, date('Y-m-d')]);
                                    $added_count++;
                                }
                            }
                        }
                    }
                    fclose($handle);
                    
                    if ($added_count > 0) {
                        $success_message = "Successfully imported $added_count assignments!";
                    } else {
                        $error_message = "No assignments were imported. Check your file format.";
                    }
                } else {
                    $error_message = "Could not read the uploaded file.";
                }
            }
        } else {
            $error_message = "Invalid file type. Please upload a CSV or Excel file.";
        }
    } else {
        $error_message = "Please select an Excel file to import.";
    }
}

// Get all agents
$agents_stmt = $pdo->query("SELECT id, name, username FROM users WHERE role = 'agent'");
$agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all stores
$stores_stmt = $pdo->query("SELECT s.id, s.name, s.mall, s.entity, s.brand, r.name as region_name FROM stores s LEFT JOIN regions r ON s.region_id = r.id");
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's assignments
$today = date('Y-m-d');
$assignments_stmt = $pdo->prepare("
    SELECT da.*, u.name as agent_name, s.name as store_name, r.name as region_name
    FROM daily_assignments da
    JOIN users u ON da.agent_id = u.id
    JOIN stores s ON da.store_id = s.id
    LEFT JOIN regions r ON s.region_id = r.id
    WHERE DATE(da.date_assigned) = ?
    ORDER BY u.name, s.name
");
$assignments_stmt->execute([$today]);
$today_assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents - Apparels Collection</title>
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    <link rel="stylesheet" href="../css/assignments.css">
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="/images/icon-192x192.png">
    <script src="../js/app.js" defer></script>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
        <div class="sidebar-logo" align="center">
              <img src="../images/logo.png" alt="Apparel Collection Logo" width="auto" height="80px" object-fit: "contain" loading="lazy">
            <div class="sidebar-logo-text"></div>
        </div>
    </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="material-symbols-outlined">home</span>
                    <span>Dashboard</span>
                </a>
                <a href="assignments.php" class="nav-item active">
                    <span class="material-symbols-outlined">assignment</span>
                    <span>Assignments</span>
                </a>
                <a href="agents.php" class="nav-item">
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
        
        <div class="content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <h2>Assign Agents to Entities</h2>
            <form method="post" action="">
                <input type="hidden" name="assign_shops" value="1">
                
                <div class="form-group">
                    <label for="assignment_date">Assignment Date:</label>
                    <input type="date" id="assignment_date" name="assignment_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="agent_id">Agent Name:</label>
                    <select id="agent_id" name="agent_id" required>
                        <option value="">Choose an agent</option>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['id']; ?>"><?php echo htmlspecialchars($agent['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
    <label>Select Assignment Details:</label>
    <div class="table-responsive"> <!-- Add this wrapper -->
        <table class="table assignment-table"> <!-- Added 'table' class -->
            <thead>
                <tr>
                    <th>Select</th>
                    <th>Region</th>
                    <th>Mall</th>
                    <th>Entity</th>
                    <th>Brand</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stores as $store): ?>
                    <tr>
                        <td><input type="checkbox" id="store_<?php echo $store['id']; ?>" name="stores[]" value="<?php echo $store['id']; ?>"></td>
                        <td><?php echo htmlspecialchars($store['region_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($store['mall'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($store['entity'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($store['brand'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
                
                <button type="submit" class="btn">Assign Entities</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Import Assignments via Excel</h2>
            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="import_excel" value="1">
                
                <div class="form-group">
                    <label for="excel_file">Upload Excel File:</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                    <small>Excel file should have columns: Agent_Name, Region, Mall, Entity, Brand</small>
                </div>
                
                <button type="submit" class="btn">Import from Excel</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Today's Assignments (<?php echo date('M j, Y'); ?>)</h2>
<?php if (count($today_assignments) > 0): ?>
    <div class="table-responsive"> <!-- Add this wrapper -->
        <table class="table"> <!-- Added 'table' class -->
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Store</th>
                    <th>Region</th>
                    <th>Mall</th>
                    <th>Entity</th>
                    <th>Brand</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($today_assignments as $assignment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($assignment['agent_name']); ?></td>
                        <td><?php echo htmlspecialchars($assignment['store_name']); ?></td>
                        <td><?php echo htmlspecialchars($assignment['region_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($assignment['mall'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($assignment['entity'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($assignment['brand'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $assignment['status']; ?>"> <!-- Changed class to match first table -->
                                <?php 
                                    echo ucfirst(str_replace('_', ' ', $assignment['status'])); 
                                ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>No assignments for today.</p>
<?php endif; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM fully loaded - testing hamburger');
        
        // Get elements
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        
        // Debug: Log elements to confirm they're found
        console.log('Hamburger:', hamburger);
        console.log('Sidebar:', sidebar);
        console.log('Overlay:', overlay);
        
        // Add basic click handler
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Toggle classes
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        });
        
        // Close menu when clicking overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('menu-open');
        });
        
        // Close menu when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 992 && 
                !sidebar.contains(e.target) && 
                !hamburger.contains(e.target)) {
                console.log('Clicked outside - closing menu');
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.classList.remove('menu-open');
            }
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger');
        
        // Test click with alert
        hamburger.addEventListener('click', function() {
            console.log('');
        });
        
        // Test with direct style change
        hamburger.addEventListener('mousedown', function() {
            this.style.backgroundColor = 'red';
        });
        
        hamburger.addEventListener('mouseup', function() {
            this.style.backgroundColor = 'transparent';
        });
    });
</script>

<style>

/* Table Styling */
.table-container {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--elevation-1);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    margin-bottom: 32px;
}

.table-responsive {
    overflow-x: auto;
    border-radius: 16px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

thead {
    background: rgba(255, 255, 255, 0.1);
    position: sticky;
    top: 0;
    z-index: 10;
}

th {
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary);
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

td {
    padding: 16px;
    color: var(--text-secondary);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: var(--transition);
    vertical-align: top;
}

tbody tr:hover td {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
}    
    
</style>
</body>
</html>