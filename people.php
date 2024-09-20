<?php
session_start();
require_once 'db_connection.php';
require_once 'translations.php';

$page_title = __('people');
include 'header.php';

// Функция для получения информации о городе по place_id
function getCityInfo($place_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT city, state, country FROM user_cities WHERE place_id = ? LIMIT 1");
    $stmt->bind_param("s", $place_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $city_info = $result->fetch_assoc();
    $stmt->close();
    return $city_info;
}

// Функция для получения отфильтрованных пользователей
function getFilteredUsers($cities, $channels, $looking_for_friends, $looking_for_partner, $age_ranges) {
    global $conn;
    
    $query = "SELECT DISTINCT u.* 
              FROM users u 
              LEFT JOIN user_cities uc ON u.id = uc.user_id 
              LEFT JOIN user_subscriptions us ON u.id = us.user_id
              LEFT JOIN youtube_channels yc ON us.channel_id = yc.channel_id
              WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($cities)) {
        $cityPlaceholders = implode(',', array_fill(0, count($cities), '?'));
        $query .= " AND uc.place_id IN ($cityPlaceholders)";
        $params = array_merge($params, $cities);
        $types .= str_repeat('s', count($cities));
    }
    
    if (!empty($channels)) {
        $channelPlaceholders = implode(',', array_fill(0, count($channels), '?'));
        $query .= " AND yc.id IN ($channelPlaceholders)";
        $params = array_merge($params, $channels);
        $types .= str_repeat('i', count($channels));
    }
    
    if ($looking_for_friends) {
        $query .= " AND u.looking_for_friends = 1";
    }
    
    if ($looking_for_partner) {
        $query .= " AND u.looking_for_partner = 1";
    }
    
    if (!empty($age_ranges)) {
        $age_conditions = [];
        foreach ($age_ranges as $range) {
            if ($range === '51+') {
                $age_conditions[] = "u.age >= 51";
            } else {
                list($min, $max) = explode('-', $range);
                $age_conditions[] = "(u.age BETWEEN ? AND ?)";
                $params[] = $min;
                $params[] = $max;
                $types .= "ii";
            }
        }
        if (!empty($age_conditions)) {
            $query .= " AND (" . implode(" OR ", $age_conditions) . ")";
        }
    }
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);

    // Получаем дополнительные данные для каждого пользователя
    foreach ($users as &$user) {
        $user['channels'] = getUserChannels($user['id']);
        $user['cities'] = getUserCities($user['id']);
    }

    return $users;
}

