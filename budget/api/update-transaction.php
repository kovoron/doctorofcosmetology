<?php
require_once '../config.php';
requireAuth();
header('Content-Type: application/json; charset=utf-8');

/**
 * Правка транзакции.
 *
 * Правило окна: изменить операцию можно только в день её создания —
 * до конца текущего дня (DATE(created_at) = CURDATE()).
 * Тип операции (доход/расход) не меняется.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    // Разрешаем и обычный form-urlencoded POST
    $input = $_POST;
}

// ==========================================
// 1. CSRF
// ==========================================
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
if (!is_string($csrf) || !verifyCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token mismatch']);
    exit;
}

$pdo = getDB();
$user_id = (int) $_SESSION['user_id'];

$id = (int) ($input['id'] ?? 0);
$amount = round((float) str_replace(',', '.', (string) ($input['amount'] ?? 0)), 2);
$category_id = (int) ($input['category_id'] ?? 0);
$description = trim((string) ($input['description'] ?? ''));
$date = (string) ($input['date'] ?? '');

// ==========================================
// 2. Транзакция: существует, своя, в окне правки
// ==========================================
// Окно правки считаем в SQL — там же, где CURDATE() (таймзона сессии MySQL)
$stmt = $pdo->prepare('
    SELECT *, (DATE(created_at) = CURDATE()) AS can_edit
    FROM transactions
    WHERE id = ? AND user_id = ?
');
$stmt->execute([$id, $user_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    http_response_code(404);
    echo json_encode(['error' => 'Транзакция не найдена']);
    exit;
}

if (!$transaction['can_edit']) {
    http_response_code(403);
    echo json_encode(['error' => 'Изменить операцию можно только в день её создания']);
    exit;
}

$type = $transaction['type'];

// ==========================================
// 3. Валидация данных
// ==========================================
$errors = [];

if ($amount <= 0) {
    $errors['amount'] = 'Сумма должна быть больше нуля';
} elseif ($amount > 99999999.99) {
    $errors['amount'] = 'Слишком большая сумма';
}

if (mb_strlen($description) > 500) {
    $errors['description'] = 'Описание длиннее 500 символов';
}

if ($date === '') {
    $date = $transaction['transaction_date'];
} elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
    $errors['date'] = 'Некорректная дата';
} elseif ($date > date('Y-m-d')) {
    $errors['date'] = 'Дата не может быть в будущем';
}

if ($category_id > 0) {
    $table = $type === 'income' ? 'income_categories' : 'expense_categories';
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE id = ? AND (user_id IS NULL OR user_id = ?)");
    $stmt->execute([$category_id, $user_id]);
    if (!$stmt->fetch()) {
        $errors['category_id'] = 'Категория недоступна';
    }
} else {
    $category_id = (int) $transaction['category_id'];
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => 'Проверьте введённые данные', 'fields' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 4. Обновление
// ==========================================
try {
    $stmt = $pdo->prepare('
        UPDATE transactions
        SET amount = ?, category_id = ?, description = ?, transaction_date = ?
        WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([$amount, $category_id, $description, $date, $id, $user_id]);
} catch (Throwable $e) {
    error_log('budget update-transaction: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось сохранить изменения']);
    exit;
}

// Аудит: кто и что изменил (без миграции таблицы)
error_log(sprintf(
    'budget edit: id=%d user=%d type=%s amount %s→%s, category %s→%s, date %s→%s',
    $id,
    $user_id,
    $type,
    $transaction['amount'],
    number_format($amount, 2, '.', ''),
    $transaction['category_id'],
    $category_id,
    $transaction['transaction_date'],
    $date
));

$table = $type === 'income' ? 'income_categories' : 'expense_categories';
$stmt = $pdo->prepare("SELECT name, icon FROM $table WHERE id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch() ?: ['name' => 'Без категории', 'icon' => '?'];

echo json_encode([
    'success' => true,
    'transaction' => [
        'id' => $id,
        'type' => $type,
        'amount' => $amount,
        'category_id' => $category_id,
        'category_name' => $category['name'],
        'icon' => $category['icon'],
        'description' => $description,
        'transaction_date' => $date,
    ],
], JSON_UNESCAPED_UNICODE);
