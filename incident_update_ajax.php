<?php
// incident_update_ajax.php — admin adds a status update + note to an incident
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit();
}

require_once 'includes/db.php';
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit();
}

$action     = $_POST['action'] ?? '';
$incidentId = (int)($_POST['incident_id'] ?? 0);
if (!$incidentId) {
    echo json_encode(['success' => false, 'message' => 'Invalid incident']);
    exit();
}

// ── History ──────────────────────────────────────────
if ($action === 'get_updates') {
    $stmt = $pdo->prepare("
        SELECT u.status, u.note, u.created_at, COALESCE(a.name, 'Admin') AS admin
        FROM incident_updates u
        LEFT JOIN users a ON a.id = u.admin_id
        WHERE u.incident_id = ?
        ORDER BY u.id DESC
    ");
    $stmt->execute([$incidentId]);
    echo json_encode(['success' => true, 'updates' => $stmt->fetchAll()]);
    exit();
}

// ── Add update ───────────────────────────────────────
if ($action === 'add_update') {
    $status = $_POST['status'] ?? '';
    $note   = trim($_POST['note'] ?? '');

    if (!in_array($status, ['open', 'in_progress', 'resolved'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    if (mb_strlen($note) > 500) {
        echo json_encode(['success' => false, 'message' => 'Note is too long (max 500 characters)']);
        exit();
    }

    $cur = $pdo->prepare("SELECT status FROM incidents WHERE id = ?");
    $cur->execute([$incidentId]);
    $current = $cur->fetchColumn();
    if ($current === false) {
        echo json_encode(['success' => false, 'message' => 'Incident not found']);
        exit();
    }
    if ($current === $status && $note === '') {
        echo json_encode(['success' => false, 'message' => 'Change the status or write a note']);
        exit();
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE incidents SET status = ? WHERE id = ?")->execute([$status, $incidentId]);
        $pdo->prepare("INSERT INTO incident_updates (incident_id, admin_id, status, note) VALUES (?,?,?,?)")
            ->execute([$incidentId, (int)($_SESSION['user_id'] ?? 0) ?: null, $status, $note !== '' ? $note : null]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('incident_update_ajax: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error. Did you import migration.sql?']);
        exit();
    }

    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
