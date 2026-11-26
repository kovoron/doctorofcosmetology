<?php
require_once __DIR__ . '/../config/urls.php';
require_once __DIR__ . '/../lib/post-content.php';

$postId = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
$requestedSlug = isset($_GET['slug']) && is_string($_GET['slug']) ? $_GET['slug'] : '';
$postDir = __DIR__ . '/posts/';
$today = date('Y-m-d');

$post = null;
$loadError = null; // notfound | draft | broken
$postFile = null;

// id — строго 5 цифр; файл может называться {id}.json или {id}-{slug}.json
if (!preg_match('/^[0-9]{5}$/', $postId)) {
    $loadError = 'notfound';
} else {
    $exactFile = $postDir . $postId . '.json';

    if (is_file($exactFile)) {
        $postFile = $exactFile;
    } else {
        $candidates = glob($postDir . $postId . '-*.json');
        if (is_array($candidates) && $candidates !== []) {
            $normalizedRequestedSlug = normalizePostSlug($requestedSlug);

            if ($normalizedRequestedSlug !== '') {
                foreach ($candidates as $candidate) {
                    if (postSlugFromFilename($candidate) === $normalizedRequestedSlug) {
                        $postFile = $candidate;
                        break;
                    }
                }
            }

            if ($postFile === null) {
                sort($candidates, SORT_STRING);
                $postFile = $candidates[0];
            }
        }
    }

    if ($postFile === null) {
        $loadError = 'notfound';
    } else {
        $rawJson = file_get_contents($postFile);
        $decoded = is_string($rawJson) ? json_decode($rawJson, true) : null;
        if (!is_array($decoded)) {
            $loadError = 'broken';
        } else {
            $identity = postIdentityFromFilename($postFile, $decoded);
            if ($identity['id'] === '') {
                $loadError = 'broken';
            } else {
                $post = $decoded;
                $post['id'] = $identity['id'];
                $post['slug'] = $identity['slug'];
                $postId = $identity['id'];

                // Черновик: publish_date > today
                if (isset($post['publish_date']) && $post['publish_date'] > $today) {
                    $loadError = 'draft';
                    $post = null;
                }
            }
        }
    }
}

