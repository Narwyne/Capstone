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
// location/reported_by are COALESCEd: prefer the live, joined name (via
// location_id / reported_by_user_id) and fall back to the point-in-time
// text snapshot if the location or user was since renamed/deleted.

$incidents = $pdo
    ? $pdo->query("
        SELECT
            i.id, i.incident_type, i.severity,
            COALESCE(loc.name, i.location) AS location,
            i.description,
            COALESCE(usr.name, i.reported_by) AS reported_by,
            i.status, i.photo_path, i.reported_at
        FROM incidents i
        LEFT JOIN locations loc ON loc.id = i.location_id
        LEFT JOIN users usr ON usr.id = i.reported_by_user_id
        ORDER BY i.reported_at DESC
      ")->fetchAll()
    : [];

// ── Emergency contacts ────────────────────────────────────────────

$emergency_contacts = [];
if ($pdo) {
    try {
        $emergency_contacts = $pdo->query("
            SELECT es.id, ec.slug AS category, es.name, es.number, es.address, es.description,
                   es.is_active, es.sort_order, es.created_at
            FROM emergency_services es
            JOIN emergency_categories ec ON ec.id = es.category_id
            ORDER BY ec.sort_order, es.sort_order, es.id
        ")->fetchAll();
    } catch (PDOException $e) {
        // Table may not exist yet — silently ignore
    }
}

// ── Locations ────────────────────────────────────────────────────

$locations = [];
if ($pdo) {
    try {
        $locations = $pdo->query("
            SELECT l.id, l.name, b.name AS branch, l.is_active, l.sort_order, l.created_at
            FROM locations l
            JOIN branches b ON b.id = l.branch_id
            ORDER BY l.sort_order, l.name
        ")->fetchAll();
    } catch (PDOException $e) {
        // Table may not exist yet — silently ignore
    }
}
