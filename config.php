<?php
define('FDG_CONTENT_SYNDICATOR_VERSION', '0.1.0');
define('FDG_CONTENT_SYNDICATOR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FDG_CONTENT_SYNDICATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

define('FDG_CONTENT_SYNDICATOR_CHUNK_SIZE', 256000);

spl_autoload_register(function ($class) {
    $prefix = 'FdgSync\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    // Базовая директория классов
    $baseDir = __DIR__ . '/classes/';

    // Отрезаем prefix
    $relativeClass = substr($class, strlen($prefix));

    // Формируем путь
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Подключаем файл
    if (file_exists($file)) {
        require $file;
    }
});