<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['user']) || !isset($_GET['last_time'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}

$user_id = $_SESSION['user_id'];
$other_user_id = intval($_GET['user']);
$last_time = $_GET['last_time'];

$conn = getDbConnection();

// Получаем новые сообщения
$stmt = $conn->prepare("
    SELECT * FROM messages 
    WHERE ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))
    AND created_at > ?
    ORDER BY created_at ASC
");
$stmt->bind_param("iiiss", $user_id, $other_user_id, $other_user_id, $user_id, $last_time);
$stmt->execute();
$result = $stmt->get_result();
$new_messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Отмечаем сообщения как прочитанные
$stmt = $conn->prepare("
    UPDATE messages 
    SET read_at = NOW()
    WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL
");
$stmt->bind_param("ii", $user_id, $other_user_id);
$stmt->execute();
$stmt->close();

// Получаем количество непрочитанных сообщений для всех контактов
$stmt = $conn->prepare("
    SELECT sender_id, COUNT(*) as unread_count 
    FROM messages 
    WHERE recipient_id = ? AND read_at IS NULL 
    GROUP BY sender_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_counts = [];
while ($row = $result->fetch_assoc()) {
    $unread_counts[$row['sender_id']] = $row['unread_count'];
}
$stmt->close();

$conn->close();

echo json_encode([
    'status' => 'success',
    'messages' => $new_messages,
    'unread_counts' => $unread_counts
]);
?>