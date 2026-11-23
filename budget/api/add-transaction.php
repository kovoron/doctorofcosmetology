<?php
require_once '../config.php';
requireAuth();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

// CSRF: заголовок X-CSRF-Token либо поле csrf_token
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
if (!is_string($csrf) || !verifyCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token mismatch']);
    exit;
}

$pdo = getDB();
$user_id = (int) $_SESSION['user_id'];

$amount = round((float) str_replace(',', '.', (string) ($input['amount'] ?? 0)), 2);
$type = $input['type'] ?? '';
$category_id = (int) ($input['category_id'] ?? 0);
$description = trim((string) ($input['description'] ?? ''));
$date = (string) ($input['date'] ?? date('Y-m-d'));

if (!in_array($type, ['income', 'expense'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

$errors = [];
if ($amount <= 0) {
    $errors['amount'] = 'Сумма должна быть больше нуля';
}
if ($category_id <= 0) {
    $errors['category_id'] = 'Не выбрана категория';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
    $errors['date'] = 'Некорректная дата';
} elseif ($date > date('Y-m-d')) {
    $errors['date'] = 'Дата не может быть в будущем';
}
if (mb_strlen($description) > 500) {
    $errors['description'] = 'Описание длиннее 500 символов';
}

// Категория должна принадлежать нужной таблице и быть доступной пользователю
if ($category_id > 0) {
    $table = $type === 'income' ? 'income_categories' : 'expense_categories';
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE id = ? AND (user_id IS NULL OR user_id = ?)");
    $stmt->execute([$category_id, $user_id]);
    if (!$stmt->fetch()) {
        $errors['category_id'] = 'Категория недоступна';
    }
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => 'Проверьте введённые данные', 'fields' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare('
        INSERT INTO transactions (user_id, type, amount, category_id, description, transaction_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$user_id, $type, $amount, $category_id, $description, $date]);

    echo json_encode(['success' => true, 'id' => (int) $pdo->lastInsertId()]);
} catch (Throwable $e) {
    error_log('budget add-transaction: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
