<?php

/**
 * Библиотека IndexNow (см. TASK.md, 5.2 и 5.3 шаг 4).
 *
 * Состав:
 *  - indexNowKeyValid()        — валидация ключа (^[A-Za-z0-9-]{8,128}$);
 *  - indexNowHost()            — host из SITE_URL (без схемы);
 *  - indexNowValidateUrls()    — фильтр схемы/хоста/query, отсев /budget/ и /sitemap.php, дедуп;
 *  - indexNowSubmit()          — чанки ≤ INDEXNOW_MAX_URLS, JSON, cURL, ретраи 429/5xx, лог;
 *  - indexNowChangedUrls()     — git diff OLD..NEW → canonical-URL затронутых статей или все URL;
 *  - indexNowLog()             — запись в лог вне docroot с ротацией по 5 МБ;
 *  - indexNowAcquireLock()     — flock на лок-файле вне docroot.
 *
 * Требования: PHP 8.2 (продовый CLI), без Composer, только curl + json.
 * Функции не бросают исключений.
 */

require_once __DIR__ . '/../config/indexnow.php';
require_once __DIR__ . '/../config/urls.php';
require_once __DIR__ . '/site-urls.php';

if (!function_exists('indexNowKeyValid')) {
    /**
     * Ключ IndexNow: 8–128 символов [A-Za-z0-9-].
     */
    function indexNowKeyValid(?string $key = null): bool
    {
        $key = $key !== null ? $key : (defined('INDEXNOW_KEY') ? (string) INDEXNOW_KEY : '');

        return preg_match('/^[A-Za-z0-9-]{8,128}$/', $key) === 1;
    }
}

if (!function_exists('indexNowHost')) {
    /**
     * Хост из SITE_URL: «https://doctorofcosmetology.ru» → «doctorofcosmetology.ru».
     */
    function indexNowHost(?string $siteUrl = null): string
    {
        $siteUrl = $siteUrl !== null ? $siteUrl : (defined('SITE_URL') ? (string) SITE_URL : '');
        $host = parse_url($siteUrl, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }
}

if (!function_exists('indexNowPrivateDir')) {
    /**
     * Создаёт служебный каталог IndexNow с правами 0700 (вне docroot).
     * true — каталог существует и доступен.
     */
    function indexNowPrivateDir(string $dir): bool
    {
        if (is_dir($dir)) {
            // Существующий каталог тоже приводим к 0700 (мог достаться от прошлых версий).
            @chmod($dir, 0700);

            return true;
        }

        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }

        // mkdir учитывает umask — выставляем права явно.
        @chmod($dir, 0700);

        return true;
    }
}

if (!function_exists('indexNowLogUrlList')) {
    /**
     * Список URL одной строкой для лога, с ограничением длины (~2000 символов).
     */
    function indexNowLogUrlList(array $urls, int $limit = 2000): string
    {
        $joined = implode(' ', $urls);
        $length = function_exists('mb_strlen') ? mb_strlen($joined, 'UTF-8') : strlen($joined);

        if ($length <= $limit) {
            return $joined;
        }

        $cut = function_exists('mb_substr') ? mb_substr($joined, 0, $limit, 'UTF-8') : substr($joined, 0, $limit);

        return $cut . '…(truncated, total=' . $length . ' chars)';
    }
}

if (!function_exists('indexNowLog')) {
    /**
     * Дозапись строки в лог вне docroot. Ротация: файл > INDEXNOW_LOG_MAX_BYTES → .1.
     * Каталог 0700, файл 0600. Лог НЕ должен ронять отправку: при любой ошибке
     * пишем в error_log и продолжаем.
     */
    function indexNowLog(string $message): void
    {
        if (!defined('INDEXNOW_LOG')) {
            error_log('indexnow: ' . $message);

            return;
        }

        $logFile = INDEXNOW_LOG;
        $dir = dirname($logFile);

        if (!indexNowPrivateDir($dir)) {
            error_log('indexnow (cannot create ' . $dir . '): ' . $message);

            return;
        }

        if (is_file($logFile) && @filesize($logFile) > INDEXNOW_LOG_MAX_BYTES) {
            @rename($logFile, $logFile . '.1');
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        $written = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            error_log('indexnow (cannot write ' . $logFile . '): ' . $message);

            return;
        }

        @chmod($logFile, 0600);
    }
}

