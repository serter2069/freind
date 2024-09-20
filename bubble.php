<?php
session_start();
require_once 'db_connection.php';
require_once 'translations.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$channel_id = intval($_GET['id']);

// Функция для получения информации о канале
function getChannelById($conn, $channel_id) {
    $stmt = $conn->prepare("SELECT * FROM youtube_channels WHERE id = ?");
    $stmt->bind_param("i", $channel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $channel = $result->fetch_assoc();
    $stmt->close();
    return $channel;
}

$conn = getDbConnection();
$channel = getChannelById($conn, $channel_id);

if (!$channel) {
    header('Location: index.php');
    exit();
}

$import_log = '';
if (!$channel['videos_imported'] || !$channel['banner_imported']) {
    ob_start();
    updateChannelDetails($conn, $channel['channel_id']);
    $import_log = ob_get_clean();
    $channel = getChannelById($conn, $channel_id);
}

// Функция для обновления деталей канала
function updateChannelDetails($conn, $channel_id) {
    // Обновление баннера
    updateChannelBanner($conn, $channel_id);
    
    // Импорт видео
    importChannelVideos($conn, $channel_id);
    
    // Отмечаем, что видео и баннер импортированы
    $stmt = $conn->prepare("UPDATE youtube_channels SET videos_imported = 1, banner_imported = 1 WHERE channel_id = ?");
    $stmt->bind_param("s", $channel_id);
    $stmt->execute();
    $stmt->close();
}

// Функция для обновления баннера канала
function updateChannelBanner($conn, $channel_id) {
    $youtube = getYouTubeService();
    $channelResponse = $youtube->channels->listChannels('brandingSettings', ['id' => $channel_id]);
    if (!empty($channelResponse->getItems())) {
        $channelInfo = $channelResponse->getItems()[0];
        $bannerSettings = $channelInfo->getBrandingSettings()->getImage();
        $bannerUrl = $bannerSettings ? ($bannerSettings->getBannerExternalUrl() ?? $bannerSettings->getBannerImageUrl()) : null;
        
        $stmt = $conn->prepare("UPDATE youtube_channels SET banner_url = ?, banner_imported = 1 WHERE channel_id = ?");
        $stmt->bind_param("ss", $bannerUrl, $channel_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Функция для импорта видео канала
function importChannelVideos($conn, $channel_id) {
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
}

// Функция для сохранения или обновления видео
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
    $stmt->execute();
    $stmt->close();
}

// Функция для получения YouTube сервиса
function getYouTubeService() {
    $client = new Google_Client();
    $client->setApplicationName("FriendFinder");
    $client->setDeveloperKey(GOOGLE_API_KEY);
    return new Google_Service_YouTube($client);
}

// Получение последних видео
function getLatestVideos($conn, $channel_id, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT * FROM youtube_videos
        WHERE channel_id = ?
        ORDER BY published_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("si", $channel_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $videos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $videos;
}

$latest_videos = getLatestVideos($conn, $channel['channel_id'], 10);

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$subscribers_per_page = 50;
$offset = ($page - 1) * $subscribers_per_page;

// Функция для получения подписчиков канала
function getChannelSubscribers($conn, $channel_id, $limit, $offset) {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.profile_picture, u.gender, u.looking_for_friends, u.looking_for_partner
        FROM users u
        JOIN user_subscriptions us ON u.id = us.user_id
        WHERE us.channel_id = ?
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii", $channel_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscribers = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $subscribers;
}

// Функция для получения общего количества подписчиков канала
function getTotalChannelSubscribers($conn, $channel_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM user_subscriptions
        WHERE channel_id = ?
    ");
    $stmt->bind_param("s", $channel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'];
}

$subscribers = getChannelSubscribers($conn, $channel['channel_id'], $subscribers_per_page, $offset);
$total_subscribers = getTotalChannelSubscribers($conn, $channel['channel_id']);
$total_pages = ceil($total_subscribers / $subscribers_per_page);

$page_title = $channel['title'];
include 'header.php';
?>

<div class="container-fluid mt-4 p-0">
    <?php if ($channel['banner_url']): ?>
    <div class="channel-banner mb-4">
        <div class="banner-container">
            <img src="<?php echo htmlspecialchars($channel['banner_url']); ?>" alt="<?php echo htmlspecialchars($channel['title']); ?> banner" class="img-fluid">
        </div>
    </div>
    <?php endif; ?>
    
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($channel['title']); ?>" class="img-fluid rounded">
            </div>
            <div class="col-md-8">
                <h1><?php echo htmlspecialchars($channel['title']); ?></h1>
                <p><?php echo nl2br(htmlspecialchars($channel['description'])); ?></p>
                <a href="https://www.youtube.com/channel/<?php echo htmlspecialchars($channel['channel_id']); ?>" target="_blank" class="btn btn-primary"><?php echo __('visit_channel'); ?></a>
                
                <?php if (!empty($channel['telegram_link'])): ?>
                <a href="<?php echo htmlspecialchars($channel['telegram_link']); ?>" target="_blank" class="btn btn-info">
                    <i class="fab fa-telegram"></i> <?php echo __('join_community', ['channel' => $channel['title']]); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($latest_videos)): ?>
        <div class="latest-videos mt-4">
            <h3><?php echo __('latest_videos'); ?></h3>
            <div class="video-container">
                <?php foreach ($latest_videos as $video): ?>
                <div class="video-item">
                    <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" class="video-thumbnail" data-video-id="<?php echo htmlspecialchars($video['video_id']); ?>">
                    <p class="video-title"><?php echo htmlspecialchars($video['title']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($subscribers)): ?>
        <div class="subscribers mt-4">
            <h3><?php echo __('channel_subscribers'); ?> (<?php echo $total_subscribers; ?>)</h3>
            <div class="subscriber-list">
                <?php foreach ($subscribers as $subscriber): ?>
                <a href="profile.php?id=<?php echo $subscriber['id']; ?>" class="subscriber-item">
                    <img src="<?php echo htmlspecialchars($subscriber['profile_picture']); ?>" alt="<?php echo htmlspecialchars($subscriber['name']); ?>" class="rounded-circle">
                    <p class="subscriber-name"><?php echo htmlspecialchars($subscriber['name']); ?></p>
                    <p class="subscriber-info">
                        <?php echo __($subscriber['gender']); ?>
                        <?php if ($subscriber['looking_for_friends']): ?>
                            • <?php echo __('looking_for_friends'); ?>
                        <?php endif; ?>
                        <?php if ($subscriber['looking_for_partner']): ?>
                            • <?php echo __('looking_for_partner'); ?>
                        <?php endif; ?>
                    </p>
                </a>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Subscriber pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?id=<?php echo $channel_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="videoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="youtubePlayer"></div>
            </div>
        </div>
    </div>
</div>

<style>
.banner-container {
    width: 100%;
    height: 0;
    padding-bottom: 16.25%;
    position: relative;
    overflow: hidden;
}
.banner-container img {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100%;
    height: auto;
}
.video-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.video-item {
    width: calc(20% - 8px);
    margin-bottom: 10px;
}
.video-thumbnail {
    width: 100%;
    cursor: pointer;
}
.video-title {
    font-size: 12px;
    margin-top: 5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.subscriber-list {
    display: flex;
    flex-wrap: wrap;
}
.subscriber-item {
    width: 150px;
    margin: 10px;
    text-align: center;
    text-decoration: none;
    color: inherit;
}
.subscriber-item img {
    width: 80px;
    height: 80px;
}
.subscriber-name {
    font-size: 14px;
    margin-top: 5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.subscriber-info {
    font-size: 12px;
    color: #666;
    margin-top: 3px;
}
.pagination {
    margin-top: 20px;
}
.page-link {
    color: #007bff;
    background-color: #fff;
    border: 1px solid #dee2e6;
}
.page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}
</style>

<script>
var player;
$(document).ready(function() {
    $('.video-thumbnail').on('click', function() {
        var videoId = $(this).data('video-id');
        var videoTitle = $(this).siblings('.video-title').text();
        $('#videoModalLabel').text(videoTitle);
        
        if (player) {
            player.loadVideoById(videoId);
        } else {
            player = new YT.Player('youtubePlayer', {
                height: '390',
                width: '100%',
                videoId: videoId,
            });
        }
        
        $('#videoModal').modal('show');
    });

    $('#videoModal').on('hidden.bs.modal', function () {
        if (player) {
            player.stopVideo();
        }
    });

    // Log import information to console
    <?php if (!empty($import_log)): ?>
    console.log('Channel import log:', <?php echo json_encode($import_log); ?>);
    <?php endif; ?>
});

function onYouTubeIframeAPIReady() {
    console.log('YouTube API ready');
}

function onYouTubeIframeAPIFailure(error) {
    console.error('YouTube API failed to load:', error);
}
</script>

<script src="https://www.youtube.com/iframe_api" onerror="onYouTubeIframeAPIFailure(event)"></script>

<?php include 'footer.php'; ?>