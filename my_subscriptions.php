<?php
session_start();
require_once 'db_connection.php';
require_once 'translations.php';
require_once 'vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

function fetchImportStatus($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT import_status, import_progress, total_subscriptions FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

function fetchUserSubscriptions($user_id) {
    global $conn;
    $query = "SELECT c.* FROM youtube_channels c
              JOIN user_subscriptions us ON c.channel_id = us.channel_id
              WHERE us.user_id = ? ORDER BY c.title";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriptions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $subscriptions;
}

function searchYouTubeChannels($query, $user_id) {
    $client = new Google_Client();
    $client->setDeveloperKey(GOOGLE_API_KEY);
    $youtube = new Google_Service_YouTube($client);

    try {
        $searchResponse = $youtube->search->listSearch('snippet', [
            'q' => $query,
            'type' => 'channel',
            'maxResults' => 5
        ]);

        $results = [];
        foreach ($searchResponse['items'] as $item) {
            $channelId = $item['id']['channelId'];
            $isSubscribed = checkSubscription($user_id, $channelId);
            $results[] = [
                'id' => $channelId,
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'thumbnail' => $item['snippet']['thumbnails']['default']['url'],
                'isSubscribed' => $isSubscribed
            ];
        }
        return $results;
    } catch (Exception $e) {
        error_log('YouTube search error: ' . $e->getMessage());
        return ['error' => 'An error occurred while searching'];
    }
}

function checkSubscription($user_id, $channel_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE user_id = ? AND channel_id = ?");
    $stmt->bind_param("is", $user_id, $channel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];
    $stmt->close();
    return $count > 0;
}

function addChannelSubscription($user_id, $channel_id, $channel_data) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO youtube_channels (channel_id, title, description, thumbnail_url) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), thumbnail_url = VALUES(thumbnail_url)");
    $stmt->bind_param("ssss", $channel_id, $channel_data['title'], $channel_data['description'], $channel_data['thumbnail']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT IGNORE INTO user_subscriptions (user_id, channel_id) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $channel_id);
    $stmt->execute();
    $stmt->close();

    importChannelVideos($channel_id);
}

