<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';
require_once 'translations.php';

$profile_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

if (!$profile_id) {
    header('Location: index.php');
    exit();
}

function getUserById($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        $user['partner_gender'] = json_decode($user['partner_gender'] ?? '[]', true);
        $user['friend_gender'] = json_decode($user['friend_gender'] ?? '[]', true);
        $user['friend_activities'] = json_decode($user['friend_activities'] ?? '[]', true);
    }
    
    return $user;
}

$user = getUserById($profile_id);

if (!$user) {
    header('Location: index.php');
    exit();
}

function getCommonSubscriptions($user_id1, $user_id2) {
    global $conn;
    
    $sql = "SELECT s.* FROM user_subscriptions us1
            JOIN user_subscriptions us2 ON us1.channel_id = us2.channel_id
            JOIN youtube_channels s ON us1.channel_id = s.channel_id
            WHERE us1.user_id = ? AND us2.user_id = ?
            ORDER BY s.title";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id1, $user_id2);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $common_subscriptions = [];
    
    while ($row = $result->fetch_assoc()) {
        $common_subscriptions[] = $row;
    }
    
    $stmt->close();
    
    return $common_subscriptions;
}

$is_own_profile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_id;

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

function getUserSubscriptionsPaginated($user_id, $limit, $offset) {
    global $conn;
    $query = "SELECT c.* FROM youtube_channels c
              JOIN user_subscriptions us ON c.channel_id = us.channel_id
              WHERE us.user_id = ? 
              ORDER BY c.title
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriptions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $subscriptions;
}

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

$subscriptions = getUserSubscriptionsPaginated($profile_id, $per_page, $offset);
$total_subscriptions = getTotalUserSubscriptions($profile_id);
$total_pages = ceil($total_subscriptions / $per_page);

$common_subscriptions = [];
if (!$is_own_profile && isset($_SESSION['user_id'])) {
    $common_subscriptions = getCommonSubscriptions($_SESSION['user_id'], $profile_id);
}

