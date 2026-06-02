<?php
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

define('PYTHON_API', 'http://localhost:5000/api');

$settings_stmt = null;
try {
    require_once 'db.php';
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings      = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $API_KEY       = $settings['api_key'] ?? 'ids-api-key-change-me';
} catch (Exception $e) {
    $API_KEY = 'ids-api-key-change-me';
}

$endpoint = trim($_GET['endpoint'] ?? 'stats');
$allowed  = [
    'stats', 'alerts', 'rules', 'blacklist',
    'logs', 'health', 'alerts/'
];

// Build query string (pass all GET params except 'endpoint')
$params = $_GET;
unset($params['endpoint']);
$query_string = http_build_query($params);

$url = PYTHON_API . '/' . $endpoint;
if ($query_string) {
    $url .= '?' . $query_string;
}

// Build request context
$method  = $_SERVER['REQUEST_METHOD'];
$options = [
    'http' => [
        'method'        => $method,
        'header'        => "X-API-Key: $API_KEY\r\nContent-Type: application/json\r\n",
        'timeout'       => 8,
        'ignore_errors' => true,
    ]
];

if ($method === 'POST' || $method === 'DELETE') {
    $body = file_get_contents('php://input');
    if ($body) {
        $options['http']['content'] = $body;
    }
}

$context  = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    http_response_code(503);
    echo json_encode(['error' => 'Python engine offline', 'url' => $url]);
    exit;
}

header('Content-Type: application/json');
echo $response; 