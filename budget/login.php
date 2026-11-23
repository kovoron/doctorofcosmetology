<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if ($username && $password) {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id, password_hash FROM budget_users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            
            // Если стоит галочка "Запомнить меня"
            if ($remember) {
                $token = generateRememberToken();
                $stmt = $pdo->prepare('UPDATE budget_users SET remember_token = ? WHERE id = ?');
                $stmt->execute([$token, $user['id']]);
                
                // Cookie на 30 дней (Secure=true только для HTTPS)
                $cookieOptions = [
                    'expires'  => time() + 30*24*60*60,
                    'path'     => '/',
                    'secure'   => true,      // Поставьте false, если тестируете на HTTP
                    'httponly' => true,
                    'samesite' => 'Lax'
                ];
                setcookie('budget_remember', $token, $cookieOptions);
            }
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Неверный логин или пароль';
        }
    } else {
        $error = 'Заполните все поля';
    }
}


?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Вход — Бюджет</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="manifest" href="assets/manifest.json">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <h1 class="login-card__title">Личный бюджет</h1>
            <?php if ($error): ?>
                <p style="color: var(--expense); text-align: center; margin-bottom: 15px;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Логин</label>
                    <input type="text" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="password" required autocomplete="current-password">
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
    <input type="checkbox" name="remember" id="remember" style="width: 18px; height: 18px; cursor: pointer;">
    <label for="remember" style="margin: 0; font-size: 14px; cursor: pointer;">Запомнить меня на 30 дней</label>
</div>


                <button type="submit" class="btn">Войти</button>
            </form>
        </div>
    </div>
    <script src="assets/app.js"></script>
</body>
</html>