// 404/500: корректный статус, noindex, рендер через header/footer, без canonical
if ($loadError !== null) {
    http_response_code($loadError === 'broken' ? 500 : 404);
    $noindex = true;
    $canonical = '';

    if ($loadError === 'draft') {
        $errorHeading = 'Пост ещё не опубликован';
    } elseif ($loadError === 'broken') {
        $errorHeading = 'Ошибка загрузки поста';
    } else {
        $errorHeading = 'Пост не найден';
    }
    $pageTitle = $errorHeading . ' — врач-косметолог Ксения Якшигулова';

    include $_SERVER['DOCUMENT_ROOT'] . '/header.php';
    ?>
    <section class="post-page">
        <div class="container" style="padding:150px 0;text-align:center;">
            <h1><?= htmlspecialchars($errorHeading, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><a href="/blog/" style="color:#8B7355;text-decoration:none;">← Вернуться в блог</a></p>
        </div>
    </section>
    <?php
    include $_SERVER['DOCUMENT_ROOT'] . '/footer.php';
    exit;
}

// Канонический адрес статьи: /blog/{id}/ или /blog/{id}-{slug}/
// 301 одним хопом покрывает /blog/{id}, /blog/{id}/ при наличии slug, чужой slug и post.php?id=
$canonicalPath = blogPostPath($post);
if ($canonicalPath !== '' && rawurldecode($canonicalPath) !== requestPath()) {
    header('Location: ' . absoluteUrl($canonicalPath), true, 301);
    exit;
}
$canonical = $canonicalPath;

// Динамический заголовок страницы
$pageTitle = htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8') . ' — врач-косметолог Ксения Якшигулова';

// Подзаголовок и meta description — из excerpt; fallback на text только если excerpt пуст
$postExcerpt = isset($post['excerpt']) && is_string($post['excerpt']) ? trim($post['excerpt']) : '';
if ($postExcerpt === '') {
    $plainText = trim(strip_tags($post['text'] ?? ''));
    $postExcerpt = mb_substr($plainText, 0, 200);
    if (mb_strlen($plainText, 'UTF-8') > 200) {
        $postExcerpt .= '…';
    }
}
$pageDescription = $postExcerpt;

// Форматируем дату и время чтения
$formattedDate = date('d M Y', strtotime($post['date'] ?? 'now'));
$readingTime = isset($post['reading_time']) ? $post['reading_time'] : '5';

// Проверяем изображения
$validImages = [];
if (!empty($post['images'])) {
    foreach ($post['images'] as $img) {
        if (file_exists(__DIR__ . '/images/' . $img)) {
            $validImages[] = '/blog/images/' . $img;
        }
    }
}

// Главное изображение: первая существующая из JSON, иначе post-{ID}-1.jpg
$thumbnail = $validImages[0] ?? null;
if ($thumbnail === null) {
    $mainImageName = 'post-' . $postId . '-1.jpg';
    $mainImagePath = $_SERVER['DOCUMENT_ROOT'] . '/blog/images/' . $mainImageName;
    $thumbnail = file_exists($mainImagePath) ? '/blog/images/' . $mainImageName : null;
}

// Категория
$categoryMap = [
    'procedury' => 'ПРОЦЕДУРЫ',
    'uhod-za-kozhey' => 'УХОД ЗА КОЖЕЙ',
    'profilaktika' => 'ПРОФИЛАКТИКА',
    'preparaty' => 'ПРЕПАРАТЫ',
    'obraz-zhizni' => 'ОБРАЗ ЖИЗНИ'
];
$categoryName = isset($post['category']) && isset($categoryMap[$post['category']]) 
    ? $categoryMap[$post['category']] 
    : 'ПРОЦЕДУРЫ';

// =========================================
// ПОДБОР УСЛУГИ ПО ТЕГАМ СТАТЬИ
// =========================================
$bestService = null;
$maxMatches = 0;
$servicesPath = $_SERVER['DOCUMENT_ROOT'] . '/uslugi.json';

if (file_exists($servicesPath) && !empty($post['tags'])) {
    $servicesData = json_decode(file_get_contents($servicesPath), true);
    if (!empty($servicesData['services'])) {
        $articleTags = array_map('strtolower', $post['tags']);
        foreach ($servicesData['services'] as $service) {
            if (!empty($service['tags'])) {
                $serviceTags = array_map('strtolower', $service['tags']);
                $commonTags = array_intersect($articleTags, $serviceTags);
                $matchCount = count($commonTags);
                if ($matchCount > $maxMatches) {
                    $maxMatches = $matchCount;
                    $bestService = $service;
                }
            }
        }
    }
}

// =========================================
// ПОДБОР ПОРТФОЛИО ПО ТЕГАМ СТАТЬИ
// =========================================
$portfolioMatches = [];
$portfolioDir = $_SERVER['DOCUMENT_ROOT'] . '/portfolio/cases/';
$currentTags = !empty($post['tags']) ? array_map('strtolower', $post['tags']) : [];

if (!empty($currentTags) && is_dir($portfolioDir)) {
    $portfolioFiles = glob($portfolioDir . 'portfolio*.json');
    foreach ($portfolioFiles as $pFile) {
        $pJson = file_get_contents($pFile);
        $pData = json_decode($pJson, true);
        if ($pData && !empty($pData['tags'])) {
            $pTags = array_map('strtolower', $pData['tags']);
            $commonTags = array_intersect($currentTags, $pTags);
            if (!empty($commonTags)) {
                $portfolioMatches[] = [
                    'case_id' => preg_replace('/\D/', '', pathinfo($pFile, PATHINFO_FILENAME)),
                    'id'      => pathinfo($pFile, PATHINFO_FILENAME),
                    'name'    => $pData['procedure_name'],
                    'tags'    => $pData['tags'],
                    'images'  => $pData['images'],
                    'score'   => count($commonTags)
                ];
            }
        }
    }
    usort($portfolioMatches, function($a, $b) { return $b['score'] - $a['score']; });
    $portfolioMatches = array_slice($portfolioMatches, 0, 3);
}

// =========================================
// ВСТАВКА 2-го и 3-го ИЗОБРАЖЕНИЙ В ТЕКСТ
// =========================================
$articleContent = $post['text'] ?? '';
$images = $post['images'] ?? [];
$imgDir = '/blog/images/';

$img2 = (!empty($images[1]) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgDir . $images[1])) ? $images[1] : null;
$img3 = (!empty($images[2]) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgDir . $images[2])) ? $images[2] : null;

if ($img2 || $img3) {
    $paragraphs = explode('</p>', $articleContent);
    $total = count($paragraphs);
    
    $createImgHtml = function($src) use ($imgDir) {
        return '</p><p><img src="' . $imgDir . $src . '" alt="Иллюстрация к статье" loading="lazy"></p>';
    };
    
    // Вставка 3-го изображения перед последним абзацем
    if ($img3 && $total > 1) {
        $insertPos = $total - 1;
        if (trim($paragraphs[$insertPos]) === '') $insertPos = $total - 2;
        if ($insertPos >= 0) $paragraphs[$insertPos] .= $createImgHtml($img3);
    }
    
    // Вставка 2-го изображения (~посередине)
    if ($img2 && $total > 2) {
        $midIndex = (int)floor($total / 2);
        if ($midIndex >= $total) $midIndex = $total - 1;
        if ($midIndex < 1) $midIndex = 1;
        $paragraphs[$midIndex] = $createImgHtml($img2) . $paragraphs[$midIndex];
    }
    
    $articleContent = implode('</p>', $paragraphs);
    $articleContent = str_replace(['<p></p>', '<p> </p>'], '', $articleContent);
}