if (!function_exists('indexNowAcquireLock')) {
    /**
     * Неблокирующий flock. Возвращает дескриптор (держать до конца работы)
     * или false, если лок занят/каталог недоступен.
     *
     * @return resource|false
     */
    function indexNowAcquireLock()
    {
        if (!defined('INDEXNOW_LOCK')) {
            return false;
        }

        $dir = dirname(INDEXNOW_LOCK);
        if (!indexNowPrivateDir($dir)) {
            return false;
        }

        $fh = @fopen(INDEXNOW_LOCK, 'c');
        if ($fh === false) {
            return false;
        }

        @chmod(INDEXNOW_LOCK, 0600);

        if (!@flock($fh, LOCK_EX | LOCK_NB)) {
            @fclose($fh);

            return false;
        }

        return $fh;
    }
}

if (!function_exists('indexNowValidateUrls')) {
    /**
     * Фильтр списка URL перед отправкой:
     *  - строка, https, хост совпадает с $host (регистр не важен);
     *  - без query и fragment;
     *  - только «человеческие» canonical-пути с завершающим «/»;
     *  - /budget/* и /sitemap.php не отправляются;
     *  - дубликаты удаляются, порядок сохраняется.
     *
     * @param bool $allowHttp Разрешить http-схему: только для локальных тестов
     *                        (CLI передаёт true лишь при --force-local).
     *
     * @return string[]
     */
    function indexNowValidateUrls(array $urls, string $host, bool $allowHttp = false): array
    {
        $host = strtolower(trim($host));
        $valid = [];

        foreach ($urls as $url) {
            if (!is_string($url)) {
                continue;
            }

            $url = trim($url);
            if ($url === '') {
                continue;
            }

            $parts = parse_url($url);
            if (!is_array($parts)) {
                continue;
            }

            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $urlHost = strtolower((string) ($parts['host'] ?? ''));
            $path = (string) ($parts['path'] ?? '');

            $schemeOk = $scheme === 'https' || ($allowHttp && $scheme === 'http');
            if (!$schemeOk || $urlHost !== $host || $path === '') {
                continue;
            }

            if (isset($parts['query']) || isset($parts['fragment'])) {
                continue;
            }

            if ($path[0] !== '/' || substr($path, -1) !== '/') {
                continue;
            }

            if (strpos($path, '/budget/') === 0 || $path === '/sitemap.php') {
                continue;
            }

            $normalized = $scheme . '://' . $host . $path;
            if (isset($valid[$normalized])) {
                continue;
            }
            $valid[$normalized] = true;
        }

        return array_keys($valid);
    }
}

if (!function_exists('indexNowChunk')) {
    /**
     * Разбиение списка на чанки заданного размера.
     *
     * @return array<int, array<int, string>>
     */
    function indexNowChunk(array $urls, int $size): array
    {
        if ($size < 1) {
            $size = 1;
        }

        return array_chunk(array_values($urls), $size);
    }
}

if (!function_exists('indexNowIsLoopbackHost')) {
    /**
     * Только loopback-хосты допустимы для тестового override эндпоинта.
     */
    function indexNowIsLoopbackHost(string $host): bool
    {
        $host = strtolower($host);

        return $host === '127.0.0.1' || $host === 'localhost' || $host === '::1' || $host === '[::1]';
    }
}

