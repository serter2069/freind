<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once 'db_connection.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

try {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT c.* 
        FROM youtube_channels c
        JOIN user_subscriptions us ON c.channel_id = us.channel_id
        WHERE us.user_id = ? AND c.id > ?
        ORDER BY c.id ASC
        LIMIT ?
    ");
    $stmt->bind_param("iii", $user_id, $last_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $newSubscriptions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $response = ['subscriptions' => []];
    foreach ($newSubscriptions as $subscription) {
        $response['subscriptions'][] = [
            'id' => $subscription['id'],
            'html' => renderSubscriptionItem($subscription)
        ];
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}