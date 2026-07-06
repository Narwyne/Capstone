<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) { echo json_encode(['error'=>'Unauthorized']); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'Invalid method']); exit(); }

require_once 'includes/groq.php';

$type        = trim($_POST['incident_type'] ?? '');
$description = trim($_POST['description']   ?? '');

if (strlen($description) < 5) {
    echo json_encode(['severity' => null, 'reason' => 'Description too short.']);
    exit();
}

$hash = md5($type.$description);
if (($_SESSION['last_classify_hash'] ?? '') === $hash) {
    echo json_encode($_SESSION['last_classify_result']);
    exit();
}

$prompt = "You are a campus safety classifier. Respond ONLY with JSON (no markdown):
{\"severity\":\"<low|medium|high|critical>\",\"reason\":\"<one short sentence>\"}
low: minor, no danger. medium: moderate risk. high: serious/urgent. critical: life-threatening.

Incident type: {$type}
Description: {$description}";

$res = groqChat([['role'=>'user','content'=>$prompt]], 0.1, 60);

if (!$res['ok']) {
    echo json_encode(['severity' => null, 'reason' => 'AI unavailable.']);
    exit();
}

$text   = preg_replace('/```(?:json)?|```/', '', trim($res['text']));
$result = json_decode($text, true);

if (!$result || !in_array($result['severity'] ?? '', ['low','medium','high','critical'])) {
    echo json_encode(['severity' => null, 'reason' => 'Could not parse AI response.']);
    exit();
}

$out = ['severity' => $result['severity'], 'reason' => $result['reason'] ?? ''];
$_SESSION['last_classify_hash']   = $hash;
$_SESSION['last_classify_result'] = $out;
echo json_encode($out);