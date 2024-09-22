<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';
require_once 'translations.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$username_error = '';

$conn = getDbConnection();

function generateTelegramHash($user_id) {
    return hash('sha256', $user_id . time() . uniqid());
}

function updateUserProfileData($conn, $user_id, $name, $gender, $age, $looking_for_partner, $partner_gender, $looking_for_friends, $friend_gender, $friend_activities, $telegram_notifications, $username) {
    $stmt = $conn->prepare("UPDATE users SET 
        name = ?, 
        gender = ?, 
        age = ?, 
        looking_for_partner = ?, 
        partner_gender = ?, 
        looking_for_friends = ?, 
        friend_gender = ?, 
        friend_activities = ?,
        telegram_notifications = ?,
        username = ?
        WHERE id = ?");
    
    $partner_gender_json = json_encode($partner_gender);
    $friend_gender_json = json_encode($friend_gender);
    $friend_activities_json = json_encode($friend_activities);
    
    $stmt->bind_param("ssiisissisi", 
        $name, 
        $gender, 
        $age, 
        $looking_for_partner, 
        $partner_gender_json, 
        $looking_for_friends, 
        $friend_gender_json, 
        $friend_activities_json,
        $telegram_notifications,
        $username,
        $user_id
    );
    
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function updateUserCities($conn, $user_id, $cities) {
    $stmt = $conn->prepare("DELETE FROM user_cities WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    foreach ($cities as $city) {
        $stmt = $conn->prepare("INSERT INTO user_cities (user_id, place_id, city, state, country) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $city['place_id'], $city['city'], $city['state'], $city['country']);
        $stmt->execute();
        $stmt->close();
    }
}

function getUserProfileData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        $user['partner_gender'] = json_decode($user['partner_gender'] ?? '[]', true);
        $user['friend_gender'] = json_decode($user['friend_gender'] ?? '[]', true);
        $user['friend_activities'] = json_decode($user['friend_activities'] ?? '[]', true);

        $stmt = $conn->prepare("SELECT place_id, city, state, country FROM user_cities WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user['cities'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    return $user;
}

function getUserTelegramData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT telegram_notifications, telegram_chat_id, telegram_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $looking_for_partner = isset($_POST['looking_for_partner']) ? 1 : 0;
    $partner_gender = $looking_for_partner ? ($_POST['partner_gender'] ?? []) : [];
    $looking_for_friends = isset($_POST['looking_for_friends']) ? 1 : 0;
    $friend_gender = $looking_for_friends ? ($_POST['friend_gender'] ?? []) : [];
    $friend_activities = $looking_for_friends ? ($_POST['friend_activities'] ?? []) : [];
    $telegram_notifications = isset($_POST['telegram_notifications']) ? 1 : 0;
    $username = trim($_POST['username'] ?? '');

    if (!empty($username)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $username_error = __('username_already_taken');
        }
        $stmt->close();
    }

    if (empty($username_error)) {
        if (updateUserProfileData($conn, $user_id, $name, $gender, $age, $looking_for_partner, $partner_gender, $looking_for_friends, $friend_gender, $friend_activities, $telegram_notifications, $username)) {
            $success = __('profile_updated_successfully');
            $user = getUserProfileData($conn, $user_id);
        } else {
            $error = __('error_updating_profile');
        }

        // Обработка городов
        $cities = isset($_POST['cities']) ? json_decode($_POST['cities'], true) : [];
        updateUserCities($conn, $user_id, $cities);
    } else {
        $error = $username_error;
    }
}

$user = getUserProfileData($conn, $user_id);
$userTelegramData = getUserTelegramData($conn, $user_id);

// Генерируем новый хэш для Telegram, если он еще не существует
if (empty($userTelegramData['telegram_hash'])) {
    $telegram_hash = generateTelegramHash($user_id);
    $stmt = $conn->prepare("UPDATE users SET telegram_hash = ? WHERE id = ?");
    $stmt->bind_param("si", $telegram_hash, $user_id);
    $stmt->execute();
    $stmt->close();
    $userTelegramData['telegram_hash'] = $telegram_hash;
}

$page_title = __('edit_profile');
include 'header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h1 class="text-center mb-4"><?php echo __('edit_profile'); ?></h1>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form action="" method="post" id="editProfileForm">
                <div class="mb-3">
                    <label for="name" class="form-label"><?php echo __('name'); ?></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                </div>
                
                <div class="mb-3 position-relative">
                    <label for="username" class="form-label"><?php echo __('username'); ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" 
                               placeholder="<?php echo __('create_username_placeholder'); ?>">
                        <span class="input-group-text" id="usernameIcon"></span>
                    </div>
                    <div id="usernameStatus" class="invalid-feedback"></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo __('gender'); ?></label>
                    <div class="btn-group w-100" role="group">
                        <?php
                        $genders = ['male', 'female', 'other'];
                        foreach ($genders as $g) {
                            echo '<input type="radio" class="btn-check" name="gender" id="' . $g . '" value="' . $g . '" ' . (($user['gender'] ?? '') == $g ? 'checked' : '') . '>';
                            echo '<label class="btn btn-outline-primary" for="' . $g . '">' . __($g) . '</label>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo __('looking_for'); ?></label>
                    <div class="form-check form-switch custom-switch">
                        <input class="form-check-input" type="checkbox" id="lookingForPartner" name="looking_for_partner" value="1" <?php echo ($user['looking_for_partner'] ?? false) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="lookingForPartner"><?php echo __('partner'); ?></label>
                    </div>
                </div>
                
                <div id="partnerPreferences" style="display: <?php echo ($user['looking_for_partner'] ?? false) ? 'block' : 'none'; ?>;">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('partner_gender'); ?></label>
                        <?php
                        $partner_genders = ['male', 'female', 'other'];
                        foreach ($partner_genders as $pg) {
                            echo '<div class="form-check form-check-inline">';
                            echo '<input class="form-check-input" type="checkbox" id="partnerGender_' . $pg . '" name="partner_gender[]" value="' . $pg . '" ' . (in_array($pg, $user['partner_gender'] ?? []) ? 'checked' : '') . '>';
                            echo '<label class="form-check-label" for="partnerGender_' . $pg . '">' . __($pg) . '</label>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check form-switch custom-switch">
                        <input class="form-check-input" type="checkbox" id="lookingForFriends" name="looking_for_friends" value="1" <?php echo ($user['looking_for_friends'] ?? false) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="lookingForFriends"><?php echo __('friends'); ?></label>
                    </div>
                </div>
                
                <div id="friendPreferences" style="display: <?php echo ($user['looking_for_friends'] ?? false) ? 'block' : 'none'; ?>;">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('friend_gender'); ?></label>
                        <?php
                        $friend_genders = ['male', 'female', 'any'];
                        foreach ($friend_genders as $fg) {
                            echo '<div class="form-check form-check-inline">';
                            echo '<input class="form-check-input" type="checkbox" id="friendGender_' . $fg . '" name="friend_gender[]" value="' . $fg . '" ' . (in_array($fg, $user['friend_gender'] ?? []) ? 'checked' : '') . '>';
                            echo '<label class="form-check-label" for="friendGender_' . $fg . '">' . __($fg) . '</label>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('friend_activities'); ?></label>
                        <div class="d-flex flex-wrap">
                            <?php
                            $activities = [
                                'coffee' => '☕ ' . __('activity_coffee'),
                                'drinks' => '🍻 ' . __('activity_drinks'),
                                'walk' => '🚶 ' . __('activity_walk'),
                                'sports' => '🏃 ' . __('activity_sports'),
                                'movie' => '🎬 ' . __('activity_movie')
                            ];
                            foreach ($activities as $key => $label) {
                                echo '<div class="form-check me-3 mb-2">';
                                echo '<input class="form-check-input" type="checkbox" id="activity_' . $key . '" name="friend_activities[]" value="' . $key . '" ' . (in_array($key, $user['friend_activities'] ?? []) ? 'checked' : '') . '>';
                                echo '<label class="form-check-label" for="activity_' . $key . '">' . $label . '</label>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="age" class="form-label"><?php echo __('age'); ?></label>
                    <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>" min="18" max="120">
                </div>
                
                <div class="mb-3">
                    <label for="cityInput" class="form-label"><?php echo __('cities'); ?> (<?php echo __('max_5'); ?>)</label>
                    <input type="text" class="form-control" id="cityInput" placeholder="<?php echo __('enter_city'); ?>">
                    <div id="selectedCities" class="mt-2">
                        <?php foreach ($user['cities'] as $city): ?>
                            <span class="badge bg-primary me-2 mb-2 city-badge" data-place-id="<?php echo htmlspecialchars($city['place_id']); ?>">
                                <?php echo htmlspecialchars($city['city'] . ', ' . $city['state'] . ', ' . $city['country']); ?>
                                <button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="removeCity('<?php echo htmlspecialchars($city['place_id']); ?>')"></button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="cities" id="citiesInput" value="<?php echo htmlspecialchars(json_encode($user['cities'])); ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100" id="saveButton"><?php echo __('save_changes'); ?></button>
            </form>

            <div class="card mt-4 mb-4">
                <div class="card-header">
                    <h3><?php echo __('telegram_notifications'); ?></h3>
                </div>
                <div class="card-body">
                    <div id="telegramStatus" class="mt-3">
                        <?php if ($userTelegramData['telegram_chat_id']): ?>
                            <p class="text-success"><?php echo __('telegram_connected'); ?></p>
                            <button type="button" class="btn btn-danger" id="telegramDisconnectBtn">
                                <?php echo __('disconnect_telegram'); ?>
                            </button>
                        <?php else: ?>
                            <p class="text-warning"><?php echo __('telegram_not_connected'); ?></p>
                            <a href="https://t.me/<?php echo $botlink; ?>?start=<?php echo $userTelegramData['telegram_hash']; ?>" class="btn btn-primary" target="_blank" id="telegramConnectBtn">
                                <?php echo __('connect_telegram'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.custom-switch .form-check-input {
    width: 3rem;
    height: 1.5rem;
    border-radius: 1.5rem;
}
.custom-switch .form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.custom-switch .form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.city-badge .btn-close {
    font-size: 0.5em;
    margin-left: 0.5em;
}
.input-group-text {
    background: none;
    border: none;
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 4;
}
#username {
    padding-right: 40px;
}
.invalid-feedback {
    display: block;
}
</style>

<script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_API_KEY_FOR_PLACES; ?>&libraries=places"></script>
<script>
$(document).ready(function() {
    var autocomplete = new google.maps.places.Autocomplete(
        document.getElementById('cityInput'), 
        {types: ['(cities)']}
    );

    autocomplete.addListener('place_changed', function() {
        var place = autocomplete.getPlace();
        if (!place.place_id) {
            console.log('No place selected');
            return;
        }
        var cityName = '';
        var stateName = '';
        var countryName = '';
        for (var i = 0; i < place.address_components.length; i++) {
            var addressType = place.address_components[i].types[0];
            if (addressType === 'locality') {
                cityName = place.address_components[i].long_name;
            } else if (addressType === 'administrative_area_level_1') {
                stateName = place.address_components[i].long_name;
            } else if (addressType === 'country') {
                countryName = place.address_components[i].long_name;
            }
        }
        addCity(place.place_id, cityName, stateName, countryName);
        $('#cityInput').val('');
    });

    $('#lookingForPartner').change(function() {
        $('#partnerPreferences').toggle(this.checked);
    });

    $('#lookingForFriends').change(function() {
        $('#friendPreferences').toggle(this.checked);
    });

    $('#partnerPreferences').toggle($('#lookingForPartner').is(':checked'));
    $('#friendPreferences').toggle($('#lookingForFriends').is(':checked'));

    var usernameTimer;
    $('#username').on('input', function() {
        clearTimeout(usernameTimer);
        var username = $(this).val().trim();
        var $status = $('#usernameStatus');
        var $icon = $('#usernameIcon');
        var $saveButton = $('#saveButton');
        var $usernameInput = $('#username');

        if (username === '') {
            $status.text('').hide();
            $icon.html('').hide();
            $usernameInput.removeClass('is-invalid is-valid');
            $saveButton.prop('disabled', false);
            return;
        }

        usernameTimer = setTimeout(function() {
            $.ajax({
                url: 'check_username.php',
                method: 'POST',
                data: { username: username },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $status.text('').hide();

                        $usernameInput.removeClass('is-invalid').addClass('is-valid');
                        $saveButton.prop('disabled', false);
                    } else {
                        $status.text(response.message).show();

                        $usernameInput.removeClass('is-valid').addClass('is-invalid');
                        $saveButton.prop('disabled', true);
                    }
                },
                error: function() {
                    $status.text('Error checking username').show();
                    $icon.html('!').removeClass('text-success').addClass('text-danger').show();
                    $usernameInput.removeClass('is-valid').addClass('is-invalid');
                    $saveButton.prop('disabled', true);
                }
            });
        }, 500);
    });

    $('#editProfileForm').submit(function(e) {
        if ($('#saveButton').prop('disabled')) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('#username').offset().top - 100
            }, 500, function() {
                $('#username').focus();
            });
        }
    });

    $('#telegramDisconnectBtn').click(function() {
        $.ajax({
            url: 'update_telegram_status.php',
            method: 'POST',
            data: { action: 'disconnect' },
            dataType: 'json',
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    var newHtml = `
                        <p class="text-warning"><?php echo __('telegram_not_connected'); ?></p>
                        <a href="https://t.me/<?php echo $botlink; ?>?start=${response.newHash}" class="btn btn-primary" target="_blank" id="telegramConnectBtn">
                            <?php echo __('connect_telegram'); ?>
                        </a>
                    `;
                    $('#telegramStatus').html(newHtml);
                    $('#telegramNotifications').prop('checked', false);
                } else {
                    alert('<?php echo __('error_disconnecting_telegram'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('<?php echo __('error_disconnecting_telegram'); ?>');
            }
        });
    });
});