// Функция для получения каналов пользователя
function getUserChannels($user_id) {
    global $conn;
    $query = "SELECT yc.* FROM youtube_channels yc
              JOIN user_subscriptions us ON yc.channel_id = us.channel_id
              WHERE us.user_id = ?
              ORDER BY yc.title";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Функция для получения городов пользователя
function getUserCities($user_id) {
    global $conn;
    $query = "SELECT * FROM user_cities WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Функция для получения статистики пользователей
function getUserStats() {
    global $conn;
    $query = "SELECT 
                SUM(looking_for_friends) as looking_for_friends,
                SUM(looking_for_partner) as looking_for_partner
              FROM users";
    $result = $conn->query($query);
    return $result->fetch_assoc();
}

// Получение параметров фильтрации
$cities = isset($_GET['cities']) && !empty($_GET['cities']) ? array_unique(explode(',', $_GET['cities'])) : [];
$city_names = [];
foreach ($cities as $city_id) {
    $city_info = getCityInfo($city_id);
    if ($city_info) {
        $city_names[$city_id] = $city_info['city'] . 
                                ($city_info['state'] ? ', ' . $city_info['state'] : '') . 
                                ', ' . $city_info['country'];
    }
}

$channels = isset($_GET['channels']) && !empty($_GET['channels']) ? array_unique(explode(',', $_GET['channels'])) : [];
$looking_for_friends = isset($_GET['looking_for_friends']) ? 1 : 0;
$looking_for_partner = isset($_GET['looking_for_partner']) ? 1 : 0;
$age_ranges = isset($_GET['age_ranges']) ? $_GET['age_ranges'] : [];

// Получение пользователей с учетом фильтров
$users = getFilteredUsers($cities, $channels, $looking_for_friends, $looking_for_partner, $age_ranges);

$stats = getUserStats();

// Функция для получения канала по ID
function getChannelById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM youtube_channels WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $channel = $result->fetch_assoc();
    $stmt->close();
    return $channel;
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
        <h3><?php echo __('filters'); ?></h3>
            <form action="" method="get" id="filterForm">
                <div class="mb-3">
                    <label for="cityInput" class="form-label"><?php echo __('cities'); ?></label>
                    <input type="text" id="cityInput" class="form-control" placeholder="<?php echo __('enter_city'); ?>">
                    <div id="selectedCities" class="mt-2">
                        <?php foreach ($cities as $city_id): 
                            if (isset($city_names[$city_id])):
                        ?>
                            <span class="badge bg-primary me-2 mb-2 city-badge" data-place-id="<?php echo htmlspecialchars($city_id); ?>">
                                <?php echo htmlspecialchars($city_names[$city_id]); ?>
                                <button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="removeCity('<?php echo htmlspecialchars($city_id); ?>')"></button>
                            </span>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    <input type="hidden" name="cities" id="citiesInput" value="<?php echo htmlspecialchars(implode(',', $cities)); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="channelInput" class="form-label"><?php echo __('channels'); ?></label>
                    <select id="channelInput" class="form-control" style="width: 100%;">
                        <option></option>
                    </select>
                    <div id="selectedChannels" class="mt-2">
                        <?php foreach ($channels as $channelId): 
                            $channel = getChannelById($channelId);
                            if ($channel):
                        ?>
                            <span class="badge bg-info me-2 mb-2 channel-badge" data-id="<?php echo $channelId; ?>">
                                <?php echo htmlspecialchars($channel['title']); ?>
                                <button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="removeChannel('<?php echo $channelId; ?>')"></button>
                            </span>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    <input type="hidden" name="channels" id="channelsInput" value="<?php echo htmlspecialchars(implode(',', $channels)); ?>">
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="looking_for_friends" name="looking_for_friends" <?php echo $looking_for_friends ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="looking_for_friends">
                            <?php echo __('looking_for_friends'); ?> (<?php echo $stats['looking_for_friends']; ?>)
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="looking_for_partner" name="looking_for_partner" <?php echo $looking_for_partner ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="looking_for_partner">
                            <?php echo __('looking_for_partner'); ?> (<?php echo $stats['looking_for_partner']; ?>)
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo __('age_range'); ?></label>
                    <?php
                    $age_ranges_options = [
                        '18-25' => '18-25',
                        '26-35' => '26-35',
                        '36-50' => '36-50',
                        '51+' => '51+'
                    ];
                    foreach ($age_ranges_options as $value => $label) {
                        $checked = in_array($value, $age_ranges) ? 'checked' : '';
                        echo "<div class='form-check'>";
                        echo "<input class='form-check-input' type='checkbox' name='age_ranges[]' value='$value' id='age_$value' $checked>";
                        echo "<label class='form-check-label' for='age_$value'>$label</label>";
                        echo "</div>";
                    }
                    ?>
                </div>
                
                <button type="submit" class="btn btn-primary"><?php echo __('apply_filters'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="resetFilters()"><?php echo __('reset_filters'); ?></button>
            </form>
        </div>
        
        <div class="col-md-9">
            <h1><?php echo __('people'); ?></h1>
            
            <?php if (empty($users)): ?>
                <div class="alert alert-info">
                    <?php echo __('no_users_found'); ?>
                    <button type="button" class="btn btn-link" onclick="resetFilters()"><?php echo __('reset_filters'); ?></button>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($users as $user): ?>
                        <div class="list-group-item user-card">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <a href="profile.php?id=<?php echo $user['id']; ?>" class="text-decoration-none">
                                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="rounded-circle me-3" width="50" height="50" alt="<?php echo htmlspecialchars($user['name']); ?>">
                                        <h5 class="mb-1 d-inline"><?php echo htmlspecialchars($user['name']); ?></h5>
                                    </a>
                                </div>
                                <div>
                                    <?php if ($user['looking_for_friends']): ?>
                                        <span class="badge bg-primary"><?php echo __('looking_for_friends'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($user['looking_for_partner']): ?>
                                        <span class="badge bg-success"><?php echo __('looking_for_partner'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="mb-1">
                                <?php if (!empty($user['age'])): ?>
                                    <?php echo __('age'); ?>: <?php echo $user['age']; ?><br>
                                <?php endif; ?>
                                <?php if (!empty($user['cities'])): ?>
                                    <?php echo __('location'); ?>: 
                                    <?php 
                                    $cityNames = array_map(function($city) {
                                        return htmlspecialchars($city['city'] . ', ' . $city['state'] . ', ' . $city['country']);
                                    }, $user['cities']);
                                    echo implode('; ', $cityNames);
                                    ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($user['friend_activities'])): ?>
                                <p class="mb-1">
                                    <?php echo __('activities'); ?>:
                                    <?php 
                                    $activities = json_decode($user['friend_activities'], true);
                                    foreach ($activities as $activity): 
                                    ?>
                                        <span class="badge bg-info me-1"><?php echo __('activity_' . $activity); ?></span>
                                    <?php endforeach; ?>
                                </p>
                            <?php endif; ?>
                            <div class="channels-container mb-2" data-user-id="<?php echo $user['id']; ?>">
                                <?php 
                                $channelCount = count($user['channels']);
                                foreach ($user['channels'] as $index => $channel): 
                                    $hidden = $index >= 10 ? 'style="display:none;"' : '';
                                ?>
                                    <a href="bubble.php?id=<?php echo $channel['id']; ?>" class="channel-icon" data-title="<?php echo htmlspecialchars($channel['title']); ?>" <?php echo $hidden; ?>>
                                        <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>" width="30" height="30" class="rounded" alt="<?php echo htmlspecialchars($channel['title']); ?>">
                                    </a>
                                <?php endforeach; ?>
                                <?php if ($channelCount > 10): ?>
                                    <button class="btn btn-sm btn-outline-secondary show-more-channels" data-user-id="<?php echo $user['id']; ?>">
                                        +<?php echo $channelCount - 10; ?> <?php echo __('more'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.channel-icon {
    display: inline-block;
    margin-right: 5px;
    position: relative;
}
.channel-icon img {
    transition: opacity 0.3s ease;
}
.channel-icon:hover img {
    opacity: 0.7;
}
.channel-tooltip {
    position: absolute;
    background-color: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 14px;
    white-space: nowrap;
    z-index: 10000;
    pointer-events: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    max-width: 200px;
    text-overflow: ellipsis;
    overflow: hidden;
}
.city-badge, .channel-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25em 0.5em;
}
.city-badge .btn-close, .channel-badge .btn-close {
    font-size: 0.5em;
    margin-left: 0.5em;
}
.user-card {
    transition: background-color 0.3s ease;
}
.user-card:hover {
    background-color: #f8f9fa;
}
.channels-container {
    transition: max-height 0.3s ease;
    overflow: hidden;
}
.channels-container.expanded {
    max-height: none !important;
}
</style>

<script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

    $('#channelInput').select2({
        ajax: {
            url: 'search_channel.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data.items.map(function(item) {
                        return {
                            id: item.id,
                            text: item.title,
                            thumbnail_url: item.thumbnail_url
                        };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 2,
        placeholder: '<?php echo __("search_channel"); ?>',
        width: '100%',
        templateResult: formatChannel,
        templateSelection: formatChannelSelection
    }).on('select2:open', function() {
        setTimeout(function() {
            $('.select2-search__field').focus();
        }, 0);
    });

    $('#channelInput').on('select2:select', function (e) {
        addChannel(e.params.data.id, e.params.data.text);
        $(this).val(null).trigger('change');
    });

    $('.show-more-channels').on('click', function() {
        var userId = $(this).data('user-id');
        var container = $(this).closest('.channels-container');
        var button = $(this);
        
        if (container.hasClass('expanded')) {
            container.removeClass('expanded');
            container.find('.channel-icon:gt(9)').hide();
            var hiddenCount = container.find('.channel-icon:hidden').length;
            button.html('+' + hiddenCount + ' <?php echo __("more"); ?>');
        } else {
            container.addClass('expanded');
            container.find('.channel-icon').show();
            button.html('<?php echo __("show_less"); ?>');
        }
    });

    var tooltip = $('<div class="channel-tooltip"></div>').appendTo('body').hide();

    $(document).on('mouseenter', '.channel-icon', function(e) {
        var title = $(this).data('title');
        tooltip.text(title).show();
        positionTooltip(e, this);
    }).on('mousemove', '.channel-icon', function(e) {
        positionTooltip(e, this);
    }).on('mouseleave', '.channel-icon', function() {
        tooltip.hide();
    });

    function positionTooltip(e, element) {
        var offset = 5;
        var left = e.pageX + offset;
        var top = e.pageY + offset;
        var tooltipWidth = tooltip.outerWidth();
        var tooltipHeight = tooltip.outerHeight();
        var windowWidth = $(window).width();
        var windowHeight = $(window).height();

        if (left + tooltipWidth > windowWidth) {
            left = e.pageX - tooltipWidth - offset;
        }
        if (top + tooltipHeight > windowHeight) {
            top = e.pageY - tooltipHeight - offset;
        }

        tooltip.css({left: left, top: top});
    }

    $(window).on('scroll resize', function() {
        tooltip.hide();
    });

    // Добавляем обработчик отправки формы
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        applyFilters();
    });

    // Добавляем обработчик нажатия клавиши Enter в поле ввода города
    $('#cityInput').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
        }
    });

    // Устанавливаем фокус на поле ввода города при загрузке страницы
    $('#cityInput').focus();

    // Инициализируем города при загрузке страницы
    initializeCities();
});

