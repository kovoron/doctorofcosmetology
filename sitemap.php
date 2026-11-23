<?php
// /sitemap.php — Динамическая генерация карты сайта для поисковых роботов

require_once __DIR__ . '/config/site.php';

// 1. Устанавливаем заголовки XML
header("Content-Type: application/xml; charset=utf-8");

$baseUrl = rtrim(SITE_URL, '/');
$today = date("Y-m-d");
$rootDir = __DIR__;

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

// ==========================================
// 2. СТАТИЧЕСКИЕ СТРАНИЦЫ
// ==========================================
$staticPages = [
    '/'           => ['freq' => 'weekly',  'priority' => '1.0'],
    '/about/'     => ['freq' => 'monthly', 'priority' => '0.9'],
    '/portfolio/' => ['freq' => 'weekly',  'priority' => '0.9'],
    '/blog/'      => ['freq' => 'weekly',  'priority' => '0.9'],
    '/q/'         => ['freq' => 'monthly', 'priority' => '0.7'],
    // /budget/ намеренно НЕ включён — приватный раздел
];

foreach ($staticPages as $path => $meta) {
    echo urlTag($baseUrl . $path, $today, $meta['freq'], $meta['priority']);
}

// ==========================================
// 3. СТАТЬИ БЛОГА (/blog/posts/*.json)
// ==========================================
$blogDir = $rootDir . '/blog/posts/';
if (is_dir($blogDir)) {
    $blogFiles = glob($blogDir . '*.json');
    
    foreach ($blogFiles as $file) {
        $json = @file_get_contents($file);
        $data = $json ? json_decode($json, true) : null;
        
        // Пропускаем, если JSON не валиден или пост не опубликован
        if (!$data || !isset($data['publish_date']) || $data['publish_date'] > $today) {
            continue;
        }
        
        $postId = pathinfo($file, PATHINFO_FILENAME);
        $postUrl = $baseUrl . '/blog/' . $postId;
        $lastmod = $data['date'] ?? date("Y-m-d", filemtime($file));
        
        echo urlTag($postUrl, $lastmod, 'monthly', '0.8');
    }
}

// ==========================================
// 4. КЕЙСЫ ПОРТФОЛИО (/portfolio/cases/portfolio*.json)
// ==========================================
$portfolioDir = $rootDir . '/portfolio/cases/';
if (is_dir($portfolioDir)) {
    $portfolioFiles = glob($portfolioDir . 'portfolio*.json');
    
    foreach ($portfolioFiles as $file) {
        $json = @file_get_contents($file);
        $data = $json ? json_decode($json, true) : null;
        
        if (!$data) continue;
        
        // Извлекаем ID из имени файла: portfolio0001 → 0001
        $caseId = preg_replace('/\D/', '', pathinfo($file, PATHINFO_FILENAME));
        $caseUrl = $baseUrl . '/portfolio/' . $caseId;
        $lastmod = date("Y-m-d", filemtime($file));
        
        echo urlTag($caseUrl, $lastmod, 'monthly', '0.7');
    }
}

// ==========================================
// 5. ЗАКРЫТИЕ XML
// ==========================================
echo '</urlset>';
?>