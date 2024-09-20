<?php
require_once 'db_connection.php';
require_once 'translations.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = __('bubbles');
include 'header.php';

$conn = getDbConnection();

// Получаем значение поиска из GET параметра
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

function renderChannelCard($channel) {
    ob_start();
    ?>
    <div class="col-md-8 mb-3 channel-card">
        <div class="card">
            <div class="row g-0 align-items-center">
                <div class="col-auto">
                    <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>" 
                         class="rounded-circle m-3" 
                         alt="<?php echo htmlspecialchars($channel['title']); ?>"
                         style="width: 88px; height: 88px; object-fit: cover;">
                </div>
                <div class="col">
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="bubble.php?id=<?php echo $channel['id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($channel['title']); ?>
                            </a>
                        </h5>
                        <p class="card-text" style="font-size: 0.9em; max-height: 3.6em; overflow: hidden;">
                            <?php echo htmlspecialchars(substr($channel['description'], 0, 270)) . (strlen($channel['description']) > 270 ? '...' : ''); ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <a href="people.php?channels=<?php echo $channel['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                    <?php echo __('find_people'); ?>
                                </a>
                            </div>
                            <?php if (!empty($channel['telegram_link'])): ?>
                                <a href="<?php echo htmlspecialchars($channel['telegram_link']); ?>" class="btn btn-primary btn-sm me-2" target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-telegram-plane"></i> <?php echo __('join_community'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<div class="container mt-4">
    <h1 class="text-center mb-4"><?php echo __('bubbles'); ?></h1>
    
    <div class="row justify-content-center mb-4">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" id="channelSearch" class="form-control" placeholder="<?php echo __('search_channels'); ?>" value="<?php echo $search; ?>">
                <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                    <i class="fas fa-times"></i>
                </button>
                <button class="btn btn-primary" id="searchButton"><?php echo __('search'); ?></button>
            </div>
        </div>
    </div>
    
    <div id="loadingIndicator" class="text-center" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden"><?php echo __('loading'); ?></span>
        </div>
        <p><?php echo __('searching'); ?></p>
    </div>

    <div id="channelList" class="row justify-content-center">
        <!-- Channels will be loaded here -->
    </div>

    <div id="noResultsMessage" class="alert alert-info text-center" style="display: none;">
        <?php echo __('no_channels_found'); ?>
        <p><?php echo __('channels_explanation'); ?></p>
        <p><?php echo __('subscribe_and_register'); ?></p>
    </div>

    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center" id="pagination">
            <!-- Pagination will be generated here -->
        </ul>
    </nav>
</div>

<style>
.channel-card {
    height: 100%;
}
.channel-card .card-text {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    var $channelSearch = $('#channelSearch');
    var $searchButton = $('#searchButton');
    var $clearSearch = $('#clearSearch');
    var $channelList = $('#channelList');
    var $noResultsMessage = $('#noResultsMessage');
    var $pagination = $('#pagination');
    var $loadingIndicator = $('#loadingIndicator');
    var currentPage = 1;
    var totalPages = 1;

    function loadChannels(page, searchTerm = '') {
        $channelList.hide();
        $noResultsMessage.hide();
        $pagination.hide();
        $loadingIndicator.show();

        $.ajax({
            url: 'get_channels.php',
            method: 'GET',
            data: { page: page, search: searchTerm },
            dataType: 'json',
            success: function(response) {
                $loadingIndicator.hide();
                $channelList.html(response.html).show();
                if (response.total > 0) {
                    updatePagination(response.currentPage, response.totalPages);
                } else {
                    $noResultsMessage.show();
                }
                // Обновляем URL с параметром поиска
                var newUrl = updateQueryStringParameter(window.location.href, 'search', searchTerm);
                history.pushState(null, '', newUrl);
            },
            error: function() {
                $loadingIndicator.hide();
                console.error('Error loading channels');
            }
        });
    }

    function updatePagination(currentPage, totalPages) {
        var paginationHtml = '';
        for (var i = 1; i <= totalPages; i++) {
            paginationHtml += '<li class="page-item ' + (i === currentPage ? 'active' : '') + '">' +
                              '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
        }
        $pagination.html(paginationHtml).show();
    }

    function performSearch() {
        currentPage = 1;
        loadChannels(currentPage, $channelSearch.val());
    }

    $searchButton.on('click', performSearch);

    $channelSearch.on('keypress', function(e) {
        if (e.which === 13) {
            performSearch();
        }
    });

    $clearSearch.on('click', function() {
        $channelSearch.val('');
        performSearch();
    });

    $pagination.on('click', '.page-link', function(e) {
        e.preventDefault();
        currentPage = parseInt($(this).data('page'));
        loadChannels(currentPage, $channelSearch.val());
    });

    // Функция для обновления параметра в URL
    function updateQueryStringParameter(uri, key, value) {
        var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
        var separator = uri.indexOf('?') !== -1 ? "&" : "?";
        if (uri.match(re)) {
            return uri.replace(re, '$1' + key + "=" + value + '$2');
        }
        else {
            return uri + separator + key + "=" + value;
        }
    }

    // Initial load
    loadChannels(currentPage, '<?php echo $search; ?>');
});
</script>

<?php include 'footer.php'; ?>