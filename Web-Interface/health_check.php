<?php
/**
 * Health check endpoint — called by nav.php status dot
 * and can be used by monitoring tools.
 * No session required — returns JSON only.
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$result = [
    'php'      => true,
    'database' => false,
    'api'      => false,
    'time'     => date('Y-m-d H:i:s'),
];

// Check database
try {
    require_once 'db.php';
    $pdo->query("SELECT 1");
    $result['database'] = true;
    $count = $pdo->query("SELECT COUNT(*) FROM alerts")->fetchColumn();
    $result['alert_count'] = intval($count);
} catch (Exception $e) {
    $result['db_error'] = $e->getMessage();
}

// Check Python API
$ctx      = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
$response = @file_get_contents('http://localhost:5000/api/health', false, $ctx);

if ($response !== false) {
    $api_data         = json_decode($response, true);
    $result['api']    = true;
    $result['model']  = $api_data['model']  ?? 'unknown';
    $result['rules']  = $api_data['rules']  ?? 0;
    $result['queue']  = $api_data['queue']  ?? 0;
}

$result['all_ok'] = $result['php']
                 && $result['database']
                 && $result['api'];

http_response_code($result['all_ok'] ? 200 : 503);
echo json_encode($result, JSON_PRETTY_PRINT);