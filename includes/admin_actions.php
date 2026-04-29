<?php
// includes/admin_actions.php
// Handles all POST form submissions in admin.php.
// Requires $pdo to already be set (include db.php first).

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$pdo) {
    return; // nothing to do
}

$action = $_POST['action'] ?? '';

// ── Incident actions ──────────────────────────────────────────────

if ($action === 'resolve_incident' && isset($_POST['id'])) {
    $pdo->prepare("UPDATE incidents SET status='resolved' WHERE id=?")
        ->execute([(int)$_POST['id']]);
    header("Location: admin.php?toast=resolved");
    exit();
}

if ($action === 'delete_incident' && isset($_POST['id'])) {
    $pdo->prepare("DELETE FROM incidents WHERE id=?")
        ->execute([(int)$_POST['id']]);
    header("Location: admin.php?toast=deleted");
    exit();
}

// ── User actions ──────────────────────────────────────────────────

if ($action === 'delete_user' && isset($_POST['id'])) {
    // Prevent deleting yourself
    if ((int)$_POST['id'] !== (int)($_SESSION['user_id'] ?? -1)) {
        $pdo->prepare("DELETE FROM users WHERE id=?")
            ->execute([(int)$_POST['id']]);
    }
    header("Location: admin.php?toast=user_deleted");
    exit();
}

// ── Emergency contact actions (fallback form-based, AJAX handled by ec_ajax.php) ──

if ($action === 'add_emergency') {
    $pdo->prepare("INSERT INTO emergency_services (category,name,number,address,description,is_active,sort_order) VALUES (?,?,?,?,?,1,0)")
        ->execute([
            $_POST['category']        ?? 'other',
            trim($_POST['ec_name']         ?? ''),
            trim($_POST['number']          ?? ''),
            trim($_POST['address']         ?? ''),
            trim($_POST['ec_description']  ?? ''),
        ]);
    header("Location: admin.php?tab=emergency&toast=ec_added");
    exit();
}

if ($action === 'delete_emergency' && isset($_POST['id'])) {
    $pdo->prepare("DELETE FROM emergency_services WHERE id=?")
        ->execute([(int)$_POST['id']]);
    header("Location: admin.php?tab=emergency&toast=ec_deleted");
    exit();
}

if ($action === 'toggle_emergency' && isset($_POST['id'])) {
    $pdo->prepare("UPDATE emergency_services SET is_active = NOT is_active WHERE id=?")
        ->execute([(int)$_POST['id']]);
    header("Location: admin.php?tab=emergency&toast=ec_updated");
    exit();
}
