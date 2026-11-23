<?php
require_once '../config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');

if (!$name) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty name']);
    exit;
}

$pdo = getDB();

try {
    // INSERT ... ON DUPLICATE KEY UPDATE — не добавит дубль
    $stmt = $pdo->prepare('
        INSERT INTO shopping_list (product_name) 
        VALUES (?) 
        ON DUPLICATE KEY UPDATE added_at = CURRENT_TIMESTAMP
    ');
    $stmt->execute([$name]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>