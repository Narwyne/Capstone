<?php
// includes/risk_engine.php
// Deterministic risk scoring from historical incident data. Requires $pdo.

const RISK_SEVERITY_WEIGHTS   = ['low' => 1, 'medium' => 3, 'high' => 6, 'critical' => 10];
const RISK_STATUS_MULTIPLIERS = ['open' => 1.5, 'in_progress' => 1.2, 'resolved' => 1.0];
const RISK_LOOKBACK_DAYS = 90;

const RISK_LEVELS = [
    ['max' => 4,           'label' => 'Low',      'color' => 'emerald'],
    ['max' => 14,          'label' => 'Moderate', 'color' => 'amber'],
    ['max' => 29,          'label' => 'High',     'color' => 'orange'],
    ['max' => PHP_INT_MAX, 'label' => 'Critical', 'color' => 'red'],
];

function riskLevelFor(float $score): array {
    foreach (RISK_LEVELS as $lvl) {
        if ($score <= $lvl['max']) return $lvl;
    }
    $levels = RISK_LEVELS;
    return end($levels);
}

function fetchScoredIncidents(PDO $pdo): array {
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . RISK_LOOKBACK_DAYS . ' days'));
    // location is COALESCEd: prefer the live name via location_id, fall
    // back to the point-in-time text snapshot if that location was since
    // renamed or deleted.
    $stmt = $pdo->prepare("
        SELECT
            i.incident_type, i.severity,
            COALESCE(loc.name, i.location) AS location,
            i.status, i.reported_at
        FROM incidents i
        LEFT JOIN locations loc ON loc.id = i.location_id
        WHERE i.reported_at >= :cutoff
    ");
    $stmt->execute([':cutoff' => $cutoff]);
    $rows = $stmt->fetchAll();

    $now = time();
    foreach ($rows as &$r) {
        $daysAgo   = max(0, ($now - strtotime($r['reported_at'])) / 86400);
        $recencyMx = max(0.4, 1 - ($daysAgo / RISK_LOOKBACK_DAYS) * 0.6); // recent = full weight, oldest = 40%
        $sevW      = RISK_SEVERITY_WEIGHTS[$r['severity']] ?? 1;
        $staW      = RISK_STATUS_MULTIPLIERS[$r['status']] ?? 1;
        $r['points'] = round($sevW * $staW * $recencyMx, 2);
    }
    return $rows;
}

function computeLocationRisk(array $scored): array {
    $byLoc = [];
    foreach ($scored as $r) {
        $loc = $r['location'];
        $byLoc[$loc]['score'] = ($byLoc[$loc]['score'] ?? 0) + $r['points'];
        $byLoc[$loc]['count'] = ($byLoc[$loc]['count'] ?? 0) + 1;
        $byLoc[$loc]['types'][$r['incident_type']] = ($byLoc[$loc]['types'][$r['incident_type']] ?? 0) + 1;
    }
    $result = [];
    foreach ($byLoc as $loc => $d) {
        arsort($d['types']);
        $level = riskLevelFor($d['score']);
        $result[] = [
            'location' => $loc, 'score' => round($d['score'], 1), 'count' => $d['count'],
            'top_type' => array_key_first($d['types']), 'level' => $level['label'], 'color' => $level['color'],
        ];
    }
    usort($result, fn($a, $b) => $b['score'] <=> $a['score']);
    return $result;
}

function computeTypeRisk(array $scored): array {
    $byType = [];
    foreach ($scored as $r) {
        $t = $r['incident_type'];
        $byType[$t]['score'] = ($byType[$t]['score'] ?? 0) + $r['points'];
        $byType[$t]['count'] = ($byType[$t]['count'] ?? 0) + 1;
    }
    $result = [];
    foreach ($byType as $t => $d) {
        $level = riskLevelFor($d['score']);
        $result[] = ['type' => $t, 'score' => round($d['score'], 1), 'count' => $d['count'], 'level' => $level['label'], 'color' => $level['color']];
    }
    usort($result, fn($a, $b) => $b['score'] <=> $a['score']);
    return $result;
}
