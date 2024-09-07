<?php
session_start();
require_once 'translations.php';
require_once 'functions.php';

// Функция для получения URL с параметром языка
function getLanguageUrl($lang) {
    return "change_language.php?lang=" . $lang;
}

$page_start_time = microtime(true);
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'FriendFinder'; ?> - FriendFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="index.php">FriendFinder</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php"><?php echo __('home'); ?></a>
                        </li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="my_subscriptions.php"><?php echo __('my_subscriptions'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="find_friends.php"><?php echo __('find_friends'); ?></a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="about.php"><?php echo __('about'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contact.php"><?php echo __('contact'); ?></a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="profile.php"><?php echo __('my_profile'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="logout.php"><?php echo __('logout'); ?></a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="google_auth_youtube.php"><?php echo __('login_with_google'); ?></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo __('language'); ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                            <?php foreach ($availableLanguages as $lang): ?>
                                <li><a class="dropdown-item" href="<?php echo getLanguageUrl($lang); ?>"><?php echo strtoupper($lang); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <main class="container mt-4">