<?php

/**
 * Общие настройки сайта.
 * Значения по умолчанию — продовые; локальные переопределяются внешним конфигом.
 */

require_once __DIR__ . '/env.php';

loadExternalConfig();

if (!defined('APP_ENV')) {
    define('APP_ENV', siteIsProduction() ? 'production' : 'local');
}

if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://doctorofcosmetology.ru');
}

if (!defined('METRIKA_ID')) {
    define('METRIKA_ID', 109925540);
}

// Локально счётчик выключен, чтобы не загрязнять боевую статистику.
if (!defined('METRIKA_ENABLED')) {
    define('METRIKA_ENABLED', siteIsProduction());
}
