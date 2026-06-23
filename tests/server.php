<?php

/**
 * Router for PHP's built-in web server, used by the integration test-suite.
 *
 * Usage: php -S 127.0.0.1:<port> tests/server.php
 *
 * It is intentionally dependency-free and simply reflects the incoming
 * request back to the client so the HTTP client behaviour can be asserted.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');

// Collect the request headers
$requestHeaders = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
        $requestHeaders[$name] = $value;
    }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
    $requestHeaders['Content-Type'] = $_SERVER['CONTENT_TYPE'];
}

$reflect = [
    'method' => $method,
    'path' => $path,
    'query' => $_GET,
    'headers' => $requestHeaders,
    'body' => $body,
    'post' => $_POST,
    'files' => array_map(static fn ($f) => ['name' => $f['name'], 'size' => $f['size']], $_FILES),
];

switch (true) {
    case $path === '/get':
    case $path === '/echo':
        header('Content-Type: application/json');
        header('X-Custom-Response: hello-world');
        echo json_encode($reflect);
        break;

    case preg_match('#^/status/(\d+)$#', $path, $m):
        http_response_code((int) $m[1]);
        header('Content-Type: application/json');
        echo json_encode($reflect + ['requested_status' => (int) $m[1]]);
        break;

    case $path === '/redirect':
        header('Location: /get', true, 302);
        break;

    case $path === '/multi-header':
        header('Set-Cookie: a=1', false);
        header('Set-Cookie: b=2', false);
        header('Content-Type: application/json');
        echo json_encode($reflect);
        break;

    case $path === '/large':
        // ~512 KiB of data, for the size-limit test. The Content-Length header
        // is mandatory here: the client aborts based on the *announced* size.
        $payload = str_repeat('x', 512 * 1024);
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . strlen($payload));
        echo $payload;
        break;

    case $path === '/cp1251':
        header('Content-Type: text/html; charset=cp1251');
        echo mb_convert_encoding('Привет', 'cp1251', 'utf-8');
        break;

    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode($reflect + ['error' => 'not found']);
        break;
}
