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

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Username and password are required', 400);
    }

    $username = trim($input['username']);
    $password = $input['password'];

    $pdo = getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        throw new Exception('Invalid credentials', 401);
    }

    if ($user['role'] !== 'agent') {
        throw new Exception('Access denied. Only agents can login to this app.', 403);
    }

    // Generate a simple token (in production, use proper JWT)
    $token = bin2hex(random_bytes(32));
    $expiresAt = time() + (7 * 24 * 60 * 60); // Token valid for 7 days
    
    // Store token in session or database (for simplicity, we'll use a basic approach)
    // In a real app, you'd want to store tokens in a secure way
    
    $response = [
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'phone' => $user['phone'],
            'role' => $user['role']
        ]
    ];

    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>