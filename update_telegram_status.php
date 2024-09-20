<?php
session_start();
require_once 'db_connection.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$enabled = isset($_POST['enabled']) ? (bool)$_POST['enabled'] : false;

$conn = getDbConnection();

if ($enabled) {
    // Если включаем уведомления, просто устанавливаем флаг
    $stmt = $conn->prepare("UPDATE users SET telegram_notifications = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
} else {
    // Если выключаем уведомления, очищаем все связанные поля и генерируем новый хэш
    $new_hash = hash('sha256', $user_id . time() . uniqid());
    $stmt = $conn->prepare("UPDATE users SET telegram_notifications = 0, telegram_chat_id = NULL, telegram_hash = ? WHERE id = ?");
    $stmt->bind_param("si", $new_hash, $user_id);
}

$result = $stmt->execute();
$stmt->close();

if ($result) {
    // Получаем обновленные данные пользователя
    $stmt = $conn->prepare("SELECT telegram_notifications, telegram_chat_id, telegram_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userdata = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'enabled' => (bool)$userdata['telegram_notifications'],
        'chatId' => $userdata['telegram_chat_id'],
        'newHash' => $userdata['telegram_hash']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>