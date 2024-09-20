<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';
require_once 'translations.php';

$page_start_time = microtime(true);

// Function to get current language


// Function to get user name
function getUserName($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return htmlspecialchars($user['name'] ?? 'Unknown User');
}

// Function to get user profile picture
function getUserProfilePicture($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user['profile_picture'] ?? 'images/default_avatar.png';
}

?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'FriendFinder'; ?> - FriendFinder</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap CSS (for compatibility with other pages) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS Bundle (for compatibility with other pages) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Alpine.js for interactivity -->
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
                    <a href="messages.php" class="hover:text-blue-200"><?php echo __('messages'); ?></a>
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
</header>
<main class="flex-grow container mx-auto px-4 py-8">