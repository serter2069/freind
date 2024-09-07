<?php
// db_connection.php

$servername = "dontsu6h.beget.tech";
$dbusername = "dontsu6h_friend";
$dbname = "dontsu6h_friend";
$dbpassword = "Orelkosyak5!";

function getDbConnection() {
    global $servername, $dbusername, $dbname, $dbpassword;
    
    $conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

// Настройки Google API
define('GOOGLE_CLIENT_ID', '32264119371-jk5jpr73bcbsntv2omp8l52umo33bp8n.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'https://svpmodels.com/google_auth_youtube.php');

// Другие настройки
define('BASE_URL', 'https://svpmodels.com');

// Оптимизированное подключение к базе данных
if (!isset($conn)) {
    $conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}