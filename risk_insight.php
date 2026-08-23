<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'includes/db.php';
require_once 'includes/risk_engine.php';
require_once 'includes/groq.php';

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit();
}

$scored     = fetchScoredIncidents($pdo);
$byLocation = computeLocationRisk($scored);
$byType     = computeTypeRisk($scored);

if (empty($byLocation)) {
    echo json_encode(['success' => true, 'text' => 'Not enough incident history yet to generate a risk briefing.']);
    exit();
}

$locSummary  = implode('; ', array_map(fn($l) => "{$l['location']}:{$l['level']}(score {$l['score']}, {$l['count']} incidents, mostly {$l['top_type']})", array_slice($byLocation, 0, 8)));
$typeSummary = implode('; ', array_map(fn($t) => "{$t['type']}:{$t['level']}(score {$t['score']})", $byType));

$prompt = "You are a campus safety risk analyst. Using ONLY this data (last " . RISK_LOOKBACK_DAYS . " days, pre-weighted by severity/status/recency), write a short briefing for administrators.

LOCATION RISK: {$locSummary}
INCIDENT TYPE RISK: {$typeSummary}

Format: 1-2 sentence overall assessment, then 2-4 bullets on highest-priority locations/types and why, then 1-2 bullets of concrete recommended actions. Under 150 words. Never invent numbers not given above.";

$res = groqChat([['role' => 'user', 'content' => $prompt]], 0.3, 400);

echo json_encode($res['ok']
    ? ['success' => true, 'text' => $res['text']]
    : ['success' => false, 'message' => $res['error']]);