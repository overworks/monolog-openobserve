<?php

// Test double for the OpenObserve ingest endpoint.
//
// The organization id segment of the request path doubles as the status code to
// return, so a test can drive any response by choosing the organization id it
// constructs the handler with.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim((string) $path, '/'));
$status = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : 200;

$scenario = $segments[1] ?? '';
$payload = json_decode(file_get_contents('php://input'));
if ($scenario === 'batch' && (!is_array($payload)
    || array_column($payload, 'message') !== ['first error', 'second error'])) {
    $status = 400;
}

if ($scenario === 'slow') {
    usleep(3_000_000);
}

http_response_code($status);
header('Content-Type: application/json');
echo json_encode([
    'code' => $status,
    'status' => [[
        'name' => $segments[2] ?? 'stream',
        'successful' => $scenario === 'rejected' ? 0 : (is_array($payload) ? count($payload) : 1),
        'failed' => in_array($scenario, ['partial', 'rejected'], true) ? 1 : 0,
    ]],
]);