// Таблицы из JSON — в горизонтально скроллируемую обёртку (TASK.md, раздел 6)
$articleContent = wrapPostTables($articleContent);

// =========================================
// ПОДБОР ПОХОЖИХ СТАТЕЙ ПО ТЕГАМ
// =========================================
$currentRealPath = $postFile !== null ? realpath($postFile) : false;

$allPosts = [];
if (is_dir($postDir)) {
    $files = scandir($postDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') {
            continue;
        }

        $filePath = $postDir . $file;

        // Исключаем текущую статью по реальному пути, а не по шаблону имени
        if ($currentRealPath !== false && realpath($filePath) === $currentRealPath) {
            continue;
        }

        $jsonData = file_get_contents($filePath);
        $postData = json_decode($jsonData, true);
        if (is_array($postData) && isset($postData['publish_date']) && $postData['publish_date'] <= $today) {
            $otherIdentity = postIdentityFromFilename($filePath, $postData);
            if ($otherIdentity['id'] === '') {
                continue;
            }
            $postData['id'] = $otherIdentity['id'];
            $postData['slug'] = $otherIdentity['slug'];
            $allPosts[] = $postData;
        }
    }
}

$similarPosts = [];
if (!empty($post['tags']) && !empty($allPosts)) {
    foreach ($allPosts as $otherPost) {
        if (!empty($otherPost['tags'])) {
            $commonTags = array_intersect($post['tags'], $otherPost['tags']);
            if (!empty($commonTags)) {
                $thumbUrl = '/blog/images/post-' . $otherPost['id'] . '-1.jpg';
                $similarPosts[] = [
                    'id'        => $otherPost['id'],
                    'slug'      => $otherPost['slug'] ?? '',
                    'title'     => $otherPost['title'],
                    'date'      => $otherPost['date'],
                    'images'    => [$thumbUrl],
                    'score'     => count($commonTags),
                    'common_tags' => $commonTags
                ];
            }
        }
    }
    usort($similarPosts, function($a, $b) { return $b['score'] - $a['score']; });
    $similarPosts = array_slice($similarPosts, 0, 4);
}

