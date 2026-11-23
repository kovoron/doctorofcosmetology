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
$stmt = $pdo->prepare('DELETE FROM shopping_list WHERE product_name = ?');
$stmt->execute([$name]);

echo json_encode(['success' => true]);
?>