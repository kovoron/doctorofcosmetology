<?php
require_once 'config.php';
require_once __DIR__ . '/lib/periods.php';
requireAuth();

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Данные за текущий месяц (фактически — с 1-го числа по сегодня:
// операций с будущими датами в базе нет)
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$stmt = $pdo->prepare('
    SELECT
        COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
');
$stmt->execute([$user_id, $month_start, $month_end]);
$summary = $stmt->fetch();

$income = (float) ($summary['total_income'] ?? 0);
$expense = (float) ($summary['total_expense'] ?? 0);
$balance = $income - $expense;

// Последние транзакции: can_edit = операция создана сегодня (править можно до конца дня)
$stmt = $pdo->prepare('
    SELECT t.*,
           COALESCE(ic.name, ec.name) as category_name,
           COALESCE(ic.icon, ec.icon) as icon,
           (DATE(t.created_at) = CURDATE()) as can_edit
    FROM transactions t
    LEFT JOIN income_categories ic ON t.category_id = ic.id AND t.type = "income"
    LEFT JOIN expense_categories ec ON t.category_id = ec.id AND t.type = "expense"
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.created_at DESC, t.id DESC
    LIMIT 10
');
$stmt->execute([$user_id]);
$recent = $stmt->fetchAll();

// Категории для формы правки (общие + личные)
$stmt = $pdo->prepare('SELECT id, name, icon FROM expense_categories WHERE user_id IS NULL OR user_id = ? ORDER BY name');
$stmt->execute([$user_id]);
$expense_categories = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT id, name, icon FROM income_categories WHERE user_id IS NULL OR user_id = ? ORDER BY name');
$stmt->execute([$user_id]);
$income_categories = $stmt->fetchAll();
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
            <div class="card__period">
                за <?= BUDGET_MONTHS_FULL[(int) date('n', strtotime($month_start))] . ' ' . date('Y', strtotime($month_start)) ?>
            </div>
        </div>

<h3 class="section-title">Последние операции</h3>
<?php if ($recent): ?>
    <div class="transactions-list">
        <?php foreach ($recent as $t): ?>
        <?php $editable = !empty($t['can_edit']); ?>
        <div class="transaction-item <?= $editable ? 'transaction-item--editable' : '' ?>"
             <?php if ($editable): ?>
             data-id="<?= (int) $t['id'] ?>"
             data-type="<?= htmlspecialchars($t['type']) ?>"
             data-amount="<?= htmlspecialchars($t['amount']) ?>"
             data-category-id="<?= (int) $t['category_id'] ?>"
             data-description="<?= htmlspecialchars($t['description'] ?? '') ?>"
             data-date="<?= htmlspecialchars($t['transaction_date']) ?>"
             data-editable="1"
             role="button"
             tabindex="0"
             <?php endif; ?>>
            <div class="transaction-item__left">
                <span class="transaction-item__icon">
                    <?= $t['type'] === 'income' ? '📥' : '📤' ?>
                </span>
                <div class="transaction-item__info">
                    <div class="transaction-item__category">
                        <?= htmlspecialchars($t['category_name'] ?? 'Без категории') ?>
                        <span class="transaction-item__type <?= $t['type'] ?>">
                            (<?= $t['type'] === 'income' ? 'Приход' : 'Расход' ?>)
                        </span>
                    </div>
                    <?php if (!empty($t['description'])): ?>
                        <div class="transaction-item__desc"><?= htmlspecialchars($t['description']) ?></div>
                    <?php endif; ?>
                    <div class="transaction-item__date">
                        <?= date('d.m.Y', strtotime($t['transaction_date'])) ?>
                        <?php if ($editable): ?>
                            · <span class="transaction-item__hint">нажмите, чтобы изменить</span>
                        <?php endif; ?>
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

    <!-- Правка транзакции (только в день создания) -->
    <div class="sheet" id="edit-sheet" aria-hidden="true">
        <div class="sheet__backdrop" data-close="1"></div>
        <div class="sheet__panel" role="dialog" aria-modal="true" aria-labelledby="edit-sheet-title">
            <div class="sheet__title">
                <span id="edit-sheet-title">Изменить операцию</span>
                <button type="button" class="sheet__close" data-close="1" aria-label="Закрыть">×</button>
            </div>

            <p class="sheet__error" id="edit-error"></p>

            <form id="edit-form">
                <input type="hidden" name="id" id="edit-id" value="">
                <input type="hidden" name="csrf_token" id="edit-csrf" value="<?= csrfToken() ?>">

                <div class="form-group">
                    <label for="edit-amount">Сумма (₽)</label>
                    <input type="number" name="amount" id="edit-amount" step="0.01" min="0" required inputmode="decimal">
                </div>

                <div class="form-group">
                    <label>Категория</label>
                    <div class="categories" data-type="expense">
                        <?php foreach ($expense_categories as $index => $cat): ?>
                        <label class="category-btn">
                            <input type="radio" name="category_id" value="<?= (int) $cat['id'] ?>" data-for="expense">
                            <span class="category-btn__icon"><?= htmlspecialchars($cat['icon']) ?></span>
                            <span class="category-btn__name"><?= htmlspecialchars($cat['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="categories hidden" data-type="income">
                        <?php foreach ($income_categories as $cat): ?>
                        <label class="category-btn">
                            <input type="radio" name="category_id" value="<?= (int) $cat['id'] ?>" data-for="income">
                            <span class="category-btn__icon"><?= htmlspecialchars($cat['icon']) ?></span>
                            <span class="category-btn__name"><?= htmlspecialchars($cat['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit-description">Описание</label>
                    <textarea name="description" id="edit-description" rows="2" placeholder="Комментарий..."></textarea>
                </div>

                <div class="form-group">
                    <label for="edit-date">Дата</label>
                    <input type="date" name="date" id="edit-date" max="<?= date('Y-m-d') ?>" required>
                </div>

                <button type="submit" class="btn" id="edit-submit">Сохранить</button>
            </form>
        </div>
    </div>

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