function importChannelVideos($channel_id) {
    $client = new Google_Client();
    $client->setDeveloperKey(GOOGLE_API_KEY);
    $youtube = new Google_Service_YouTube($client);

    try {
        $searchResponse = $youtube->search->listSearch('snippet', [
            'channelId' => $channel_id,
            'type' => 'video',
            'order' => 'date',
            'maxResults' => 10
        ]);

        global $conn;
        foreach ($searchResponse['items'] as $item) {
            $video_id = $item['id']['videoId'];
            $title = $item['snippet']['title'];
            $description = $item['snippet']['description'];
            $thumbnail_url = $item['snippet']['thumbnails']['default']['url'];
            $published_at = $item['snippet']['publishedAt'];

            $stmt = $conn->prepare("INSERT IGNORE INTO youtube_videos (channel_id, video_id, title, description, thumbnail_url, published_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $channel_id, $video_id, $title, $description, $thumbnail_url, $published_at);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log('Error importing channel videos: ' . $e->getMessage());
    }
}

$import_status = fetchImportStatus($user_id);
$subscriptions = fetchUserSubscriptions($user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        if ($_POST['action'] === 'search') {
            $query = $_POST['query'] ?? '';
            $results = searchYouTubeChannels($query, $user_id);
            echo json_encode($results);
            exit;
        } elseif ($_POST['action'] === 'add_subscription') {
            $channel_data = json_decode($_POST['channel_data'], true);
            addChannelSubscription($user_id, $channel_data['id'], $channel_data);
            echo json_encode(['status' => 'success']);
            exit;
        }
    }
}

$page_title = __('my_subscriptions');
include 'header.php';
?>

<div class="container mt-4">
    <h1 class="text-center mb-4"><?php echo __('my_subscriptions'); ?></h1>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if ($import_status['import_status'] === 'not_started'): ?>
                <div class="alert alert-info">
                    <p><?php echo __('no_subscriptions_message'); ?></p>
                    <a href="import_subscriptions.php" class="btn btn-primary"><?php echo __('get_youtube_data'); ?></a>
                </div>
            <?php else: ?>
                <?php if ($import_status['import_status'] === 'in_progress'): ?>
                    <div id="importStatus" class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-2" role="status">
                                <span class="visually-hidden"><?php echo __('loading'); ?></span>
                            </div>
                            <span><?php echo __('import_in_progress'); ?></span>
                        </div>
                        <p class="mb-0"><?php echo sprintf(__('imported_count'), $import_status['import_progress'], $import_status['total_subscriptions']); ?></p>
                    </div>
                <?php endif; ?>

                <div class="mb-4">
                    <button id="showSearchField" class="btn btn-primary mb-2"><?php echo __('add_channel'); ?></button>
                </div>

                <div id="subscriptionsList">
                    <?php if (empty($subscriptions)): ?>
                        <p><?php echo __('no_subscriptions_yet'); ?></p>
                    <?php else: ?>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <div class="subscription-item" data-channel-id="<?php echo htmlspecialchars($subscription['channel_id']); ?>">
                                <div class="subscription-content">
                                    <img src="<?php echo htmlspecialchars($subscription['thumbnail_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($subscription['title']); ?>" 
                                         class="subscription-thumbnail">
                                    <div class="subscription-info">
                                        <h5 class="subscription-title">
                                            <a href="bubble.php?id=<?php echo $subscription['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($subscription['title']); ?>
                                            </a>
                                        </h5>
                                        <p class="subscription-description">
                                            <?php echo htmlspecialchars(substr($subscription['description'], 0, 100) . '...'); ?>
                                        </p>
                                        <div class="subscription-actions">
                                            <a href="people.php?channels[]=<?php echo $subscription['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <?php echo __('find_people'); ?>
                                            </a>
                                            <?php if (!empty($subscription['telegram_link'])): ?>
                                                <a href="<?php echo htmlspecialchars($subscription['telegram_link']); ?>" target="_blank" class="btn btn-telegram btn-sm">
                                                    <i class="fab fa-telegram-plane"></i> <?php echo __('join_community'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <button class="btn-remove" title="<?php echo __('remove'); ?>">
                                    &times;
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Search Popup -->
<div id="searchPopup" class="popup">
    <div class="popup-content">
        <span class="close">&times;</span>
        <h2><?php echo __('search_channels'); ?></h2>
        <div class="input-group mb-3">
            <input type="text" id="channelSearch" class="form-control" placeholder="<?php echo __('search_channels'); ?>">
            <button class="btn btn-outline-secondary" type="button" id="searchButton"><?php echo __('search'); ?></button>
        </div>
        <div id="searchResults"></div>
    </div>
</div>

<!-- Notification -->
<div id="notification" class="notification">
    <span id="notificationMessage"></span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const showSearchFieldBtn = document.getElementById('showSearchField');
    const searchPopup = document.getElementById('searchPopup');
    const closePopup = document.querySelector('.close');
    const channelSearch = document.getElementById('channelSearch');
    const searchButton = document.getElementById('searchButton');
    const searchResults = document.getElementById('searchResults');
    const notification = document.getElementById('notification');
    const notificationMessage = document.getElementById('notificationMessage');

    showSearchFieldBtn.addEventListener('click', function() {
        searchPopup.style.display = 'block';
        document.body.classList.add('popup-open');
        channelSearch.focus();
    });

    closePopup.addEventListener('click', function() {
        searchPopup.style.display = 'none';
        document.body.classList.remove('popup-open');
    });

    window.addEventListener('click', function(event) {
        if (event.target === searchPopup) {
            searchPopup.style.display = 'none';
            document.body.classList.remove('popup-open');
        }
    });

    searchButton.addEventListener('click', performSearch);
    channelSearch.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });

    function performSearch() {
        const query = channelSearch.value.trim();
        if (query) {
            searchResults.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            fetch('my_subscriptions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=search&query=${encodeURIComponent(query)}`
            })
                .then(response => response.json())
                .then(data => displaySearchResults(data))
                .catch(error => {
                    console.error('Error:', error);
                    searchResults.innerHTML = '<p class="text-danger">An error occurred while searching.</p>';
                });
        }
    }

    function displaySearchResults(data) {
        searchResults.innerHTML = '';
        if (data.error) {
            searchResults.innerHTML = `<p class="text-danger">${data.error}</p>`;
            return;
        }
        if (data.length > 0) {
            data.forEach(item => {
                const channelHtml = `
                    <div class="channel-item">
                        <img src="${item.thumbnail}" alt="${item.title}" class="channel-thumbnail">
                        <div class="channel-info">
                            <h5 class="channel-title">${item.title}</h5>
                            <p class="channel-description">${item.description.substring(0, 100)}...</p>
                        </div>
                        ${item.isSubscribed 
                            ? '<span class="badge bg-success">Subscribed</span>'
                            : `<button class="btn btn-sm btn-primary btn-add" data-channel-id="${item.id}" data-channel-title="${item.title}" data-channel-description="${item.description}" data-channel-thumbnail="${item.thumbnail}">Add</button>`
                        }
                    </div>
                `;
                searchResults.insertAdjacentHTML('beforeend', channelHtml);
            });
            addEventListenersToAddButtons();
        } else {
            searchResults.innerHTML = '<p>No channels found.</p>';
        }
    }

    function addEventListenersToAddButtons() {
        document.querySelectorAll('.btn-add').forEach(button => {
            button.addEventListener('click', function() {
                const channelData = {
                    id: this.dataset.channelId,
                    title: this.dataset.channelTitle,
                    description: this.dataset.channelDescription,
                    thumbnail: this.dataset.channelThumbnail
                };
                addChannel(channelData);
            });
        });
    }

    function addChannel(channelData) {
        showNotification('Adding channel...', 'info');
        fetch('my_subscriptions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_subscription&channel_data=${encodeURIComponent(JSON.stringify(channelData))}`,
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification('Channel added successfully!', 'success');
                location.reload();
            } else {
                showNotification('Error adding channel: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while adding the channel.', 'error');
        });
    }

    document.querySelectorAll('.btn-remove').forEach(button => {
        button.addEventListener('click', function() {
            const subscriptionItem = this.closest('.subscription-item');
            const channelId = subscriptionItem.dataset.channelId;
            
            showNotification('Removing subscription...', 'info');
            fetch('remove_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ channel_id: channelId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    subscriptionItem.style.opacity = '0';
                    subscriptionItem.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        subscriptionItem.remove();
                        showNotification('Subscription removed successfully!', 'success');
                    }, 300);
                } else {
                    showNotification('Error removing subscription', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error removing subscription', 'error');
            });
        });
    });

    function showNotification(message, type) {
        notificationMessage.textContent = message;
        notification.className = 'notification';
        notification.classList.add(type);
        notification.style.display = 'block';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }

    function checkImportStatus() {
        fetch('check_import_progress.php')
        .then(response => response.json())
        .then(data => {
            console.log('Import status:', data);
            const importStatus = document.getElementById('importStatus');
            if (importStatus) {
                if (data.status === 'completed') {
                    importStatus.remove();
                    location.reload();
                } else if (data.status === 'in_progress') {
                    importStatus.querySelector('p').textContent = `Imported ${data.imported} of ${data.total} subscriptions`;
                    setTimeout(checkImportStatus, 5000);
                }
            }
        })
        .catch(error => console.error('Error:', error));
    }

    if (document.getElementById('importStatus')) {
        checkImportStatus();
    }
});
</script>

