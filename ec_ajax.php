<?php
// ec_ajax.php — AJAX handler for emergency contacts
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

$host   = 'localhost';
$dbname = 'campus_system';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit();
}

$action = $_POST['action'] ?? '';

// ── Toggle active ─────────────────────────────────────
if ($action === 'toggle_emergency') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }

    $pdo->prepare("UPDATE emergency_services SET is_active = NOT is_active WHERE id=?")->execute([$id]);
    $row = $pdo->prepare("SELECT is_active FROM emergency_services WHERE id=?");
    $row->execute([$id]);
    $result = $row->fetch();

    echo json_encode(['success' => true, 'is_active' => (int)$result['is_active']]);
    exit();
}

// ── Delete ────────────────────────────────────────────
if ($action === 'delete_emergency') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }

    $pdo->prepare("DELETE FROM emergency_services WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit();
}

// ── Add new ───────────────────────────────────────────
if ($action === 'add_emergency') {
    $allowed_cats = ['fire','medical','police','campus','other'];
    $category    = trim($_POST['category']       ?? '');
    $name        = trim($_POST['ec_name']         ?? '');
    $number      = trim($_POST['number']          ?? '');
    $address     = trim($_POST['address']         ?? '');
    $description = trim($_POST['ec_description']  ?? '');

    if (!in_array($category, $allowed_cats))  { echo json_encode(['success'=>false,'message'=>'Invalid category']); exit(); }
    if (empty($name))   { echo json_encode(['success'=>false,'message'=>'Contact name is required']); exit(); }
    if (empty($number)) { echo json_encode(['success'=>false,'message'=>'Phone number is required']); exit(); }

    $stmt = $pdo->prepare("INSERT INTO emergency_services (category,name,number,address,description,is_active,sort_order) VALUES (?,?,?,?,?,1,0)");
    $stmt->execute([$category, $name, $number, $address, $description]);
    $newId = $pdo->lastInsertId();

    $row = $pdo->prepare("SELECT * FROM emergency_services WHERE id=?");
    $row->execute([$newId]);
    $contact = $row->fetch();

    echo json_encode(['success' => true, 'contact' => $contact]);
    exit();
}

// ── Edit ──────────────────────────────────────────────
if ($action === 'edit_emergency') {
    $allowed_cats = ['fire','medical','police','campus','other'];
    $id          = (int)($_POST['id']            ?? 0);
    $category    = trim($_POST['category']       ?? '');
    $name        = trim($_POST['ec_name']         ?? '');
    $number      = trim($_POST['number']          ?? '');
    $address     = trim($_POST['address']         ?? '');
    $description = trim($_POST['ec_description']  ?? '');

    if (!$id)                                    { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }
    if (!in_array($category, $allowed_cats))     { echo json_encode(['success'=>false,'message'=>'Invalid category']); exit(); }
    if (empty($name))   { echo json_encode(['success'=>false,'message'=>'Contact name is required']); exit(); }
    if (empty($number)) { echo json_encode(['success'=>false,'message'=>'Phone number is required']); exit(); }

    $stmt = $pdo->prepare("UPDATE emergency_services SET category=?,name=?,number=?,address=?,description=? WHERE id=?");
    $stmt->execute([$category, $name, $number, $address, $description, $id]);

    $row = $pdo->prepare("SELECT * FROM emergency_services WHERE id=?");
    $row->execute([$id]);
    $contact = $row->fetch();

    echo json_encode(['success' => true, 'contact' => $contact]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);