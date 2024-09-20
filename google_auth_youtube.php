<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';
require_once 'vendor/autoload.php';

function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/debug.log');
}

debug_log("Google Auth YouTube script started");

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope([
    Google_Service_YouTube::YOUTUBE_READONLY,
    Google_Service_Oauth2::USERINFO_EMAIL,
    Google_Service_Oauth2::USERINFO_PROFILE
]);

$client->setAccessType('offline');
$client->setPrompt('consent');

debug_log("Client Configuration: ClientID: " . substr(GOOGLE_CLIENT_ID, 0, 8) . "..., RedirectURI: " . GOOGLE_REDIRECT_URI);

if (isset($_GET['code'])) {
    debug_log("Received Code: " . $_GET['code']);

    try {
        debug_log("Fetching Access Token");
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        debug_log("Token received: " . json_encode($token));
        
        if (isset($token['error'])) {
            throw new Exception('Error fetching access token: ' . ($token['error_description'] ?? $token['error']));
        }
        
        $client->setAccessToken($token);

        debug_log("Getting User Info");
        $oauth2 = new Google_Service_Oauth2($client);
        $userInfo = $oauth2->userinfo->get();

        debug_log("User Info: " . json_encode($userInfo));

        $googleId = $userInfo->getId();
        $email = $userInfo->getEmail();
        $name = $userInfo->getName();
        $profilePicture = $userInfo->getPicture();

        debug_log("Checking User in Database");
        $user = getUserByGoogleId($googleId);

        if (!$user) {
            debug_log("User not found. Creating new user");
            $userId = createUser($googleId, $email, $name, $profilePicture);
            debug_log("New user created with ID: " . $userId);
            $_SESSION['new_user'] = true;
        } else {
            debug_log("User found with ID: " . $user['id']);
            $userId = $user['id'];
            if ($profilePicture !== $user['profile_picture']) {
                updateUserProfilePicture($userId, $profilePicture);
                debug_log("Profile picture updated for user ID: " . $userId);
            }
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $name;

        if (isset($token['refresh_token'])) {
            saveRefreshToken($userId, $token['refresh_token']);
            debug_log("Refresh Token Saved");
        } else {
            debug_log("No Refresh Token Received");
        }

        $_SESSION['access_token'] = $token['access_token'];

        debug_log("Authentication Successful!");

        if (isset($_SESSION['new_user']) && $_SESSION['new_user']) {
            unset($_SESSION['new_user']);
            header("Location: import_subscriptions.php");
        } else {
            header("Location: my_subscriptions.php");
        }
        exit();
    } catch (Exception $e) {
        debug_log("Error Occurred: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        header("Location: index.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    debug_log("No Code Received. Initiating OAuth Flow");
    $authUrl = $client->createAuthUrl();
    debug_log("Auth URL: " . $authUrl);
    header("Location: " . $authUrl);
    exit();
}

function getUserByGoogleId($googleId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE google_user_id = ?");
    $stmt->bind_param("s", $googleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    debug_log("getUserByGoogleId: " . json_encode($user));
    return $user;
}

function createUser($googleId, $email, $name, $profilePicture) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO users (google_user_id, email, name, profile_picture, registration_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $googleId, $email, $name, $profilePicture);
    $stmt->execute();
    $userId = $stmt->insert_id;
    $stmt->close();
    debug_log("createUser: New user created with ID " . $userId);
    return $userId;
}

function updateUserProfilePicture($userId, $profilePicture) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    $stmt->bind_param("si", $profilePicture, $userId);
    $stmt->execute();
    $stmt->close();
    debug_log("updateUserProfilePicture: Updated for user ID " . $userId);
}

function saveRefreshToken($userId, $refreshToken) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET google_refresh_token = ? WHERE id = ?");
    $stmt->bind_param("si", $refreshToken, $userId);
    $stmt->execute();
    $stmt->close();
    debug_log("saveRefreshToken: Refresh token saved for user " . $userId);
}