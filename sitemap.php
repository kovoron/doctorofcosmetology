<?php
// /sitemap.php — Динамическая генерация карты сайта для поисковых роботов
// Список URL собирается в lib/site-urls.php (общий источник с IndexNow).

require_once __DIR__ . '/config/site.php';
require_once __DIR__ . '/config/urls.php';
require_once __DIR__ . '/lib/site-urls.php';

// 1. Устанавливаем заголовки XML
header("Content-Type: application/xml; charset=utf-8");

// Вспомогательная функция для генерации XML-тегов
function urlTag($loc, $lastmod, $changefreq, $priority) {
    return "\t<url>\n" .
           "\t\t<loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n" .
           "\t\t<lastmod>" . $lastmod . "</lastmod>\n" .
           "\t\t<changefreq>" . $changefreq . "</changefreq>\n" .
           "\t\t<priority>" . $priority . "</priority>\n" .
           "\t</url>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 2. Все страницы: статика + опубликованные статьи + кейсы портфолио.
foreach (collectSiteEntries() as $entry) {
    echo urlTag($entry['url'], $entry['lastmod'], $entry['changefreq'], $entry['priority']);
}

// 3. Закрытие XML
echo '</urlset>';