if (!function_exists('indexNowEndpointUrl')) {
    /**
     * Итоговый URL эндпоинта или null, если конфигурация недопустима.
     *
     * Override (тестовый mock) принимается только при $allowOverride = true
     * (CLI передаёт true лишь вместе с --force-local) и только на loopback-хосте.
     * Любой отклонённый/невалидный override → null: на боевой api.indexnow.org
     * запрос НЕ уходит.
     */
    function indexNowEndpointUrl(
        string $endpointKey,
        ?string $overrideUrl = null,
        bool $allowOverride = false
    ): ?string {
        if ($overrideUrl !== null && trim($overrideUrl) !== '') {
            if (!$allowOverride) {
                return null;
            }

            $host = parse_url($overrideUrl, PHP_URL_HOST);
            $scheme = strtolower((string) parse_url($overrideUrl, PHP_URL_SCHEME));

            if (!is_string($host) || !indexNowIsLoopbackHost($host) || !in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            return $overrideUrl;
        }

        $endpoints = defined('INDEXNOW_ENDPOINTS') && is_array(INDEXNOW_ENDPOINTS) ? INDEXNOW_ENDPOINTS : [];
        $endpoint = $endpoints[$endpointKey] ?? null;

        return is_string($endpoint) && $endpoint !== '' ? $endpoint : null;
    }
}

if (!function_exists('indexNowHttpPost')) {
    /**
     * Один POST с JSON. Возвращает ['status' => int, 'body' => string, 'error' => string].
     * status = 0 при сетевой ошибке.
     */
    function indexNowHttpPost(string $endpointUrl, string $jsonBody): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => 0, 'body' => '', 'error' => 'curl extension is not available'];
        }

        $ch = curl_init($endpointUrl);
        if ($ch === false) {
            return ['status' => 0, 'body' => '', 'error' => 'curl_init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => (int) INDEXNOW_TIMEOUT_CONNECT,
            CURLOPT_TIMEOUT        => (int) INDEXNOW_TIMEOUT_TOTAL,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'User-Agent: doctorofcosmetology-indexnow/1.0',
            ],
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno !== 0 ? curl_error($ch) : '';
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status' => $errno !== 0 ? 0 : $status,
            'body'   => is_string($body) ? $body : '',
            'error'  => $error,
        ];
    }
}

