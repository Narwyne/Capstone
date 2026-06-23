<?php
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$host   = 'localhost';
$dbname = 'campus_system';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Stats
    $stats = [
        'total'     => (int) $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(),
        'active'    => (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='open'")->fetchColumn(),
        'resolved'  => (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='resolved'")->fetchColumn(),
        'high_risk' => (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE severity IN ('high','critical') AND status='open'")->fetchColumn(),
    ];

    // Breakdown by type (open only) — compact, not full rows
    $byType = $pdo->query("
        SELECT incident_type, severity, COUNT(*) as cnt
        FROM incidents WHERE status='open'
        GROUP BY incident_type, severity
        ORDER BY incident_type
    ")->fetchAll();

    // Last 5 open incidents — minimal fields only
    $recent = $pdo->query("
        SELECT incident_type, severity, location, reported_at
        FROM incidents WHERE status='open'
        ORDER BY reported_at DESC LIMIT 5
    ")->fetchAll();

    echo json_encode([
        'stats'   => $stats,
        'byType'  => $byType,
        'recent'  => $recent,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
