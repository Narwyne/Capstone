<?php
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

require_once 'includes/groq.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$messages = [];
if (!empty($data['system'])) $messages[] = ['role' => 'system', 'content' => $data['system']];
foreach ($data['messages'] as $m) $messages[] = ['role' => $m['role'], 'content' => $m['content']];

$res = groqChat($messages, 0.4, 600);

if ($res['ok']) {
    echo json_encode(['content' => [['type' => 'text', 'text' => $res['text']]]]);
} else {
    http_response_code(502);
    echo json_encode(['error' => $res['error']]);
}