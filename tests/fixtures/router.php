<?php

// Test double for the OpenObserve ingest endpoint.
//
// The organization id segment of the request path doubles as the status code to
// return, so a test can drive any response by choosing the organization id it
// constructs the handler with.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim((string) $path, '/'));
$status = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : 200;

http_response_code($status);
header('Content-Type: application/json');
echo json_encode(['code' => $status, 'message' => 'stub response']);
