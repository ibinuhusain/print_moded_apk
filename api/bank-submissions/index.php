<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    $headers = getallheaders();
    $authorization = $headers['Authorization'] ?? '';
    
    if (strpos($authorization, 'Bearer ') !== 0) {
        throw new Exception('Authorization header missing or invalid', 401);
    }
    
    $token = substr($authorization, 7); // Remove 'Bearer ' prefix
    
    // For this demo, we'll extract user ID from a session or pass it in another way
    $input = json_decode(file_get_contents('php://input'), true);
    $userIdFromInput = $input['user_id'] ?? null;
    
    if (!$userIdFromInput && !isset($_POST['user_id'])) {
        throw new Exception('User ID required', 400);
    }
    
    $userId = $userIdFromInput ?: $_POST['user_id'];
    
    $pdo = getConnection();
    
    if ($method === 'POST') {
        // Handle bank submission
        $totalAmount = floatval($_POST['total_amount'] ?? 0);
        
        // Handle file upload for receipt image
        $receiptImage = null;
        $uploadDir = '../../uploads/bank_receipts/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['receipt_image']['tmp_name'];
            $name = $_FILES['receipt_image']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
                $newFilename = uniqid() . '_' . basename($name, ".$ext") . '.' . $ext;
                $destination = $uploadDir . $newFilename;
                
                if (move_uploaded_file($tmpName, $destination)) {
                    $receiptImage = $newFilename;
                } else {
                    throw new Exception("Failed to upload bank receipt image: $name", 500);
                }
            } else {
                throw new Exception("Invalid file type: $ext. Only JPG, PNG, GIF, PDF allowed.", 400);
            }
        } else {
            throw new Exception("Bank receipt image is required.", 400);
        }
        
        // Insert bank submission
        $stmt = $pdo->prepare("
            INSERT INTO bank_submissions (agent_id, total_amount, receipt_image, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $totalAmount, $receiptImage]);
        
        // Get the ID of the newly inserted record
        $submissionId = $pdo->lastInsertId();
        
        // Mark today's collections as submitted to bank
        $today = date('Y-m-d');
        $updateStmt = $pdo->prepare("
            UPDATE collections c
            JOIN daily_assignments da ON c.assignment_id = da.id
            SET c.submitted_to_bank = TRUE, c.submitted_at = NOW()
            WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
        ");
        $updateStmt->execute([$userId, $today]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank submission sent for approval successfully!',
            'submission_id' => $submissionId
        ]);
        
    } else if ($method === 'GET') {
        // Return bank submission history for the user
        $limit = intval($_GET['limit'] ?? 10);
        $offset = intval($_GET['offset'] ?? 0);
        
        $stmt = $pdo->prepare("
            SELECT bs.*, u2.name as approved_by_name, 
                   DATE_FORMAT(bs.approved_at, '%M %d, %Y at %h:%i %p') as formatted_approved_at,
                   DATE_FORMAT(bs.created_at, '%M %d, %Y at %h:%i %p') as formatted_created_at
            FROM bank_submissions bs
            LEFT JOIN users u2 ON bs.approved_by = u2.id
            WHERE bs.agent_id = ?
            ORDER BY bs.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($submissions);
    } else {
        throw new Exception('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>