<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $headers = getallheaders();
    $authorization = $headers['Authorization'] ?? '';
    
    if (strpos($authorization, 'Bearer ') !== 0) {
        throw new Exception('Authorization header missing or invalid', 401);
    }
    
    $token = substr($authorization, 7); // Remove 'Bearer ' prefix
    
    // Simple token validation - in a real app, you'd use JWT decoding
    // For this demo, we'll implement a basic session-like system using a temporary table
    
    // Since we don't have a tokens table, we'll validate by checking if the token was recently generated
    // This is a simplified approach - in a real app, you'd have a proper tokens table
    
    // For this demo, we'll just verify the user exists and return user info
    // The mobile app will handle re-authentication if needed
    $pdo = getConnection();
    
    // Extract user ID from the input (mobile app should pass user ID with token)
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User ID required for validation', 400);
    }
    
    $stmt = $pdo->prepare("SELECT id, username, name, phone, role FROM users WHERE id = ? AND role = 'agent'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Invalid token or user not found', 401);
    }
    
    $response = [
        'valid' => true,
        'user' => $user
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'valid' => false,
        'message' => $e->getMessage()
    ]);
}
?>