function formatChannel(channel) {
    if (channel.loading) {
        return channel.text;
    }
    var $container = $(
        "<div class='select2-result-channel clearfix'>" +
        "<div class='select2-result-channel__avatar'><img src='" + channel.thumbnail_url + "' /></div>" +
        "<div class='select2-result-channel__meta'>" +
        "<div class='select2-result-channel__title'></div>" +
        "</div>" +
        "</div>"
    );
    $container.find(".select2-result-channel__title").text(channel.text);
    return $container;
}

function formatChannelSelection(channel) {
    return channel.text || channel.title;
}

function addCity(placeId, cityName, stateName, countryName) {
    var cities = $('#citiesInput').val().split(',').filter(Boolean);
    if (!cities.includes(placeId)) {
        cities.push(placeId);
        var fullCityName = cityName + (stateName ? ', ' + stateName : '') + ', ' + countryName;
        $('#selectedCities').append(
            '<span class="badge bg-primary me-2 mb-2 city-badge" data-place-id="' + placeId + '">' +
            fullCityName +
            '<button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="removeCity(\'' + placeId + '\')"></button>' +
            '</span>'
        );
        $('#citiesInput').val(cities.join(','));
    }
}

function removeCity(placeId) {
    var cities = $('#citiesInput').val().split(',').filter(Boolean);
    cities = cities.filter(id => id !== placeId);
    $('#citiesInput').val(cities.join(','));
    $('#selectedCities .city-badge[data-place-id="' + placeId + '"]').remove();
}

