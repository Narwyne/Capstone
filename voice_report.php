<?php
// voice_report.php — turns a spoken transcript into structured incident fields.
// It does NOT save anything; the modal fills the form so the user can review & submit.
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid method']); exit(); }

require_once 'includes/db.php';
require_once 'includes/groq.php';

$data       = json_decode(file_get_contents('php://input'), true);
$transcript = trim($data['transcript'] ?? '');

if (strlen($transcript) < 5) {
    echo json_encode(['success'=>false,'message'=>'I could not hear enough. Please try again.']);
    exit();
}
$transcript = mb_substr($transcript, 0, 1000);

// Active locations (name => branch) so the AI can only pick real ones
$locs = [];
if ($pdo) {
    try {
        $rows = $pdo->query("
            SELECT l.name, b.name AS branch
            FROM locations l JOIN branches b ON b.id = l.branch_id
            WHERE l.is_active = 1
        ")->fetchAll();
        foreach ($rows as $r) $locs[$r['name']] = $r['branch'];
    } catch (PDOException $e) {}
}
$locList = $locs ? implode(', ', array_keys($locs)) : 'none';

$prompt = "You are a campus incident report assistant. The user SPOKE this report (may be English, Filipino/Tagalog, or Waray; may have speech-to-text errors):
\"{$transcript}\"

Respond ONLY with JSON (no markdown):
{\"incident_type\":\"<fire|medical|accident|suspicious|theft|flooding|earthquake|other>\",\"severity\":\"<low|medium|high|critical>\",\"location\":\"<one of the list or empty string>\",\"description\":\"<clear English description, 1-2 sentences, only facts the user said>\",\"reason\":\"<short reason for severity>\"}
low: minor, no danger. medium: moderate risk. high: serious/urgent. critical: life-threatening.
Valid locations: {$locList}
If the spoken place does not clearly match a valid location, use empty string. Never invent facts.";

$res = groqChat([['role'=>'user','content'=>$prompt]], 0.1, 500, 15);

if (!$res['ok']) {
    echo json_encode(['success'=>false,'message'=>'AI unavailable. Please try again.']);
    exit();
}

$text = preg_replace('/```(?:json)?|```/', '', trim($res['text']));
$out  = json_decode($text, true);

$types = ['fire','medical','accident','suspicious','theft','flooding','earthquake','other'];
$sevs  = ['low','medium','high','critical'];

if (!$out || !in_array($out['incident_type'] ?? '', $types) || !in_array($out['severity'] ?? '', $sevs)) {
    echo json_encode(['success'=>false,'message'=>'Could not understand the report. Try speaking again.']);
    exit();
}

$loc = $out['location'] ?? '';
if (!isset($locs[$loc])) $loc = '';

echo json_encode([
    'success'       => true,
    'incident_type' => $out['incident_type'],
    'severity'      => $out['severity'],
    'reason'        => $out['reason'] ?? '',
    'location'      => $loc,
    'branch'        => $loc ? $locs[$loc] : '',
    'description'   => trim($out['description'] ?? $transcript),
]);
