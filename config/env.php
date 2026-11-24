<?php

/**
 * Определение окружения и загрузка внешних конфигов.
 *
 * Секреты (доступы к БД и пр.) в репозитории не хранятся:
 *  - локально — /Users/kovoron/Herd/.doctorofcosmetology.local.php (вне проекта);
 *  - на проде — /var/www/u3548935/data/.doctorofcosmetology.prod.php (вне docroot, chmod 600).
 */

if (!function_exists('siteProductionConfigFile')) {
    /**
     * Путь прод-конфига (вне docroot, chmod 600). Единственное место, где он задан.
     */
    function siteProductionConfigFile(): string
    {
        return '/var/www/u3548935/data/.doctorofcosmetology.prod.php';
    }
}

if (!function_exists('siteIsProduction')) {
    function siteIsProduction(): bool
    {
        // CLI: HTTP_HOST и DOCUMENT_ROOT пусты, поэтому хост/докрут ничего не скажут.
        // Признак прода в CLI — наличие прод-конфига (тот же путь, что в loadExternalConfig()).
        // Нет файла — проваливаемся в обычную проверку host/docroot (локально это false).
        if (PHP_SAPI === 'cli' && is_file(siteProductionConfigFile())) {
            return true;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

        return preg_match('/(^|\.)doctorofcosmetology\.ru$/i', $host) === 1
            || strpos($docRoot, '/var/www/') === 0;
    }
}

if (!function_exists('appTimezone')) {
    /**
     * Часовой пояс приложения (PHP-функции date()/strtotime()).
     * По умолчанию — московское время: в нём живёт владелец бюджета,
     * а PHP на хостинге настроен на UTC.
     */
    function appTimezone(): string
    {
        return defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Europe/Moscow';
    }
}

if (!function_exists('dbTimezone')) {
    /**
     * Смещение часового пояса для сессии MySQL: нужно, чтобы CURDATE()
     * и DATE(created_at) считались в той же зоне, что и PHP.
     */
    function dbTimezone(): string
    {
        return defined('DB_TIMEZONE') ? DB_TIMEZONE : '+03:00';
    }
}

if (!function_exists('loadExternalConfig')) {
    function loadExternalConfig(): void
    {
        $production = siteIsProduction();

        $file = $production
            ? siteProductionConfigFile()
            : dirname(dirname(__DIR__)) . '/.doctorofcosmetology.local.php';

        if (is_file($file)) {
            require_once $file;

            return;
        }

        throw new RuntimeException(sprintf(
            'Не найден внешний конфиг: %s. Секреты в репозитории не хранятся — создайте файл (%s).',
            $file,
            $production ? 'вне docroot, chmod 600' : 'локальная конфигурация вне проекта'
        ));
    }
}
