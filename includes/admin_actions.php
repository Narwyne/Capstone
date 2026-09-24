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
    $categorySlug = $_POST['category'] ?? 'other';
    $catStmt = $pdo->prepare("SELECT id FROM emergency_categories WHERE slug = ?");
    $catStmt->execute([$categorySlug]);
    $categoryId = $catStmt->fetchColumn();
    if (!$categoryId) {
        // Unknown slug — fall back to "other" rather than failing the insert
        $catStmt->execute(['other']);
        $categoryId = $catStmt->fetchColumn();
    }

    $pdo->prepare("INSERT INTO emergency_services (category_id,name,number,address,description,is_active,sort_order) VALUES (?,?,?,?,?,1,0)")
        ->execute([
            $categoryId,
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
