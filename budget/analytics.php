<?php
require_once 'config.php';
require_once __DIR__ . '/lib/periods.php';
requireAuth();

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// ==========================================
// 1. ПЕРИОД И СРАВНЕНИЕ «НА ТУ ЖЕ ДАТУ»
// ==========================================
$filter = $_GET['filter'] ?? 'current_month';
$period = budgetResolvePeriod($filter, $_GET['start'] ?? null, $_GET['end'] ?? null);

$start_date = $period['start'];
$end_date = $period['end'];       // по календарю (для формы)
$fact_end = $period['fact_end'];  // фактический конец данных (сегодня, если период не завершён)

// Предыдущий период: та же длина, привязка к той же дате (1–23 ноя → 1–23 окт)
$prev = budgetPrevPeriod($start_date, $fact_end);

// ==========================================
// 2. СВОДКА ЗА ПЕРИОД
// ==========================================
$summarySql = '
    SELECT
        COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
';
$stmt = $pdo->prepare($summarySql);
$stmt->execute([$user_id, $start_date, $fact_end]);
$current = $stmt->fetch();

$stmt = $pdo->prepare($summarySql);
$stmt->execute([$user_id, $prev['start'], $prev['end']]);
$previous = $stmt->fetch();

$total_income = (float) ($current['total_income'] ?? 0);
$total_expense = (float) ($current['total_expense'] ?? 0);
$balance = $total_income - $total_expense;

$prev_income = (float) ($previous['total_income'] ?? 0);
$prev_expense = (float) ($previous['total_expense'] ?? 0);

$pct_income = budgetPct($total_income, $prev_income);
$pct_expense = budgetPct($total_expense, $prev_expense);

$delta_income = $total_income - $prev_income;
$delta_expense = $total_expense - $prev_expense;

$period_label = budgetPeriodLabel($start_date, $fact_end, $period['incomplete']);
$prev_label = budgetPeriodLabel($prev['start'], $prev['end']);

// ==========================================
// 3. РАЗБИВКА ПО КАТЕГОРИЯМ
// ==========================================
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
$stmt->execute([$user_id, $start_date, $fact_end]);
$breakdown = $stmt->fetchAll();

// Параметры периода для запросов динамики
$trend_query = http_build_query(array_filter([
    'filter' => $period['filter'],
    'start' => $period['filter'] === 'custom' ? $start_date : null,
    'end' => $period['filter'] === 'custom' ? $end_date : null,
]));
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
            <a href="?filter=current_month" class="date-filter <?= $period['filter'] === 'current_month' ? 'active' : '' ?>">Текущий месяц</a>
            <a href="?filter=last_month" class="date-filter <?= $period['filter'] === 'last_month' ? 'active' : '' ?>">Прошлый месяц</a>
            <a href="?filter=current_year" class="date-filter <?= $period['filter'] === 'current_year' ? 'active' : '' ?>">Текущий год</a>
            <a href="?filter=last_year" class="date-filter <?= $period['filter'] === 'last_year' ? 'active' : '' ?>">Прошлый год</a>
        </div>

        <!-- Кастомный диапазон -->
        <form class="custom-range" method="GET">
            <input type="date" name="start" value="<?= htmlspecialchars($start_date) ?>">
            <input type="date" name="end" value="<?= htmlspecialchars($end_date) ?>">
            <button type="submit" name="filter" value="custom" class="btn btn--small">OK</button>
        </form>

        <p class="period-caption">
            <?= htmlspecialchars($period_label) ?>
            <span class="period-caption__vs">vs <?= htmlspecialchars($prev_label) ?></span>
        </p>

        <!-- Сравнение -->
        <div class="summary-cards">
            <div class="card">
                <div class="card__label">Доход</div>
                <div class="card__value income"><?= number_format($total_income, 0, '.', ' ') ?> ₽</div>
                <div class="card__diff <?= $delta_income >= 0 ? 'diff-up' : 'diff-down' ?>">
                    <?= $delta_income >= 0 ? '↑' : '↓' ?>
                    <?= budgetPctLabel($pct_income) ?>
                    <span class="card__delta">(<?= ($delta_income >= 0 ? '+' : '−') . number_format(abs($delta_income), 0, '.', ' ') ?> ₽)</span>
                </div>
                <div class="card__period">было <?= number_format($prev_income, 0, '.', ' ') ?> ₽</div>
            </div>
            <div class="card">
                <div class="card__label">Расход</div>
                <div class="card__value expense"><?= number_format($total_expense, 0, '.', ' ') ?> ₽</div>
                <!-- Для расхода рост — плохо: зелёный при снижении, красный при росте -->
                <div class="card__diff <?= $delta_expense <= 0 ? 'diff-up' : 'diff-down' ?>">
                    <?= $delta_expense <= 0 ? '↓' : '↑' ?>
                    <?= budgetPctLabel($pct_expense) ?>
                    <span class="card__delta">(<?= ($delta_expense >= 0 ? '+' : '−') . number_format(abs($delta_expense), 0, '.', ' ') ?> ₽)</span>
                </div>
                <div class="card__period">было <?= number_format($prev_expense, 0, '.', ' ') ?> ₽</div>
            </div>
        </div>

        <div class="card">
            <div class="card__label">Баланс</div>
            <div class="card__value" style="color: <?= $balance >= 0 ? 'var(--income)' : 'var(--expense)' ?>">
                <?= number_format($balance, 0, '.', ' ') ?> ₽
            </div>
            <div class="card__period"><?= htmlspecialchars($period_label) ?></div>
        </div>

        <!-- Динамика по статьям -->
        <section class="trend">
            <h3 class="section-title">Динамика по статьям</h3>

            <div class="trend__controls">
                <div class="tabs trend__tabs" data-role="type">
                    <button type="button" class="tab active" data-type="expense">Расходы</button>
                    <button type="button" class="tab" data-type="income">Доходы</button>
                </div>
                <div class="tabs trend__tabs" data-role="granularity">
                    <button type="button" class="tab active" data-granularity="auto">Авто</button>
                    <button type="button" class="tab" data-granularity="day">День</button>
                    <button type="button" class="tab" data-granularity="week">Неделя</button>
                    <button type="button" class="tab" data-granularity="month">Месяц</button>
                </div>
            </div>

            <div class="trend__categories" id="trend-categories"></div>

            <div class="trend__chart" id="trend-chart" data-base="<?= htmlspecialchars($trend_query) ?>">
                <p class="empty-state">Загрузка графика…</p>
            </div>

            <div class="trend__legend" id="trend-legend"></div>
            <p class="trend__note" id="trend-note"></p>
        </section>

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

    <script src="assets/chart.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
