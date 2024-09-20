<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connection.php';
require_once 'vendor/autoload.php';

echo "<h1>Обновление информации о каналах</h1>";

$conn = getDbConnection();

function getYouTubeService() {
    $client = new Google_Client();
    $client->setApplicationName("FriendFinder");
    $client->setDeveloperKey(GOOGLE_API_KEY);
    return new Google_Service_YouTube($client);
}

function updateChannelBanner($conn, $channel_id) {
    $youtube = getYouTubeService();
    
    $channelResponse = $youtube->channels->listChannels('brandingSettings', ['id' => $channel_id]);
    if (empty($channelResponse->getItems())) {
        echo "Канал не найден: $channel_id<br>";
        return false;
    }
    $channelInfo = $channelResponse->getItems()[0];

    $bannerUrl = null;
    $bannerSettings = $channelInfo->getBrandingSettings()->getImage();
    if ($bannerSettings) {
        if ($bannerSettings->getBannerExternalUrl()) {
            $bannerUrl = $bannerSettings->getBannerExternalUrl();
        } elseif ($bannerSettings->getBannerImageUrl()) {
            $bannerUrl = $bannerSettings->getBannerImageUrl();
        }
    }

    $stmt = $conn->prepare("UPDATE youtube_channels SET banner_url = ?, banner_imported = 1 WHERE channel_id = ?");
    $stmt->bind_param("ss", $bannerUrl, $channel_id);
    if ($stmt->execute()) {
        echo "Информация о баннере обновлена в БД для канала $channel_id<br>";
    } else {
        echo "Ошибка при обновлении информации о баннере в БД для канала $channel_id: " . $stmt->error . "<br>";
    }
    $stmt->close();
}

function updateChannelVideos($conn, $channel_id) {
    $youtube = getYouTubeService();
    
    $videosResponse = $youtube->search->listSearch('snippet', [
        'channelId' => $channel_id,
        'type' => 'video',
        'order' => 'date',
        'maxResults' => 10
    ]);

    foreach ($videosResponse->getItems() as $item) {
        $videoId = $item->getId()->getVideoId();
        $videoData = [
            'channel_id' => $channel_id,
            'video_id' => $videoId,
            'title' => $item->getSnippet()->getTitle(),
            'description' => $item->getSnippet()->getDescription(),
            'thumbnail_url' => $item->getSnippet()->getThumbnails()->getMedium()->getUrl(),
            'published_at' => $item->getSnippet()->getPublishedAt()
        ];
        saveOrUpdateVideo($conn, $videoData);
    }

    $stmt = $conn->prepare("UPDATE youtube_channels SET videos_imported = 1 WHERE channel_id = ?");
    $stmt->bind_param("s", $channel_id);
    if ($stmt->execute()) {
        echo "Отмечено, что видео импортированы для канала: $channel_id<br>";
    } else {
        echo "Ошибка при обновлении статуса импорта видео для канала $channel_id: " . $stmt->error . "<br>";
    }
    $stmt->close();
}

function saveOrUpdateVideo($conn, $videoData) {
    $stmt = $conn->prepare("
        INSERT INTO youtube_videos (channel_id, video_id, title, description, thumbnail_url, published_at)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        description = VALUES(description),
        thumbnail_url = VALUES(thumbnail_url),
        published_at = VALUES(published_at)
    ");
    $stmt->bind_param("ssssss", $videoData['channel_id'], $videoData['video_id'], $videoData['title'], $videoData['description'], $videoData['thumbnail_url'], $videoData['published_at']);
    if ($stmt->execute()) {
        echo "Видео {$videoData['video_id']} сохранено/обновлено<br>";
    } else {
        echo "Ошибка при сохранении/обновлении видео {$videoData['video_id']}: " . $stmt->error . "<br>";
    }
    $stmt->close();
}

// Этап 1: Обновление баннеров
echo "Этап 1: Обновление баннеров<br>";
$stmt = $conn->prepare("SELECT channel_id FROM youtube_channels WHERE banner_imported != 1 OR banner_imported IS NULL LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "Обновление баннера канала: " . $row['channel_id'] . "<br>";
    updateChannelBanner($conn, $row['channel_id']);
    sleep(1);
}

$stmt->close();

// Этап 2: Импорт видео
echo "Этап 2: Импорт видео<br>";
$stmt = $conn->prepare("SELECT channel_id FROM youtube_channels WHERE videos_imported != 1 OR videos_imported IS NULL LIMIT 3");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "Импорт видео для канала: " . $row['channel_id'] . "<br>";
    updateChannelVideos($conn, $row['channel_id']);
    sleep(1);
}

$stmt->close();

// Этап 3: Удаление дубликатов видео
echo "Этап 3: Удаление дубликатов видео<br>";
$stmt = $conn->prepare("
    DELETE v1 FROM youtube_videos v1
    INNER JOIN youtube_videos v2 
    WHERE v1.id > v2.id AND v1.video_id = v2.video_id
");
$stmt->execute();
$deletedCount = $stmt->affected_rows;
echo "Удалено дубликатов: $deletedCount<br>";
$stmt->close();

$conn->close();

echo "Обновление завершено<br>";