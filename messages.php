<?php
session_start();
require_once 'db_connection.php';
require_once 'translations.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = __('messages');
include 'header.php';

$selected_user_id = null;
$error_message = '';

if (isset($_GET['action']) && $_GET['action'] == 'write' && isset($_GET['user']) && is_numeric($_GET['user'])) {
    $selected_user_id = intval($_GET['user']);
    // Проверяем, существует ли пользователь
    $user_exists = checkUserExists($selected_user_id);
    if (!$user_exists) {
        $error_message = __('user_not_found');
        $selected_user_id = null;
    }
} elseif (isset($_GET['user']) && is_numeric($_GET['user'])) {
    $selected_user_id = intval($_GET['user']);
}

$contacts = getContactsWithLastMessages($user_id);

$chat_messages = [];
if ($selected_user_id) {
    $chat_messages = getChatMessages($user_id, $selected_user_id);
    markMessagesAsRead($user_id, $selected_user_id);
}

$unread_counts = getUnreadMessageCounts($user_id);

function checkUserExists($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function getContactsWithLastMessages($user_id) {
    global $conn;
    $query = "SELECT u.id, u.name, u.profile_picture, m.content as last_message, m.created_at as last_message_time, m.sender_id as last_message_sender_id,
                     (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND recipient_id = ? AND read_at IS NULL) as unread_count
              FROM users u
              INNER JOIN (
                  SELECT 
                      CASE 
                          WHEN sender_id = ? THEN recipient_id
                          ELSE sender_id
                      END as contact_id,
                      content,
                      created_at,
                      sender_id
                  FROM messages
                  WHERE sender_id = ? OR recipient_id = ?
                  ORDER BY created_at DESC
              ) m ON u.id = m.contact_id
              WHERE u.id != ?
              GROUP BY u.id
              ORDER BY m.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getChatMessages($user_id, $other_user_id) {
    global $conn;
    $query = "SELECT * FROM messages 
              WHERE (sender_id = ? AND recipient_id = ?) 
                 OR (sender_id = ? AND recipient_id = ?)
              ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function markMessagesAsRead($recipient_id, $sender_id) {
    global $conn;
    $query = "UPDATE messages SET read_at = CURRENT_TIMESTAMP 
              WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $recipient_id, $sender_id);
    $stmt->execute();
    return $stmt->affected_rows;
}

function getUnreadMessageCounts($user_id) {
    global $conn;
    $query = "SELECT sender_id, COUNT(*) as count 
              FROM messages 
              WHERE recipient_id = ? AND read_at IS NULL 
              GROUP BY sender_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts = [];
    while ($row = $result->fetch_assoc()) {
        $counts[$row['sender_id']] = $row['count'];
    }
    return $counts;
}

function getUserById($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getProfilePictureUrl($user_id) {
    $user = getUserById($user_id);
    if ($user && !empty($user['profile_picture'])) {
        return $user['profile_picture'];
    } else {
        return 'path/to/default/avatar.png';
    }
}

function formatMessageDate($date) {
    $now = new DateTime();
    $messageDate = new DateTime($date);
    $diff = $now->diff($messageDate);

    if ($diff->y > 0) {
        return $messageDate->format('d.m.Y');
    } elseif ($diff->m > 0 || $diff->d > 6) {
        return $messageDate->format('d M');
    } elseif ($diff->d > 0) {
        return $messageDate->format('D');
    } else {
        return $messageDate->format('H:i');
    }
}
?>

<div class="container-fluid mt-3 chat-container">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="row h-100">
        <div class="col-md-4 contacts-list">
            <div class="list-group">
                <?php if (empty($contacts) && !$selected_user_id): ?>
                    <div class="alert alert-info">
                        <?php echo __('no_messages_yet'); ?>
                        <a href="people.php"><?php echo __('find_people'); ?></a>
                    </div>
                <?php else: ?>
                    <?php foreach ($contacts as $contact): ?>
                        <a href="?user=<?php echo $contact['id']; ?>" class="list-group-item list-group-item-action <?php echo ($selected_user_id == $contact['id']) ? 'active' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo getProfilePictureUrl($contact['id']); ?>" alt="<?php echo htmlspecialchars($contact['name']); ?>" class="rounded-circle me-2" width="40" height="40">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($contact['name']); ?></h5>
                                        <p class="mb-1 small text-muted">
                                            <?php
                                            $lastMessageSender = ($contact['last_message_sender_id'] == $user_id) ? __('you') : $contact['name'];
                                            echo $lastMessageSender . ': ' . htmlspecialchars(substr($contact['last_message'], 0, 30)) . (strlen($contact['last_message']) > 30 ? '...' : '');
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small><?php echo formatMessageDate($contact['last_message_time']); ?></small>
                                    <?php if (isset($unread_counts[$contact['id']]) && $unread_counts[$contact['id']] > 0): ?>
                                        <span class="badge bg-primary rounded-pill"><?php echo $unread_counts[$contact['id']]; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-8 messages-area">
            <?php if ($selected_user_id): ?>
                <?php 
                $selected_user = getUserById($selected_user_id);
                if ($selected_user):
                ?>
                    <div class="card mb-3 sticky-top">
                        <div class="card-body d-flex align-items-center">
                            <img src="<?php echo getProfilePictureUrl($selected_user_id); ?>" alt="<?php echo htmlspecialchars($selected_user['name']); ?>" class="rounded-circle me-3" width="50" height="50">
                            <h5 class="card-title mb-0">
                                <a href="profile.php?id=<?php echo $selected_user_id; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($selected_user['name']); ?>
                                </a>
                            </h5>
                        </div>
                    </div>
                    <div id="chat-messages" class="mb-3 p-3 bg-light rounded messages-list">
                        <?php foreach ($chat_messages as $message): ?>
                            <div class="message <?php echo ($message['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                                <div class="message-sender">
                                    <?php echo ($message['sender_id'] == $user_id) ? __('you') : htmlspecialchars($selected_user['name']); ?>
                                </div>
                                <div class="message-content <?php echo ($message['sender_id'] == $user_id) ? 'bg-primary text-white' : 'bg-white'; ?> d-inline-block p-2 rounded mb-2">
                                    <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                    <div class="message-time small <?php echo ($message['sender_id'] == $user_id) ? 'text-white-50' : 'text-muted'; ?>">
                                        <?php echo formatMessageDate($message['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="message-input-container" class="message-input">
                        <form id="message-form">
                            <input type="hidden" name="recipient_id" value="<?php echo $selected_user_id; ?>">
                            <div class="input-group">
                                <textarea class="form-control" name="content" rows="1" placeholder="<?php echo __('type_your_message'); ?>"></textarea>
                                <button type="submit" class="btn btn-primary"><?php echo __('send'); ?></button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <?php echo __('user_not_found'); ?>
                    </div>
                <?php endif; ?>
            <?php elseif (empty($contacts)): ?>
                <div class="alert alert-info">
                    <?php echo __('select_contact_to_start_chat'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.chat-container {
    height: calc(100vh - 60px);
    display: flex;
    flex-direction: column;
}
.row.h-100 {
    flex-grow: 1;
}
.contacts-list {
    height: 100%;
    overflow-y: auto;
}
.messages-area {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.messages-list {
    flex-grow: 1;
    overflow-y: auto;
    padding-bottom: 60px;
}
.message-input {
    position: fixed;
    bottom: 0;
    right: 0;
    width: calc(66.666% - 30px);
    background: white;
    padding: 15px;
    border-top: 1px solid #dee2e6;
}
.message {
    margin-bottom: 10px;
}
.message.sent {
    text-align: right;
}
.message-sender {
    font-size: 0.8em;
    margin-bottom: 2px;
    color: #6c757d;
}
.message-content {
    max-width: 70%;
    display: inline-block;
}
.sticky-top {
    top: 0;
    z-index: 1020;
}
textarea[name="content"] {
    resize: none;
    overflow-y: hidden;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    var chatMessages = $('#chat-messages');
    var messageInput = $('textarea[name="content"]');
    var messageForm = $('#message-form');
    var lastMessageTime = '<?php echo !empty($chat_messages) ? end($chat_messages)['created_at'] : ''; ?>';
    
    chatMessages.scrollTop(chatMessages[0].scrollHeight);

    messageForm.on('submit', function(e) {
        e.preventDefault();
        sendMessage();
    });

    messageInput.on('keydown', function(e) {
        if (e.keyCode === 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    messageInput.on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
        var maxHeight = window.innerHeight * 0.25; // 25% от высоты экрана
        if (this.scrollHeight > maxHeight) {
            $(this).css('overflow-y', 'auto');
            $(this).css('height', maxHeight + 'px');
        } else {
            $(this).css('overflow-y', 'hidden');
        }
    });

    function sendMessage() {
        var content = messageInput.val().trim();
        if (content) {
            var submitButton = messageForm.find('button[type="submit"]');
            submitButton.prop('disabled', true);
            $.ajax({
                url: 'send_message.php',
                method: 'POST',
                data: messageForm.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        messageInput.val('');
                        messageInput.trigger('input');
                        addMessageToChat(response.message);
                        lastMessageTime = response.message.created_at;
                    } else {
                        alert('<?php echo __("error_sending_message"); ?>: ' + response.message);
                    }
                },
                error: function() {
                    alert('<?php echo __("error_sending_message"); ?>');
                },
                complete: function() {
                    submitButton.prop('disabled', false);
                }
            });
        }
    }

    function addMessageToChat(message) {
        var messageHtml = '<div class="message sent" data-id="' + message.id + '">' +
            '<div class="message-sender"><?php echo __('you'); ?></div>' +
            '<div class="message-content bg-primary text-white d-inline-block p-2 rounded mb-2">' +
            message.content +
            '<div class="message-time small text-white-50">' +
            formatMessageDate(message.created_at) +
            '</div>' +
            '</div>' +
            '</div>';
        chatMessages.append(messageHtml);
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    }

    function formatMessageDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    function loadNewMessages() {
        $.ajax({
            url: 'get_new_messages.php',
            method: 'GET',
            data: {
                user: <?php echo $selected_user_id ?? 0; ?>,
                last_time: lastMessageTime
            },
            dataType: 'json',
            success: function(response) {
                if (response.messages && response.messages.length > 0) {
                    response.messages.forEach(function(message) {
                        if (message.sender_id != <?php echo $user_id; ?>) {
                            addReceivedMessageToChat(message);
                        }
                    });
                    lastMessageTime = response.messages[response.messages.length - 1].created_at;
                }
                if (response.unread_counts) {
                    updateUnreadCounts(response.unread_counts);
                }
            }
        });
    }

    function addReceivedMessageToChat(message) {
        if ($('.message[data-id="' + message.id + '"]').length === 0) {
            var messageHtml = '<div class="message received" data-id="' + message.id + '">' +
                '<div class="message-sender"><?php echo htmlspecialchars($selected_user['name'] ?? ''); ?></div>' +
                '<div class="message-content bg-white d-inline-block p-2 rounded mb-2">' +
                message.content +
                '<div class="message-time small text-muted">' +
                formatMessageDate(message.created_at) +
                '</div>' +
                '</div>' +
                '</div>';
            chatMessages.append(messageHtml);
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
        }
    }

    function updateUnreadCounts(unreadCounts) {
        $('.list-group-item').each(function() {
            var userId = $(this).attr('href').split('=')[1];
            var badgeElement = $(this).find('.badge');
            if (unreadCounts[userId] && unreadCounts[userId] > 0) {
                if (badgeElement.length) {
                    badgeElement.text(unreadCounts[userId]);
                } else {
                    $(this).find('.text-end').append('<span class="badge bg-primary rounded-pill">' + unreadCounts[userId] + '</span>');
                }
            } else {
                badgeElement.remove();
            }
        });
    }

    setInterval(loadNewMessages, 5000);
});
</script>

<?php include 'footer.php'; ?>