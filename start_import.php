<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

function initializeImport($conn, $user_id) {
    $stmt = $conn->prepare("
        UPDATE users 
        SET import_status = 'in_progress', 
            import_progress = 0, 
            total_subscriptions = 0,
            subscriptions_imported = 0
        WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

try {
    $conn = getDbConnection();
    initializeImport($conn, $user_id);
    
    // Запускаем фоновый процесс
    $command = "php " . __DIR__ . "/import_subscriptions_background.php {$user_id} > /dev/null 2>&1 &";
    exec($command);
    
    echo json_encode([
        'status' => 'started',
        'message' => 'Import process has been initiated'
    ]);
} catch (Exception $e) {
    error_log('Error in start_import.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}