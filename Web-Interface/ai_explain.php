<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

// Read OpenAI key from .env file in Python engine folder
$env_path = 'C:/projects/ids-engine/.env';
$openai_key = '';

if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'OPENAI_KEY=') === 0) {
            $openai_key = trim(substr($line, strlen('OPENAI_KEY=')));
            break;
        }
    }
}

if (!$openai_key) {
    echo json_encode([
        'explanation' =>
            "**No OpenAI key configured.**\n\n" .
            "To enable AI explanations:\n" .
            "1. Go to https://platform.openai.com/api-keys\n" .
            "2. Create an API key\n" .
            "3. Add it to C:/projects/ids-engine/.env as OPENAI_KEY=sk-...\n\n" .
            "For now, here is a manual explanation based on the threat type."
    ]);
    exit;
}

// Read alert data from POST body
$alert = json_decode(file_get_contents('php://input'), true);
if (!$alert) {
    echo json_encode(['error' => 'No alert data received']);
    exit;
}

$threat_type = $alert['threat_type']  ?? 'UNKNOWN';
$severity    = $alert['severity']     ?? 'UNKNOWN';
$src_ip      = $alert['src_ip']       ?? 'unknown';
$dst_port    = $alert['dst_port']     ?? 'unknown';
$protocol    = $alert['protocol']     ?? 'unknown';
$confidence  = isset($alert['confidence'])
    ? round(floatval($alert['confidence']) * 100, 1) . '%'
    : 'unknown';
$abuse_score = $alert['abuse_score']  ?? 0;
$country     = $alert['country']      ?? 'unknown';
$description = $alert['description']  ?? '';

$prompt = <<<PROMPT
You are a cybersecurity analyst assistant. Explain the following security alert in plain English for a junior security analyst. Be concise (max 200 words), practical, and structured.

Alert details:
- Threat type: {$threat_type}
- Severity: {$severity}
- Source IP: {$src_ip} (Country: {$country}, AbuseIPDB score: {$abuse_score}/100)
- Destination port: {$dst_port}
- Protocol: {$protocol}
- Detection confidence: {$confidence}
- Description: {$description}

Explain:
1. What this threat type means in simple terms
2. Why it is dangerous
3. What the attacker is likely trying to do
4. What immediate action the analyst should take

Use **bold** for key terms. Keep it practical and direct.
PROMPT;

// Call OpenAI API
$payload = json_encode([
    'model'       => 'gpt-4o-mini',
    'max_tokens'  => 400,
    'messages'    => [
        [
            'role'    => 'system',
            'content' => 'You are a cybersecurity expert. Give concise, practical security analysis.',
        ],
        [
            'role'    => 'user',
            'content' => $prompt,
        ],
    ],
]);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_key,
    ],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response) {
    echo json_encode(['error' => 'No response from OpenAI API']);
    exit;
}

$data = json_decode($response, true);

if ($http_code !== 200) {
    $error = $data['error']['message'] ?? 'OpenAI API error';
    echo json_encode(['error' => $error]);
    exit;
}

$explanation = $data['choices'][0]['message']['content'] ?? '';

if (!$explanation) {
    echo json_encode(['error' => 'Empty response from OpenAI']);
    exit;
}

echo json_encode(['explanation' => $explanation]);