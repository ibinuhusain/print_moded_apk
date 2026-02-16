<?php
require_once '../includes/auth.php';
requireLogin();

if (hasRole('admin')) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];

// Get assignment ID from URL parameter
$assignment_id = $_GET['assignment_id'] ?? null;

if (!$assignment_id) {
    header("Location: dashboard.php");
    exit();
}

// Verify that this assignment belongs to the current agent
$stmt = $pdo->prepare("
    SELECT da.*, s.name as store_name, s.address as store_address, u.name as agent_name
    FROM daily_assignments da
    JOIN stores s ON da.store_id = s.id
    JOIN users u ON da.agent_id = u.id
    WHERE da.id = ? AND da.agent_id = ?
");
$stmt->execute([$assignment_id, $agent_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $message = 'Collection information saved successfully!';
}

if (!$assignment) {
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount_collected = floatval($_POST['amount_collected']);
    $pending_amount = floatval($_POST['pending_amount']);
    $comments = trim($_POST['comments']);
    
    // Handle file uploads
    $receipt_images = [];
    if (isset($_FILES['receipt_images']) && $_FILES['receipt_images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
        $upload_dir = '../uploads/';
        
        for ($i = 0; $i < count($_FILES['receipt_images']['name']); $i++) {
            if ($_FILES['receipt_images']['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['receipt_images']['tmp_name'][$i];
                $name = $_FILES['receipt_images']['name'][$i];
                
                // Sanitize filename
                $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
                    $new_filename = uniqid() . '_' . $name;
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($tmp_name, $destination)) {
                        $receipt_images[] = $new_filename;
                    } else {
                        $error = 'Failed to upload one or more images.';
                    }
                } else {
                    $error = 'Only JPG, JPEG, PNG, GIF, and PDF files are allowed.';
                }
            }
        }
    }
    
    if (empty($error)) {
        try {
            // Check if collection record already exists
            $check_stmt = $pdo->prepare("SELECT id FROM collections WHERE assignment_id = ?");
            $check_stmt->execute([$assignment_id]);
            $existing_collection = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_collection) {
                // Update existing collection
                $update_stmt = $pdo->prepare("
                    UPDATE collections 
                    SET amount_collected = ?, pending_amount = ?, comments = ?, receipt_images = ?, updated_at = NOW()
                    WHERE assignment_id = ?
                ");
                $update_stmt->execute([
                    $amount_collected, 
                    $pending_amount, 
                    $comments, 
                    json_encode($receipt_images), 
                    $assignment_id
                ]);
            } else {
                // Insert new collection
                $insert_stmt = $pdo->prepare("
                    INSERT INTO collections (assignment_id, amount_collected, pending_amount, comments, receipt_images, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $insert_stmt->execute([
                    $assignment_id, 
                    $amount_collected, 
                    $pending_amount, 
                    $comments, 
                    json_encode($receipt_images)
                ]);
            }
            
            // Update assignment status to completed
            $update_assignment = $pdo->prepare("UPDATE daily_assignments SET status = 'completed' WHERE id = ?");
            $update_assignment->execute([$assignment_id]);
            
            header("Location: store.php?assignment_id=" . $assignment_id . "&saved=1");
            exit;
        } catch (PDOException $e) {
            $error = 'Error saving collection: ' . $e->getMessage();
        }
    }
}

// Get existing collection data if it exists
$collection = null;
$stmt = $pdo->prepare("SELECT * FROM collections WHERE assignment_id = ?");
$stmt->execute([$assignment_id]);
$collection = $stmt->fetch(PDO::FETCH_ASSOC);

// Set default values if no collection exists yet
$amount_collected = $collection ? $collection['amount_collected'] : 0;
$pending_amount = $collection ? $collection['pending_amount'] : 0;
$comments = $collection ? $collection['comments'] : '';
$receipt_images = $collection ? json_decode($collection['receipt_images'], true) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Collection - Apparels Collection</title>
    <!-- Fix the CSS path - added /css/ -->
    <link rel="stylesheet" href="../css/agent-styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" href="../images/icon-192x192.png" type="image/png">
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="../images/logo.png" alt="Apparel Collection Logo">
                    <div class="sidebar-logo-text"></div>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="material-symbols-outlined">home</span>
                    <span>Dashboard</span>
                </a>
                <a href="store.php" class="nav-item active">
                    <span class="material-symbols-outlined">storefront</span>
                    <span>Store</span>
                </a>
                <a href="submissions.php" class="nav-item">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <span>Submissions</span>
                </a>
            </div>
        </div> <!-- Close sidebar div -->
        
        <!-- Add this overlay div -->
        <div class="overlay"></div>
        
        <!-- Main Content Area - moved outside of sidebar -->
        <div class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger-menu">
                        <span class="material-symbols-outlined">menu</span>
                    </div>
                    <div class="header-title">Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Agent'); ?></div>
                </div>
                <div class="header-right">
                    <a href="../logout.php" class="logout-btn">
                        <span class="material-symbols-outlined">logout</span>
                        <span>Logout</span>
                    </a>
                </div>
            </div> <!-- Close top-header div -->
            
            <!-- Content Area -->
            <div class="content">
                <div class="agent-main-content">
                    <div class="header">
                        <h1>Store Collection</h1>
                    </div>
                    
                    <div class="agent-content">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <h2>Store Information</h2>
                        <div class="card">
                            <p><strong>Store Name:</strong> <?php echo htmlspecialchars($assignment['store_name']); ?></p>
                            <p><strong>Region:</strong> <?php 
                                $store_details = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
                                $store_details->execute([$assignment['store_id']]);
                                $store_info = $store_details->fetch(PDO::FETCH_ASSOC);
                                echo htmlspecialchars($store_info['region_name'] ?? 'N/A'); 
                            ?></p>
                            <p><strong>Mall:</strong> <?php echo htmlspecialchars($store_info['mall'] ?? 'N/A'); ?></p>
                            <p><strong>Entity:</strong> <?php echo htmlspecialchars($store_info['entity'] ?? 'N/A'); ?></p>
                            <p><strong>Brand:</strong> <?php echo htmlspecialchars($store_info['brand'] ?? 'N/A'); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($assignment['store_address']); ?></p>
                            <p><strong>Assigned Agent:</strong> <?php echo htmlspecialchars($assignment['agent_name']); ?></p>
                            <p><strong>Target Amount:</strong> <?php echo number_format($assignment['target_amount'], 2); ?></p>
                        </div>
                        
                        <h2>Collection Details</h2>
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="amount_collected">Amount Collected:</label>
                                <input type="number" id="amount_collected" name="amount_collected" 
                                       value="<?php echo $amount_collected; ?>" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="pending_amount">Pending Amount:</label>
                                <input type="number" id="pending_amount" name="pending_amount" 
                                       value="<?php echo $pending_amount; ?>" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="comments">Comments:</label>
                                <textarea id="comments" name="comments" rows="4"><?php echo htmlspecialchars($comments); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="receipt_images">Upload Receipts/Books Images:</label>
                                <input type="file" id="receipt_images" name="receipt_images[]" multiple accept="image/*,.pdf">
                                <small>Upload receipt images, book photos, etc. (JPG, PNG, GIF, PDF)</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Collection</button>
                                <button type="button" class="btn btn-secondary" onclick="printReceipt(<?php echo $assignment_id; ?>)">
    Print Receipt
</button>
                                <a href="dashboard.php" class="btn btn-link">Back to Dashboard</a>
                            </div>
                        </form>
                        
                        <?php if (!empty($receipt_images)): ?>
                            <h2>Uploaded Receipts</h2>
                            <div class="image-preview">
                                <?php foreach ($receipt_images as $img): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($img); ?>" target="_blank">
                                        <img src="../uploads/<?php echo htmlspecialchars($img); ?>" alt="Receipt">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div> <!-- Close agent-content -->
                </div> <!-- Close agent-main-content -->
            </div> <!-- Close content -->
        </div> <!-- Close main-content -->
    </div> <!-- Close container -->
    
    <!-- JavaScript Files -->
    <script src="../js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get elements
            const hamburger = document.querySelector('.hamburger-menu');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            
            // Add click event to hamburger menu
            if (hamburger && sidebar) {
                hamburger.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                    document.body.classList.toggle('menu-open');
                });
            }

            // Add click event to overlay to close menu
            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.classList.remove('menu-open');
                });
            }

            // Close menu when clicking on nav items (mobile)
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                        if (overlay) overlay.classList.remove('active');
                        document.body.classList.remove('menu-open');
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    if (sidebar) sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                    document.body.classList.remove('menu-open');
                }
            });
        });

</script>
<script>
<script>
function printReceipt() {
    var collected = document.getElementById("amount_collected").value;
    var pending = document.getElementById("pending_amount").value;

    const printData = {
        assignment_id: "<?php echo $assignment_id; ?>",
        store_name: "<?php echo addslashes($assignment['store_name']); ?>",
        agent_name: "<?php echo addslashes($assignment['agent_name']); ?>",
        target_amount: "<?php echo $assignment['target_amount']; ?>",
        amount_collected: collected,
        pending_amount: pending,
        date: new Date().toLocaleString()
    };

    // 1. Package the message
    const messageObj = {
        type: "PRINT_RECEIPT",
        payload: printData
    };

    // 2. CRITICAL: Convert object to String to bypass WebView security blocks
    const messageString = JSON.stringify(messageObj);
    
    console.log("Sending stringified data to parent");

    // 3. Send to top-level parent
    window.top.postMessage(messageString, "*");
}
</script>
</script>


</body>
</html>