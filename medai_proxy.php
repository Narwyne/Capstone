<?php
session_start();

// Only allow logged-in users
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// ---- YOUR GROQ API KEY ----
$api_key = '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';
// ---------------------------

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || !isset($data['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

// Build Groq-compatible request
// system prompt is passed separately, prepend it as a system message
$messages = [];
if (!empty($data['system'])) {
    $messages[] = ['role' => 'system', 'content' => $data['system']];
}
foreach ($data['messages'] as $msg) {
    $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
}

$payload = [
    'model'       => 'llama-3.1-8b-instant',
    'max_tokens'  => 1000,
    'messages'    => $messages,
];

// Forward to Groq
$ch = curl_init(' ');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
    ],
]);

$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Convert Groq response format to what the JS expects
// Groq uses OpenAI format: choices[0].message.content
// We wrap it to match what dashboard.js reads: content[0].text
$groq = json_decode($response, true);
if (isset($groq['choices'][0]['message']['content'])) {
    $reply = $groq['choices'][0]['message']['content'];
    http_response_code(200);
    echo json_encode([
        'content' => [['type' => 'text', 'text' => $reply]]
    ]);
} else {
    http_response_code($httpCode);
    echo $response;
}
