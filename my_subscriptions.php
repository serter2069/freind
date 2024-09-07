<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'translations.php';
$page_title = __('my_subscriptions');
include 'header.php';
require_once 'db_connection.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_SESSION['new_user']) && $_SESSION['new_user']) {
    $client = getGoogleClient();
    $subscriptionsAdded = fetchAndSaveUserSubscriptions($user_id, $client);
    if ($subscriptionsAdded) {
        $_SESSION['message'] = __('subscriptions_added_successfully');
    } else {
        $_SESSION['message'] = __('error_adding_subscriptions');
    }
    unset($_SESSION['new_user']);
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$totalSubscriptions = getTotalUserSubscriptions($user_id);
$totalPages = ceil($totalSubscriptions / $perPage);

$subscriptions = getUserSubscriptionsWithPagination($user_id, $offset, $perPage);
?>

<div class="container" style="max-width: 800px;">
    <h1 class="mt-4 mb-4"><?php echo __('my_subscriptions'); ?></h1>
    <p class="mb-4"><?php echo __('subscriptions_explanation'); ?></p>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <div id="subscriptionsList">
        <?php foreach ($subscriptions as $subscription): ?>
            <div class="subscription-item" data-channel-id="<?php echo $subscription['channel_id']; ?>">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <img src="<?php echo $subscription['thumbnail_url']; ?>" alt="<?php echo $subscription['title']; ?>" width="88" height="88" class="img-thumbnail">
                    </div>
                    <div class="col">
                        <h3 class="subscription-title">
                            <a href="https://www.youtube.com/channel/<?php echo $subscription['channel_id']; ?>" target="_blank">
                                <?php echo $subscription['title']; ?>
                            </a>
                        </h3>
                        <p class="subscription-description text-muted">
                            <?php echo substr($subscription['description'], 0, 300) . (strlen($subscription['description']) > 300 ? '...' : ''); ?>
                        </p>
                    </div>
                    <!-- Внутри цикла foreach для каждой подписки -->
<div class="col-auto">
    <button class="btn btn-link remove-subscription" data-channel-id="<?php echo $subscription['channel_id']; ?>" title="<?php echo __('remove'); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
        </svg>
    </button>
</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <button id="updateSubscriptions" class="btn btn-primary mt-3 mb-4">
        <?php echo __('update_subscriptions'); ?>
    </button>
</div>

<script>
document.getElementById('updateSubscriptions').addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<?php echo __('updating'); ?>';
    
    fetch('update_subscriptions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert('<?php echo __('update_error'); ?>');
            this.disabled = false;
            this.innerHTML = '<?php echo __('update_subscriptions'); ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo __('update_error'); ?>');
        this.disabled = false;
        this.innerHTML = '<?php echo __('update_subscriptions'); ?>';
    });
});

document.querySelectorAll('.remove-subscription').forEach(button => {
    button.addEventListener('click', function() {
        const channelId = this.getAttribute('data-channel-id');
        const item = this.closest('.subscription-item');

        fetch('remove_subscription.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ channel_id: channelId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                item.remove();
            } else {
                alert('<?php echo __('error_removing_subscription'); ?>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php echo __('error_removing_subscription'); ?>');
        });
    });
});
</script>

<style>
.subscription-item {
    padding: 15px 0;
    border-bottom: 1px solid #e9ecef;
}
.subscription-item:last-child {
    border-bottom: none;
}
.subscription-item:hover {
    background-color: #f8f9fa;
}
.subscription-title {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}
.subscription-description {
    font-size: 0.9rem;
    line-height: 1.4;
}
.remove-subscription {
    color: #ced4da;
    transition: all 0.3s ease;
    padding: 0;
    opacity: 0;
}
.remove-subscription:hover {
    color: #dc3545;
}
.subscription-item:hover .remove-subscription {
    opacity: 1;
}
</style>

<?php include 'footer.php'; ?>