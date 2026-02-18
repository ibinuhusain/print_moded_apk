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
        // Handle collection submission
        $assignmentId = $_POST['assignment_id'] ?? null;
        $amountCollected = floatval($_POST['amount_collected'] ?? 0);
        $pendingAmount = floatval($_POST['pending_amount'] ?? 0);
        $comments = trim($_POST['comments'] ?? '');
        
        if (!$assignmentId) {
            throw new Exception('Assignment ID is required', 400);
        }
        
        // Verify the assignment belongs to the current user
        $verifyStmt = $pdo->prepare("SELECT id FROM daily_assignments WHERE id = ? AND agent_id = ?");
        $verifyStmt->execute([$assignmentId, $userId]);
        $assignment = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assignment) {
            throw new Exception('Assignment not found or does not belong to user', 404);
        }
        
        // Handle file uploads
        $receiptImages = [];
        $uploadDir = '../../uploads/receipts/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        if (isset($_FILES['receipt_images'])) {
            $files = $_FILES['receipt_images'];
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$i];
                    $name = $files['name'][$i];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $newFilename = uniqid() . '_' . basename($name, ".$ext") . '.' . $ext;
                        $destination = $uploadDir . $newFilename;
                        
                        if (move_uploaded_file($tmpName, $destination)) {
                            $receiptImages[] = $newFilename;
                        } else {
                            throw new Exception("Failed to upload receipt image: $name", 500);
                        }
                    } else {
                        throw new Exception("Invalid file type: $ext. Only JPG, PNG, GIF allowed.", 400);
                    }
                } elseif ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    throw new Exception("File upload error: " . $files['error'][$i], 500);
                }
            }
        }
        
        // Check if collection exists
        $checkStmt = $pdo->prepare("SELECT id FROM collections WHERE assignment_id = ?");
        $checkStmt->execute([$assignmentId]);
        $existingCollection = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingCollection) {
            // Update existing collection
            $updateStmt = $pdo->prepare("
                UPDATE collections 
                SET amount_collected = ?, pending_amount = ?, comments = ?, receipt_images = ?
                WHERE assignment_id = ?
            ");
            $updateStmt->execute([
                $amountCollected, 
                $pendingAmount, 
                $comments, 
                json_encode($receiptImages), 
                $assignmentId
            ]);
        } else {
            // Insert new collection
            $insertStmt = $pdo->prepare("
                INSERT INTO collections (assignment_id, amount_collected, pending_amount, comments, receipt_images)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $assignmentId, 
                $amountCollected, 
                $pendingAmount, 
                $comments, 
                json_encode($receiptImages)
            ]);
        }
        
        // Update assignment status to 'completed'
        $updateAssignment = $pdo->prepare("UPDATE daily_assignments SET status = 'completed' WHERE id = ?");
        $updateAssignment->execute([$assignmentId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Collection saved successfully',
            'assignment_id' => $assignmentId
        ]);
        
    } else if ($method === 'GET') {
        // Return collections for the user (not specifically used in the mobile app but useful for API completeness)
        $assignmentId = $_GET['assignment_id'] ?? null;
        
        if ($assignmentId) {
            // Get specific assignment's collection
            $stmt = $pdo->prepare("
                SELECT * FROM collections 
                WHERE assignment_id = ? AND EXISTS (
                    SELECT 1 FROM daily_assignments 
                    WHERE id = ? AND agent_id = ?
                )
            ");
            $stmt->execute([$assignmentId, $assignmentId, $userId]);
            $collection = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($collection) {
                $collection['receipt_images'] = json_decode($collection['receipt_images'], true) ?: [];
            }
            
            echo json_encode($collection ?: []);
        } else {
            // Get all collections for the user
            $stmt = $pdo->prepare("
                SELECT c.*, da.id as assignment_id 
                FROM collections c
                JOIN daily_assignments da ON c.assignment_id = da.id
                WHERE da.agent_id = ?
            ");
            $stmt->execute([$userId]);
            $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($collections as &$collection) {
                $collection['receipt_images'] = json_decode($collection['receipt_images'], true) ?: [];
            }
            
            echo json_encode($collections);
        }
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