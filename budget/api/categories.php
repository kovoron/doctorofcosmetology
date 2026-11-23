<?php
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'expense';

$table = $type === 'income' ? 'income_categories' : 'expense_categories';

$stmt = $pdo->prepare("SELECT id, name, icon FROM $table WHERE user_id IS NULL OR user_id = ? ORDER BY name");
$stmt->execute([$user_id]);

echo json_encode($stmt->fetchAll());
?>