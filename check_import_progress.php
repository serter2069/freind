<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

function getImportStatus($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT import_status, import_progress, total_subscriptions, subscriptions_imported
        FROM users
        WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        return [
            'status' => $row['import_status'],
            'imported' => $row['import_progress'],
            'total' => $row['total_subscriptions'],
            'progress' => $row['total_subscriptions'] > 0 ? round(($row['import_progress'] / $row['total_subscriptions']) * 100, 2) : 0,
            'completed' => $row['subscriptions_imported'] == 1
        ];
    }
    
    return [
        'status' => 'not_started',
        'imported' => 0,
        'total' => 0,
        'progress' => 0,
        'completed' => false
    ];
}

try {
    $conn = getDbConnection();
    $import_status = getImportStatus($conn, $user_id);
    echo json_encode($import_status);
} catch (Exception $e) {
    error_log('Error in check_import_progress.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}