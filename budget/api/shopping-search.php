<?php
require_once '../config.php';
header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('
    SELECT name 
    FROM shopping_products 
    WHERE name LIKE ? 
    ORDER BY name 
    LIMIT 10
');
$stmt->execute(["%$query%"]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>