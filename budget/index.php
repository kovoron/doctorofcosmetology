<?php
require_once 'config.php';
requireAuth();

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Получаем данные за текущий месяц
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$stmt = $pdo->prepare('
    SELECT 
        SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense
    FROM transactions 
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
');
$stmt->execute([$user_id, $month_start, $month_end]);
$summary = $stmt->fetch();

$income = $summary['total_income'] ?? 0;
$expense = $summary['total_expense'] ?? 0;
$balance = $income - $expense;

// Последние транзакции
$stmt = $pdo->prepare('
    SELECT t.*, c.name as category_name, c.icon 
    FROM transactions t
    JOIN income_categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT 10
');
$stmt->execute([$user_id]);
$recent = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Бюджет — Главная</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="manifest" href="assets/manifest.json">
</head>
<body>
    <header class="header">
        <h1 class="header__title">📊 Бюджет</h1>
        <a href="logout.php" class="header__logout">Выйти</a>
    </header>

    <main class="dashboard">
        <div class="summary-cards">
            <div class="card">
                <div class="card__label">Доход</div>
                <div class="card__value income"><?= number_format($income, 0, '.', ' ') ?> ₽</div>
            </div>
            <div class="card">
                <div class="card__label">Расход</div>
                <div class="card__value expense"><?= number_format($expense, 0, '.', ' ') ?> ₽</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card__label">Баланс</div>
            <div class="card__value card__balance" style="color: <?= $balance >= 0 ? 'var(--income)' : 'var(--expense)' ?>">
                <?= number_format($balance, 0, '.', ' ') ?> ₽
            </div>
            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                за <?= date('F Y', strtotime($month_start)) ?>
            </div>
        </div>

<?php
// Последние транзакции (доходы + расходы)
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
?>

<h3 class="section-title">Последние операции</h3>
<?php if ($recent): ?>
    <div class="transactions-list">
        <?php foreach ($recent as $t): ?>
        <div class="transaction-item">
            <div class="transaction-item__left">
                <span class="transaction-item__icon">
                    <?php 
                    // Иконка в зависимости от типа
                    echo $t['type'] === 'income' ? '📥' : '📤';
                    ?>
                </span>
                <div class="transaction-item__info">
                    <div class="transaction-item__category">
                        <?= htmlspecialchars($t['category_name']) ?>
                        <span class="transaction-item__type <?= $t['type'] ?>">
                            (<?= $t['type'] === 'income' ? 'Приход' : 'Расход' ?>)
                        </span>
                    </div>
                    <?php if (!empty($t['description'])): ?>
                        <div class="transaction-item__desc"><?= htmlspecialchars($t['description']) ?></div>
                    <?php endif; ?>
                    <div class="transaction-item__date">
                        <?= date('d.m.Y', strtotime($t['transaction_date'])) ?>
                    </div>
                </div>
            </div>
            <div class="transaction-item__amount <?= $t['type'] ?>">
                <?= $t['type'] === 'income' ? '+' : '-' ?><?= formatMoney($t['amount']) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="empty-state">Нет операций за этот период</p>
<?php endif; ?>
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