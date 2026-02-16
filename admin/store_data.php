<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_region'])) {
        $region_name = trim($_POST['region_name']);

        if (empty($region_name)) {
            $error = 'Region name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO regions (name) VALUES (?)");
                $stmt->execute([$region_name]);
                $message = 'Region added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding region: ' . $e->getMessage();
            }
        }

    } elseif (isset($_POST['add_store'])) {

        $store_name = trim($_POST['store_name']);
        $mall = trim($_POST['mall']);
        $entity = trim($_POST['entity']);
        $brand = trim($_POST['brand']);
        $store_id = trim($_POST['store_id']);
        $region_id = $_POST['region_id'];

        if (empty($store_name) || empty($region_id)) {
            $error = 'Store name and region are required.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO stores (name, mall, entity, brand, region_id)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$store_name, $mall, $entity, $brand, $region_id]);
                $message = 'Store added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding store: ' . $e->getMessage();
            }
        }

    } elseif (isset($_POST['import_stores'])) {

        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {

            $file_tmp  = $_FILES['excel_file']['tmp_name'];
            $file_type = $_FILES['excel_file']['type'];

            $valid_types = [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv'
            ];

            if (in_array($file_type, $valid_types)) {

                if (($handle = fopen($file_tmp, "r")) !== FALSE) {

                    // Skip header
                    fgetcsv($handle);

                    $added_count = 0;

                    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {

                        if (count($data) < 6) {
                            continue;
                        }

                        $store_name = trim($data[0]);
                        $store_id   = trim($data[1]);
                        $brand      = trim($data[2]);
                        $mall       = trim($data[3]);
                        $entity     = trim($data[4]);
                        $regionName = trim($data[5]); // ✅ REGION COLUMN

                        if ($store_name === '' || $regionName === '') {
                            continue;
                        }

                        // Check store existence
                        $check_stmt = $pdo->prepare("SELECT id FROM stores WHERE id = ?");
                        $check_stmt->execute([$store_id]);

                        if ($check_stmt->fetch()) {
                            continue;
                        }

                        // 🔹 Find or create region (NOT mall)
                        $region_stmt = $pdo->prepare("SELECT id FROM regions WHERE name = ?");
                        $region_stmt->execute([$regionName]);
                        $region = $region_stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$region) {
                            $insert_region = $pdo->prepare("INSERT INTO regions (name) VALUES (?)");
                            $insert_region->execute([$regionName]);
                            $region_id = $pdo->lastInsertId();
                        } else {
                            $region_id = $region['id'];
                        }

                        // Insert store
                        $stmt = $pdo->prepare(
                            "INSERT INTO stores (id, name, mall, entity, brand, region_id)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->execute([
                            $store_id,
                            $store_name,
                            $mall,
                            $entity,
                            $brand,
                            $region_id
                        ]);

                        $added_count++;
                    }

                    fclose($handle);

                    if ($added_count > 0) {
                        $message = "Successfully imported $added_count stores!";
                    } else {
                        $error = "No stores were imported. Check CSV format.";
                    }

                } else {
                    $error = "Could not read the uploaded file.";
                }

            } else {
                $error = "Invalid file type. Please upload CSV or Excel.";
            }

        } else {
            $error = "Please select a file to import.";
        }

    } elseif (isset($_POST['delete_store'])) {

        $store_id = $_POST['store_id'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM daily_assignments WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $assignment_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($assignment_ids)) {
                $placeholders = implode(',', array_fill(0, count($assignment_ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM collections WHERE assignment_id IN ($placeholders)");
                $stmt->execute($assignment_ids);
            }

            $stmt = $pdo->prepare("DELETE FROM daily_assignments WHERE store_id = ?");
            $stmt->execute([$store_id]);

            $stmt = $pdo->prepare("DELETE FROM stores WHERE id = ?");
            $stmt->execute([$store_id]);

            $pdo->commit();
            $message = 'Store deleted successfully!';

        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error deleting store: ' . $e->getMessage();
        }

    } elseif (isset($_POST['update_approval'])) {

        $submission_id = $_POST['submission_id'];
        $status = $_POST['status'];
        $approved_by = $_SESSION['user_id'];

        try {
            $stmt = $pdo->prepare(
                "UPDATE bank_submissions
                 SET status = ?, approved_by = ?, approved_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$status, $approved_by, $submission_id]);
            $message = 'Submission status updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating submission: ' . $e->getMessage();
        }
    }
}

