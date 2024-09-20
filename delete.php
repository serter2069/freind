<?php
// delete.php
require_once 'db_connection.php';

// Функция для очистки таблицы
function clearTable($conn, $table) {
    $query = "TRUNCATE TABLE `$table`";
    if ($conn->query($query) === TRUE) {
        echo "Таблица $table очищена успешно<br>";
    } else {
        echo "Ошибка при очистке таблицы $table: " . $conn->error . "<br>";
    }
}

// Получаем соединение с базой данных
$conn = getDbConnection();

// Получаем список всех таблиц
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// Отключаем проверку внешних ключей
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Очищаем каждую таблицу
foreach ($tables as $table) {
    clearTable($conn, $table);
}

// Включаем обратно проверку внешних ключей
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "Все таблицы успешно очищены";

// Закрываем соединение
$conn->close();