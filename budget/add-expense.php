<?php
require_once 'config.php';
requireAuth();

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Получаем категории расходов
$stmt = $pdo->prepare('SELECT * FROM expense_categories WHERE user_id IS NULL OR user_id = ? ORDER BY name');
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }
    
    $amount = floatval($_POST['amount'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');
    
    if ($amount > 0 && $category_id > 0) {
        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, type, amount, category_id, description, transaction_date)
            VALUES (?, "expense", ?, ?, ?, ?)
        ');
        $stmt->execute([$user_id, $amount, $category_id, $description, $date]);
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Добавить расход — Бюджет</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="manifest" href="assets/manifest.json">
</head>
<body>
    <header class="header">
        <h1 class="header__title">➖ Расход</h1>
        <a href="index.php" class="header__logout">← Назад</a>
    </header>

    <main class="form">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            
            <div class="form-group">
                <label>Сумма (₽)</label>
                <input type="number" name="amount" step="0.01" min="0" required placeholder="0" inputmode="decimal">
            </div>
            
            <div class="form-group">
                <label>Категория</label>
                <div class="categories">
    <?php foreach ($categories as $index => $cat): ?>
    <label class="category-btn">
        <input type="radio" name="category_id" value="<?= $cat['id'] ?>" required <?= $index === 0 ? 'checked' : '' ?>>
        <span class="category-btn__icon"><?= htmlspecialchars($cat['icon']) ?></span>
        <span class="category-btn__name"><?= htmlspecialchars($cat['name']) ?></span>
    </label>
    <?php endforeach; ?>
</div>
            </div>
            
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" rows="2" placeholder="Комментарий..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Дата</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <button type="submit" class="btn btn--expense">Добавить расход</button>
        </form>
    </main>

<nav class="bottom-nav">
    <a href="index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
        <span class="nav-item__icon">🏠</span>
        <span>Главная</span>
    </a>
    <a href="add-expense.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'add-expense.php' ? 'active' : '' ?>">
        <span class="nav-item__icon">➖</span>
        <span>Расход</span>
    </a>
    <a href="add-income.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'add-income.php' ? 'active' : '' ?>">
        <span class="nav-item__icon">➕</span>
        <span>Доход</span>
    </a>
    <a href="analytics.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : '' ?>">
        <span class="nav-item__icon">📈</span>
        <span>Анализ</span>
    </a>
    <a href="shopping.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'shopping.php' ? 'active' : '' ?>">
        <span class="nav-item__icon">🛒</span>
        <span>Покупки</span>
    </a>
</nav>

    <script src="assets/app.js"></script>
</body>
</html>