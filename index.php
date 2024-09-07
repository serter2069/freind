<?php
error_reporting(E_ALL);  // Показывать все ошибки
ini_set('display_errors', 1);  // Включить вывод ошибок
require_once 'translations.php';
$page_title = __('home');
include 'header.php';
require_once 'db_connection.php';
require_once 'functions.php';
?>

<div class="container">
    <h1 class="mt-5"><?php echo __('welcome_message', ['name' => $_SESSION['user_name'] ?? '']); ?></h1>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['user_id'])): ?>
        <p><?php echo __('youtube_subscriptions'); ?></p>
        <a href="my_subscriptions.php" class="btn btn-primary"><?php echo __('view_profile'); ?></a>
    <?php else: ?>
        <p><?php echo __('login_message'); ?></p>
        <a href="google_auth_youtube.php" class="btn btn-primary"><?php echo __('login_with_google'); ?></a>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>