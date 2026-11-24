<?php
/**
 * bin/indexnow.php — CLI-отправка URL в IndexNow (Bing, Яндекс).
 *
 * Лежит в docroot, поэтому первым делом отбивает веб-запуск: при не-CLI SAPI
 * отдаёт 404 и выходит, ничего не логируя. Дополнительно — bin/.htaccess.
 *
 * Режимы:
 *   --all                    все URL из общего источника (lib/site-urls.php)
 *   --changed=OLD..NEW       URL, затронутые диапазоном git-коммитов
 *   --url=URL                конкретный URL (можно повторять)
 *
 * Опции:
 *   --endpoint=shared|bing|yandex   эндпоинт (по умолчанию shared)
 *   --endpoint-url=URL              тестовый эндпоинт, только вместе с --force-local (loopback)
 *   --dry-run                       показать, что было бы отправлено, без сети
 *   --force-local                   разрешить работу при непродовом хосте SITE_URL
 *   --quiet                         без вывода (коды выхода сохраняются)
 *   --git=PATH                      каталог git/bare-репозитория для --changed
 *   --help                          эта справка
 *
 * Коды выхода:
 *   0 — все ответы 2xx (или отправлять было нечего);
 *   1 — ошибка конфигурации/валидации (ключ, эндпоинт, аргументы, лок);
 *   2 — частичный отказ эндпоинта.
 *
 * PHP 8.2-совместимо, без Composer.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/indexnow.php';
require_once __DIR__ . '/../config/urls.php';
require_once __DIR__ . '/../lib/site-urls.php';
require_once __DIR__ . '/../lib/indexnow.php';

const INDEXNOW_PROD_HOSTS = ['doctorofcosmetology.ru', 'www.doctorofcosmetology.ru'];

function indexNowCliHelp(): string
{
    return <<<TXT
IndexNow CLI — отправка URL в Bing/Яндекс (Google IndexNow не поддерживает).

Использование:
  php bin/indexnow.php --all [--dry-run]
  php bin/indexnow.php --changed=OLD..NEW [--git=/path/to/repo.git] [--dry-run]
  php bin/indexnow.php --url=https://doctorofcosmetology.ru/blog/00047-.../ [--url=...]

Опции:
  --all                            все canonical-URL сайта (как в sitemap)
  --changed=OLD..NEW               URL, затронутые диапазоном git-коммитов
  --url=URL                        конкретный URL (можно повторять)
  --endpoint=shared|bing|yandex    эндпоинт; по умолчанию shared (api.indexnow.org)
  --endpoint-url=URL               тестовый эндпоинт; только с --force-local, только loopback
  --dry-run                        без сети: показать список и эндпоинт
  --force-local                    разрешить непродовый host SITE_URL (локальная проверка)
  --quiet                          без вывода
  --git=PATH                       git/bare-репозиторий для --changed (по умолчанию .git проекта)
  --help                           показать справку

Коды: 0 — все 2xx, 1 — конфигурация/валидация, 2 — частичный отказ эндпоинта.

TXT;
}

function indexNowCliOut(string $line, bool $quiet): void
{
    if (!$quiet) {
        fwrite(STDOUT, $line . PHP_EOL);
    }
}

function indexNowCliErr(string $line, bool $quiet): void
{
    if (!$quiet) {
        fwrite(STDERR, $line . PHP_EOL);
    }
}

$options = [
    'all'          => false,
    'changed'      => null,
    'urls'         => [],
    'endpoint'     => 'shared',
    'endpoint_url' => null,
    'dry_run'      => false,
    'force_local'  => false,
    'quiet'        => false,
    'git'          => null,
    'help'         => false,
];

$args = isset($argv) && is_array($argv) ? array_slice($argv, 1) : [];

if ($args === []) {
    fwrite(STDERR, indexNowCliHelp());
    exit(1);
}

for ($i = 0; $i < count($args); $i++) {
    $arg = (string) $args[$i];

    if ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
        continue;
    }
    if ($arg === '--all') {
        $options['all'] = true;
        continue;
    }
    if ($arg === '--dry-run') {
        $options['dry_run'] = true;
        continue;
    }
    if ($arg === '--force-local') {
        $options['force_local'] = true;
        continue;
    }
    if ($arg === '--quiet') {
        $options['quiet'] = true;
        continue;
    }
    if (strpos($arg, '--changed=') === 0) {
        $options['changed'] = substr($arg, strlen('--changed='));
        continue;
    }
    if (strpos($arg, '--url=') === 0) {
        $options['urls'][] = substr($arg, strlen('--url='));
        continue;
    }
    if (strpos($arg, '--endpoint=') === 0) {
        $options['endpoint'] = substr($arg, strlen('--endpoint='));
        continue;
    }
    if (strpos($arg, '--endpoint-url=') === 0) {
        $options['endpoint_url'] = substr($arg, strlen('--endpoint-url='));
        continue;
    }
    if (strpos($arg, '--git=') === 0) {
        $options['git'] = substr($arg, strlen('--git='));
        continue;
    }

    fwrite(STDERR, 'Unknown argument: ' . $arg . PHP_EOL . PHP_EOL . indexNowCliHelp());
    exit(1);
}

$quiet = (bool) $options['quiet'];

if ($options['help']) {
    fwrite(STDOUT, indexNowCliHelp());
    exit(0);
}

// --- Валидация аргументов до любой работы -----------------------------------

if ($options['endpoint_url'] !== null && !$options['force_local']) {
    indexNowCliErr('--endpoint-url разрешён только вместе с --force-local (тестовый режим).', $quiet);
    exit(1);
}

if (!array_key_exists((string) $options['endpoint'], INDEXNOW_ENDPOINTS)) {
    indexNowCliErr('Unknown --endpoint; allowed: ' . implode(', ', array_keys(INDEXNOW_ENDPOINTS)), $quiet);
    exit(1);
}

$endpointKey = (string) $options['endpoint'];
$overrideUrl = $options['endpoint_url'] !== null && trim((string) $options['endpoint_url']) !== ''
    ? (string) $options['endpoint_url']
    : null;

// Итоговый эндпоинт считаем один раз и до сбора URL: отклонённый override
// (не loopback / не с --force-local) даёт null — тогда никакой отправки,
// в том числе на боевой api.indexnow.org, не будет.
$resolvedEndpointUrl = indexNowEndpointUrl($endpointKey, $overrideUrl, (bool) $options['force_local']);
if ($resolvedEndpointUrl === null) {
    indexNowCliErr(sprintf(
        'Endpoint rejected: %s%s — override разрешён только с --force-local и только на loopback-хосте.',
        $endpointKey,
        $overrideUrl !== null ? ' (override: ' . $overrideUrl . ')' : ''
    ), $quiet);
    exit(1);
}

$changedRange = null;
if ($options['changed'] !== null) {
    $rawRange = trim((string) $options['changed']);
    $parts = explode('..', $rawRange);
    if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
        indexNowCliErr('--changed expects OLD..NEW (e.g. HEAD~1..HEAD).', $quiet);
        exit(1);
    }
    $changedRange = [trim($parts[0]), trim($parts[1])];
}

if (!$options['all'] && $changedRange === null && $options['urls'] === []) {
    indexNowCliErr('Nothing to do: use --all, --changed=OLD..NEW or --url=...', $quiet);
    indexNowCliErr('', $quiet);
    indexNowCliErr(indexNowCliHelp(), $quiet);
    exit(1);
}

// Валидация ключа: при невалидном ключе отправка не выполняется.
if (!indexNowKeyValid()) {
    indexNowCliErr('Invalid INDEXNOW_KEY: expected ^[A-Za-z0-9-]{8,128}$ (config/indexnow.php).', $quiet);
    exit(1);
}

// Отключено внешним конфигом / ENV.
if (!INDEXNOW_ENABLED) {
    indexNowLog('disabled (INDEXNOW_ENABLED=false): skip');
    indexNowCliOut('IndexNow disabled (INDEXNOW_ENABLED=false) — nothing to do.', $quiet);
    exit(0);
}

$host = indexNowHost();
$isProdHost = in_array($host, INDEXNOW_PROD_HOSTS, true);

if (!$isProdHost && !$options['force_local']) {
    indexNowCliErr(
        'SITE_URL host "' . $host . '" is not production; refusing to run without --force-local.',
        $quiet
    );
    exit(1);
}

// Локально без прод-хоста — только dry-run или фиктивный loopback-эндпоинт:
// реальные запросы к api.indexnow.org с .test-URL бессмысленны.
if (!$isProdHost && !$options['dry_run'] && $overrideUrl === null) {
    indexNowCliErr(
        'Non-production host: use --dry-run or --endpoint-url=http://127.0.0.1:PORT (with --force-local).',
        $quiet
    );
    exit(1);
}

// --- Сбор URL ---------------------------------------------------------------

$siteDir = dirname(__DIR__);
$collected = [];

if ($options['all']) {
    $collected = array_merge($collected, collectSiteUrls($siteDir));
}

if ($changedRange !== null) {
    $gitDir = $options['git'] !== null && $options['git'] !== ''
        ? (string) $options['git']
        : $siteDir . '/.git';

    $changedUrls = indexNowChangedUrls($changedRange[0], $changedRange[1], $siteDir, $gitDir);
    $collected = array_merge($collected, $changedUrls);
}

foreach ($options['urls'] as $url) {
    $collected[] = (string) $url;
}

// Локально (--force-local) SITE_URL обычно http://…test: разрешаем http-схему,
// чтобы dry-run и mock-эндпоинт проверяли тот же набор URL, что и sitemap.
$urls = indexNowValidateUrls($collected, $host, (bool) $options['force_local']);

if ($urls === [] && $changedRange !== null && !$options['all'] && $options['urls'] === []) {
    if (!$options['dry_run']) {
        indexNowLog(sprintf(
            'run mode=changed range=%s urls=0 result=nothing-to-send',
            $changedRange[0] . '..' . $changedRange[1]
        ));
    }
    indexNowCliOut('No relevant changes in ' . $changedRange[0] . '..' . $changedRange[1] . ' — nothing to send.', $quiet);
    exit(0);
}

if ($urls === []) {
    indexNowCliErr('No valid URLs after filtering (https, host ' . $host . ', no query, trailing slash).', $quiet);
    exit(1);
}

$mode = $options['all'] ? 'all' : ($changedRange !== null ? 'changed' : 'url');

// --- Показ плана ------------------------------------------------------------

if ($options['dry_run']) {
    $planChunks = indexNowChunk($urls, (int) INDEXNOW_MAX_URLS);
    $chunkSizes = array_map('count', $planChunks);

    indexNowCliOut('IndexNow dry run', $quiet);
    indexNowCliOut('  mode:      ' . $mode, $quiet);
    if ($changedRange !== null) {
        indexNowCliOut('  range:     ' . $changedRange[0] . '..' . $changedRange[1], $quiet);
    }
    indexNowCliOut('  host:      ' . $host . (!empty($isProdHost) ? '' : ' (non-production, --force-local)'), $quiet);
    indexNowCliOut(
        '  endpoint:  ' . $endpointKey . ' → ' . $resolvedEndpointUrl . ($overrideUrl !== null ? ' (override)' : ''),
        $quiet
    );
    indexNowCliOut('  urls:      ' . count($urls), $quiet);
    indexNowCliOut(
        '  chunks:    ' . count($planChunks) . ' (' . implode(' + ', $chunkSizes) . ' url)',
        $quiet
    );

    if (!$quiet) {
        foreach ($urls as $url) {
            fwrite(STDOUT, '  - ' . $url . PHP_EOL);
        }
    }

    exit(0);
}

// --- Блокировка против параллельных запусков --------------------------------

$lock = indexNowAcquireLock();
if ($lock === false) {
    indexNowCliErr('Cannot acquire lock ' . INDEXNOW_LOCK . ' (another run in progress?).', $quiet);
    exit(1);
}

indexNowLog(sprintf(
    'run mode=%s range=%s endpoint=%s urls=%d host=%s',
    $mode,
    $changedRange !== null ? $changedRange[0] . '..' . $changedRange[1] : '-',
    $endpointKey,
    count($urls),
    $host
));

$result = indexNowSubmit($urls, $endpointKey, false, $overrideUrl, (bool) $options['force_local']);

foreach ($result['results'] as $row) {
    indexNowCliOut(sprintf(
        '  chunk %d/%d: %d urls → %s: HTTP %d (attempts %d)',
        (int) $row['chunk'],
        (int) $row['chunks'],
        (int) $row['urls'],
        (string) $row['endpoint'],
        (int) $row['status'],
        (int) $row['attempts']
    ), $quiet);
}

@flock($lock, LOCK_UN);
@fclose($lock);

if ($result['error'] !== '') {
    indexNowLog('error: ' . $result['error']);
    indexNowCliErr('Error: ' . $result['error'], $quiet);
    exit(1);
}

if ($result['ok']) {
    indexNowLog(sprintf(
        'done mode=%s attempted=%d accepted=%d result=ok',
        $mode,
        (int) $result['attempted'],
        (int) $result['accepted']
    ));
    indexNowCliOut(sprintf(
        'IndexNow: OK, %d url(s) accepted (%d attempted).',
        (int) $result['accepted'],
        (int) $result['attempted']
    ), $quiet);
    exit(0);
}

indexNowLog(sprintf(
    'done mode=%s attempted=%d accepted=%d result=partial',
    $mode,
    (int) $result['attempted'],
    (int) $result['accepted']
));
indexNowCliErr('IndexNow: partial failure — see ' . INDEXNOW_LOG, $quiet);
exit(2);
