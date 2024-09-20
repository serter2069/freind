<?php
session_start();
require_once 'db_connection.php';
require_once 'functions.php';

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
$stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, content, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $sender_id, $recipient_id, $content);

if ($stmt->execute()) {
    $message_id = $stmt->insert_id;
    $stmt->close();

    // Получаем информацию о только что отправленном сообщении
    $stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'message' => [
            'id' => $message['id'],
            'sender_id' => $message['sender_id'],
            'recipient_id' => $message['recipient_id'],
            'content' => $message['content'],
            'created_at' => $message['created_at'],
            'read_at' => $message['read_at']
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
}

$conn->close();