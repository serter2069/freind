<?php
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
$limit = 10;

$subscriptions = getUserSubscriptions($user_id, $limit, $last_id);

$response = ['subscriptions' => []];
foreach ($subscriptions as $subscription) {
    $response['subscriptions'][] = [
        'id' => $subscription['id'],
        'html' => renderSubscriptionItem($subscription)
    ];
}

echo json_encode($response);