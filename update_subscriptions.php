<?php
session_start();
require_once 'db_connection.php';
require_once 'functions.php';
require_once 'vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$client = getGoogleClient();

try {
    $youtube = new Google_Service_YouTube($client);
    $subscriptions = [];
    $nextPageToken = '';

    do {
        $subscriptionsResponse = $youtube->subscriptions->listSubscriptions('snippet', [
            'mine' => true,
            'maxResults' => 50,
            'pageToken' => $nextPageToken
        ]);

        foreach ($subscriptionsResponse['items'] as $item) {
            $channelId = $item['snippet']['resourceId']['channelId'];
            $channelData = [
                'channel_id' => $channelId,
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'thumbnail_url' => $item['snippet']['thumbnails']['default']['url']
            ];
            saveOrUpdateChannel($channelData);
            addUserSubscription($user_id, $channelId);
            $subscriptions[] = $channelData;
        }

        $nextPageToken = $subscriptionsResponse->getNextPageToken();
    } while ($nextPageToken);

    updateUserLastSubscriptionsUpdate($user_id);

    echo json_encode(['status' => 'success', 'subscriptions' => $subscriptions]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}