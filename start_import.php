<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

function getTotalUserSubscriptions($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_subscriptions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'];
}

function updateImportStatus($user_id, $status, $imported, $total) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET import_status = ?, import_progress = ?, total_subscriptions = ? WHERE id = ?");
    $stmt->bind_param("siii", $status, $imported, $total, $user_id);
    $stmt->execute();
    $stmt->close();
}

try {
    $total_subscriptions = getTotalUserSubscriptions($user_id);
    
    // Обновляем статус импорта
    updateImportStatus($user_id, 'in_progress', 0, $total_subscriptions);
    
    // Запускаем фоновый процесс
    $command = "php " . __DIR__ . "/import_subscriptions_background.php {$user_id} > /dev/null 2>&1 &";
    exec($command);
    
    echo json_encode([
        'status' => 'started',
        'total' => $total_subscriptions
    ]);
} catch (Exception $e) {
    error_log('Error in start_import.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}