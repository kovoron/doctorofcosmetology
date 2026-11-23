<?php
require_once '../config.php';
require_once __DIR__ . '/../lib/periods.php';
requireAuth();
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
$user_id = (int) $_SESSION['user_id'];

// ==========================================
// 1. ПАРАМЕТРЫ
// ==========================================
$filter = (string) ($_GET['filter'] ?? 'current_month');
$period = budgetResolvePeriod($filter, $_GET['start'] ?? null, $_GET['end'] ?? null);

$type = ($_GET['type'] ?? 'expense') === 'income' ? 'income' : 'expense';
$table = $type === 'income' ? 'income_categories' : 'expense_categories';

$requested = (string) ($_GET['granularity'] ?? 'auto');
$granularity = budgetGranularityAuto($period['start'], $period['fact_end'], $requested);
$step = $granularity['effective'];

$selectedIds = [];
if (isset($_GET['category_ids'])) {
    $raw = $_GET['category_ids'];
    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }
    if (is_array($raw)) {
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $selectedIds[$id] = $id;
            }
        }
    }
}
$selectedIds = array_values($selectedIds);

// ==========================================
// 2. ДАННЫЕ ПО БАКЕТАМ И КАТЕГОРИЯМ
// ==========================================
$buckets = budgetBuckets($period['start'], $period['fact_end'], $step);
$bucketIndex = [];
foreach ($buckets as $i => $bucket) {
    $bucketIndex[$bucket['key']] = $i;
}

$sql = '
    SELECT t.category_id, ' . budgetBucketSqlKey($step, 't.transaction_date') . ' AS bucket_key,
           SUM(t.amount) AS total
    FROM transactions t
    WHERE t.user_id = ? AND t.type = ? AND t.transaction_date BETWEEN ? AND ?
    GROUP BY t.category_id, bucket_key
';
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $type, $period['start'], $period['fact_end']]);
$rows = $stmt->fetchAll();

// Категории (глобальные + личные)
$stmt = $pdo->prepare("SELECT id, name, icon FROM $table WHERE user_id IS NULL OR user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$categoriesMeta = [];
foreach ($stmt->fetchAll() as $category) {
    $categoriesMeta[(int) $category['id']] = $category;
}

// Суммы по категориям
$totals = [];
$byCategory = [];
foreach ($rows as $row) {
    $categoryId = (int) $row['category_id'];
    $amount = (float) $row['total'];
    $totals[$categoryId] = ($totals[$categoryId] ?? 0) + $amount;

    if (isset($bucketIndex[(string) $row['bucket_key']])) {
        $byCategory[$categoryId][$bucketIndex[(string) $row['bucket_key']]] = $amount;
    }
}

arsort($totals);

// ==========================================
// 3. СЕРИИ ГРАФИКА
// ==========================================
$series = [];
$total = 0.0;

$pushSeries = function (string $key, ?int $categoryId, string $name, string $icon, array $totalsByBucket) use (&$series, &$total, $buckets): void {
    $values = [];
    $sum = 0.0;
    foreach ($buckets as $i => $bucket) {
        $value = (float) ($totalsByBucket[$i] ?? 0);
        $values[] = round($value, 2);
        $sum += $value;
    }
    $series[] = [
        'key' => $key,
        'category_id' => $categoryId,
        'name' => $name,
        'icon' => $icon,
        'total' => round($sum, 2),
        'values' => $values,
    ];
    $total += $sum;
};

if ($selectedIds) {
    // Пользователь выбрал конкретные статьи
    foreach ($selectedIds as $categoryId) {
        if (!isset($categoriesMeta[$categoryId]) && !isset($totals[$categoryId])) {
            continue;
        }
        $meta = $categoriesMeta[$categoryId] ?? ['name' => 'Категория ' . $categoryId, 'icon' => '?'];
        $pushSeries('cat-' . $categoryId, $categoryId, $meta['name'], $meta['icon'], $byCategory[$categoryId] ?? []);
    }
} else {
    // Топ-N статей + «Прочее»
    $limit = 6;
    $top = array_slice($totals, 0, $limit, true);
    $rest = array_slice($totals, $limit, null, true);

    foreach ($top as $categoryId => $sum) {
        $meta = $categoriesMeta[$categoryId] ?? ['name' => 'Категория ' . $categoryId, 'icon' => '?'];
        $pushSeries('cat-' . $categoryId, $categoryId, $meta['name'], $meta['icon'], $byCategory[$categoryId] ?? []);
    }

    if ($rest) {
        $othersByBucket = [];
        foreach ($rest as $categoryId => $sum) {
            foreach ($byCategory[$categoryId] ?? [] as $i => $amount) {
                $othersByBucket[$i] = ($othersByBucket[$i] ?? 0) + $amount;
            }
        }
        $pushSeries('others', null, 'Прочее', '•••', $othersByBucket);
    }
}

// ==========================================
// 4. ОТВЕТ
// ==========================================
$categoriesOut = [];
foreach ($totals as $categoryId => $sum) {
    $meta = $categoriesMeta[$categoryId] ?? ['name' => 'Категория ' . $categoryId, 'icon' => '?'];
    $categoriesOut[] = [
        'id' => $categoryId,
        'name' => $meta['name'],
        'icon' => $meta['icon'],
        'total' => round($sum, 2),
        'selected' => in_array($categoryId, $selectedIds, true),
    ];
}

echo json_encode([
    'period' => [
        'start' => $period['start'],
        'end' => $period['end'],
        'fact_end' => $period['fact_end'],
        'incomplete' => $period['incomplete'],
        'label' => budgetPeriodLabel($period['start'], $period['fact_end'], $period['incomplete']),
    ],
    'prev' => [
        'start' => budgetPrevPeriod($period['start'], $period['fact_end'])['start'],
        'end' => budgetPrevPeriod($period['start'], $period['fact_end'])['end'],
    ],
    'type' => $type,
    'granularity' => $granularity,
    'buckets' => $buckets,
    'series' => $series,
    'categories' => $categoriesOut,
    'total' => round($total, 2),
], JSON_UNESCAPED_UNICODE);