function addChannel(channelId, channelName) {
    var channels = $('#channelsInput').val().split(',').filter(Boolean);
    if (!channels.includes(channelId)) {
        channels.push(channelId);
        $('#selectedChannels').append(
            '<span class="badge bg-info me-2 mb-2 channel-badge" data-id="' + channelId + '">' +
            channelName +
            '<button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="removeChannel(\'' + channelId + '\')"></button>' +
            '</span>'
        );
        $('#channelsInput').val(channels.join(','));
    }
}

function removeChannel(channelId) {
    var channels = $('#channelsInput').val().split(',').filter(Boolean);
    channels = channels.filter(id => id !== channelId);
    $('#channelsInput').val(channels.join(','));
    $('#selectedChannels .channel-badge[data-id="' + channelId + '"]').remove();
}

function resetFilters() {
    window.location.href = 'people.php';
}

function applyFilters() {
    var formData = $('#filterForm').serialize();
    window.location.href = 'people.php?' + formData;
}

function initializeCities() {
    var cities = $('#citiesInput').val().split(',').filter(Boolean);
    cities.forEach(function(cityId) {
        var cityBadge = $('#selectedCities').find('[data-place-id="' + cityId + '"]');
        if (cityBadge.length === 0) {
            // Если бейджа для города еще нет, делаем AJAX запрос для получения информации о городе
            $.ajax({
                url: 'get_city_info.php',
                method: 'GET',
                data: { place_id: cityId },
                success: function(response) {
                    if (response.success) {
                        addCity(cityId, response.city, response.state, response.country);
                    }
                }
            });
        }
    });
}
</script>

<?php include 'footer.php'; ?>