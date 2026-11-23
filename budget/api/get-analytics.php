<?php
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Получаем параметры
$filter = $_GET['filter'] ?? 'current_month';
$start = $end = '';

switch ($filter) {
    case 'current_month':
        $start = date('Y-m-01'); $end = date('Y-m-t'); break;
    case 'last_month':
        $start = date('Y-m-01', strtotime('-1 month'));
        $end = date('Y-m-t', strtotime('-1 month')); break;
    case 'current_year':
        $start = date('Y-01-01'); $end = date('Y-12-31'); break;
    case 'last_year':
        $start = date('Y-01-01', strtotime('-1 year'));
        $end = date('Y-12-31', strtotime('-1 year')); break;
    case 'custom':
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t'); break;
}

// Текущий период
$stmt = $pdo->prepare('
    SELECT 
        SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense
    FROM transactions WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
');
$stmt->execute([$user_id, $start, $end]);
$current = $stmt->fetch();

// Разбивка по категориям
$stmt = $pdo->prepare('
    SELECT c.name, c.icon, SUM(t.amount) as total, t.type
    FROM transactions t
    LEFT JOIN income_categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?
    GROUP BY t.category_id, t.type ORDER BY total DESC
');
$stmt->execute([$user_id, $start, $end]);
$breakdown = $stmt->fetchAll();

echo json_encode([
    'period' => ['start' => $start, 'end' => $end],
    'current' => $current,
    'breakdown' => $breakdown
]);
?>