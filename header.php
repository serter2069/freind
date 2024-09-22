<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';
require_once 'translations.php';

$page_start_time = microtime(true);

function getUserName($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    return htmlspecialchars($user_data['name'] ?? 'Unknown User');
}

function getUserProfilePicture($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    return $user_data['profile_picture'] ?? 'images/default_avatar.png';
}

function hasTelegramChatId($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT telegram_chat_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    return !empty($user_data['telegram_chat_id']);
}

function getUserLanguage($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT preferred_language FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    return $user_data['preferred_language'] ?? getCurrentLanguage();
}

function getUnreadMessageCount($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND read_at IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count;
}

$current_language = isset($_SESSION['user_id']) ? getUserLanguage($_SESSION['user_id']) : getCurrentLanguage();

?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'FriendFinder'; ?> - FriendFinder</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
</head>
<body class="flex flex-col min-h-screen bg-gray-100">

<header class="bg-blue-600 text-white">
    <nav x-data="{ open: false }" class="container mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <a href="index.php" class="text-xl font-bold">FriendFinder</a>
            <button @click="open = !open" class="md:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
            </button>
            <div class="hidden md:flex space-x-4">
                <a href="people.php" class="hover:text-blue-200"><?php echo __('people'); ?></a>
                <a href="bubbles.php" class="hover:text-blue-200"><?php echo __('bubbles'); ?></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="my_subscriptions.php" class="hover:text-blue-200"><?php echo __('my_subscriptions'); ?></a>
                    <a href="find_friends.php" class="hover:text-blue-200"><?php echo __('find_friends'); ?></a>
                    <a href="messages.php" class="hover:text-blue-200 relative">
                        <?php echo __('messages'); ?>
                        <?php 
                        $unread_count = getUnreadMessageCount($_SESSION['user_id']);
                        if ($unread_count > 0):
                        ?>
                            <span class="absolute top-0 right-0 -mt-1 -mr-1 px-1 py-0.5 bg-red-500 rounded-full text-xs"></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center hover:text-blue-200">
                            <img src="<?php echo htmlspecialchars(getUserProfilePicture($_SESSION['user_id'])); ?>" alt="Profile" class="w-8 h-8 rounded-full mr-2">
                            <?php echo htmlspecialchars(getUserName($_SESSION['user_id'])); ?>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><?php echo __('my_profile'); ?></a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><?php echo __('logout'); ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="google_auth_youtube.php" class="hover:text-blue-200"><?php echo __('login_with_google'); ?></a>
                <?php endif; ?>
                
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center hover:text-blue-200">
                        <span class="mr-1"><?php echo strtoupper($current_language); ?></span>
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                        <?php foreach ($availableLanguages as $lang): ?>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" onclick="changeLanguage('<?php echo $lang; ?>')"><?php echo strtoupper($lang); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div x-show="open" class="md:hidden">
            <a href="people.php" class="block py-2 hover:text-blue-200"><?php echo __('people'); ?></a>
            <a href="bubbles.php" class="block py-2 hover:text-blue-200"><?php echo __('bubbles'); ?></a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="my_subscriptions.php" class="block py-2 hover:text-blue-200"><?php echo __('my_subscriptions'); ?></a>
                <a href="find_friends.php" class="block py-2 hover:text-blue-200"><?php echo __('find_friends'); ?></a>
                <a href="messages.php" class="block py-2 hover:text-blue-200"><?php echo __('messages'); ?></a>
                <a href="profile.php" class="block py-2 hover:text-blue-200"><?php echo __('my_profile'); ?></a>
                <a href="logout.php" class="block py-2 hover:text-blue-200"><?php echo __('logout'); ?></a>
            <?php else: ?>
                <a href="google_auth_youtube.php" class="block py-2 hover:text-blue-200"><?php echo __('login_with_google'); ?></a>
            <?php endif; ?>
        </div>
    </nav>
    <?php if (isset($_SESSION['user_id']) && !hasTelegramChatId($_SESSION['user_id'])): ?>
    <div class="bg-yellow-100 border-b border-yellow-200 text-yellow-700 px-4 py-2">
        <div class="container mx-auto flex justify-between items-center">
            <p class="text-sm">
                <?php echo __('telegram_notification_message'); ?>
            </p>
            <a href="edit_profile.php#notification-section" class="text-yellow-700 font-bold text-sm hover:text-yellow-800">
                <?php echo __('enable_now'); ?> &rarr;
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT profile_visited FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();

        if (!$user_data['profile_visited']):
    ?>
        <div class="bg-warning border-b border-warning-200 text-warning-700 px-4 py-2">
            <div class="container mx-auto flex justify-between items-center">
                <p class="text-sm">
                    <?php echo __('complete_profile_message'); ?>
                </p>
                <a href="edit_profile.php" class="text-warning-700 font-bold text-sm hover:text-warning-800">
                    <?php echo __('complete_now'); ?> &rarr;
                </a>
            </div>
        </div>
    <?php
        endif;
    }
    ?>
</header>
<main class="flex-grow container mx-auto px-4 py-8">

<script>
function changeLanguage(lang) {
    fetch('change_language.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'lang=' + lang
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            console.error('Failed to change language');
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>