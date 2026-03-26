<?php
// Router script for PHP built-in server
// Serves static files directly, routes everything else to index.php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // Serve static files directly
}

require __DIR__ . '/index.php';
