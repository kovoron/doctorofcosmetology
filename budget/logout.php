<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE budget_users SET remember_token = NULL WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
}

// Удаляем cookie
setcookie('budget_remember', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);

session_destroy();
header('Location: login.php');
exit;
?>

