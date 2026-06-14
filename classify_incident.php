<?php
// classify_incident.php — AI severity classifier using Gemini (free)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) { echo json_encode(['error'=>'Unauthorized']); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'Invalid method']); exit(); }

// ← Get your FREE key at https://aistudio.google.com/app/apikey
define('GEMINI_API_KEY', 'AQ.Ab8RN6JghLAigAu-enkTyT9HDqplmpdLAwfacPgdiMjf92UR_A');

$type        = trim($_POST['incident_type'] ?? '');
$description = trim($_POST['description']   ?? '');

if (strlen($description) < 5) {
    echo json_encode(['severity' => null, 'reason' => 'Description too short.']);
    exit();
}

$prompt = "You are a campus safety classifier. Given an incident report, respond ONLY with a JSON object in this exact format (no markdown, no extra text):
{\"severity\":\"<low|medium|high|critical>\",\"reason\":\"<one short sentence>\"}

Severity guidelines:
- low: minor, no immediate danger (e.g. small spill, lost item)
- medium: moderate risk, needs attention (e.g. minor injury, suspicious person)
- high: serious danger, urgent response needed (e.g. large fire, assault)
- critical: life-threatening, immediate emergency (e.g. cardiac arrest, armed threat)

Incident type: {$type}
Description: {$description}";

$payload = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 80]
]);

$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . GEMINI_API_KEY);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 10,
]);
$raw  = curl_exec($ch);
$err  = curl_error($ch);
curl_close($ch);

if ($err) { echo json_encode(['severity' => null, 'reason' => 'AI unavailable.']); exit(); }

$data = json_decode($raw, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Strip possible markdown fences
$text = preg_replace('/```(?:json)?|```/', '', trim($text));

$result = json_decode($text, true);
if (!$result || !isset($result['severity'])) {
    echo json_encode(['severity' => null, 'reason' => 'Could not parse AI response.']);
    exit();
}

$allowed = ['low','medium','high','critical'];
if (!in_array($result['severity'], $allowed)) {
    echo json_encode(['severity' => null, 'reason' => 'Invalid AI response.']);
    exit();
}

echo json_encode([
    'severity' => $result['severity'],
    'reason'   => $result['reason'] ?? ''
]);
