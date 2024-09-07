<?php
session_start();
require_once 'db_connection.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$channel_id = $data['channel_id'] ?? null;

if (!$channel_id) {
    echo json_encode(['status' => 'error', 'message' => 'Channel ID is required']);
    exit();
}

if (removeUserSubscription($user_id, $channel_id)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove subscription']);
}