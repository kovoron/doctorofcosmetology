<?php

/**
 * Шаблон прод-конфига doctorofcosmetology.
 *
 * Куда положить: /var/www/u3548935/data/.doctorofcosmetology.prod.php (вне docroot!)
 * Права: chmod 600
 * Реальные значения в репозиторий не попадают.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'u3548935_default');
define('DB_USER', 'u3548935_default');
define('DB_PASS', 'ЗАМЕНИТЕ_НА_БОЕВОЙ_ПАРОЛЬ');
define('DB_CHARSET', 'utf8mb4');

define('APP_ENV', 'production');
define('SITE_URL', 'https://doctorofcosmetology.ru');
define('METRIKA_ID', 109925540);
define('METRIKA_ENABLED', true);
