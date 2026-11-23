<?php 
$pageTitle = 'Портфолио работ врача-косметолога. Процедуры, которые делаю каждый день. Ксения Якшигулова';

include $_SERVER['DOCUMENT_ROOT'] . '/header.php'; ?>

<?php
// Настройки путей
$casesDir   = __DIR__ . '/cases/';
$imagesDir  = '/portfolio/images/';
$cases      = [];

// Чтение JSON-файлов
if (is_dir($casesDir)) {
    $files = glob($casesDir . 'portfolio*.json');
    natsort($files);                // Сортировка: portfolio0001, portfolio0002...
    $files = array_reverse($files); // Новые кейсы сверху

    foreach ($files as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if ($data) {
            $data['id'] = preg_replace('/\D/', '', pathinfo($file, PATHINFO_FILENAME));
            $cases[] = $data;
        }
    }
}
?>
<meta name="description" content="Порфолио работ врача-косметолога с красным дипломом и опытом с 2011 года. Контурная пластика и Ботокс в Спб, Приморский район м. Беговая. Обучалась в Австрии, Испании, Турции, Кипре и России">

<section class="portfolio-page">
    <div class="container">
        <h1 class="portfolio-title">ПОРТФОЛИО РАБОТ ВРАЧА КСЕНИИ ЯКШИГУЛОВОЙ</h1>
        <p class="portfolio-subtitle">Реальные результаты до и после процедур</p>

        <?php if (!empty($cases)): ?>
            <div class="portfolio-grid">
                <?php foreach ($cases as $case): ?>
                    <?php
                    // Проверка наличия изображений на сервере
                    $beforePath = $_SERVER['DOCUMENT_ROOT'] . $imagesDir . $case['images']['before'];
                    $afterPath  = $_SERVER['DOCUMENT_ROOT'] . $imagesDir . $case['images']['after'];
                    $hasBefore  = @file_exists($beforePath);
                    $hasAfter   = @file_exists($afterPath);
                    ?>
                    <article class="portfolio-card">
                        <div class="portfolio-card__images">
                            <div class="portfolio-card__img-wrap">
                                <span class="portfolio-label">ДО</span>
                                <?php if ($hasBefore): ?>
                                    <img src="<?= htmlspecialchars($imagesDir . $case['images']['before']) ?>" alt="До процедуры" loading="lazy">
                                <?php else: ?>
                                    <div class="portfolio-card__placeholder">Нет фото</div>
                                <?php endif; ?>
                            </div>
                            <div class="portfolio-card__img-wrap">
                                <span class="portfolio-label portfolio-label--after">ПОСЛЕ</span>
                                <?php if ($hasAfter): ?>
                                    <img src="<?= htmlspecialchars($imagesDir . $case['images']['after']) ?>" alt="После процедуры" loading="lazy">
                                <?php else: ?>
                                    <div class="portfolio-card__placeholder">Нет фото</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="portfolio-card__info">
                            <h3 class="portfolio-card__title"><?= htmlspecialchars($case['procedure_name']) ?></h3>
                            <p class="portfolio-card__desc"><?= nl2br(htmlspecialchars($case['description'])) ?></p>
                            
                            <?php if (!empty($case['tags'])): ?>
                            <div class="portfolio-card__tags">
                                <?php foreach ($case['tags'] as $tag): ?>
                                    <span class="portfolio-tag"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="portfolio-empty">
                <p>Пока в портфолио нет работ. Следите за обновлениями!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
/* =========================================
   PORTFOLIO PAGE STYLES
   ========================================= */
.portfolio-page {
    padding: 120px 0 80px;
    background: #F9F7F4;
    min-height: 100vh;
}

.portfolio-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 56px;
    text-align: center;
    margin-bottom: 10px;
    color: #2C2420;
    letter-spacing: 1px;
}

.portfolio-subtitle {
    text-align: center;
    color: #8B7355;
    margin-bottom: 60px;
    font-family: 'Montserrat', sans-serif;
    font-size: 16px;
}

.portfolio-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 40px;
}

.portfolio-card {
    background: #FFFFFF;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.portfolio-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

.portfolio-card__images {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2px;
    background: #EBE5DE;
}

.portfolio-card__img-wrap {
    position: relative;
    aspect-ratio: 4 / 5;
    overflow: hidden;
}

.portfolio-card__img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.portfolio-card__placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #D4C8B8;
    color: #2C2420;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
}

.portfolio-label {
    position: absolute;
    top: 12px;
    left: 12px;
    background: rgba(255,255,255,0.95);
    padding: 5px 10px;
    border-radius: 6px;
    font-family: 'Montserrat', sans-serif;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    z-index: 2;
}

.portfolio-label--after {
    background: #8B7355;
    color: #fff;
}

.portfolio-card__info {
    padding: 30px;
}

.portfolio-card__title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 26px;
    color: #2C2420;
    margin: 0 0 12px;
    font-weight: 600;
}

.portfolio-card__desc {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    line-height: 1.6;
    color: #5A4A42;
    margin: 0 0 20px;
}

.portfolio-card__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.portfolio-tag {
    font-family: 'Montserrat', sans-serif;
    font-size: 11px;
    padding: 6px 12px;
    background: #F5F1ED;
    color: #8B7355;
    border-radius: 15px;
    transition: background 0.3s ease, color 0.3s ease;
}

.portfolio-tag:hover {
    background: #8B7355;
    color: #fff;
}

.portfolio-empty {
    text-align: center;
    padding: 80px 20px;
    color: #888;
    font-size: 18px;
    font-family: 'Montserrat', sans-serif;
}

/* Адаптивность */
@media (max-width: 968px) {
    .portfolio-grid {
        grid-template-columns: 1fr;
    }
    .portfolio-title {
        font-size: 42px;
    }
    .portfolio-subtitle {
        margin-bottom: 40px;
    }
}
</style>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>