if (!function_exists('indexNowSubmit')) {
    /**
     * Отправка URL в IndexNow.
     *
     * @param string[]    $urls                URL (уже прошедшие indexNowValidateUrls())
     * @param string|null $endpointKey         shared|bing|yandex (по умолчанию shared)
     * @param bool        $dryRun              true — ничего не отправлять и не логировать
     * @param string|null $overrideEndpointUrl тестовый эндпоинт (только loopback, см. indexNowEndpointUrl())
     * @param bool        $allowOverride       разрешить override (только при --force-local)
     *
     * @return array{
     *   ok: bool, partial: bool, error: string,
     *   attempted: int, accepted: int, results: array<int, array<string, mixed>>
     * }
     */
    function indexNowSubmit(
        array $urls,
        ?string $endpointKey = null,
        bool $dryRun = false,
        ?string $overrideEndpointUrl = null,
        bool $allowOverride = false
    ): array {
        $endpointKey = $endpointKey !== null && $endpointKey !== '' ? $endpointKey : 'shared';
        $summary = [
            'ok'        => false,
            'partial'   => false,
            'error'     => '',
            'attempted' => 0,
            'accepted'  => 0,
            'results'   => [],
        ];

        if (!indexNowKeyValid()) {
            $summary['error'] = 'invalid INDEXNOW_KEY (expected ^[A-Za-z0-9-]{8,128}$)';

            return $summary;
        }

        $endpointUrl = indexNowEndpointUrl($endpointKey, $overrideEndpointUrl, $allowOverride);
        if ($endpointUrl === null) {
            $summary['error'] = 'endpoint rejected or unknown: ' . $endpointKey
                . ($overrideEndpointUrl !== null ? ' (override: ' . $overrideEndpointUrl . ')' : '');

            return $summary;
        }

        $urls = array_values($urls);
        if ($urls === []) {
            $summary['error'] = 'no urls to submit';

            return $summary;
        }

        $host = indexNowHost();
        if ($host === '') {
            $summary['error'] = 'cannot determine host from SITE_URL';

            return $summary;
        }

        $chunks = indexNowChunk($urls, (int) INDEXNOW_MAX_URLS);
        $totalChunks = count($chunks);
        $allOk = true;

        foreach ($chunks as $index => $chunk) {
            $chunkNo = $index + 1;
            $payload = [
                'host'    => $host,
                'key'     => (string) INDEXNOW_KEY,
                'urlList' => array_values($chunk),
            ];
            $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($jsonBody)) {
                $summary['error'] = 'json_encode failed';
                $summary['partial'] = true;

                return $summary;
            }

            if ($dryRun) {
                $summary['results'][] = [
                    'endpoint' => $endpointKey,
                    'url'      => $endpointUrl,
                    'chunk'    => $chunkNo,
                    'chunks'   => $totalChunks,
                    'urls'     => count($chunk),
                    'status'   => 0,
                    'attempts' => 0,
                    'ok'       => true,
                    'dry_run'  => true,
                ];
                $summary['attempted'] += count($chunk);
                continue;
            }

            $attempt = 0;
            $status = 0;
            $body = '';
            $error = '';

            while ($attempt < (int) INDEXNOW_MAX_ATTEMPTS) {
                $attempt++;
                $response = indexNowHttpPost($endpointUrl, $jsonBody);
                $status = $response['status'];
                $body = $response['body'];
                $error = $response['error'];

                $retryable = $status === 429 || ($status >= 500 && $status <= 599);
                if (!$retryable) {
                    break;
                }

                if ($attempt < (int) INDEXNOW_MAX_ATTEMPTS) {
                    usleep((int) INDEXNOW_RETRY_PAUSE_MS * 1000 * $attempt);
                }
            }

            // 200/202 — успех; 400/403/422 — ошибка конфигурации без ретрая.
            $ok = $status === 200 || $status === 202;
            if (!$ok) {
                $allOk = false;
            }

            $bodySnippet = substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, (int) INDEXNOW_RESPONSE_LOG_BYTES);
            $urlListSnippet = indexNowLogUrlList($chunk);

            indexNowLog(sprintf(
                'submit endpoint=%s chunk=%d/%d urls=%d host=%s status=%d attempts=%d%s%s urls_list="%s"',
                $endpointKey,
                $chunkNo,
                $totalChunks,
                count($chunk),
                $host,
                $status,
                $attempt,
                $error !== '' ? ' curl_error="' . $error . '"' : '',
                $bodySnippet !== '' ? ' body="' . $bodySnippet . '"' : '',
                $urlListSnippet
            ));

            $summary['results'][] = [
                'endpoint' => $endpointKey,
                'url'      => $endpointUrl,
                'chunk'    => $chunkNo,
                'chunks'   => $totalChunks,
                'urls'     => count($chunk),
                'status'   => $status,
                'attempts' => $attempt,
                'ok'       => $ok,
                'error'    => $error,
                'body'     => $bodySnippet,
            ];

            $summary['attempted'] += count($chunk);
            if ($ok) {
                $summary['accepted'] += count($chunk);
            }
        }

        $summary['ok'] = $allOk;
        $summary['partial'] = !$allOk;

        return $summary;
    }
}

if (!function_exists('indexNowIsGlobalPath')) {
    /**
     * Влияет ли изменённый файл на все страницы (шаблоны, стили, конфиги, .htaccess).
     */
    function indexNowIsGlobalPath(string $path): bool
    {
        $path = ltrim($path, '/');

        // .htaccess в любом каталоге.
        if (preg_match('#(^|/)\.htaccess$#', $path) === 1) {
            return true;
        }

        // Общие конфиги и библиотеки.
        if (preg_match('#^(config|lib)/#', $path) === 1) {
            return true;
        }

        // Шаблоны и страницы, влияющие на множество URL.
        $exact = [
            'header.php',
            'footer.php',
            'index.php',
            'sitemap.php',
            'blog/index.php',
            'blog/post.php',
            'portfolio/index.php',
            'portfolio/case.php',
            'q/index.php',
        ];
        if (in_array($path, $exact, true)) {
            return true;
        }

        // Корневые веб-ресурсы (стили, манифест, robots, иконки, подтверждения).
        if (strpos($path, '/') === false) {
            return preg_match('#\.(php|css|js|txt|html|webmanifest|ico|png|svg)$#i', $path) === 1;
        }

        return false;
    }
}

