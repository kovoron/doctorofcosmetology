<?php

/**
 * Настройки IndexNow (см. TASK.md, раздел 5).
 *
 * Ключ IndexNow публичен по протоколу: он лежит и в этом файле, и в файле
 * /{KEY}.txt в корне сайта (содержимое — сам ключ, без HTML). Секретом не является.
 *
 * Все константы определяются через !defined(), поэтому внешний конфиг
 * (config/env.php → .doctorofcosmetology.prod.php) может их переопределить.
 * INDEXNOW_ENABLED дополнительно переопределяется переменной окружения
 * `INDEXNOW_ENABLED=0` (удобно для локальных прогонов и ручных запусков).
 *
 * Важно: require site.php идёт ДО всех define() — site.php подтягивает внешний
 * конфиг, поэтому INDEXNOW_LOG / INDEXNOW_LOCK / INDEXNOW_DATA_DIR можно
 * задать там и они не будут перезаписаны значениями по умолчанию.
 */

require_once __DIR__ . '/site.php';

if (!defined('INDEXNOW_KEY')) {
    define('INDEXNOW_KEY', '5f709a63f0e26d8e0ee645dff70ba2b0');
}

if (!defined('INDEXNOW_ENABLED')) {
    $indexNowEnv = getenv('INDEXNOW_ENABLED');
    $indexNowEnabled = true;

    if ($indexNowEnv !== false && trim((string) $indexNowEnv) !== '') {
        $indexNowEnabled = !in_array(
            strtolower(trim((string) $indexNowEnv)),
            ['0', 'false', 'no', 'off'],
            true
        );
    }

    define('INDEXNOW_ENABLED', $indexNowEnabled);
}

// Эндпоинты протокола. Штатный режим — только `shared` (api.indexnow.org):
// Яндекс и Bing получают запрос через него; bing/yandex нужны для диагностики.
if (!defined('INDEXNOW_ENDPOINTS')) {
    define('INDEXNOW_ENDPOINTS', [
        'shared' => 'https://api.indexnow.org/indexnow',
        'bing'   => 'https://www.bing.com/indexnow',
        'yandex' => 'https://yandex.com/indexnow',
    ]);
}

// Лог и лок-файл — ВНЕ docroot: рядом с каталогом проекта, в отдельном
// служебном каталоге (не публикуется по HTTP, в .gitignore).
// Каталог создаётся с правами 0700, файлы — 0600 (см. lib/indexnow.php).
if (!defined('INDEXNOW_DATA_DIR')) {
    define('INDEXNOW_DATA_DIR', dirname(__DIR__, 2) . '/.doctorofcosmetology-indexnow');
}

if (!defined('INDEXNOW_LOG')) {
    define('INDEXNOW_LOG', INDEXNOW_DATA_DIR . '/indexnow.log');
}

if (!defined('INDEXNOW_LOCK')) {
    define('INDEXNOW_LOCK', INDEXNOW_DATA_DIR . '/indexnow.lock');
}

// Максимум URL в одном запросе по протоколу.
if (!defined('INDEXNOW_MAX_URLS')) {
    define('INDEXNOW_MAX_URLS', 10000);
}

// Сетевые параметры и надёжность.
if (!defined('INDEXNOW_TIMEOUT_CONNECT')) {
    define('INDEXNOW_TIMEOUT_CONNECT', 3);
}

if (!defined('INDEXNOW_TIMEOUT_TOTAL')) {
    define('INDEXNOW_TIMEOUT_TOTAL', 10);
}

if (!defined('INDEXNOW_MAX_ATTEMPTS')) {
    define('INDEXNOW_MAX_ATTEMPTS', 3);
}

if (!defined('INDEXNOW_RETRY_PAUSE_MS')) {
    define('INDEXNOW_RETRY_PAUSE_MS', 500);
}

// Ротация лога по размеру: > 5 МБ → indexnow.log.1
if (!defined('INDEXNOW_LOG_MAX_BYTES')) {
    define('INDEXNOW_LOG_MAX_BYTES', 5 * 1024 * 1024);
}

if (!defined('INDEXNOW_RESPONSE_LOG_BYTES')) {
    define('INDEXNOW_RESPONSE_LOG_BYTES', 500);
}
