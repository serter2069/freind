<?php
if (php_sapi_name() !== 'cli') {
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/vendor/autoload.php';

$user_id = $argv[1] ?? null;

if (!$user_id) {
    error_log('User ID not provided to import_subscriptions_background.php');
    exit;
}

function getGoogleClient($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT google_refresh_token FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !$user['google_refresh_token']) {
        return null;
    }

    $client = new Google_Client();
    $client->setApplicationName("FriendFinder");
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    $client->refreshToken($user['google_refresh_token']);

    return $client;
}

function saveOrUpdateChannel($conn, $channelData) {
    $stmt = $conn->prepare("
        INSERT INTO youtube_channels (channel_id, title, description, thumbnail_url)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        description = VALUES(description),
        thumbnail_url = VALUES(thumbnail_url)
    ");
    $stmt->bind_param("ssss", $channelData['channel_id'], $channelData['title'], $channelData['description'], $channelData['thumbnail_url']);
    $stmt->execute();
    $stmt->close();
}

function addUserSubscription($conn, $user_id, $channel_id) {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO user_subscriptions (user_id, channel_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("is", $user_id, $channel_id);
    $stmt->execute();
    $stmt->close();
}

function updateImportProgress($conn, $user_id, $imported_count, $total_count) {
    $stmt = $conn->prepare("
        UPDATE users 
        SET import_progress = ?, 
            total_subscriptions = ?,
            last_subscriptions_update = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->bind_param("iii", $imported_count, $total_count, $user_id);
    $stmt->execute();
    $stmt->close();
}

function finalizeImport($conn, $user_id, $status) {
    $stmt = $conn->prepare("
        UPDATE users 
        SET import_status = ?,
            subscriptions_imported = 1
        WHERE id = ?
    ");
    $stmt->bind_param("si", $status, $user_id);
    $stmt->execute();
    $stmt->close();
}

try {
    $conn = getDbConnection();
    $google_client = getGoogleClient($user_id);
    if (!$google_client) {
        throw new Exception('Failed to get Google client for user ' . $user_id);
    }

    $youtube_service = new Google_Service_YouTube($google_client);

    $imported_count = 0;
    $page_token = '';
    
    do {
        $subscriptions_response = $youtube_service->subscriptions->listSubscriptions('snippet', [
            'mine' => true,
            'maxResults' => 50,
            'pageToken' => $page_token
        ]);

        foreach ($subscriptions_response->getItems() as $subscription) {
            $channel_id = $subscription->getSnippet()->getResourceId()->getChannelId();
            $channel_data = [
                'channel_id' => $channel_id,
                'title' => $subscription->getSnippet()->getTitle(),
                'description' => $subscription->getSnippet()->getDescription(),
                'thumbnail_url' => $subscription->getSnippet()->getThumbnails()->getDefault()->getUrl()
            ];
            
            saveOrUpdateChannel($conn, $channel_data);
            addUserSubscription($conn, $user_id, $channel_id);
            
            $imported_count++;
            updateImportProgress($conn, $user_id, $imported_count, $subscriptions_response->getPageInfo()->getTotalResults());
        }

        $page_token = $subscriptions_response->getNextPageToken();
    } while ($page_token);

    finalizeImport($conn, $user_id, 'completed');
    error_log("Import completed for user {$user_id}. Total imported: {$imported_count}");
} catch (Exception $e) {
    error_log("Error during import for user {$user_id}: " . $e->getMessage());
    finalizeImport($conn, $user_id, 'error');
}

$conn->close();