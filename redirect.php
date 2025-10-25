<?php
// redirect.php

define('DB_PATH', __DIR__ . '/urls.db');

// 从 URL 路径中提取短码
 $requestUri = $_SERVER['REQUEST_URI'];
 $requestUri = strtok($requestUri, '?');

if (preg_match('/^\/s\/([a-zA-Z0-9]+)$/', $requestUri, $matches)) {
    $shortCode = $matches[1];
} else {
    http_response_code(404);
    die('<h1>404 Not Found</h1><p>The requested short URL was not found on this server.</p>');
}

try {
    $db = new SQLite3(DB_PATH);
    $db->busyTimeout(5000);
    
    $stmt = $db->prepare('SELECT original_url FROM urls WHERE short_code = :code LIMIT 1');
    $stmt->bindValue(':code', $shortCode, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray();
    
    $db->close();

    if ($row && isset($row['original_url'])) {
        header('Location: ' . $row['original_url'], true, 301);
        exit();
    } else {
        http_response_code(404);
        die('<h1>404 Not Found</h1><p>The requested short URL was not found on this server.</p>');
    }
} catch (Exception $e) {
    http_response_code(500);
    die('<h1>500 Server Error</h1><p>Could not connect to the database.</p>');
}