include $_SERVER['DOCUMENT_ROOT'] . '/header.php';
?>
<article class="post-page">
    <!-- Header поста -->
    <div class="post-header">
        <div class="container">
            <div class="post-header__content">
                <div class="post-header__top">
                    <div class="post-header__left">
                        <span class="post-header__category"><?= $categoryName ?></span>
                        <h1 class="post-header__title"><?= htmlspecialchars($post['title']) ?></h1>
                        <p class="post-header__excerpt">
                            <?= htmlspecialchars($postExcerpt, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <div class="post-header__meta">
                            <time datetime="<?= htmlspecialchars($post['date']) ?>">
                                <?= $formattedDate ?>
                            </time>
                            <span class="post-header__separator">•</span>
                            <span class="post-header__reading"><?= $readingTime ?> мин чтения</span>
                        </div>
                        <div class="post-header__share">
                            <span>ПОДЕЛИТЬСЯ:</span>
                            <div class="post-header__share-buttons">
                                <a href="#" class="post-header__share-btn" aria-label="Telegram">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path>
                                    </svg>
                                </a>
                                <a href="#" class="post-header__share-btn" aria-label="Instagram">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                                    </svg>
                                </a>
                                <a href="#" class="post-header__share-btn" aria-label="WhatsApp">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="post-header__image">
                        <?php if ($thumbnail !== null): ?>
                        <img src="<?= htmlspecialchars($thumbnail) ?>" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Основной контент -->
    <div class="post-content-wrapper">
        <div class="container">
            <div class="post-layout">
                <div class="post-main">
                    <div class="post-body">
                        <?= $articleContent ?>
                    </div>

                    <!-- CTA блок с динамической услугой -->
<div class="post-cta">
    <div class="post-cta__content">
        <?php if ($bestService && $maxMatches > 0): ?>
            <span class="post-cta__label">Рекомендуемая процедура:</span>
            <h3 class="post-cta__title"><?= htmlspecialchars($bestService['name']) ?></h3>
            <p class="post-cta__text">
                Стоимость: <strong><?= htmlspecialchars($bestService['price']) ?></strong>.<br>
                Подберу индивидуальный курс и удобное время для проведения процедуры.
            </p>
        <?php else: ?>
            <h3 class="post-cta__title">Хотите улучшить качество кожи?</h3>
            <p class="post-cta__text">Подберу индивидуальный уход и курс процедур для вашей кожи.</p>
        <?php endif; ?>
        
        <a href="https://max.ru/u/f9LHodD0cOLGlvkkywieaju4QXn5XPH-B_dFHU-2UB0kocaZvHQneo71bXc" class="post-cta__btn" target="_blank">
            Напишите мне напрямую, отвечу всем
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </a>
    </div>
</div>
                </div>

                <!-- Sidebar -->
                <aside class="post-sidebar">
                    <!-- О блоге -->
                    <div class="post-widget">
                        <div class="post-widget__icon">
                            <svg width="40" height="40" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M24 4C24 4 12 16 12 28C12 36.8366 19.1634 44 28 44C36.8366 44 44 36.8366 44 28C44 16 32 4 32 4" stroke="#8B7355" stroke-width="2" stroke-linecap="round"/>
                                <path d="M24 4C24 4 36 16 36 28" stroke="#8B7355" stroke-width="2" stroke-linecap="round"/>
                                <path d="M18 24L24 30L30 24" stroke="#8B7355" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3 class="post-widget__title">ПРОФЕССИОНАЛЬНО<br>О КРАСОТЕ</h3>
                        <div class="post-widget__divider"></div>
                        <p class="post-widget__text">
                            Проверенная информация<br>
                            о процедурах, уходе и здоровье<br>
                            кожи от врача-косметолога
                        </p>
                        <a href="/q/" class="post-widget__btn">
                            ЗАДАТЬ ВОПРОС
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>

                    <!-- Подписка -->
                    <div class="post-widget post-widget--subscribe">
                        <h3 class="post-widget__title">ПОЛЕЗНОЕ<br>В ВАШЕЙ ПОЧТЕ</h3>
                        <p class="post-widget__text">
                            Подпишитесь на рассылку<br>
                            и получайте подборки<br>
                            статей и рекомендации
                        </p>
                        <form class="post-subscribe">
                            <input type="email" placeholder="Ваш e-mail" class="post-subscribe__input" required>
                            <button type="submit" class="post-subscribe__btn">
                                ПОДПИСАТЬСЯ
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                </svg>
                            </button>
                        </form>
                    </div>

                    <!-- Популярные статьи -->
                    <?php if (!empty($similarPosts)): ?>
                    <div class="post-widget post-widget--popular">
                        <h3 class="post-widget__title">ПОПУЛЯРНЫЕ<br>СТАТЬИ</h3>
                        <div class="post-popular">
                            <?php foreach ($similarPosts as $similar): 
                                $similarImage = null;
                                if (!empty($similar['images']) && file_exists(__DIR__ . str_replace('/blog', '', $similar['images'][0]))) {
                                    $similarImage = $similar['images'][0];
                                }
                            ?>
                            <a href="<?= htmlspecialchars(blogPostPath($similar), ENT_QUOTES, 'UTF-8') ?>" class="post-popular__item">
                                <?php if ($similarImage): ?>
                                    <img src="<?= htmlspecialchars($similarImage) ?>" alt="<?= htmlspecialchars($similar['title']) ?>" class="post-popular__image" loading="lazy">
                                <?php else: ?>
                                    <div class="post-popular__image post-popular__image--placeholder"></div>
                                <?php endif; ?>
                                <div class="post-popular__content">
                                    <span class="post-popular__title"><?= htmlspecialchars($similar['title']) ?></span>
                                    <time><?= date('d M Y', strtotime($similar['date'])) ?></time>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Портфолио по теме -->
                    <?php if (!empty($portfolioMatches)): ?>
                    <div class="post-widget">
                        <h3 class="post-widget__title">ПОРТФОЛИО<br>ПО ТЕМЕ</h3>
                        <div class="portfolio-matches">
                            <?php foreach ($portfolioMatches as $match): 
                                $thumbPath = !empty($match['images']['before']) ? '/portfolio/images/' . $match['images']['before'] : '';
                            ?>
                            <a href="/portfolio/<?= htmlspecialchars($match['case_id']) ?>/" class="portfolio-match-item">
                                <?php if ($thumbPath): ?>
                                    <img src="<?= htmlspecialchars($thumbPath) ?>" alt="<?= htmlspecialchars($match['name']) ?>" class="portfolio-match__img" loading="lazy">
                                <?php endif; ?>
                                <div class="portfolio-match__info">
                                    <span class="portfolio-match__name"><?= htmlspecialchars($match['name']) ?></span>
                                    <span class="portfolio-match__tags"><?= htmlspecialchars(implode(', ', array_slice($match['tags'], 0, 2))) ?></span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>
</article>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>