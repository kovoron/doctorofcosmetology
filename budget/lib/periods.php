<?php

// Прямой доступ по URL (/budget/lib/periods.php) запрещён: файл подключается
// только после budget/config.php, который определяет BUDGET_APP.
if (!defined('BUDGET_APP')) {
    http_response_code(404);
    exit;
}

/**
 * Расчёт периодов и бакетов для раздела «Анализ».
 *
 * Модуль намеренно не зависит от БД, сессии и конфигов: только чистые функции
 * над строками дат 'Y-m-d'. Поэтому его легко проверять из CLI:
 *
 *   php -r 'require "budget/lib/periods.php"; print_r(budgetPrevPeriod("2026-11-01","2026-11-23"));'
 *
 * Таймзона берётся из настроек PHP (в budget/config.php вызывается
 * date_default_timezone_set(appTimezone()) — Europe/Moscow).
 */

const BUDGET_MONTHS_SHORT = [
    1 => 'янв', 'фев', 'мар', 'апр', 'мая', 'июн',
    'июл', 'авг', 'сен', 'окт', 'ноя', 'дек',
];

const BUDGET_MONTHS_FULL = [
    1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь',
    'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь',
];

/**
 * Лимиты количества точек на графике. При превышении шаг понижается
 * автоматически (день → неделя → месяц), чтобы график оставался читаемым.
 */
function budgetBucketLimits(): array
{
    return ['day' => 62, 'week' => 78, 'month' => 36];
}

/**
 * Сегодняшняя дата (можно подменить для тестов).
 */
function budgetToday(?string $today = null): string
{
    return $today !== null && budgetValidDate($today) ? $today : date('Y-m-d');
}

/**
 * Проверка корректности даты в формате Y-m-d.
 */
function budgetValidDate(?string $date): bool
{
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    [$y, $m, $d] = array_map('intval', explode('-', $date));

    return checkdate($m, $d, $y);
}

/**
 * Сдвиг даты на N месяцев с «прижатием» к концу месяца
 * (31 марта − 1 месяц = 28/29 февраля, а не 3 марта).
 */
function budgetShiftMonths(string $date, int $months): string
{
    [$y, $m, $d] = array_map('intval', explode('-', $date));

    $total = $y * 12 + ($m - 1) + $months;
    $year = intdiv($total, 12);
    $month = ($total % 12) + 1;
    $lastDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));

    return sprintf('%04d-%02d-%02d', $year, $month, min($d, $lastDay));
}

/**
 * Количество дней в периоде включительно.
 */
function budgetDaysInclusive(string $start, string $end): int
{
    $startTs = strtotime($start . ' 00:00:00');
    $endTs = strtotime($end . ' 00:00:00');

    if ($startTs === false || $endTs === false || $endTs < $startTs) {
        return 0;
    }

    return (int) round(($endTs - $startTs) / 86400) + 1;
}

/**
 * Разбор фильтра в конкретный период.
 *
 * @return array{filter:string,start:string,end:string,fact_end:string,incomplete:bool}
 *         fact_end — конец фактических данных: для незавершённого периода
 *         обрезается по сегодняшней дате.
 */
function budgetResolvePeriod(
    string $filter,
    ?string $start = null,
    ?string $end = null,
    ?string $today = null
): array {
    $todayDate = budgetToday($today);
    $todayTs = strtotime($todayDate);

    switch ($filter) {
        case 'last_month':
            $startDate = date('Y-m-01', strtotime('-1 month', $todayTs));
            $endDate = date('Y-m-t', strtotime($startDate));
            break;

        case 'current_year':
            $startDate = date('Y-01-01', $todayTs);
            $endDate = date('Y-12-31', $todayTs);
            break;

        case 'last_year':
            $startDate = date('Y-01-01', strtotime('-1 year', $todayTs));
            $endDate = date('Y-12-31', strtotime($startDate));
            break;

        case 'custom':
            if (!budgetValidDate($start) || !budgetValidDate($end)) {
                $filter = 'current_month';
                $startDate = date('Y-m-01', $todayTs);
                $endDate = date('Y-m-t', $todayTs);
                break;
            }
            $startDate = $start;
            $endDate = $end;
            if ($startDate > $endDate) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }
            break;

        case 'current_month':
        default:
            $filter = 'current_month';
            $startDate = date('Y-m-01', $todayTs);
            $endDate = date('Y-m-t', $todayTs);
            break;
    }

    // Незавершённый период обрезаем по сегодняшний день: сравнивать
    // «1–23 ноября» с полным октябрём бессмысленно.
    $factEnd = $endDate > $todayDate ? $todayDate : $endDate;
    if ($factEnd < $startDate) {
        // Период целиком в будущем (например, «следующий месяц») — данных нет.
        $factEnd = $startDate;
    }

    return [
        'filter' => $filter,
        'start' => $startDate,
        'end' => $endDate,
        'fact_end' => $factEnd,
        'incomplete' => $factEnd < $endDate,
    ];
}