// Get all regions
$regions_stmt = $pdo->query("SELECT * FROM regions ORDER BY name");
$regions = $regions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all stores with region names
$stores_stmt = $pdo->query("
    SELECT s.*, r.name AS region_name
    FROM stores s
    LEFT JOIN regions r ON s.region_id = r.id
    ORDER BY r.name, s.name
");
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending submissions
$pending_submissions_stmt = $pdo->query("
    SELECT bs.*, u.name AS agent_name, u.username AS agent_username
    FROM bank_submissions bs
    JOIN users u ON bs.agent_id = u.id
    WHERE bs.status = 'pending'
    ORDER BY bs.created_at DESC
");
$pending_submissions = $pending_submissions_stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Data - Apparels Collection</title>
    <!--
    <link rel="stylesheet" href="../css/material-style.css">
    -->
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/store-data.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" href="../images/icon-192x192.png" type="image/png">
    <script src="../js/app.js"></script>
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
                <a href="assignments.php" class="nav-item">
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
                <a href="store_data.php" class="nav-item active">
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
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <h2>Import Stores via Excel</h2>
            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="import_stores" value="1">
                
                <div class="form-group">
                    <label for="excel_file">Upload Excel File:</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                    <small>Excel file should have columns: Storename, Store ID, Brand, Mall, Entity</small>
                </div>
                
                <button type="submit" class="btn">Import from Excel</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Add New Region</h2>
            <form method="post" action="">
                <input type="hidden" name="add_region" value="1">
                
                <div class="form-group">
                    <label for="region_name">Region Name:</label>
                    <input type="text" id="region_name" name="region_name" required>
                </div>
                
                <button type="submit" class="btn">Add Region</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Add New Store</h2>
            <form method="post" action="">
                <input type="hidden" name="add_store" value="1">
                
                <div class="form-group">
                    <label for="store_name">Store Name:</label>
                    <input type="text" id="store_name" name="store_name" required>
                </div>
                
                <div class="form-group">
                    <label for="mall">Mall:</label>
                    <input type="text" id="mall" name="mall">
                </div>
                
                <div class="form-group">
                    <label for="entity">Entity:</label>
                    <input type="text" id="entity" name="entity">
                </div>
                
                <div class="form-group">
                    <label for="brand">Brand:</label>
                    <input type="text" id="brand" name="brand">
                </div>
                
                <div class="form-group">
                    <label for="store_id">Store ID:</label>
                    <input type="text" id="store_id" name="store_id">
                </div>
                
                <div class="form-group">
                    <label for="region_id">Region:</label>
                    <select id="region_id" name="region_id" required>
                        <option value="">Select a region</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn">Add Store</button>
            </form>
            
            <hr style="margin: 30px 0;">

            <h2>Import Stores via Excel</h2>
            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="import_stores" value="1">
                
                <div class="form-group">
                    <label for="excel_file_stores">Upload Excel File:</label>
                    <input type="file" id="excel_file_stores" name="excel_file_stores" accept=".xlsx,.xls" required>
                    <small>Excel file should have columns: Storename, Store_ID, Brand, Mall, Entity</small>
                </div>
                
                <button type="submit" class="btn">Import Stores from Excel</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Regions</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Region Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($regions as $region): ?>
                        <tr>
                            <td><?php echo $region['id']; ?></td>
                            <td><?php echo htmlspecialchars($region['name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Stores</h2>
            <div class="table-responsive">
            <table>
            
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Store Name</th>
                        <th>Mall</th>
                        <th>Entity</th>
                        <th>Brand</th>
                        <th>Region</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $store): ?>
                        <tr>
                            <td><?php echo $store['id']; ?></td>
                            <td><?php echo htmlspecialchars($store['name']); ?></td>
                            <td><?php echo htmlspecialchars($store['mall'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($store['entity'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($store['brand'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($store['region_name'] ?? 'N/A'); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this store?');">
                                    <input type="hidden" name="delete_store" value="1">
                                    <input type="hidden" name="store_id" value="<?php echo $store['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <hr style="margin: 30px 0;">
            
            <h2>Bank Submissions for Approval</h2>
            <?php if (count($pending_submissions) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Amount</th>
                            <th>Receipt Image</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_submissions as $submission): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($submission['agent_name']); ?> (<?php echo htmlspecialchars($submission['agent_username']); ?>)</td>
                                <td><?php echo number_format($submission['total_amount'], 2); ?></td>
                                <td>
                                    <?php if ($submission['receipt_image']): ?>
                                        <a href="../uploads/<?php echo htmlspecialchars($submission['receipt_image']); ?>" target="_blank">View Receipt</a>
                                    <?php else: ?>
                                        No receipt
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($submission['created_at'])); ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="update_approval" value="1">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        <select name="status" required>
                                            <option value="">Choose action</option>
                                            <option value="approved">Approve</option>
                                            <option value="rejected">Reject</option>
                                        </select>
                                        <button type="submit" class="btn">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No pending bank submissions for approval.</p>
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
            console.log('Overlay clicked');
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
            alert('Hamburger clicked!');
            console.log('Hamburger clicked!');
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
</body>
</html>