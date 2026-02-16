<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config.php';

//requireAdmin();
$pdo = getConnection();

// Handle approval/rejection
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_reject_submission'])) {
    $submission_id = $_POST['submission_id'];
    $status = $_POST['status'];
    $approved_by = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("UPDATE bank_submissions SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $approved_by, $submission_id]);
        
        $message = 'Submission status updated successfully!';
    } catch (PDOException $e) {
        $error = 'Error updating submission: ' . $e->getMessage();
    }
}

// Get pending bank submissions for approval with agent details
$pending_submissions_stmt = $pdo->query("
    SELECT bs.*, u.name as agent_name, u.username as agent_username,
           DATE_FORMAT(bs.created_at, '%M %d, %Y at %h:%i %p') as formatted_created_at
    FROM bank_submissions bs
    JOIN users u ON bs.agent_id = u.id
    WHERE bs.status = 'pending'
    ORDER BY bs.created_at DESC
");
$pending_submissions = $pending_submissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get agent statistics for the dashboard
$agents_stmt = $pdo->prepare("
    SELECT u.id, u.name, u.username,
           (SELECT COUNT(*) FROM daily_assignments da WHERE da.agent_id = u.id AND da.status = 'completed') as completed_stores,
           (SELECT COUNT(DISTINCT s.mall) FROM daily_assignments da 
            JOIN stores s ON da.store_id = s.id 
            WHERE da.agent_id = u.id AND da.status = 'completed') as completed_malls,
           (SELECT COUNT(DISTINCT s.region_id) FROM daily_assignments da 
            JOIN stores s ON da.store_id = s.id 
            WHERE da.agent_id = u.id AND da.status = 'completed') as completed_regions
    FROM users u
    WHERE u.role = 'agent'
    ORDER BY u.name
");
$agents_stmt->execute();
$agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Submissions Approval - Apparels Collection</title>
    <!--
    <link rel="stylesheet" href="../css/material-style.css">
    -->
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    <link rel="stylesheet" href="../css/bank-approvals.css">
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
                <a href="store_data.php" class="nav-item">
                    <span class="material-symbols-outlined">storefront</span>
                    <span>Store Data</span>
                </a>
                <a href="bank_approvals.php" class="nav-item active">
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
            
            <!-- Agent Statistics -->
            <h2>Agent Performance</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Agent ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Completed Stores</th>
                            <th>Completed Malls</th>
                            <th>Completed Regions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $agent): ?>
                            <tr>
                                <td><?php echo $agent['id']; ?></td>
                                <td><?php echo htmlspecialchars($agent['name']); ?></td>
                                <td><?php echo htmlspecialchars($agent['username']); ?></td>
                                <td><?php echo $agent['completed_stores']; ?></td>
                                <td><?php echo $agent['completed_malls']; ?></td>
                                <td><?php echo $agent['completed_regions']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <!-- Pending Submissions -->
            <h2>Pending Bank Submissions</h2>
            <?php if (count($pending_submissions) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Amount</th>
                                <th>Submitted Date</th>
                                <th>Receipt</th>
                                <th>Collection Details</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_submissions as $submission): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($submission['agent_name']); ?></strong><br>
                                        <small>(<?php echo htmlspecialchars($submission['agent_username']); ?>)</small>
                                    </td>
                                    <td><?php echo number_format($submission['total_amount'], 2); ?></td>
                                    <td><?php echo $submission['formatted_created_at']; ?></td>
                                    <td>
                                        <?php if ($submission['receipt_image']): ?>
                                            <a href="../uploads/<?php echo htmlspecialchars($submission['receipt_image']); ?>" target="_blank">View Receipt</a>
                                        <?php else: ?>
                                            No receipt
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Fetch collection details for this agent's assignments on the submission day -->
                                        <?php
                                        $collection_details_stmt = $pdo->prepare("
                                            SELECT SUM(c.amount_collected) as total_collected, 
                                                   SUM(c.pending_amount) as total_pending
                                            FROM collections c
                                            JOIN daily_assignments da ON c.assignment_id = da.id
                                            WHERE da.agent_id = ? AND DATE(da.date_assigned) = DATE(?)
                                        ");
                                        $collection_details_stmt->execute([$submission['agent_id'], $submission['created_at']]);
                                        $collection_details = $collection_details_stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        $cash_collected = $collection_details['total_collected'] ?: 0;
                                        $pending_amount = $collection_details['total_pending'] ?: 0;
                                        ?>
                                        <div class="collection-details">
                                            <p><strong>Cash Collected:</strong> <?php echo number_format($cash_collected, 2); ?></p>
                                            <p><strong>Pending Amount:</strong> <?php echo number_format($pending_amount, 2); ?></p>
                                            <p><strong>Payment Mode:</strong> Bank Deposit</p>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to approve this submission?');">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <input type="hidden" name="approve_reject_submission" value="1">
                                            <button type="submit" class="btn btn-success">Approve</button>
                                        </form>
                                        <form method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to reject this submission?');">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <input type="hidden" name="approve_reject_submission" value="1">
                                            <button type="submit" class="btn btn-danger">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No pending bank submissions for approval.</p>
            <?php endif; ?>
            
            <!-- Approved/Rejected Submissions -->
            <h2 style="margin-top: 40px;">Recent Bank Submissions</h2>
            <?php
            $recent_submissions_stmt = $pdo->query("
                SELECT bs.*, u.name as agent_name, u.username as agent_username,
                       u2.name as approved_by_name,
                       DATE_FORMAT(bs.approved_at, '%M %d, %Y at %h:%i %p') as formatted_approved_at,
                       DATE_FORMAT(bs.created_at, '%M %d, %Y at %h:%i %p') as formatted_created_at
                FROM bank_submissions bs
                JOIN users u ON bs.agent_id = u.id
                LEFT JOIN users u2 ON bs.approved_by = u2.id
                WHERE bs.status != 'pending'
                ORDER BY bs.created_at DESC
                LIMIT 10
            ");
            $recent_submissions = $recent_submissions_stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <?php if (count($recent_submissions) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th>Approved By</th>
                                <th>Approved At</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_submissions as $submission): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($submission['agent_name']); ?></strong><br>
                                        <small>(<?php echo htmlspecialchars($submission['agent_username']); ?>)</small>
                                    </td>
                                    <td><?php echo number_format($submission['total_amount'], 2); ?></td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        if ($submission['status'] === 'approved') {
                                            $status_class = 'status-approved';
                                        } elseif ($submission['status'] === 'rejected') {
                                            $status_class = 'status-rejected';
                                        } else {
                                            $status_class = 'status-pending';
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>">
                                            <?php echo ucfirst($submission['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $submission['formatted_created_at']; ?></td>
                                    <td><?php echo $submission['approved_by_name'] ?? 'N/A'; ?></td>
                                    <td><?php echo $submission['formatted_approved_at'] ?? 'N/A'; ?></td>
                                    <td>
                                        <?php if ($submission['receipt_image']): ?>
                                            <a href="../uploads/<?php echo htmlspecialchars($submission['receipt_image']); ?>" target="_blank">View Receipt</a>
                                        <?php else: ?>
                                            No receipt
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No recent bank submissions found.</p>
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
            alert('');
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
</body>
</html>