/**
 * Предыдущий период той же длины «на ту же дату».
 *
 * Правило: обе границы сдвигаются назад на 1 месяц (для периодов,
 * начинающихся 1 января, — на 1 год) с прижатием к концу месяца.
 *
 *   1–23 ноя 2026 → 1–23 окт 2026
 *   1–30 ноя 2026 → 1–30 окт 2026
 *   1–31 окт 2026 → 1–30 сен 2026
 *   1 янв – 23 ноя 2026 → 1 янв – 23 ноя 2025
 *
 * @return array{start:string,end:string,unit:string}
 */
function budgetPrevPeriod(string $start, string $factEnd): array
{
    $unit = (substr($start, 5) === '01-01') ? 'year' : 'month';
    $shift = $unit === 'year' ? -12 : -1;

    return [
        'start' => budgetShiftMonths($start, $shift),
        'end' => budgetShiftMonths($factEnd, $shift),
        'unit' => $unit,
    ];
}

/**
 * Процент изменения. null — если базы для сравнения нет (prev <= 0).
 */
function budgetPct($current, $previous): ?float
{
    $current = (float) $current;
    $previous = (float) $previous;

    if ($previous <= 0) {
        return null;
    }

    return round(($current - $previous) / $previous * 100, 1);
}

/**
 * Начало недели (понедельник, ISO) для указанной даты.
 */
function budgetWeekStart(string $date): string
{
    $ts = strtotime($date . ' 00:00:00');
    $weekday = (int) date('N', $ts);

    return date('Y-m-d', strtotime('-' . ($weekday - 1) . ' days', $ts));
}

/**
 * Количество бакетов выбранного шага в периоде.
 */
function budgetBucketCount(string $start, string $end, string $granularity): int
{
    if ($start > $end) {
        return 0;
    }

    switch ($granularity) {
        case 'week':
            $from = strtotime(budgetWeekStart($start));
            $to = strtotime(budgetWeekStart($end));
            return (int) round(($to - $from) / (86400 * 7)) + 1;

        case 'month':
            $from = strtotime(date('Y-m-01', strtotime($start)));
            $to = strtotime(date('Y-m-01', strtotime($end)));
            $months = ((int) date('Y', $to) - (int) date('Y', $from)) * 12
                + ((int) date('n', $to) - (int) date('n', $from));
            return $months + 1;

        case 'day':
        default:
            return budgetDaysInclusive($start, $end);
    }
}

/**
 * Подбор фактического шага графика.
 *
 * @return array{requested:string,effective:string,downgraded:bool}
 */
function budgetGranularityAuto(string $start, string $end, string $requested = 'auto'): array
{
    $limits = budgetBucketLimits();
    $requested = in_array($requested, ['day', 'week', 'month'], true) ? $requested : 'auto';

    if ($requested === 'auto') {
        // Автовыбор: до ~2 месяцев — дни, до ~1.5 лет — недели, дальше месяцы.
        $days = budgetDaysInclusive($start, $end);
        $effective = $days <= $limits['day'] ? 'day' : ($days <= 180 ? 'week' : 'month');

        return ['requested' => 'auto', 'effective' => $effective, 'downgraded' => false];
    }

    $order = ['day', 'week', 'month'];
    $index = array_search($requested, $order, true);
    $effective = $requested;

    while (budgetBucketCount($start, $end, $effective) > $limits[$effective]) {
        if ($index >= count($order) - 1) {
            break;
        }
        $index++;
        $effective = $order[$index];
    }

    return [
        'requested' => $requested,
        'effective' => $effective,
        'downgraded' => $effective !== $requested,
    ];
}

/**
 * Список бакетов периода с человекочитаемыми подписями.
 *
 * @return array<int, array{key:string,label:string,label_full:string,start:string,end:string}>
 */
