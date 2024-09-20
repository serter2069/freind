<?php
require_once 'db_connection.php';
require_once 'translations.php';

header('Content-Type: application/json');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$perPage = 100;
$offset = ($page - 1) * $perPage;

$conn = getDbConnection();

$query = "SELECT id, title, description, thumbnail_url, telegram_link FROM youtube_channels";
$countQuery = "SELECT COUNT(*) as total FROM youtube_channels";
$params = [];
$types = "";

if (!empty($search)) {
    $searchCondition = " WHERE title LIKE ? OR description LIKE ?";
    $query .= $searchCondition;
    $countQuery .= $searchCondition;
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam];
    $types = "ss";
}

$query .= " ORDER BY title LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$channels = $result->fetch_all(MYSQLI_ASSOC);

$countStmt = $conn->prepare($countQuery);
if (!empty($search)) {
    $countStmt->bind_param("ss", $searchParam, $searchParam);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();
$total = $countRow['total'];

$totalPages = ceil($total / $perPage);

$html = '';
foreach ($channels as $channel) {
    $html .= renderChannelCard($channel);
}

echo json_encode([
    'html' => $html,
    'total' => $total,
    'currentPage' => $page,
    'totalPages' => $totalPages
]);

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