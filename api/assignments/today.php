<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }

    $headers = getallheaders();
    $authorization = $headers['Authorization'] ?? '';
    
    if (strpos($authorization, 'Bearer ') !== 0) {
        throw new Exception('Authorization header missing or invalid', 401);
    }
    
    $token = substr($authorization, 7); // Remove 'Bearer ' prefix
    
    // For this demo, we'll extract user ID from a session or pass it in another way
    // In a real app, you'd decode the JWT to get user info
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User ID required', 400);
    }
    
    $pdo = getConnection();
    $today = date('Y-m-d');
    
    // Get agent's assignments for today with related data
    $stmt = $pdo->prepare("
        SELECT 
            da.id,
            da.agent_id,
            da.store_id,
            da.date_assigned,
            da.status,
            s.name as store_name,
            s.address as store_address,
            s.mall,
            s.entity,
            s.brand,
            r.name as region_name,
            COALESCE(SUM(c.amount_collected), 0) as collected_amount,
            COALESCE(SUM(c.pending_amount), 0) as pending_amount,
            c.comments,
            c.receipt_images,
            c.submitted_to_bank,
            c.submitted_at
        FROM daily_assignments da
        JOIN stores s ON da.store_id = s.id
        LEFT JOIN regions r ON s.region_id = r.id
        LEFT JOIN collections c ON da.id = c.assignment_id
        WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
        GROUP BY da.id
        ORDER BY s.name
    ");
    $stmt->execute([$userId, $today]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each assignment to include collection data properly
    foreach ($assignments as &$assignment) {
        // Format the collection data
        $assignment['collection'] = [
            'amount_collected' => (float)$assignment['collected_amount'],
            'pending_amount' => (float)$assignment['pending_amount'],
            'comments' => $assignment['comments'],
            'receipt_images' => json_decode($assignment['receipt_images'], true) ?: [],
            'submitted_to_bank' => (bool)$assignment['submitted_to_bank'],
            'submitted_at' => $assignment['submitted_at']
        ];
        
        // Remove the individual fields that are now part of collection
        unset(
            $assignment['collected_amount'],
            $assignment['pending_amount'],
            $assignment['comments'],
            $assignment['receipt_images'],
            $assignment['submitted_to_bank'],
            $assignment['submitted_at']
        );
    }
    
    echo json_encode($assignments);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>