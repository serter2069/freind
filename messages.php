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

// Обработка запроса на создание нового чата
if (isset($_GET['action']) && $_GET['action'] === 'write' && isset($_GET['user'])) {
    $other_user_id = intval($_GET['user']);
    if (getOrCreateChat($user_id, $other_user_id)) {
        $selected_user_id = $other_user_id;
    } else {
        $error_message = __('error_creating_chat');
    }
}

// Получение списка контактов с последними сообщениями
$contacts = getContactsWithLastMessages($user_id);

// Если выбран пользователь, получаем историю чата
$chat_messages = [];
if ($selected_user_id || (isset($_GET['user']) && is_numeric($_GET['user']))) {
    $selected_user_id = $selected_user_id ?: intval($_GET['user']);
    $chat_messages = getChatMessages($user_id, $selected_user_id);
    markMessagesAsRead($user_id, $selected_user_id);
}

// Получение количества непрочитанных сообщений
$unread_counts = getUnreadMessageCounts($user_id);

// Функции, ранее находившиеся в functions.php
function getOrCreateChat($user_id, $other_user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT id FROM messages 
        WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
        LIMIT 1
    ");
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        $stmt = $conn->prepare("
            INSERT INTO messages (sender_id, recipient_id, content, created_at)
            VALUES (?, ?, '', NOW())
        ");
        $stmt->bind_param("ii", $user_id, $other_user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

function getContactsWithLastMessages($user_id) {
    global $conn;
    $query = "SELECT u.id, u.name, u.profile_picture, m.content as last_message, m.created_at as last_message_time,
                     (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND recipient_id = ? AND read_at IS NULL) as unread_count
              FROM users u
              INNER JOIN (
                  SELECT 
                      CASE 
                          WHEN sender_id = ? THEN recipient_id
                          ELSE sender_id
                      END as contact_id,
                      content,
                      created_at
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
        return 'path/to/default/avatar.png'; // Замените на путь к вашему изображению по умолчанию
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

<div class="container mt-4">
    <h1 class="text-center mb-4"><?php echo __('messages'); ?></h1>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (empty($contacts)): ?>
        <div class="alert alert-info">
            <?php echo __('no_messages_yet'); ?>
            <a href="people.php"><?php echo __('find_people'); ?></a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-4">
                <div class="list-group">
                    <?php foreach ($contacts as $contact): ?>
                        <a href="?user=<?php echo $contact['id']; ?>" class="list-group-item list-group-item-action <?php echo ($selected_user_id == $contact['id']) ? 'active' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo getProfilePictureUrl($contact['id']); ?>" alt="<?php echo htmlspecialchars($contact['name']); ?>" class="rounded-circle me-2" width="40" height="40">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($contact['name']); ?></h5>
                                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars(substr($contact['last_message'], 0, 30)) . (strlen($contact['last_message']) > 30 ? '...' : ''); ?></p>
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
                </div>
            </div>
            <div class="col-md-8">
                <?php if ($selected_user_id): ?>
                    <?php 
                    $selected_user = getUserById($selected_user_id);
                    if ($selected_user):
                    ?>
                        <div class="card mb-3">
                            <div class="card-body d-flex align-items-center">
                                <img src="<?php echo getProfilePictureUrl($selected_user_id); ?>" alt="<?php echo htmlspecialchars($selected_user['name']); ?>" class="rounded-circle me-3" width="50" height="50">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($selected_user['name']); ?></h5>
                            </div>
                        </div>
                        <div id="chat-messages" class="mb-3 p-3 bg-light rounded" style="height: 400px; overflow-y: auto;">
                            <?php foreach ($chat_messages as $message): ?>
                                <div class="message <?php echo ($message['sender_id'] == $user_id) ? 'text-end' : ''; ?>">
                                    <div class="message-content <?php echo ($message['sender_id'] == $user_id) ? 'bg-primary text-white' : 'bg-white'; ?> d-inline-block p-2 rounded mb-2">
                                        <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                    </div>
                                    <div class="message-time small text-muted">
                                        <?php echo formatMessageDate($message['created_at']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form id="message-form">
                            <input type="hidden" name="recipient_id" value="<?php echo $selected_user_id; ?>">
                            <div class="mb-3">
                                <textarea class="form-control" name="content" rows="3" placeholder="<?php echo __('type_your_message'); ?>"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo __('send'); ?></button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <?php echo __('user_not_found'); ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <?php echo __('select_contact_to_start_chat'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    var chatMessages = $('#chat-messages');
    chatMessages.scrollTop(chatMessages[0].scrollHeight);

    $('#message-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var content = form.find('textarea[name="content"]').val().trim();
        if (content) {
            $.ajax({
                url: 'send_message.php',
                method: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        form.find('textarea[name="content"]').val('');
                        addMessageToChat(response.message);
                    } else {
                        alert('<?php echo __("error_sending_message"); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo __("error_sending_message"); ?>');
                }
            });
        }
    });

    function addMessageToChat(message) {
        var messageHtml = '<div class="message text-end">' +
            '<div class="message-content bg-primary text-white d-inline-block p-2 rounded mb-2">' +
            message.content +
            '</div>' +
            '<div class="message-time small text-muted">' +
            formatMessageDate(message.created_at) +
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
        var lastMessageTime = chatMessages.find('.message:last .message-time').text();
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
                        addMessageToChat(message);
                    });
                }
                if (response.unread_counts) {
                    updateUnreadCounts(response.unread_counts);
                }
            }
        });
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