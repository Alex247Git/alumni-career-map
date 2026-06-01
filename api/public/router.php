<?php
// Router script for PHP built-in development server
// Serves static files or routes to index.php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the request is for a real file, serve it
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Otherwise, route to index.php
require __DIR__ . '/index.php';