<style>
.subscription-item {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    transition: background-color 0.3s ease, opacity 0.3s ease, transform 0.3s ease;
}
.subscription-item:hover {
    background-color: #f8f9fa;
}
.subscription-content {
    display: flex;
    flex-grow: 1;
}
.subscription-thumbnail {
    width: 88px;
    height: 88px;
    object-fit: cover;
    margin-right: 15px;
}
.subscription-info {
    flex-grow: 1;
    min-width: 0;
}
.subscription-title {
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.subscription-description {
    font-size: 0.9em;
    color: #6c757d;
    margin-bottom: 10px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.subscription-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.btn-remove {
    display: none;
    background: none;
    border: none;
    color: #dc3545;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0 10px;
}
.subscription-item:hover .btn-remove {
    display: block;
}
.btn-telegram {
    color: #fff;
    background-color: #0088cc;
    border-color: #0088cc;
}
.btn-telegram:hover {
    color: #fff;
    background-color: #0077b3;
    border-color: #006da6;
}
.popup {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}
.popup-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 5px;
    position: relative;
}
.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close:hover,
.close:focus {
    color: #000;
    text-decoration: none;
    cursor: pointer;
}
#searchResults .channel-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
}
#searchResults .channel-thumbnail {
    width: 50px;
    height: 50px;
    margin-right: 10px;
}
#searchResults .channel-info {
    flex-grow: 1;
}
#searchResults .channel-title {
    margin-bottom: 0;
}
#searchResults .btn-add {
    white-space: nowrap;
}
.popup-open {
    overflow: hidden;
}
.notification {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 4px;
    color: #fff;
    font-weight: bold;
    z-index: 1001;
}
.notification.success {
    background-color: #28a745;
}
.notification.error {
    background-color: #dc3545;
}
.notification.info {
    background-color: #17a2b8;
}
</style>

<?php include 'footer.php'; ?>