if (!function_exists('indexNowGitDiffNames')) {
    /**
     * `git --git-dir=<gitDir> diff --name-only --diff-filter=ACMR --no-renames OLD NEW`.
     * Возвращает список путей или [] при ошибке (с записью в лог).
     *
     * @return string[]
     */
    function indexNowGitDiffNames(string $old, string $new, string $gitDir): array
    {
        if ($old === '' || $new === '') {
            return [];
        }

        // ACMR: только добавления/копии/изменения/переименования; --no-renames
        // отключает эвристику переименований (иначе путь может «потеряться»).
        $cmd = 'git --git-dir=' . escapeshellarg($gitDir)
            . ' diff --name-only --diff-filter=ACMR --no-renames '
            . escapeshellarg($old) . ' ' . escapeshellarg($new) . ' 2>&1';

        $output = [];
        $exitCode = 1;
        @exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            indexNowLog('git diff failed: ' . implode(' | ', array_slice($output, 0, 5)));

            return [];
        }

        $paths = [];
        foreach ($output as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $paths[] = $line;
            }
        }

        return $paths;
    }
}

if (!function_exists('indexNowChangedUrls')) {
    /**
     * Какие URL пинговать при деплое OLD..NEW (см. TASK.md, 5.2 п.5):
     *  - blog/posts/{id}.json → canonical-URL статьи (черновики и битый JSON пропускаются);
     *  - blog/images/*        → ничего;
     *  - глобальные файлы     → все URL из collectSiteUrls();
     *  - прочее               → игнорируется.
     *
     * @return string[]
     */
    function indexNowChangedUrls(string $old, string $new, string $siteDir, ?string $gitDir = null): array
    {
        $siteDir = rtrim($siteDir, '/');
        $gitDir = $gitDir !== null && $gitDir !== '' ? $gitDir : $siteDir . '/.git';

        $changedPaths = indexNowGitDiffNames($old, $new, $gitDir);
        if ($changedPaths === []) {
            return [];
        }

        $today = siteUrlToday();
        $urls = [];

        foreach ($changedPaths as $path) {
            // Картинки блога страницу не меняют — их отправит изменённый JSON.
            if (strpos($path, 'blog/images/') === 0) {
                continue;
            }

            if (preg_match('#^blog/posts/([0-9]{5})\.json$#', $path, $m) === 1) {
                $file = $siteDir . '/' . $path;
                if (!is_file($file)) {
                    // Удалённый пост: старый URL отдельно не пингуем.
                    continue;
                }

                $json = @file_get_contents($file);
                $data = $json ? json_decode($json, true) : null;

                if (!is_array($data)) {
                    indexNowLog('changed: invalid json, skipped: ' . $path);
                    continue;
                }

                if (!isset($data['publish_date']) || $data['publish_date'] > $today) {
                    indexNowLog('changed: draft, skipped: ' . $path);
                    continue;
                }

                $postUrl = blogPostUrl(['id' => $m[1], 'slug' => $data['slug'] ?? '']);
                if ($postUrl !== '') {
                    $urls[$postUrl] = true;
                }

                continue;
            }

            if (indexNowIsGlobalPath($path)) {
                foreach (collectSiteUrls($siteDir) as $url) {
                    $urls[$url] = true;
                }

                continue;
            }

            // Всё остальное (budget/, q/process.php, *.md, deploy/, bin/) не влияет на sitemap.
        }

        return array_keys($urls);
    }
}