function addCity(placeId, cityName, stateName, countryName) {
    var cities = JSON.parse($('#citiesInput').val() || '[]');
    if (cities.length >= 5) {
        alert('<?php echo __("max_5_cities"); ?>');
        return;
    }
    var newCity = {
        place_id: placeId,
        city: cityName,
        state: stateName,
        country: countryName
    };
    if (!cities.some(city => city.place_id === placeId)) {
        cities.push(newCity);
        $('#selectedCities').append(
            '<span class="badge bg-primary me-2 mb-2 city-badge" data-place-id="' + placeId + '">' +
            cityName + ', ' + stateName + ', ' + countryName +
            '<button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="removeCity(\'' + placeId + '\')"></button>' +
            '</span>'
        );
        $('#citiesInput').val(JSON.stringify(cities));
    }
}

function removeCity(placeId) {
    var cities = JSON.parse($('#citiesInput').val() || '[]');
    cities = cities.filter(city => city.place_id !== placeId);
    $('#citiesInput').val(JSON.stringify(cities));
    $('.city-badge[data-place-id="' + placeId + '"]').remove();
}
</script>

<?php if (!empty($username_error)): ?>
<script>
$(document).ready(function() {
    $('#usernameStatus').text('<?php echo $username_error; ?>').show();

    $('#username').addClass('is-invalid');
    $('#saveButton').prop('disabled', true);
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
