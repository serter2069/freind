<?php
require_once 'db_connection.php';

function getUserByGoogleId($googleId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE google_user_id = ?");
    $stmt->bind_param("s", $googleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

function createUser($googleId, $email, $name) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT INTO users (google_user_id, email, name, registration_date) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $googleId, $email, $name);
    $stmt->execute();
    $userId = $stmt->insert_id;
    $stmt->close();
    return $userId;
}

function saveRefreshToken($userId, $refreshToken) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("UPDATE users SET google_refresh_token = ? WHERE id = ?");
    $stmt->bind_param("si", $refreshToken, $userId);
    $stmt->execute();
    $stmt->close();
}

function getUserSubscriptions($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT c.* 
        FROM youtube_channels c
        JOIN user_subscriptions us ON c.channel_id = us.channel_id
        WHERE us.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriptions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $subscriptions;
}

function fetchAndSaveUserSubscriptions($userId, $client) {
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
                addUserSubscription($userId, $channelId);
                $subscriptions[] = $channelData;
            }

            $nextPageToken = $subscriptionsResponse->getNextPageToken();
        } while ($nextPageToken);

        updateUserLastSubscriptionsUpdate($userId);

        return true;
    } catch (Exception $e) {
        error_log("Error fetching subscriptions: " . $e->getMessage());
        return false;
    }
}

function saveOrUpdateChannel($channelData) {
    $conn = getDbConnection();
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

function addUserSubscription($user_id, $channel_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        INSERT IGNORE INTO user_subscriptions (user_id, channel_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("is", $user_id, $channel_id);
    $stmt->execute();
    $stmt->close();
}

function updateUserLastSubscriptionsUpdate($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        UPDATE users 
        SET last_subscriptions_update = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

function removeUserSubscription($user_id, $channel_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        DELETE FROM user_subscriptions
        WHERE user_id = ? AND channel_id = ?
    ");
    $stmt->bind_param("is", $user_id, $channel_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function getGoogleClient() {
    $client = new Google_Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    $client->addScope(Google_Service_YouTube::YOUTUBE_READONLY);
    $client->setAccessType('offline');
    
    // Получаем refresh token из базы данных
    $user_id = $_SESSION['user_id'];
    $refresh_token = getUserRefreshToken($user_id);
    
    if ($refresh_token) {
        $client->refreshToken($refresh_token);
    } else {
        // Если refresh token отсутствует, перенаправляем пользователя на повторную авторизацию
        header('Location: google_auth_youtube.php');
        exit();
    }
    
    return $client;
}

function getUserRefreshToken($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT google_refresh_token FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user['google_refresh_token'] ?? null;
}

// Добавьте здесь любые другие функции, которые вам могут понадобиться

// ... (существующий код)

function getTotalUserSubscriptions($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM user_subscriptions
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'];
}

function getUserSubscriptionsWithPagination($user_id, $offset, $limit) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT c.* 
        FROM youtube_channels c
        JOIN user_subscriptions us ON c.channel_id = us.channel_id
        WHERE us.user_id = ?
        ORDER BY c.title
        LIMIT ?, ?
    ");
    $stmt->bind_param("iii", $user_id, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriptions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $subscriptions;
}

