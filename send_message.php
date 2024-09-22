<?php
session_start();
require_once 'db_connection.php';
require_once 'translations.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['recipient_id']) || !isset($_POST['content'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$recipient_id = intval($_POST['recipient_id']);
$content = trim($_POST['content']);

if (empty($content)) {
    echo json_encode(['status' => 'error', 'message' => 'Message content cannot be empty']);
    exit();
}

$conn = getDbConnection();
$debug_info = [];

// Проверка на дублирование сообщения
$stmt = $conn->prepare("SELECT id FROM messages WHERE sender_id = ? AND recipient_id = ? AND content = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
$stmt->bind_param("iis", $sender_id, $recipient_id, $content);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Duplicate message']);
    exit();
}

$stmt->close();

$stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, content, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $sender_id, $recipient_id, $content);

if ($stmt->execute()) {
    $message_id = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    $stmt->close();

    // Отправка уведомления в Telegram
    $telegram_result = sendTelegramNotification($conn, $sender_id, $recipient_id, $content);
    $debug_info = $telegram_result['debug_info'];

    echo json_encode([
        'status' => 'success',
        'message' => [
            'id' => $message['id'],
            'sender_id' => $message['sender_id'],
            'recipient_id' => $message['recipient_id'],
            'content' => $message['content'],
            'created_at' => $message['created_at'],
            'read_at' => $message['read_at']
        ],
        'debug_info' => $debug_info
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message: ' . $conn->error]);
}

$conn->close();

function sendTelegramNotification($conn, $sender_id, $recipient_id, $content) {
    $debug_info = [];
    
    // Получаем информацию о получателе
    $stmt = $conn->prepare("SELECT telegram_chat_id, preferred_language, name FROM users WHERE id = ?");
    $stmt->bind_param("i", $recipient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipient = $result->fetch_assoc();
    $stmt->close();

    $debug_info['recipient_info'] = $recipient;

    // Проверяем, подключен ли Telegram у получателя
    if (empty($recipient['telegram_chat_id'])) {
        $debug_info['telegram_status'] = 'Telegram не подключен у получателя';
        return ['status' => false, 'debug_info' => $debug_info];
    }

    // Получаем имя отправителя
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sender = $result->fetch_assoc();
    $stmt->close();

    $debug_info['sender_name'] = $sender['name'];

    // Формируем сообщение на языке получателя
    $language = $recipient['preferred_language'];
    $message = getTranslatedMessage($language, $sender['name'], $content);

    // Формируем ссылку на чат
    $chat_url = BASE_URL . "/messages.php?action=write&user=" . $sender_id;

    // Отправляем уведомление в Telegram
    $telegram_message = urlencode($message . "\n\n" . __('reply_link', ['link' => $chat_url], $language));
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage?chat_id=" . $recipient['telegram_chat_id'] . "&text=" . $telegram_message;
    
    $debug_info['telegram_request_url'] = $url;

    $response = file_get_contents($url);
    $debug_info['telegram_response'] = $response;

    // Логирование
    $log_message = date('Y-m-d H:i:s') . " - Отправка уведомления в Telegram:\n" . 
                   "URL: $url\n" . 
                   "Ответ: $response\n\n";
    file_put_contents('telegram_log.txt', $log_message, FILE_APPEND);

    return ['status' => true, 'debug_info' => $debug_info];
}

function getTranslatedMessage($language, $sender_name, $content) {
    $message = __('new_message_from', ['name' => $sender_name], $language) . "\n\n";
    $message .= __('message_content', [], $language) . ":\n";
    $message .= substr($content, 0, 100) . (strlen($content) > 100 ? '...' : '');
    return $message;
}

?>