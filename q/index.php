<?php
session_start();
$pageTitle = 'Вопросы для врача-косметолога. Можно анонимно. Любая тема.';
$canonical = '/q/';

include $_SERVER['DOCUMENT_ROOT'] . '/header.php';

$questionsDir = __DIR__ . '/questions/';
$publishedQAs = [];

// Чтение и фильтрация опубликованных вопросов
if (is_dir($questionsDir)) {
    $files = glob($questionsDir . 'q*.json');
    $allQAs = [];
    
    foreach ($files as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if ($data && isset($data['published']) && $data['published'] === true) {
            $allQAs[] = $data;
        }
    }

    // Сортировка: новые сверху (по ID)
    usort($allQAs, function($a, $b) {
        return strcmp($b['id'], $a['id']);
    });

    // Берём только 5 последних
    $publishedQAs = array_slice($allQAs, 0, 5);
}

$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<section class="ask-question-page">
    <div class="container">
        <h1 class="page-title">Задать вопрос врачу</h1>
        <p class="page-subtitle">Напишите ваш вопрос, и Ксения ответит на него в ближайшее время</p>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?= $successMsg ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><?= $errorMsg ?></div>
        <?php endif; ?>

        <form class="qa-form" action="/q/process.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Ваше имя *</label>
                <input type="text" id="name" name="name" required placeholder="Как к вам обращаться?">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Телефон</label>
                    <input type="tel" id="phone" name="phone" placeholder="+7 (___) ___-__-__" style="width: 320px;">
                </div>
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="example@mail.ru">
                </div>
            </div>

            <div class="form-group">
                <label for="question">Ваш вопрос *</label>
                <textarea id="question" name="question" rows="5" required placeholder="Опишите вашу ситуацию или вопрос..."></textarea>
            </div>

            <div class="form-group">
                <label for="photos">Прикрепить фото (до 4 шт., необязательно)</label>
                <input type="file" id="photos" name="photos[]" multiple accept="image/*">
            </div>

            <button type="submit" class="btn-submit">Задать вопрос</button>
        </form>

        <!-- Опубликованные вопросы и ответы -->
        <?php if (!empty($publishedQAs)): ?>
        <div class="published-qa">
            <h2 class="section-title">Ответы на вопросы</h2>
            <div class="qa-list">
                <?php foreach ($publishedQAs as $qa): ?>
                <article class="qa-item">
                    <div class="qa-question">
                        <span class="qa-author"><?= htmlspecialchars($qa['name']) ?></span>
                        <time><?= date('d.m.Y', strtotime($qa['date'])) ?></time>
                        <p><?= $qa['question'] ?></p>
                        
                        <?php if (!empty($qa['images'])): ?>
                        <div class="qa-images">
                            <?php foreach ($qa['images'] as $img): ?>
                            <img src="/q/images/<?= htmlspecialchars($img) ?>" alt="Фото к вопросу" loading="lazy">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($qa['answer'])): ?>
                    <div class="qa-answer">
                        <span class="qa-doctor">Ксения Якшигулова</span>
                        <p><?= $qa['answer'] ?></p>
                    </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>