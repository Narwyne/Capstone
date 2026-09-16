<?php
// location_ajax.php — AJAX handler for admin-managed incident locations
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
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

$action = $_POST['action'] ?? '';

function slugify(string $s): string {
    return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $s)), '_');
}

// ── Add new ───────────────────────────────────────────
if ($action === 'add_location') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') { echo json_encode(['success'=>false,'message'=>'Name is required']); exit(); }

    $slug = slugify($name);
    if ($slug === '') { echo json_encode(['success'=>false,'message'=>'Invalid name']); exit(); }

    try {
        $pdo->prepare("INSERT INTO locations (name,is_active,sort_order) VALUES (?,1,0)")
            ->execute([$slug]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'That location already exists']);
        exit();
    }

    $newId = $pdo->lastInsertId();
    $row = $pdo->prepare("SELECT * FROM locations WHERE id=?");
    $row->execute([$newId]);
    echo json_encode(['success' => true, 'location' => $row->fetch()]);
    exit();
}

// ── Edit ──────────────────────────────────────────────
if ($action === 'edit_location') {
    $id   = (int)($_POST['id']   ?? 0);
    $name = trim($_POST['name']  ?? '');

    if (!$id)          { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    if ($name === '')  { echo json_encode(['success'=>false,'message'=>'Name is required']); exit(); }

    $slug = slugify($name);
    if ($slug === '') { echo json_encode(['success'=>false,'message'=>'Invalid name']); exit(); }

    try {
        $pdo->prepare("UPDATE locations SET name=? WHERE id=?")->execute([$slug, $id]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'That name is already in use']);
        exit();
    }

    $row = $pdo->prepare("SELECT * FROM locations WHERE id=?");
    $row->execute([$id]);
    echo json_encode(['success' => true, 'location' => $row->fetch()]);
    exit();
}

// ── Toggle active ─────────────────────────────────────
if ($action === 'toggle_location') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }

    $pdo->prepare("UPDATE locations SET is_active = NOT is_active WHERE id=?")->execute([$id]);
    $row = $pdo->prepare("SELECT is_active FROM locations WHERE id=?");
    $row->execute([$id]);
    $result = $row->fetch();

    echo json_encode(['success' => true, 'is_active' => (int)$result['is_active']]);
    exit();
}

// ── Delete ────────────────────────────────────────────
if ($action === 'delete_location') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }

    $pdo->prepare("DELETE FROM locations WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
