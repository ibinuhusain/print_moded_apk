<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Database connection (adjust credentials as needed)
$servername = "localhost";
$username = "your_username"; // Replace with your DB username
$password = "your_password"; // Replace with your DB password
$dbname = "your_database"; // Replace with your database name

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(["error" => "Connection failed: " . $e->getMessage()]);
    exit;
}

// Handle incoming requests
$request_method = $_SERVER["REQUEST_METHOD"];

switch($request_method) {
    case 'GET':
        get_receipt_data();
        break;
    case 'POST':
        save_receipt_data();
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

function get_receipt_data() {
    global $pdo;

    $receipt_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$receipt_id) {
        echo json_encode(["error" => "Receipt ID is required"]);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM receipts WHERE id = :id");
        $stmt->bindParam(':id', $receipt_id);
        $stmt->execute();
        
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($receipt) {
            echo json_encode($receipt);
        } else {
            echo json_encode(["error" => "Receipt not found"]);
        }
    } catch(PDOException $e) {
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function save_receipt_data() {
    global $pdo;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode(["error" => "Invalid JSON data"]);
        return;
    }

    // Validate required fields
    if (!isset($data['customer_name']) || !isset($data['items']) || !isset($data['total'])) {
        echo json_encode(["error" => "Missing required fields"]);
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO receipts (customer_name, items, total, transaction_date) VALUES (:customer_name, :items, :total, NOW())");
        
        $items_json = json_encode($data['items']);
        
        $stmt->bindParam(':customer_name', $data['customer_name']);
        $stmt->bindParam(':items', $items_json);
        $stmt->bindParam(':total', $data['total']);
        
        $stmt->execute();
        
        $receipt_id = $pdo->lastInsertId();
        
        echo json_encode([
            "success" => true,
            "receipt_id" => $receipt_id,
            "message" => "Receipt saved successfully"
        ]);
    } catch(PDOException $e) {
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}
?>