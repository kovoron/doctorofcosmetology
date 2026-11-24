<?php

/**
 * Единый источник URL сайта (см. TASK.md, 5.2 п.3).
 *
 * Используется и sitemap.php, и bin/indexnow.php — чтобы canonical-URL,
 * slug и фильтр черновиков не разъезжались.
 *
 * Только чтение: файлы и config/urls.php. Никакого вывода.
 */

require_once __DIR__ . '/../config/site.php';
require_once __DIR__ . '/../config/urls.php';

if (!function_exists('siteUrlToday')) {
    /**
     * Сегодняшняя дата в формате Y-m-d (как в sitemap.php).
     */
    function siteUrlToday(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('collectSiteEntries')) {
    /**
     * Полный список страниц сайта с метаданными для sitemap.
     *
     * Возвращает список массивов:
     *   ['url' => абсолютный URL, 'lastmod' => Y-m-d, 'changefreq' => ..., 'priority' => ...]
     *
     * Порядок: статические страницы → статьи блога (по имени файла) → кейсы портфолио.
     *
     * @param string|null $siteDir Корень сайта (по умолчанию — корень проекта)
     */
    function collectSiteEntries(?string $siteDir = null): array
    {
        $siteDir = $siteDir !== null ? rtrim($siteDir, '/') : dirname(__DIR__);
        $today = siteUrlToday();
        $entries = [];

        // 1. Статические страницы. /budget/ намеренно НЕ включён — приватный раздел.
        $staticPages = [
            '/'           => ['freq' => 'weekly',  'priority' => '1.0'],
            '/about/'     => ['freq' => 'monthly', 'priority' => '0.9'],
            '/portfolio/' => ['freq' => 'weekly',  'priority' => '0.9'],
            '/blog/'      => ['freq' => 'weekly',  'priority' => '0.9'],
            '/q/'         => ['freq' => 'monthly', 'priority' => '0.7'],
        ];

        foreach ($staticPages as $path => $meta) {
            $entries[] = [
                'url'        => absoluteUrl($path),
                'lastmod'    => $today,
                'changefreq' => $meta['freq'],
                'priority'   => $meta['priority'],
            ];
        }

        // 2. Статьи блога (/blog/posts/*.json): только опубликованные и с валидным id.
        $blogDir = $siteDir . '/blog/posts/';
        if (is_dir($blogDir)) {
            $blogFiles = glob($blogDir . '*.json');
            if (is_array($blogFiles)) {
                sort($blogFiles, SORT_STRING);

                foreach ($blogFiles as $file) {
                    $json = @file_get_contents($file);
                    $data = $json ? json_decode($json, true) : null;

                    // Пропускаем битый JSON и неопубликованные (черновик) посты.
                    if (!is_array($data) || !isset($data['publish_date']) || $data['publish_date'] > $today) {
                        continue;
                    }

                    $identity = postIdentityFromFilename($file, $data);
                    if ($identity['id'] === '') {
                        // Невалидное имя файла: пропускаем.
                        continue;
                    }

                    $postPath = blogPostPath($identity);
                    if ($postPath === '') {
                        continue;
                    }

                    $entries[] = [
                        'url'        => absoluteUrl($postPath),
                        'lastmod'    => isset($data['date']) && $data['date'] !== ''
                            ? (string) $data['date']
                            : date('Y-m-d', (int) filemtime($file)),
                        'changefreq' => 'monthly',
                        'priority'   => '0.8',
                    ];
                }
            }
        }

        // 3. Кейсы портфолио (/portfolio/cases/portfolio*.json).
        $portfolioDir = $siteDir . '/portfolio/cases/';
        if (is_dir($portfolioDir)) {
            $portfolioFiles = glob($portfolioDir . 'portfolio*.json');
            if (is_array($portfolioFiles)) {
                sort($portfolioFiles, SORT_STRING);

                foreach ($portfolioFiles as $file) {
                    $json = @file_get_contents($file);
                    $data = $json ? json_decode($json, true) : null;

                    if (!is_array($data)) {
                        continue;
                    }

                    // portfolio0001 → 0001
                    $caseId = preg_replace('/\D/', '', pathinfo($file, PATHINFO_FILENAME));
                    if ($caseId === '') {
                        continue;
                    }

                    $entries[] = [
                        'url'        => absoluteUrl('/portfolio/' . $caseId . '/'),
                        'lastmod'    => date('Y-m-d', (int) filemtime($file)),
                        'changefreq' => 'monthly',
                        'priority'   => '0.7',
                    ];
                }
            }
        }

        return $entries;
    }
}

if (!function_exists('collectSiteUrls')) {
    /**
     * Только абсолютные URL (без query, без дублей, в порядке collectSiteEntries()).
     *
     * @param string|null $siteDir Корень сайта (по умолчанию — корень проекта)
     */
    function collectSiteUrls(?string $siteDir = null): array
    {
        $urls = [];

        foreach (collectSiteEntries($siteDir) as $entry) {
            $url = $entry['url'] ?? '';
            if ($url === '' || isset($urls[$url])) {
                continue;
            }
            $urls[$url] = true;
        }

        return array_keys($urls);
    }
}
