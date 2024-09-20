<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$channel_id = $input['channel_id'] ?? null;

if (!$channel_id) {
    echo json_encode(['status' => 'error', 'message' => 'Channel ID is required']);
    exit();
}

function removeUserSubscription($user_id, $channel_id) {
    global $conn;
    $stmt = $conn->prepare("
        DELETE FROM user_subscriptions
        WHERE user_id = ? AND channel_id = ?
    ");
    $stmt->bind_param("is", $user_id, $channel_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

$result = removeUserSubscription($user_id, $channel_id);

if ($result) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove subscription']);
}