<?php
require_once __DIR__ . '/../config/urls.php';

// ID кейса из URL (например, /portfolio/0003 → 0003)
$caseId = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
$caseData = null;

// Валидация id: только 1–5 цифр; имя файла собирается строго по шаблону portfolio{id}.json
if (preg_match('/^[0-9]{1,5}$/', $caseId)) {
    $caseFile = __DIR__ . '/cases/portfolio' . $caseId . '.json';
    if (is_file($caseFile)) {
        $rawJson = file_get_contents($caseFile);
        $decoded = is_string($rawJson) ? json_decode($rawJson, true) : null;
        if (is_array($decoded)) {
            $caseData = $decoded;
        }
    }
}

$caseFound = $caseData !== null;
$imagesDir = '/portfolio/images/';
$canonical = $caseFound ? '/portfolio/' . $caseId . '/' : '';

// Один канонический URL кейса: /portfolio/{id}/ (покрывает /portfolio/0003 и case.php?id=0003)
if ($caseFound && requestPath() !== $canonical) {
    header('Location: ' . absoluteUrl($canonical), true, 301);
    exit;
}

// Динамический заголовок страницы (сработает в header.php)
if ($caseFound) {
    $pageTitle = htmlspecialchars($caseData['procedure_name'] ?? 'Кейс') . ' — Ксения Якшигулова';
} else {
    http_response_code(404);
    $noindex = true;
    $pageTitle = 'Кейс не найден — Ксения Якшигулова';
}

// Загружаем хедер
include $_SERVER['DOCUMENT_ROOT'] . '/header.php';

// Несуществующий кейс: единый рендер через header/footer, без canonical
if (!$caseFound) {
    ?>
    <section class="post-page">
        <div class="container" style="padding:150px 0;text-align:center;">
            <h1>Кейс не найден</h1>
            <p><a href="/portfolio/" style="color:#8B7355;text-decoration:none;">← Вернуться в портфолио</a></p>
        </div>
    </section>
    <?php
    include $_SERVER['DOCUMENT_ROOT'] . '/footer.php';
    exit;
}
?>

<section class="portfolio-case-page">
    <div class="container">
        <a href="/portfolio/" class="case-back">← Назад в портфолио</a>
        
        <header class="case-header">
            <h1 class="case-title"><?= htmlspecialchars($caseData['procedure_name']) ?></h1>
            <div class="case-tags">
                <?php foreach (($caseData['tags'] ?? []) as $tag): ?>
                    <span class="case-tag"><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </div>
        </header>

        <div class="case-images">
            <div class="case-image-card">
                <span class="case-label">ДО</span>
                <img src="<?= htmlspecialchars($imagesDir . ($caseData['images']['before'] ?? '')) ?>" alt="До процедуры" loading="lazy">
            </div>
            <div class="case-image-card">
                <span class="case-label case-label--after">ПОСЛЕ</span>
                <img src="<?= htmlspecialchars($imagesDir . ($caseData['images']['after'] ?? '')) ?>" alt="После процедуры" loading="lazy">
            </div>
        </div>

        <div class="case-description">
            <h2>Описание процедуры</h2>
            <p><?= nl2br(htmlspecialchars($caseData['description'] ?? '')) ?></p>
        </div>

        <div class="case-cta">
            <p>Хотите получить похожий результат?</p>
            <a href="https://max.ru/u/f9LHodD0cOLGlvkkywieaju4QXn5XPH-B_dFHU-2UB0kocaZvHQneo71bXc" class="case-btn" target="_blank">
                ЗАПИСАТЬСЯ НА КОНСУЛЬТАЦИЮ
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </a>
        </div>
    </div>
</section>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>