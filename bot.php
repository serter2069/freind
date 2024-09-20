<?php
require_once 'db_connection.php';
require_once 'translations.php';

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];

    if (strpos($text, '/start') === 0) {
        $parts = explode(' ', $text);
        if (isset($parts[1])) {
            $telegram_hash = $parts[1];
            $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_hash = ?");
            $stmt->bind_param("s", $telegram_hash);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                $user_id = $user['id'];
                $stmt->close();
                
                $stmt = $conn->prepare("UPDATE users SET telegram_chat_id = ?, telegram_notifications = 1, telegram_hash = NULL WHERE id = ?");
                $stmt->bind_param("si", $chat_id, $user_id);
                if ($stmt->execute()) {
                    sendTelegramMessage($chat_id, __('telegram_connected_successfully'));
                    $return_url = "https://svpmodels.com/edit_profile.php";
                    sendTelegramMessage($chat_id, __('return_to_profile', ['url' => $return_url]));
                } else {
                    sendTelegramMessage($chat_id, __('telegram_connection_error'));
                }
                $stmt->close();
            } else {
                sendTelegramMessage($chat_id, __('telegram_invalid_hash'));
            }
        } else {
            sendTelegramMessage($chat_id, __('telegram_start_message'));
        }
    } else {
        sendTelegramMessage($chat_id, __('telegram_unknown_command'));
    }
}

function sendTelegramMessage($chat_id, $text) {
    global $token;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    file_get_contents($url . '?' . http_build_query($data));
}