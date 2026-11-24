<?php
require_once __DIR__ . '/../config/urls.php';

$postsDir = __DIR__ . '/posts/';
$postsPerPage = 5;

// Получаем текущую страницу и категорию
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$currentCategory = isset($_GET['category']) ? $_GET['category'] : '';

// Читаем все JSON файлы
$allPosts = [];
if (is_dir($postsDir)) {
    $files = scandir($postsDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $filePath = $postsDir . $file;
            $json = file_get_contents($filePath);
            $data = json_decode($json, true);
            if ($data) {
                $data['id'] = pathinfo($file, PATHINFO_FILENAME);
                $allPosts[] = $data;
            }
        }
    }
}

// Фильтруем по дате публикации и категории
$today = date('Y-m-d');
$validPosts = array_filter($allPosts, function($post) use ($today, $currentCategory) {
    $dateOk = isset($post['publish_date']) && $post['publish_date'] <= $today;
    $categoryOk = empty($currentCategory) || 
                  (isset($post['category']) && $post['category'] === $currentCategory);
    return $dateOk && $categoryOk;
});

// Сортируем по дате (сначала свежие)
usort($validPosts, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Пагинация
$totalPosts = count($validPosts);
$totalPages = ceil($totalPosts / $postsPerPage);
$offset = ($page - 1) * $postsPerPage;
$posts = array_slice($validPosts, $offset, $postsPerPage);

// Категории для фильтра
$categories = [
    '' => 'ВСЕ СТАТЬИ',
    'uhod-za-kozhey' => 'УХОД ЗА КОЖЕЙ',
    'procedury' => 'ПРОЦЕДУРЫ',
    'profilaktika' => 'ПРОФИЛАКТИКА',
    'preparaty' => 'ПРЕПАРАТЫ',
    'obraz-zhizni' => 'ОБРАЗ ЖИЗНИ'
];

$pageTitle = 'Личный блог Врача-косметолога где я пишу про перпараты и процедуры, которые делаю каждый день. Врача-косметолог Ксения Якшигулова';

// canonical списка всегда /blog/, включая ?category= и ?page=
$canonical = '/blog/';
$pageDescription = 'Блог Врача-косметолог с красным дипломом и опытом с 2011 года. Пишу о работе без прикрас. Обучалась в Австрии, Испании, Турции, Кипре и России. Ксения Юрьевна Якшигулова.';

include $_SERVER['DOCUMENT_ROOT'] . '/header.php';
?>


<section class="blog-page">
    <div class="container" style="
    margin-left: 30px;
    margin-right: 30px;"
>
        <!-- Фильтры категорий -->
        <div class="blog-filters">
            <div class="blog-categories">
                <?php foreach ($categories as $slug => $name): ?>
                <a href="/blog/?category=<?= $slug ?>" 
                   class="blog-category <?= $currentCategory === $slug ? 'active' : '' ?>">
                    <?= $name ?>
                </a>
                <?php endforeach; ?>
            </div>
            
            <div class="blog-search">
                <input type="text" placeholder="Поиск по статьям" class="blog-search__input">
                <button class="blog-search__btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="blog-layout">
            <!-- Список постов -->
            <div class="blog-posts">
                <?php foreach ($posts as $post): ?>
                <?php
                // Проверяем изображения
                $validImages = [];
                if (!empty($post['images'])) {
                    foreach ($post['images'] as $img) {
                        if (file_exists(__DIR__ . '/images/' . $img)) {
                            $validImages[] = '/blog/images/' . $img;
                        }
                    }
                }
        $thumbnail = '/blog/images/post-' . htmlspecialchars($post['id']) . '-1.jpg';

                // Анонс: excerpt из JSON; обрезанный текст — только если excerpt пуст
                $excerpt = isset($post['excerpt']) && is_string($post['excerpt']) ? trim($post['excerpt']) : '';
                if ($excerpt === '') {
                    $plainText = trim(strip_tags($post['text'] ?? ''));
                    $excerpt = mb_substr($plainText, 0, 180);
                    if (mb_strlen($plainText, 'UTF-8') > 180) {
                        $excerpt .= '…';
                    }
                }

                // Канонический путь карточки: /blog/{id}/ или /blog/{id}-{slug}/
                $postPath = blogPostPath($post);
                
                // Форматируем дату
                $formattedDate = date('d M Y', strtotime($post['date']));
                
                // Получаем название категории
                $categoryName = isset($post['category']) && isset($categories[$post['category']]) 
                    ? $categories[$post['category']] 
                    : 'ПРОЦЕДУРЫ';
                ?>
                
                <article class="blog-post-card">
                    <div class="blog-post-card__image">
                        <img src="<?= htmlspecialchars($thumbnail) ?>" 
                             alt="<?= htmlspecialchars($post['title']) ?>" 
                             loading="lazy">
                    </div>
                    
                    <div class="blog-post-card__content">
                        <div class="blog-post-card__category"><?= $categoryName ?></div>
                        
                        <h2 class="blog-post-card__title">
                            <a href="<?= htmlspecialchars($postPath, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                        </h2>
                        
                        <div class="blog-post-card__excerpt">
                            <?= htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        
                        <div class="blog-post-card__meta">
                            <time datetime="<?= htmlspecialchars($post['date']) ?>">
                                <?= $formattedDate ?>
                            </time>
                            <span class="blog-post-card__separator">•</span>
                            <span class="blog-post-card__reading-time">
                                <?= isset($post['reading_time']) ? $post['reading_time'] : '5' ?> мин чтения
                            </span>
                        </div>
                        
                        <a href="<?= htmlspecialchars($postPath, ENT_QUOTES, 'UTF-8') ?>" class="blog-post-card__btn">
                            ЧИТАТЬ
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
                
                <?php if ($totalPages > 1): ?>
                <div class="blog-load-more">
                    <?php if ($page < $totalPages): ?>
                    <a href="/blog/?page=<?= $page + 1 ?><?php echo $currentCategory ? '&category='.$currentCategory : '' ?>" 
                       class="blog-load-more__btn">
                        ЗАГРУЗИТЬ ЕЩЕ СТАТЬИ
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Сайдбар -->
            <aside class="blog-sidebar">
                <!-- О блоге -->
                <div class="blog-widget blog-widget--about">
                    <div class="blog-widget__icon">
                        <svg width="40" height="40" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M24 4C24 4 12 16 12 28C12 36.8366 19.1634 44 28 44C36.8366 44 44 36.8366 44 28C44 16 32 4 32 4" stroke="#8B7355" stroke-width="2" stroke-linecap="round"/>
                            <path d="M24 4C24 4 36 16 36 28" stroke="#8B7355" stroke-width="2" stroke-linecap="round"/>
                            <path d="M18 24L24 30L30 24" stroke="#8B7355" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="blog-widget__title">ПРОФЕССИОНАЛЬНО<br>О КРАСОТЕ</h3>
                    <div class="blog-widget__divider"></div>
                    <p class="blog-widget__text">
                        Проверенная информация<br>
                        о процедурах, уходе и здоровье<br>
                        кожи от врача-косметолога
                    </p>
                    <a href="/q/" class="blog-widget__btn">
                        ЗАДАТЬ ВОПРОС
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>

                <!-- Подписка -->
                <div class="blog-widget blog-widget--subscribe">
                    <h3 class="blog-widget__title">ПОЛЕЗНОЕ<br>В ВАШЕЙ ПОЧТЕ</h3>
                    <p class="blog-widget__text">
                        Подпишитесь на рассылку<br>
                        и получайте подборки<br>
                        статей и рекомендации
                    </p>
                    <form class="blog-subscribe">
                        <input type="email" placeholder="Ваш e-mail" class="blog-subscribe__input" required>
                        <button type="submit" class="blog-subscribe__btn">
                            ПОДПИСАТЬСЯ
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                    </form>
                </div>

                <!-- Популярные темы -->
                <div class="blog-widget blog-widget--tags">
                    <h3 class="blog-widget__title">ПОПУЛЯРНЫЕ<br>ТЕМЫ</h3>
                    <div class="blog-tags">
                        <a href="#" class="blog-tag">антивозрастной уход</a>
                        <a href="#" class="blog-tag">увлажнение кожи</a>
                        <a href="#" class="blog-tag">акне и постакне</a>
                        <a href="#" class="blog-tag">пилинги</a>
                        <a href="#" class="blog-tag">ботулинотерапия</a>
                        <a href="#" class="blog-tag">лазерные методики</a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>