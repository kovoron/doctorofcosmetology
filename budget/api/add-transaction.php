<?php
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

$amount = floatval($input['amount'] ?? 0);
$type = $input['type'] ?? '';
$category_id = intval($input['category_id'] ?? 0);
$description = trim($input['description'] ?? '');
$date = $input['date'] ?? date('Y-m-d');

if (!in_array($type, ['income', 'expense'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

if ($amount <= 0 || $category_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid amount or category']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        INSERT INTO transactions (user_id, type, amount, category_id, description, transaction_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$user_id, $type, $amount, $category_id, $description, $date]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>