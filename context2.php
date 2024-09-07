<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Устанавливаем кодировку UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// Проверка наличия расширения ZipArchive
if (!extension_loaded('zip')) {
    die('ZipArchive не доступен');
}

// Функция для добавления файлов в архив (только из текущей директории)
function addFilesToZip($dir, $zipArchive)
{
    global $fileCount;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file !== "." && $file !== "..") {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath)) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array($extension, ['php', 'js', 'yaml'])) {
                    if ($zipArchive->addFile($filePath, $file)) {
                        $fileCount++;
                        echo "Добавлен файл: $file<br>";
                    } else {
                        echo "Не удалось добавить файл: $file<br>";
                    }
                }
            }
        }
    }
}

// Функция для получения структуры базы данных
function getDatabaseStructure($conn)
{
    $structure = "";

    // Получение списка таблиц
    $tables = getDatabaseTables($conn);
    $structure .= "Список таблиц в базе данных:\n";
    foreach ($tables as $table) {
        $structure .= "Таблица: $table\n";
        $columns = getTableColumns($conn, $table);
        foreach ($columns as $column) {
            $structure .= "  Колонка: " . $column['Field'] . " - " . $column['Type'] . "\n";
        }
        $structure .= "\n";
    }

    // Получение внешних ключей
    $structure .= "\nСвязи между таблицами (Внешние ключи):\n";
    foreach ($tables as $table) {
        $structure .= "Таблица: $table\n";
        $foreignKeys = getTableForeignKeys($conn, $table);
        foreach ($foreignKeys as $fk) {
            $structure .= "  Колонка: " . $fk['COLUMN_NAME'] . " -> " . $fk['REFERENCED_TABLE_NAME'] . "(" . $fk['REFERENCED_COLUMN_NAME'] . ")\n";
        }
        $structure .= "\n";
    }

    // Получение примеров данных
    $structure .= "\nПримеры данных из таблиц:\n";
    foreach ($tables as $table) {
        $structure .= "Таблица: $table (Примеры записей)\n";
        $sampleData = getTableSampleData($conn, $table);
        foreach ($sampleData as $row) {
            $structure .= json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        $structure .= "\n";
    }

    return $structure;
}

// Функция для получения списка таблиц в базе данных
function getDatabaseTables($conn)
{
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    return $tables;
}

// Функция для получения колонок таблицы
function getTableColumns($conn, $table)
{
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    return $columns;
}

// Функция для получения внешних ключей таблицы
function getTableForeignKeys($conn, $table)
{
    $foreignKeys = [];
    $query = "
        SELECT 
            kcu.COLUMN_NAME, 
            kcu.REFERENCED_TABLE_NAME, 
            kcu.REFERENCED_COLUMN_NAME 
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
        WHERE 
            kcu.TABLE_SCHEMA = DATABASE() AND 
            kcu.TABLE_NAME = '$table' AND 
            kcu.REFERENCED_TABLE_NAME IS NOT NULL
    ";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $foreignKeys[] = $row;
    }
    return $foreignKeys;
}

// Функция для получения примеров данных таблицы
function getTableSampleData($conn, $table)
{
    $sampleData = [];
    $query = "SELECT * FROM `$table` LIMIT 3";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $sampleData[] = $row;
    }
    return $sampleData;
}

// Подключение к базе данных
include 'db_connection.php';

$currentDir = __DIR__; // Путь к текущей директории
$zipFileName = 'archive.zip'; // Имя создаваемого архива

// Проверка пароля
$correctPassword = 'Orelkosyak5';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredPassword = $_POST['password'] ?? '';

    if ($enteredPassword === $correctPassword) {
        $fileCount = 0;
        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'zip');
        
        echo "Попытка создать архив: $tempFile<br>";
        
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            echo "Архив успешно создан.<br>";
            
            // Добавление файлов в архив (только из текущей директории)
            addFilesToZip($currentDir, $zip);
            
            echo "Всего файлов добавлено в архив: $fileCount<br>";
            
            // Получение структуры базы данных и добавление её в архив
            echo "Получение структуры базы данных...<br>";
            $dbStructure = getDatabaseStructure($conn);
            $zip->addFromString('database_structure.txt', $dbStructure);
            echo "Структура базы данных добавлена в архив.<br>";
            
            $zip->close();
            echo "Архив закрыт.<br>";

            // Проверка размера архива
            if (file_exists($tempFile) && filesize($tempFile) > 0) {
                echo "Архив существует и не пустой. Размер: " . filesize($tempFile) . " байт<br>";
                // Скачиваем архив
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($zipFileName) . '"');
                header('Content-Length: ' . filesize($tempFile));
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                if (ob_get_length()) ob_clean();
                flush();
                if (readfile($tempFile)) {
                    echo "Файл успешно отправлен.<br>";
                } else {
                    echo "Ошибка при отправке файла.<br>";
                }
                // Удаляем временный файл после скачивания
                unlink($tempFile);
                exit;
            } else {
                echo "Архив пуст или не был создан. Временный файл: $tempFile, Размер: " . (file_exists($tempFile) ? filesize($tempFile) : 'Файл не существует') . " байт<br>";
            }
        } else {
            echo "Не удалось создать архив. Код ошибки ZipArchive: " . $zip->getStatusString() . "<br>";
        }
    } else {
        echo 'Неверный пароль. Пожалуйста, попробуйте снова.';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Скачать архив</title>
</head>
<body>
    <h2>Введите пароль для скачивания архива</h2>
    <form method="post">
        <input type="password" name="password" required>
        <button type="submit">Скачать архив</button>
    </form>
</body>
</html>