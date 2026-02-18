<?php
// Full API endpoint for Apparel Collection Agent App
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

// Get the requested path
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$endpoint = str_replace('/api-full.php', '', $request);

// Parse endpoint segments
$segments = explode('/', trim($endpoint, '/'));

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        // Handle preflight requests
        http_response_code(200);
        exit();
    }

    // Route based on the endpoint
    if (count($segments) >= 1) {
        switch ($segments[1]) {
            case 'login':
                handleLogin();
                break;
            case 'validate-token':
                validateToken();
                break;
            case 'assignments':
                handleAssignments($segments);
                break;
            case 'collections':
                handleCollections($segments);
                break;
            case 'bank-submissions':
                handleBankSubmissions($segments);
                break;
            default:
                throw new Exception('Endpoint not found', 404);
        }
    } else {
        throw new Exception('Invalid endpoint', 400);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleLogin() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Username and password are required', 400);
    }

    $username = trim($input['username']);
    $password = $input['password'];

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
}

function validateToken() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $headers = getallheaders();
    $authorization = $headers['Authorization'] ?? '';

    if (strpos($authorization, 'Bearer ') !== 0) {
        throw new Exception('Authorization header missing or invalid', 401);
    }

    $token = substr($authorization, 7); // Remove 'Bearer ' prefix

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
}

function handleAssignments($segments) {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }

    $headers = getallheaders();
    $authorization = $headers['Authorization'] ?? '';

    if (strpos($authorization, 'Bearer ') !== 0) {
        throw new Exception('Authorization header missing or invalid', 401);
    }

    $token = substr($authorization, 7); // Remove 'Bearer ' prefix

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
}

function handleCollections($segments) {
    global $pdo;
    
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
        $uploadDir = 'uploads/receipts/';

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
}

function handleBankSubmissions($segments) {
    global $pdo;
    
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

    if ($method === 'POST') {
        // Handle bank submission
        $totalAmount = floatval($_POST['total_amount'] ?? 0);

        // Handle file upload for receipt image
        $receiptImage = null;
        $uploadDir = 'uploads/bank_receipts/';

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
}
?>