$page_title = $is_own_profile ? __('my_profile') : __('user_profile', ['name' => $user['name']]);
include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'images/default_avatar.png'); ?>" 
                     alt="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" 
                     class="card-img-top">
                <div class="card-body">
                    <h1 class="card-title"><?php echo htmlspecialchars($user['name'] ?? ''); ?></h1>
                    <p class="card-text"><strong><?php echo __('email'); ?>:</strong> <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    <p class="card-text"><strong><?php echo __('gender'); ?>:</strong> <?php echo $user['gender'] ? __($user['gender']) : __('not_specified'); ?></p>
                    <p class="card-text"><strong><?php echo __('age'); ?>:</strong> <?php echo $user['age'] ?: __('not_specified'); ?></p>
                    <?php if (!empty($user['city_id'])): ?>
                        <?php 
                        $stmt = $conn->prepare("SELECT name, country FROM cities WHERE id = ?");
                        $stmt->bind_param("i", $user['city_id']);
                        $stmt->execute();
                        $city_result = $stmt->get_result();
                        $city = $city_result->fetch_assoc();
                        $stmt->close();
                        ?>
                        <?php if ($city): ?>
                            <p class="card-text"><strong><?php echo __('city'); ?>:</strong> <?php echo htmlspecialchars($city['name'] . ', ' . $city['country']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p class="card-text"><strong><?php echo __('looking_for_partner'); ?>:</strong> <?php echo $user['looking_for_partner'] ? __('yes') : __('no'); ?></p>
                    <?php if ($user['looking_for_partner'] && !empty($user['partner_gender'])): ?>
                        <p class="card-text"><strong><?php echo __('partner_gender'); ?>:</strong> 
                            <?php echo implode(', ', array_map(function($gender) { return __($gender); }, $user['partner_gender'])); ?>
                        </p>
                    <?php endif; ?>
                    <p class="card-text"><strong><?php echo __('looking_for_friends'); ?>:</strong> <?php echo $user['looking_for_friends'] ? __('yes') : __('no'); ?></p>
                    <?php if ($user['looking_for_friends'] && !empty($user['friend_gender'])): ?>
                        <p class="card-text"><strong><?php echo __('friend_gender'); ?>:</strong> 
                            <?php echo implode(', ', array_map(function($gender) { return __($gender); }, $user['friend_gender'])); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($user['looking_for_friends'] && !empty($user['friend_activities'])): ?>
                        <p class="card-text"><strong><?php echo __('friend_activities'); ?>:</strong> 
                            <?php echo implode(', ', array_map(function($activity) { return __('activity_' . $activity); }, $user['friend_activities'])); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($is_own_profile): ?>
                        <a href="edit_profile.php" class="btn btn-primary"><?php echo __('edit_profile'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <button id="shareProfile" class="btn btn-success mt-3 w-100"><?php echo __('share_profile'); ?></button>
            <?php if (!$is_own_profile): ?>
                <a href="messages.php?action=write&user=<?php echo $profile_id; ?>" class="btn btn-primary mt-3 w-100">
                    <?php echo __('write_message'); ?>
                </a>
            <?php endif; ?>
        </div>
        <div class="col-md-8">

        <?php if (!empty($user['ai_generated_description'])): ?>
    <div class="card mb-4 mt-4">
        <div class="card-header bg-info text-white">
            <h2 class="mb-0">
                <i class="fas fa-user-circle"></i> <?php echo __('user_description'); ?>
            </h2>
        </div>
        <div class="card-body">
            <p><?php echo nl2br(htmlspecialchars($user['ai_generated_description'])); ?></p>
            <p class="text-muted mt-2" style="font-size: 0.8em;">
                <i class="fas fa-robot"></i> <?php echo __('ai_generated_disclaimer'); ?>
            </p>
        </div>
    </div>
<?php endif; ?>
            
            <?php if (!empty($common_subscriptions)): ?>
                <div class="card mb-4 mt-4 border-primary" id="commonInterests">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0">
                            <i class="fas fa-star"></i> <?php echo __('common_interests'); ?>
                        </h2>
                    </div>
                    <div class="card-body">
                        <p class="lead"><?php echo __('you_have_x_common_interests', ['count' => count($common_subscriptions)]); ?></p>
                        <div class="row">
                            <?php foreach ($common_subscriptions as $subscription): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 common-subscription-card">
                                    <div class="card-body text-center">
                                        <div class="channel-image-wrapper mb-2">
                                            <img src="<?php echo htmlspecialchars($subscription['thumbnail_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($subscription['title']); ?>"
                                                 class="channel-image">
                                        </div>
                                        <h5 class="card-title"><?php echo htmlspecialchars($subscription['title']); ?></h5>
                                        <a href="bubble.php?id=<?php echo $subscription['id']; ?>" class="btn btn-primary btn-sm"><?php echo __('view_bubble'); ?></a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!$is_own_profile): ?>
                            <div class="text-center mt-3">
                                 <p class="mb-3"><?php echo __('common_interests_explanation', ['name' => $user['name']]); ?></p>
                                <a href="messages.php?action=write&user=<?php echo $profile_id; ?>" class="btn btn-primary">
                                    <?php echo __('write_message'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2><?php echo __('user_subscriptions'); ?></h2>
                <?php if ($is_own_profile): ?>
                    <a href="my_subscriptions.php" class="btn btn-primary">
                        <i class="fas fa-edit"></i> <?php echo __('edit_subscriptions'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="subscription-list">
                <?php foreach ($subscriptions as $subscription): ?>
                    <div class="subscription-item">
                        <div class="d-flex">
                            <a href="bubble.php?id=<?php echo $subscription['id']; ?>">
                                <img src="<?php echo htmlspecialchars($subscription['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($subscription['title']); ?>" class="subscription-thumbnail">
                            </a>
                            <div class="ms-3 flex-grow-1">
                                <h5 class="mb-1">
                                    <a href="bubble.php?id=<?php echo $subscription['id']; ?>">
                                        <?php echo htmlspecialchars($subscription['title']); ?>
                                    </a>
                                </h5>
                                <p class="mb-1 subscription-description">
                                    <span class="short-description"><?php echo htmlspecialchars(substr($subscription['description'], 0, 100)); ?><?php echo strlen($subscription['description']) > 100 ? '...' : ''; ?></span>
                                    <?php if (strlen($subscription['description']) > 100): ?>
                                        <span class="full-description d-none"><?php echo nl2br(htmlspecialchars($subscription['description'])); ?></span>
                                        <a href="#" class="toggle-description"><?php echo __('read_more'); ?></a>
                                    <?php endif; ?>
                                </p>
                                <div class="mt-2">
                                    <a href="people.php?channels=<?php echo $subscription['id']; ?>" class="btn btn-sm btn-primary"><?php echo __('find_people'); ?></a>
                                    <?php if (!empty($subscription['telegram_link'])): ?>
                                        <?php
                                        $telegram_link = $subscription['telegram_link'];
                                        if (!preg_match("~^(?:f|ht)tps?://~i", $telegram_link)) {
                                            $telegram_link = "https://" . $telegram_link;
                                        }
                                        ?>
                                        <a href="<?php echo htmlspecialchars($telegram_link); ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fab fa-telegram"></i> <?php echo __('join_community'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?id=<?php echo $profile_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="shareToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto"><?php echo __('share_profile'); ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
        <div class="toast-body">
            <?php echo __('profile_link_copied'); ?>
        </div>
    </div>
</div>

<style>
.subscription-list {
    max-height: 600px;
    overflow-y: auto;
}
.subscription-item {
    transition: background-color 0.3s;
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
}
.subscription-item:last-child {
    border-bottom: none;
}
.subscription-item:hover {
    background-color: #f8f9fa;
}
.subscription-thumbnail {
    width: 88px;
    height: 88px;
    object-fit: cover;
}
.subscription-description {
    font-size: 0.9em;
    color: #6c757d;
}
.toggle-description {
    color: #007bff;
    text-decoration: none;
    cursor: pointer;
}
.full-description {
    white-space: pre-wrap;
}
.common-subscription-card {
    transition: transform 0.3s ease-in-out;
}
.common-subscription-card:hover {
    transform: scale(1.05);
}
.channel-image-wrapper {
    width: 88px;
    height: 88px;
    margin: 0 auto;
    overflow: hidden;
    border-radius: 50%;
}
.channel-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>

<script>
document.getElementById('shareProfile').addEventListener('click', function() {
    var profileUrl = window.location.href;
    navigator.clipboard.writeText(profileUrl).then(function() {
        var toast = new bootstrap.Toast(document.getElementById('shareToast'));
        toast.show();
    });
});

document.querySelectorAll('.toggle-description').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        var descriptionElement = this.closest('.subscription-description');
        var shortDescription = descriptionElement.querySelector('.short-description');
        var fullDescription = descriptionElement.querySelector('.full-description');
        
        if (fullDescription.classList.contains('d-none')) {
            shortDescription.classList.add('d-none');
            fullDescription.classList.remove('d-none');
            this.textContent = '<?php echo __('read_less'); ?>';
        } else {
            shortDescription.classList.remove('d-none');
            fullDescription.classList.add('d-none');
            this.textContent = '<?php echo __('read_more'); ?>';
        }
    });
});

// Анимация для общих интересов
document.addEventListener('DOMContentLoaded', function() {
    var commonInterestsCard = document.getElementById('commonInterests');
    if (commonInterestsCard) {
        commonInterestsCard.style.opacity = '0';
        commonInterestsCard.style.transform = 'translateY(20px)';
        commonInterestsCard.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
        
        setTimeout(function() {
            commonInterestsCard.style.opacity = '1';
            commonInterestsCard.style.transform = 'translateY(0)';
        }, 300);
    }
});
</script>

<?php include 'footer.php'; ?>