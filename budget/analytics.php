<?php
require_once 'config.php';
requireAuth();

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Фильтры дат
$filter = $_GET['filter'] ?? 'current_month';
$start_date = $end_date = '';

switch ($filter) {
    case 'current_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        break;
    case 'current_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'last_year':
        $start_date = date('Y-01-01', strtotime('-1 year'));
        $end_date = date('Y-12-31', strtotime('-1 year'));
        break;
    case 'custom':
        $start_date = $_GET['start'] ?? date('Y-m-01');
        $end_date = $_GET['end'] ?? date('Y-m-t');
        break;
}

// Получаем данные за выбранный период
$stmt = $pdo->prepare('
    SELECT 
        SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense
    FROM transactions 
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
');
$stmt->execute([$user_id, $start_date, $end_date]);
$current = $stmt->fetch();

// Данные за предыдущий период для сравнения
$prev_start = date('Y-m-d', strtotime("$start_date -1 day -".(strtotime($end_date)-strtotime($start_date))." seconds"));
$prev_end = date('Y-m-d', strtotime("$start_date -1 day"));

$stmt = $pdo->prepare('
    SELECT 
        SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense
    FROM transactions 
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
');
$stmt->execute([$user_id, $prev_start, $prev_end]);
$previous = $stmt->fetch();


// ... (ваш код фильтрации дат $start_date, $end_date остаётся без изменений) ...

// Данные за выбранный период
$stmt = $pdo->prepare('
    SELECT 
        SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense
    FROM transactions 
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
');
$stmt->execute([$user_id, $start_date, $end_date]);
$current = $stmt->fetch();

$total_income = $current['total_income'] ?? 0;
$total_expense = $current['total_expense'] ?? 0;
$balance = $total_income - $total_expense;

// Разбивка по категориям (Доходы + Расходы)
$stmt = $pdo->prepare('
    SELECT 
        COALESCE(ic.name, ec.name) as name,
        COALESCE(ic.icon, ec.icon) as icon,
        SUM(t.amount) as total,
        t.type
    FROM transactions t
    LEFT JOIN income_categories ic ON t.category_id = ic.id AND t.type = "income"
    LEFT JOIN expense_categories ec ON t.category_id = ec.id AND t.type = "expense"
    WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?
    GROUP BY t.category_id, t.type
    ORDER BY t.type DESC, total DESC
');
$stmt->execute([$user_id, $start_date, $end_date]);
$breakdown = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Анализ — Бюджет</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="manifest" href="assets/manifest.json">
</head>
<body>
    <header class="header">
        <h1 class="header__title">📈 Анализ</h1>
        <a href="index.php" class="header__logout">← Назад</a>
    </header>

    <main class="analytics">
        <!-- Фильтры дат -->
        <div class="date-filters">
            <a href="?filter=current_month" class="date-filter <?= $filter === 'current_month' ? 'active' : '' ?>">Текущий месяц</a>
            <a href="?filter=last_month" class="date-filter <?= $filter === 'last_month' ? 'active' : '' ?>">Прошлый месяц</a>
            <a href="?filter=current_year" class="date-filter <?= $filter === 'current_year' ? 'active' : '' ?>">Текущий год</a>
            <a href="?filter=last_year" class="date-filter <?= $filter === 'last_year' ? 'active' : '' ?>">Прошлый год</a>
        </div>
        
        <!-- Кастомный диапазон -->
        <form style="margin-bottom: 20px; display: flex; gap: 10px;">
            <input type="date" name="start" value="<?= $start_date ?>" style="flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
            <input type="date" name="end" value="<?= $end_date ?>" style="flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
            <button type="submit" name="filter" value="custom" class="btn" style="padding: 10px 15px; width: auto;">OK</button>
        </form>

        <!-- Сравнение -->
        <div class="summary-cards">
            <div class="card">
                <div class="card__label">Доход</div>
                <div class="card__value income"><?= number_format($current['total_income'] ?? 0, 0, '.', ' ') ?> ₽</div>
                <?php if ($previous['total_income'] > 0): ?>
                    <div style="font-size: 12px; margin-top: 5px; color: <?= ($current['total_income'] ?? 0) >= $previous['total_income'] ? 'var(--income)' : 'var(--expense)' ?>">
                        <?= ($current['total_income'] ?? 0) >= $previous['total_income'] ? '↑' : '↓' ?> 
                        <?= number_format(abs((($current['total_income'] ?? 0) - $previous['total_income']) / $previous['total_income'] * 100), 0) ?>% к прош. периоду
                    </div>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card__label">Расход</div>
                <div class="card__value expense"><?= number_format($current['total_expense'] ?? 0, 0, '.', ' ') ?> ₽</div>
                <?php if ($previous['total_expense'] > 0): ?>
                    <div style="font-size: 12px; margin-top: 5px; color: <?= ($current['total_expense'] ?? 0) <= $previous['total_expense'] ? 'var(--income)' : 'var(--expense)' ?>">
                        <?= ($current['total_expense'] ?? 0) <= $previous['total_expense'] ? '↓' : '↑' ?> 
                        <?= number_format(abs((($current['total_expense'] ?? 0) - $previous['total_expense']) / $previous['total_expense'] * 100), 0) ?>% к прош. периоду
                    </div>
                <?php endif; ?>
            </div>
        </div>



<!-- ... ваш HTML с фильтрами и карточками сравнения остаётся без изменений ... -->

<!-- Разбивка по категориям -->
<div class="category-breakdown">
    <h3 class="section-title">По категориям</h3>
    <?php if ($breakdown): ?>
        <?php foreach ($breakdown as $item): ?>
        <div class="category-item">
            <div class="category-item__name">
                <span class="category-item__icon"><?= htmlspecialchars($item['icon']) ?></span>
                <span>
                    <?= htmlspecialchars($item['name']) ?> 
                    <small style="color: <?= $item['type'] === 'income' ? 'var(--income)' : 'var(--expense)' ?>">
                        (<?= $item['type'] === 'income' ? 'Доход' : 'Расход' ?>)
                    </small>
                </span>
            </div>
            <div class="category-item__amount" style="color: <?= $item['type'] === 'income' ? 'var(--income)' : 'var(--expense)' ?>">
                <?= $item['type'] === 'income' ? '+' : '-' ?><?= formatMoney($item['total']) ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Итоговый баланс за период -->
        <div class="category-item" style="border-top: 2px solid var(--border); margin-top: 15px; padding-top: 15px;">
            <div class="category-item__name" style="font-weight: 600; font-size: 15px;">
                💰 ИТОГО БАЛАНС
            </div>
            <div class="category-item__amount" style="color: <?= $balance >= 0 ? 'var(--income)' : 'var(--expense)' ?>; font-size: 18px;">
                <?= formatMoney($balance) ?>
            </div>
        </div>
    <?php else: ?>
        <p class="empty-state">Нет данных за выбранный период</p>
    <?php endif; ?>
</div>
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