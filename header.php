<?php
require_once __DIR__ . '/config/site.php';
require_once __DIR__ . '/config/urls.php';

$requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

// 404, черновики, служебные разделы — noindex без canonical
$isNoindex = !empty($noindex);

// canonical не выводим для noindex и для прямых запросов post.php:
// там страница закрывает переход 301 до вывода шапки.
$canonicalHref = '';
if (!$isNoindex && stripos($requestUri, 'post.php') === false) {
    if (!isset($canonical) || !is_string($canonical) || trim($canonical) === '') {
        error_log('header.php: $canonical не задан для ' . $requestUri . ' — использую requestPath()');
        $canonical = requestPath();
    }
    $canonicalHref = absoluteUrl($canonical);
}

$descriptionText = '';
if (isset($pageDescription) && is_string($pageDescription)) {
    $descriptionText = trim($pageDescription);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Ксения Якшигулова — врач-косметолог, дерматовенеролог'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <link rel="manifest" href="/site.webmanifest">
<?php if ($canonicalHref !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalHref, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php if ($isNoindex): ?>
    <meta name="robots" content="noindex, follow">
<?php endif; ?>
<?php if ($descriptionText !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($descriptionText, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
</head>
<body>
<header class="header" id="header">
    <div class="header__container">
        <a href="/" class="header__logo">
            <div class="header__logo-icon">КЯ</div>
            <div class="header__logo-text">
                <span class="header__logo-name">Ксения Якшигулова</span>
                <span class="header__logo-subtitle">врач-косметолог</span>
            </div>
        </a>
        
        <nav class="header__nav">
            <a href="/about/" class="header__link">О ВРАЧЕ</a>
            <a href="/portfolio/" class="header__link">ПОРТФОЛИО</a>
            <a href="/blog/" class="header__link">БЛОГ</a>
            <a href="/q/" class="header__link">ЗАДАТЬ ВОПРОС</a>
        </nav>

        <a href="https://max.ru/u/f9LHodD0cOLGlvkkywieaju4QXn5XPH-B_dFHU-2UB0kocaZvHQneo71bXc" class="header__btn" target="_blank">
            НАПИШИТЕ В МАКС
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </a>

        <!-- Бургер кнопка -->
        <button class="header__burger" aria-label="Меню">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>

<!-- Мобильное меню -->
<div class="mobile-menu">
    <button class="mobile-menu__close">&times;</button>
    <a href="/about/" class="mobile-menu__link">О ВРАЧЕ</a>
    <a href="/portfolio/" class="mobile-menu__link">ПОРТФОЛИО</a>
    <a href="/blog/" class="mobile-menu__link">БЛОГ</a>
    <a href="/q/" class="mobile-menu__link">ЗАДАТЬ ВОПРОС</a>
    <a href="https://max.ru/u/f9LHodD0cOLGlvkkywieaju4QXn5XPH-B_dFHU-2UB0kocaZvHQneo71bXc" class="header__btn" target="_blank">
        ЗАПИСАТЬСЯ НА ПРИЕМ
    </a>
</div>