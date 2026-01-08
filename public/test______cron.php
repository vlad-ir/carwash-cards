<?php
// test_cron.php
$logFile = __DIR__ . '/cron_test.log';
$timestamp = date('Y-m-d H:i:s');
$scriptName = basename(__FILE__);

// Информация о запуске
$message = "=== Запуск cron ===\n";
$message .= "Время: $timestamp\n";
$message .= "Скрипт: $scriptName\n";
$message .= "Директория: " . __DIR__ . "\n";
$message .= "Пользователь: " . get_current_user() . "\n";
$message .= "PHP версия: " . phpversion() . "\n";
$message .= "========================\n\n";

// Запись в лог
if (file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX)) {
    echo "OK: Лог обновлен\n";
} else {
    echo "ERROR: Не удалось записать в лог\n";
}
