<?php

/**
 * Определение окружения и загрузка внешних конфигов.
 *
 * Секреты (доступы к БД и пр.) в репозитории не хранятся:
 *  - локально — /Users/kovoron/Herd/.doctorofcosmetology.local.php (вне проекта);
 *  - на проде — /var/www/u3548935/data/.doctorofcosmetology.prod.php (вне docroot, chmod 600).
 */

if (!function_exists('siteIsProduction')) {
    function siteIsProduction(): bool
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

        return preg_match('/(^|\.)doctorofcosmetology\.ru$/i', $host) === 1
            || strpos($docRoot, '/var/www/') === 0;
    }
}

if (!function_exists('loadExternalConfig')) {
    function loadExternalConfig(): void
    {
        $production = siteIsProduction();

        $file = $production
            ? '/var/www/u3548935/data/.doctorofcosmetology.prod.php'
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
