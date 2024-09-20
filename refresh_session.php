<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'success', 'message' => 'Session refreshed']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No active session']);
}