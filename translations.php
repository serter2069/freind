<?php
$availableLanguages = ['en', 'ru'];

function getCurrentLanguage() {
    global $availableLanguages;
    
    if (isset($_SESSION['language']) && in_array($_SESSION['language'], $availableLanguages)) {
        return $_SESSION['language'];
    }
    
    $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2);
    if (in_array($browserLang, $availableLanguages)) {
        return $browserLang;
    }
    
    return 'en';
}

function setLanguage($lang) {
    global $availableLanguages;
    if (in_array($lang, $availableLanguages)) {
        $_SESSION['language'] = $lang;
    }
}

$translations = [
    'en' => [
        'login_with_google' => 'Login with Google',
        'logout' => 'Logout',
        'my_profile' => 'My Profile',
        'welcome_message' => 'Welcome, %name%!',
        'view_profile' => 'View Profile',
        'youtube_subscriptions' => 'Your YouTube Subscriptions',
        'dashboard' => 'Dashboard',
        'my_subscriptions' => 'My Subscriptions',
        'subscriptions_explanation' => 'Here are your YouTube channel subscriptions. We use these to help you find friends with similar interests.',
        'remove' => 'Remove',
        'error_removing_subscription' => 'Error removing subscription. Please try again.',
        'find_friends' => 'Find Friends',
        'settings' => 'Settings',
        'home' => 'Home',
        'about' => 'About',
        'contact' => 'Contact',
        'privacy_policy' => 'Privacy Policy',
        'terms_of_service' => 'Terms of Service',
        'all_rights_reserved' => 'All rights reserved.',
        'language' => 'Language',
        'loading_subscriptions' => 'Loading your YouTube subscriptions. This may take a moment...',
        'last_update' => 'Last update',
        'update_subscriptions' => 'Update Subscriptions',
        'updating' => 'Updating...',
        'update_error' => 'An error occurred while updating subscriptions. Please try again.',
    ],
    'ru' => [
        'login_with_google' => 'Войти через Google',
        'logout' => 'Выйти',
        'my_profile' => 'Мой профиль',
        'welcome_message' => 'Добро пожаловать, %name%!',
        'view_profile' => 'Просмотреть профиль',
        'youtube_subscriptions' => 'Ваши подписки на YouTube',
        'dashboard' => 'Панель управления',
        'my_subscriptions' => 'Мои подписки',
        'subscriptions_explanation' => 'Здесь представлены ваши подписки на каналы YouTube. Мы используем их, чтобы помочь вам найти друзей со схожими интересами.',
        'remove' => 'Удалить',
        'error_removing_subscription' => 'Ошибка при удалении подписки. Пожалуйста, попробуйте еще раз.',
        'find_friends' => 'Найти друзей',
        'settings' => 'Настройки',
        'home' => 'Главная',
        'about' => 'О нас',
        'contact' => 'Контакты',
        'privacy_policy' => 'Политика конфиденциальности',
        'terms_of_service' => 'Условия использования',
        'all_rights_reserved' => 'Все права защищены.',
        'language' => 'Язык',
        'loading_subscriptions' => 'Загрузка ваших подписок на YouTube. Это может занять некоторое время...',
        'last_update' => 'Последнее обновление',
        'update_subscriptions' => 'Обновить подписки',
        'updating' => 'Обновление...',
        'update_error' => 'Произошла ошибка при обновлении подписок. Пожалуйста, попробуйте еще раз.',
    ]
];

function __($key, $params = []) {
    global $translations;
    $lang = getCurrentLanguage();
    
    if (isset($translations[$lang][$key])) {
        $translation = $translations[$lang][$key];
        foreach ($params as $param => $value) {
            $translation = str_replace("%$param%", $value, $translation);
        }
        return $translation;
    }
    
    if ($lang !== 'en' && isset($translations['en'][$key])) {
        return $translations['en'][$key];
    }
    
    return $key;
}
?>