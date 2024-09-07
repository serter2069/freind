<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';
require_once 'functions.php';
require_once 'vendor/autoload.php';

function debug_log($message) {
    error_log($message);
}

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

debug_log("Client Configuration:");
debug_log("Client ID: " . $client->getClientId());
debug_log("Redirect URI: " . $client->getRedirectUri());
debug_log("Scopes: " . implode(", ", $client->getScopes()));

if (isset($_GET['code'])) {
    debug_log("Received Code: " . $_GET['code']);

    try {
        debug_log("Fetching Access Token:");
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        debug_log("Token received: " . print_r($token, true));
        
        if (isset($token['error'])) {
            throw new Exception('Error fetching access token: ' . ($token['error_description'] ?? $token['error']));
        }
        
        $client->setAccessToken($token);

        debug_log("Getting User Info:");
        $oauth2 = new Google_Service_Oauth2($client);
        $userInfo = $oauth2->userinfo->get();

        debug_log("User Info: " . print_r($userInfo, true));

        $googleId = $userInfo->getId();
        $email = $userInfo->getEmail();
        $name = $userInfo->getName();

        debug_log("Checking User in Database:");
        $user = getUserByGoogleId($googleId);

        if (!$user) {
            debug_log("User not found. Creating new user...");
            $userId = createUser($googleId, $email, $name);
            debug_log("New user created with ID: " . $userId);
            $_SESSION['new_user'] = true;
        } else {
            debug_log("User found with ID: " . $user['id']);
            $userId = $user['id'];
        }

        if (isset($token['refresh_token'])) {
            saveRefreshToken($userId, $token['refresh_token']);
            debug_log("Refresh Token Saved");
        } else {
            debug_log("No Refresh Token Received");
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['access_token'] = $token['access_token'];

        debug_log("Authentication Successful!");
        header("Location: my_subscriptions.php");
        exit();
    } catch (Exception $e) {
        debug_log("Error Occurred:");
        debug_log($e->getMessage() . "\n\n" . $e->getTraceAsString());
        header("Location: index.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    debug_log("No Code Received. Initiating OAuth Flow:");
    $authUrl = $client->createAuthUrl();
    debug_log("Auth URL: " . $authUrl);
    header("Location: " . $authUrl);
    exit();
}