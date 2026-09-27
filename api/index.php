<?php
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request_uri, PHP_URL_PATH);

if ($path === '/' || $path === '') {
    $path = '/index.php';
}

// Convert the request path to a local file path
$file = realpath(__DIR__ . '/..' . $path);
$base_dir = realpath(__DIR__ . '/..');

// Basic security check to ensure the file is within the project directory
if ($file && strpos($file, $base_dir) === 0 && file_exists($file) && is_file($file)) {
    // Only process PHP files here
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        require $file;
    } else {
        http_response_code(403);
        echo "403 Forbidden";
    }
} else {
    http_response_code(404);
    echo "404 Not Found: " . htmlspecialchars($path);
}
