<?php
session_start();

$questionsDir = __DIR__ . '/questions/';
$imagesDir    = __DIR__ . '/images/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $question = trim($_POST['question'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    // Валидация обязательных полей
    if (empty($name) || empty($question)) {
        $_SESSION['error'] = 'Пожалуйста, заполните имя и текст вопроса.';
        header('Location: /q/');
        exit;
    }

    // Генерация следующего ID (q00001, q00002...)
    $files = glob($questionsDir . 'q*.json');
    $maxId = 0;
    foreach ($files as $file) {
        preg_match('/q(\d+)/', basename($file), $matches);
        if (isset($matches[1]) && (int)$matches[1] > $maxId) {
            $maxId = (int)$matches[1];
        }
    }
    $newIdNum = $maxId + 1;
    $newId    = 'q' . str_pad($newIdNum, 5, '0', STR_PAD_LEFT);

    // Обработка загруженных изображений (макс. 4)
    $savedImages = [];
    if (!empty($_FILES['photos']['name'][0])) {
        if (!is_dir($imagesDir)) mkdir($imagesDir, 0755, true);
        
        $count = 0;
        foreach ($_FILES['photos']['name'] as $key => $fileName) {
            if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK && $count < 4) {
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    // Имя файла: q00001-1.jpg, q00001-2.jpg и т.д.
                    $newFileName = $newId . '-' . ($count + 1) . '.' . $ext;
                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$key], $imagesDir . $newFileName)) {
                        $savedImages[] = $newFileName;
                        $count++;
                    }
                }
            }
        }
    }

    // Формирование данных
    $data = [
        'id'        => $newId,
        'date'      => date('Y-m-d'),
        'name'      => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'phone'     => htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'),
        'email'     => $email ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '',
        'question'  => nl2br(htmlspecialchars($question, ENT_QUOTES, 'UTF-8')),
        'images'    => $savedImages,
        'answer'    => '',
        'published' => false // По умолчанию скрыто, админка будет менять на true
    ];

    if (!is_dir($questionsDir)) mkdir($questionsDir, 0755, true);
    file_put_contents($questionsDir . $newId . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $_SESSION['success'] = 'Вопрос успешно отправлен! Он появится на сайте после проверки врачом.';
    header('Location: /q/');
    exit;
}
?>