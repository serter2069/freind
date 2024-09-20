<?php
require_once 'db_connection.php';
require_once 'functions.php';

header('Content-Type: application/json');

$videoId = $_GET['video_id'] ?? '';

if (empty($videoId)) {
    echo json_encode(['error' => 'No video ID provided']);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT c.id, c.channel_id, c.title, c.description, c.thumbnail_url 
    FROM youtube_channels c
    JOIN youtube_videos v ON c.channel_id = v.channel_id
    WHERE v.video_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $videoId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'channel' => [
            'id' => $row['id'],
            'channel_id' => $row['channel_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'thumbnail_url' => $row['thumbnail_url']
        ]
    ]);
} else {
    echo json_encode(['error' => 'Channel not found']);
}