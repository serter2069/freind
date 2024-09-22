<?php
require_once 'db_connection.php';
require_once 'vendor/autoload.php';

function logMessage($message, $level = 'INFO') {
    $date = date('[Y-m-d H:i:s]');
    echo "$date [$level] $message" . PHP_EOL;
}

function getUsersNeedingDescription($conn) {
    logMessage("Fetching users needing description...");
    $stmt = $conn->prepare("
        SELECT u.id, u.gender, u.preferred_language, u.import_status, u.ai_description_generated
        FROM users u
        WHERE (u.ai_description_generated IS NULL OR u.ai_description_generated != 1)
        AND u.gender IS NOT NULL
        AND u.gender != ''
        AND u.import_status = 'completed'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    logMessage("Found " . count($users) . " users needing description.");
    foreach ($users as $user) {
        logMessage("User ID: {$user['id']}");
        logMessage("  Gender: {$user['gender']}");
        logMessage("  Preferred Language: {$user['preferred_language']}");
        logMessage("  Import Status: {$user['import_status']}");
        logMessage("  AI Description Generated: " . ($user['ai_description_generated'] === null ? "NULL" : $user['ai_description_generated']));
        logMessage("--------------------");
    }
    return $users;
}

function getUserSubscriptions($conn, $userId) {
    logMessage("Fetching subscriptions for user ID: $userId");
    $stmt = $conn->prepare("
        SELECT yc.channel_id, yc.ai_generated_description
        FROM user_subscriptions us
        JOIN youtube_channels yc ON us.channel_id = yc.channel_id
        WHERE us.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriptions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    logMessage("Found " . count($subscriptions) . " subscriptions for user ID: $userId");
    return $subscriptions;
}

function generateUserDescription($subscriptions, $gender, $preferred_language) {
    global $googleAI_API_key;

    logMessage("Generating description for user (gender: $gender, preferred language: $preferred_language)");

    $descriptions = array_column($subscriptions, 'ai_generated_description');
    $descriptions = array_filter($descriptions);

    if (empty($descriptions)) {
        logMessage("No valid channel descriptions found for this user.");
        return null;
    }

    $prompt = getPromptForLanguage($preferred_language, $gender, $descriptions);
    logMessage("Generated prompt: " . substr($prompt, 0, 200) . "...");

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=" . $googleAI_API_key;
    
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "topK" => 40,
            "topP" => 0.95,
            "maxOutputTokens" => 1024,
        ]
    ];

    logMessage("Sending request to Google AI API...");
    logMessage("Request data: " . json_encode($data, JSON_PRETTY_PRINT));

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);

    try {
        $result = file_get_contents($url, false, $context);
        if ($result === FALSE) {
            throw new Exception("Error calling Google AI API");
        }

        logMessage("API Response: " . $result);

        $response = json_decode($result, true);
        
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $generatedDescription = $response['candidates'][0]['content']['parts'][0]['text'];
            logMessage("Description successfully generated. Length: " . strlen($generatedDescription) . " characters");
            logMessage("Generated description: " . substr($generatedDescription, 0, 200) . "...");
            return $generatedDescription;
        } else {
            throw new Exception("Unexpected response format from Google AI API");
        }
    } catch (Exception $e) {
        logMessage("Error generating description: " . $e->getMessage());
        return null;
    }
}

function getPromptForLanguage($language, $gender, $descriptions) {
    $genderTerm = ($gender == 'male') ? 'man' : (($gender == 'female') ? 'woman' : 'person');
    $descriptionsText = implode("\n", $descriptions);

    logMessage("Preparing prompt for language: $language, gender: $genderTerm");

    $languageNames = [
        'ru' => 'Russian',
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ko' => 'Korean'
    ];

    $languageName = $languageNames[$language] ?? 'English';

    $prompt = "Based on the following descriptions of YouTube channels the user is subscribed to, create a complimentary description of this $genderTerm in $languageName. The description should be positive and reflect the user's interests and character. The response must be in $languageName only. Do not translate, use $languageName exclusively:\n\n$descriptionsText";

    logMessage("Generated prompt in $languageName for $genderTerm");
    return $prompt;
}

function updateUserDescription($conn, $userId, $description) {
    logMessage("Updating description for user ID: $userId");
    $stmt = $conn->prepare("
        UPDATE users
        SET ai_generated_description = ?,
            ai_description_generated = 1,
            last_ai_description_update = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->bind_param("si", $description, $userId);
    $result = $stmt->execute();
    if ($result) {
        logMessage("Description successfully updated for user ID: $userId");
    } else {
        logMessage("Failed to update description for user ID: $userId. Error: " . $stmt->error);
    }
    $stmt->close();
}

logMessage("Script started.");
$conn = getDbConnection();
logMessage("Database connection established.");

$users = getUsersNeedingDescription($conn);

foreach ($users as $user) {
    logMessage("Processing user ID: " . $user['id']);
    $subscriptions = getUserSubscriptions($conn, $user['id']);
    
    if (count($subscriptions) > 1) {
        logMessage("User has " . count($subscriptions) . " subscriptions.");
        $allHaveDescription = true;
        foreach ($subscriptions as $subscription) {
            if (empty($subscription['ai_generated_description'])) {
                $allHaveDescription = false;
                logMessage("Channel ID: " . $subscription['channel_id'] . " does not have an AI-generated description.");
                break;
            }
        }
        
        if ($allHaveDescription) {
            logMessage("All subscribed channels have AI-generated descriptions. Proceeding to generate user description.");
            $description = generateUserDescription($subscriptions, $user['gender'], $user['preferred_language']);
            if ($description) {
                updateUserDescription($conn, $user['id'], $description);
            } else {
                logMessage("Failed to generate description for user ID: " . $user['id']);
            }
        } else {
            logMessage("Not all subscribed channels have AI-generated descriptions. Skipping user ID: " . $user['id']);
        }
    } else {
        logMessage("User ID: " . $user['id'] . " has less than 2 subscriptions. Skipping.");
    }
    logMessage("--------------------");
}

$conn->close();
logMessage("Database connection closed.");
logMessage("Script completed.");
?>