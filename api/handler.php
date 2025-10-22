<?php
/**
 * MySQL Database Manager — Backend Proxy Handler
 *
 * API Documentation (proxied here, never exposed to client)
 * Base URL: https://aos.madoosi.com/api/db/api/api.php
 * Auth Header: X-Api-Key: <session_api_key>
 *
 * Endpoints:
 * - GET    /api.php?table=<table_name>                 → Fetch rows
 * - POST   /api.php?table=<table_name>                 → Insert row
 * - PUT    /api.php?table=<table_name>                 → Update row
 * - DELETE /api.php?table=<table_name>                 → Remove row
 * - POST   /api.php?action=generatekey                 → Create new API user and key
 *
 * Notes:
 * - This file validates session authentication and forwards requests to the upstream API with the stored API key.
 * - The client must NEVER see the API key; it stays server-side within this handler.
 */

// ------------------------------
// Session and guards
// ------------------------------
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['auth']) || empty($_SESSION['api_key'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Allow only specific actions
$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
if (!in_array($method, $allowedMethods, true)) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// Read JSON body if present
$rawBody = file_get_contents('php://input');
$body = [];
if (!empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $body = $decoded;
    }
}

// Simple router via query params: action or table
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$table  = isset($_GET['table']) ? trim((string)$_GET['table']) : '';

$baseUrl = 'https://aos.madoosi.com/api/db/api/api.php';

// Build upstream URL
$upstreamUrl = $baseUrl;
$query = [];
if ($action !== '') {
    $query['action'] = $action;
}
if ($table !== '') {
    $query['table'] = $table;
}
if (!empty($query)) {
    $upstreamUrl .= '?' . http_build_query($query);
}

// Prepare cURL
$ch = curl_init($upstreamUrl);
$headers = [
    'Content-Type: application/json',
    'X-Api-Key: ' . $_SESSION['api_key'],
];

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
}

$responseBody = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno !== 0) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'Upstream connection failed',
        'detail' => $error,
    ]);
    exit;
}

// Forward status code and body (ensure valid JSON)
if ($httpCode >= 400) {
    http_response_code($httpCode);
}

$decodedResp = json_decode($responseBody, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo json_encode($decodedResp);
} else {
    // Non-JSON response; wrap it
    echo json_encode([
        'status' => ($httpCode >= 400 ? 'error' : 'success'),
        'raw' => $responseBody,
    ]);
}