function budgetBuckets(string $start, string $end, string $granularity): array
{
    $buckets = [];

    if ($start > $end) {
        return $buckets;
    }

    switch ($granularity) {
        case 'week':
            $cursor = strtotime(budgetWeekStart($start));
            $last = strtotime(budgetWeekStart($end));
            while ($cursor <= $last) {
                $from = date('Y-m-d', $cursor);
                $to = date('Y-m-d', strtotime('+6 days', $cursor));
                $buckets[] = [
                    'key' => $from,
                    'label' => date('d.m', $cursor),
                    'label_full' => date('d.m', $cursor) . '–' . date('d.m.Y', strtotime($to)),
                    'start' => $from,
                    'end' => $to,
                ];
                $cursor = strtotime('+7 days', $cursor);
            }
            break;

        case 'month':
            $cursor = strtotime(date('Y-m-01', strtotime($start)));
            $last = strtotime(date('Y-m-01', strtotime($end)));
            while ($cursor <= $last) {
                $month = (int) date('n', $cursor);
                $from = date('Y-m-d', $cursor);
                $buckets[] = [
                    'key' => $from,
                    'label' => BUDGET_MONTHS_SHORT[$month] . ' ' . date('y', $cursor),
                    'label_full' => BUDGET_MONTHS_FULL[$month] . ' ' . date('Y', $cursor),
                    'start' => $from,
                    'end' => date('Y-m-t', $cursor),
                ];
                $cursor = strtotime('+1 month', $cursor);
            }
            break;

        case 'day':
        default:
            $cursor = strtotime($start);
            $last = strtotime($end);
            while ($cursor <= $last) {
                $day = date('Y-m-d', $cursor);
                $buckets[] = [
                    'key' => $day,
                    'label' => date('d.m', $cursor),
                    'label_full' => date('d.m.Y', $cursor),
                    'start' => $day,
                    'end' => $day,
                ];
                $cursor = strtotime('+1 day', $cursor);
            }
            break;
    }

    return $buckets;
}

/**
 * SQL-выражение группировки, возвращающее ключ бакета,
 * совпадающий с полем key из budgetBuckets().
 */
function budgetBucketSqlKey(string $granularity, string $column = 'transaction_date'): string
{
    switch ($granularity) {
        case 'week':
            return "DATE_SUB($column, INTERVAL WEEKDAY($column) DAY)";

        case 'month':
            return "DATE_FORMAT($column, '%Y-%m-01')";

        case 'day':
        default:
            return "DATE($column)";
    }
}

/**
 * Подпись периода: «1–23 ноя 2026», «1–23 ноя 2026 (по 23 ноя)».
 */
function budgetPeriodLabel(string $start, string $end, bool $incomplete = false): string
{
    $startTs = strtotime($start);
    $endTs = strtotime($end);

    $startDay = (int) date('j', $startTs);
    $endDay = (int) date('j', $endTs);
    $startMonth = (int) date('n', $startTs);
    $endMonth = (int) date('n', $endTs);
    $startYear = date('Y', $startTs);
    $endYear = date('Y', $endTs);

    if ($startYear !== $endYear) {
        $label = sprintf(
            '%d %s %s — %d %s %s',
            $startDay,
            BUDGET_MONTHS_SHORT[$startMonth],
            $startYear,
            $endDay,
            BUDGET_MONTHS_SHORT[$endMonth],
            $endYear
        );
    } elseif ($startMonth !== $endMonth) {
        $label = sprintf(
            '%d %s — %d %s %s',
            $startDay,
            BUDGET_MONTHS_SHORT[$startMonth],
            $endDay,
            BUDGET_MONTHS_SHORT[$endMonth],
            $endYear
        );
    } elseif ($startDay === $endDay) {
        $label = sprintf('%d %s %s', $startDay, BUDGET_MONTHS_SHORT[$startMonth], $endYear);
    } else {
        $label = sprintf(
            '%d–%d %s %s',
            $startDay,
            $endDay,
            BUDGET_MONTHS_SHORT[$startMonth],
            $endYear
        );
    }

    return $incomplete ? $label . ' (данные по ' . date('j', $endTs) . ' ' . BUDGET_MONTHS_SHORT[$endMonth] . ')' : $label;
}

/**
 * Подпись для значения Δ% с учётом отсутствия базы сравнения.
 */
function budgetPctLabel(?float $pct): string
{
    if ($pct === null) {
        return '—';
    }

    return ($pct > 0 ? '+' : '') . number_format($pct, 0, '.', ' ') . '%';
}
