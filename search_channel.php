<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';

header('Content-Type: application/json');

$searchTerm = $_GET['q'] ?? '';
$page = $_GET['page'] ?? 1;
$perPage = 30;
$mode = $_GET['mode'] ?? 'autocomplete';

error_log("-------- New Search Request --------");
error_log("Search Channel - Input: searchTerm=" . $searchTerm . ", mode=" . $mode . ", page=" . $page);
error_log("PHP Version: " . phpversion());
error_log("Loaded extensions: " . implode(", ", get_loaded_extensions()));

function extractChannelIdFromUrl($url) {
    error_log("Extracting channel ID from URL: " . $url);
    $patterns = [
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/channel\/([a-zA-Z0-9_-]+)/' => 'channel',
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/user\/([a-zA-Z0-9_-]+)/' => 'user',
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/c\/([a-zA-Z0-9_-]+)/' => 'custom',
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/@([a-zA-Z0-9_-]+)/' => 'handle'
    ];

    foreach ($patterns as $pattern => $type) {
        error_log("Trying pattern for type '$type': $pattern");
        if (preg_match($pattern, $url, $matches)) {
            error_log("Matched pattern type: " . $type . ", extracted ID: " . $matches[1]);
            return $matches[1];
        }
    }

    error_log("No channel ID extracted from URL. All patterns failed.");
    return null;
}

function searchChannels($conn, $searchTerm, $mode, $page, $perPage) {
    error_log("Searching channels: mode=" . $mode . ", searchTerm=" . $searchTerm);
    
    if ($mode === 'url') {
        $channelId = extractChannelIdFromUrl($searchTerm);
        error_log("Extracted Channel ID: " . ($channelId ? $channelId : "NULL"));
        
        if (!$channelId) {
            error_log("Invalid channel URL or unable to extract channel ID");
            return ['items' => [], 'total_count' => 0];
        }

        $query = "SELECT id, channel_id, title, description, thumbnail_url FROM youtube_channels WHERE channel_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $channelId);
        error_log("SQL Query (URL mode): " . $query);
        error_log("Query parameters: channelId = " . $channelId);
    } else {
        $query = "SELECT id, channel_id, title, description, thumbnail_url FROM youtube_channels WHERE title LIKE ? OR description LIKE ? LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        $searchTermLike = "%$searchTerm%";
        $offset = ($page - 1) * $perPage;
        $stmt->bind_param("ssii", $searchTermLike, $searchTermLike, $perPage, $offset);
        error_log("SQL Query (autocomplete mode): " . $query);
        error_log("Query parameters: searchTerm = " . $searchTermLike . ", limit = " . $perPage . ", offset = " . $offset);
    }

    $startTime = microtime(true);
    $stmt->execute();
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
    error_log("Query execution time: " . $executionTime . " seconds");

    $result = $stmt->get_result();
    $channels = $result->fetch_all(MYSQLI_ASSOC);
    error_log("Number of results: " . count($channels));
    error_log("Query result: " . json_encode($channels));

    if (empty($channels)) {
        error_log("No channels found for the given search term: " . $searchTerm);
    }

    $totalCount = 0;
    if ($mode !== 'url') {
        $countQuery = "SELECT COUNT(*) as total FROM youtube_channels WHERE title LIKE ? OR description LIKE ?";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param("ss", $searchTermLike, $searchTermLike);
        error_log("Count Query: " . $countQuery);
        error_log("Count Query parameters: searchTerm = " . $searchTermLike);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalCount = $countResult->fetch_assoc()['total'];
        error_log("Total count: " . $totalCount);
    }

    return ['items' => $channels, 'total_count' => $totalCount];
}

try {
    $startTime = microtime(true);
    
    $conn = getDbConnection();
    error_log("Database connection: " . $conn->host_info);
    
    if (!filter_var($searchTerm, FILTER_VALIDATE_URL) && $mode === 'url') {
        error_log("Invalid URL provided: " . $searchTerm);
    }
    
    $searchResults = searchChannels($conn, $searchTerm, $mode, $page, $perPage);
    
    $endTime = microtime(true);
    $totalExecutionTime = $endTime - $startTime;
    error_log("Total execution time: " . $totalExecutionTime . " seconds");
    
    error_log("Final search results: " . json_encode($searchResults));
    error_log("Pagination: page=" . $page . ", perPage=" . $perPage . ", totalResults=" . $searchResults['total_count']);

    echo json_encode($searchResults);
} catch (Exception $e) {
    error_log("Search Channel Error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

error_log("-------- End of Search Request --------");