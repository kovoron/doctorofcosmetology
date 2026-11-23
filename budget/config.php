<?php
// Маркер загрузки: файлы из budget/lib/ не должны открываться напрямую по URL
define('BUDGET_APP', true);

session_start();

// ==========================================
// 1. НАСТРОЙКИ БАЗЫ ДАННЫХ
// ==========================================
// Секреты в репозитории не хранятся: локально — .doctorofcosmetology.local.php
// вне проекта, на проде — .doctorofcosmetology.prod.php вне docroot.
require_once __DIR__ . '/../config/env.php';
loadExternalConfig();

// Единая таймзона: PHP на хостинге настроен на UTC, а владелец бюджета живёт в MSK.
// Без этого «сегодня», «текущий месяц» и сравнение периодов смещаются на 3 часа.
date_default_timezone_set(appTimezone());

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $requiredConstant) {
    if (!defined($requiredConstant)) {
        throw new RuntimeException(sprintf(
            'Константа %s не задана во внешнем конфиге БД.',
            $requiredConstant
        ));
    }
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Получение экземпляра PDO (Singleton)
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        // Сессия MySQL считает CURDATE()/DATE(created_at) в той же зоне, что и PHP.
        $pdo->exec('SET time_zone = ' . $pdo->quote(dbTimezone()));
    }
    return $pdo;
}

// ==========================================
// 2. CSRF ЗАЩИТА
// ==========================================
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// ==========================================
// 3. ПОМОЩНИКИ
// ==========================================
function formatMoney($amount) {
    return number_format($amount, 0, '.', ' ') . ' ₽';
}

function generateRememberToken() {
    return bin2hex(random_bytes(32));
}

// ==========================================
// 4. АВТОРИЗАЦИЯ И СЕССИЯ
// ==========================================

// Проверка куки "Запомнить меня" и восстановление сессии
function checkRememberMe() {
    // Если уже авторизован в сессии — выходим
    if (isset($_SESSION['user_id'])) return;

    // Проверяем наличие куки
    if (isset($_COOKIE['budget_remember'])) {
        $cookieToken = $_COOKIE['budget_remember'];
        $pdo = getDB();
        
        // Ищем пользователя с таким токеном в базе
        $stmt = $pdo->prepare('SELECT id, username FROM budget_users WHERE remember_token = ?');
        $stmt->execute([$cookieToken]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
        } else {
            // Токен не найден в базе (например, сменили пароль или вышли) — удаляем куку
            setcookie('budget_remember', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'samesite' => 'Lax'
            ]);
        }
    }
}

// Проверка авторизации (используется на защищенных страницах)
function requireAuth() {
    // 1. Сначала пытаемся восстановить сессию из куки
    checkRememberMe(); 
    
    // 2. Если сессии всё ещё нет — редирект на логин
    if (!isset($_SESSION['user_id'])) {
        // Если это запрос к API, возвращаем JSON ошибку 401
        if (strpos($_SERVER['REQUEST_URI'], '/budget/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        // Обычный редирект для страниц
        header('Location: /budget/login.php');
        exit;
    }
}