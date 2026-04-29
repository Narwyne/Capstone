<?php
// includes/admin_queries.php
// Runs all SELECT queries needed to render admin.php.
// Requires $pdo to already be set (include db.php first).

// ── Stats ─────────────────────────────────────────────────────────

$stats = ['users' => 0, 'total_reports' => 0, 'resolved' => 0, 'high_risk' => 0];

if ($pdo) {
    $stats['users']         = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_reports'] = (int) $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
    $stats['resolved']      = (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='resolved'")->fetchColumn();
    $stats['high_risk']     = (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE severity IN ('high','critical') AND status='open'")->fetchColumn();
}

// ── Users ─────────────────────────────────────────────────────────

$users = $pdo
    ? $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY id DESC")->fetchAll()
    : [];

// ── Incidents ─────────────────────────────────────────────────────

$incidents = $pdo
    ? $pdo->query("
        SELECT id, incident_type, severity, location, description,
               reported_by, status, photo_path, reported_at
        FROM incidents
        ORDER BY reported_at DESC
      ")->fetchAll()
    : [];

// ── Emergency contacts ────────────────────────────────────────────

$emergency_contacts = [];
if ($pdo) {
    try {
        $emergency_contacts = $pdo->query("
            SELECT * FROM emergency_services
            ORDER BY category, sort_order, id
        ")->fetchAll();
    } catch (PDOException $e) {
        // Table may not exist yet — silently ignore
    }
}
