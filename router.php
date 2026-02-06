<?php
// router.php - serve static files when they exist, otherwise route to index.php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requested = __DIR__ . $uri;
if ($uri !== '/' && file_exists($requested) && is_file($requested)) {
    return false; // let the built-in server serve the file
}
require_once __DIR__ . '/index.php';
