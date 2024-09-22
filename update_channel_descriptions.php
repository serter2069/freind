<?php
require_once 'db_connection.php';
require_once 'vendor/autoload.php';

use GuzzleHttp\Client;

// Функция для логирования
function logMessage($message) {
    echo date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
}

// Функция для получения каналов без AI-описания
function getChannelsWithoutDescription($conn, $limit = 10) {
    logMessage("Fetching up to $limit channels without AI description...");
    $stmt = $conn->prepare("
        SELECT id, channel_id, title
        FROM youtube_channels
        WHERE ai_description_generated = 0
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $channels = $result->fetch_all(MYSQLI_ASSOC);
    logMessage("Found " . count($channels) . " channels to process.");
    return $channels;
}

// Функция для генерации описания с помощью Perplexity API
function generateDescriptionWithPerplexity($channelUrl) {
    global $perplexity_API_key;
    logMessage("Generating description for channel: $channelUrl");
    
    $client = new Client();
    
    $prompt = "Provide a concise description (max 400 characters) in English about the YouTube channel: {$channelUrl}. Focus on the content type, main topics, and target audience.";

    try {
        logMessage("Sending request to Perplexity API...");
        $response = $client->post('https://api.perplexity.ai/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $perplexity_API_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'llama-3.1-sonar-small-128k-online',
                'messages' => [
                    ['role' => 'system', 'content' => 'Be precise and concise.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 400,
                'temperature' => 0.2,
                'top_p' => 0.9,
                'return_citations' => false,
                'stream' => false,
            ],
        ]);

        $result = json_decode($response->getBody(), true);
        $description = $result['choices'][0]['message']['content'];
        logMessage("Description generated successfully. Length: " . strlen($description) . " characters.");
        return $description;
    } catch (Exception $e) {
        logMessage("Error generating description: " . $e->getMessage());
        return null;
    }
}

// Функция для обновления описания канала в базе данных
function updateChannelDescription($conn, $channelId, $description) {
    logMessage("Updating description for channel ID: $channelId");
    $stmt = $conn->prepare("
        UPDATE youtube_channels
        SET ai_generated_description = ?,
            ai_description_generated = 1,
            last_ai_description_update = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->bind_param("si", $description, $channelId);
    $result = $stmt->execute();
    if ($result) {
        logMessage("Description updated successfully in database.");
    } else {
        logMessage("Failed to update description in database. Error: " . $stmt->error);
    }
}

// Основной код скрипта
logMessage("Script started.");
$conn = getDbConnection();
logMessage("Database connection established.");

$channels = getChannelsWithoutDescription($conn);

foreach ($channels as $channel) {
    logMessage("Processing channel: " . $channel['title'] . " (ID: " . $channel['id'] . ")");
    $channelUrl = "https://www.youtube.com/channel/" . $channel['channel_id'];
    $description = generateDescriptionWithPerplexity($channelUrl);
    
    if ($description) {
        updateChannelDescription($conn, $channel['id'], $description);
        logMessage("Channel processed successfully: " . $channel['title']);
    } else {
        logMessage("Failed to generate description for channel: " . $channel['title']);
    }
    
    logMessage("Waiting for 2 seconds before processing next channel...");
    sleep(2);
}

$conn->close();
logMessage("Database connection closed.");
logMessage("Script completed.");