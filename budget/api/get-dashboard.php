<?php
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Сводка
$stmt = $pdo->prepare('
    SELECT 
        SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense
    FROM transactions 
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
');
$stmt->execute([$user_id, $month_start, $month_end]);
$summary = $stmt->fetch();

// Последние транзакции
$stmt = $pdo->prepare('
    SELECT t.*, 
           COALESCE(ic.name, ec.name) as category_name,
           COALESCE(ic.icon, ec.icon) as icon
    FROM transactions t
    LEFT JOIN income_categories ic ON t.category_id = ic.id AND t.type = "income"
    LEFT JOIN expense_categories ec ON t.category_id = ec.id AND t.type = "expense"
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT 10
');
$stmt->execute([$user_id]);
$recent = $stmt->fetchAll();

echo json_encode([
    'income' => $summary['total_income'] ?? 0,
    'expense' => $summary['total_expense'] ?? 0,
    'balance' => ($summary['total_income'] ?? 0) - ($summary['total_expense'] ?? 0),
    'recent' => $recent
]);
?>