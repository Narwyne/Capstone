<?php
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

require_once 'includes/db.php';
require_once 'includes/types.php';

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
}

try {
    $stats = [
        'total'     => (int) $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(),
        'active'    => (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='open'")->fetchColumn(),
        'resolved'  => (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='resolved'")->fetchColumn(),
        'high_risk' => (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE severity IN ('high','critical') AND status='open'")->fetchColumn(),
    ];

    // Breakdown by type (open only)
    $byType = $pdo->query("
        SELECT incident_type, severity, COUNT(*) as cnt
        FROM incidents WHERE status='open'
        GROUP BY incident_type, severity
        ORDER BY incident_type
    ")->fetchAll();

    // Last 8 unresolved incidents, with status and the latest admin note
    $recent = $pdo->query("
        SELECT
            i.incident_type, i.severity, i.status,
            CONCAT(COALESCE(loc.name, i.location), IF(br.name IS NULL, '', CONCAT(' (', br.name, ')'))) AS location,
            i.reported_at,
            (SELECT u.note FROM incident_updates u
              WHERE u.incident_id = i.id AND u.note IS NOT NULL AND u.note <> ''
              ORDER BY u.id DESC LIMIT 1) AS last_note
        FROM incidents i
        LEFT JOIN locations loc ON loc.id = i.location_id
        LEFT JOIN branches br ON br.id = loc.branch_id
        WHERE i.status <> 'resolved'
        ORDER BY i.reported_at DESC LIMIT 8
    ")->fetchAll();

    $locations = [];
    try {
        $locations = $pdo->query("
            SELECT b.name AS branch, l.name
            FROM locations l JOIN branches b ON b.id = l.branch_id
            WHERE l.is_active = 1
            ORDER BY b.name, l.sort_order, l.name
        ")->fetchAll();
    } catch (PDOException $e) {}

    echo json_encode([
        'stats'  => $stats,
        'locations' => $locations,
        'byType' => $byType,
        'recent' => $recent,
        'types'  => array_map(fn($t) => ['slug' => $t['slug'], 'label' => $t['label']], incidentTypes($pdo)),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}