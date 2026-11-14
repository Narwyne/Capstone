<?php
// types_ajax.php — admin AJAX for incident types
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid method']); exit();
}
require_once 'includes/db.php';
if (!$pdo) { echo json_encode(['success'=>false,'message'=>'DB connection failed']); exit(); }

$action = $_POST['action'] ?? '';
function fail(string $m) { echo json_encode(["success"=>false,"message"=>$m]); exit(); }
function slugify(string $s): string { return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $s)), '_'); }
function fetchType(PDO $pdo, int $id) {
    $s = $pdo->prepare("SELECT * FROM incident_types WHERE id=?"); $s->execute([$id]); return $s->fetch();
}

try {
    if ($action === 'list') {
        echo json_encode(['success'=>true,'types'=>$pdo->query("SELECT * FROM incident_types ORDER BY sort_order, id")->fetchAll()]);
        exit();
    }

    if ($action === 'add') {
        $label = trim($_POST['label'] ?? ''); $icon = trim($_POST['icon'] ?? '') ?: '📋';
        if ($label === '' || mb_strlen($label) > 100) fail('Enter a name (max 100 characters)');
        if (mb_strlen($icon) > 8) fail('Icon should be a single emoji');
        $slug = slugify($label);
        if ($slug === '' || strlen($slug) > 50) fail('Invalid name');
        $next = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM incident_types WHERE slug<>'other'")->fetchColumn();
        try {
            $pdo->prepare("INSERT INTO incident_types (slug,label,icon,is_active,sort_order) VALUES (?,?,?,1,?)")
                ->execute([$slug, $label, $icon, min($next, 90)]);
        } catch (PDOException $e) { fail('That incident type already exists'); }
        echo json_encode(['success'=>true,'type'=>fetchType($pdo, (int)$pdo->lastInsertId())]); exit();
    }

    $id = (int)($_POST['id'] ?? 0);
    $t  = $id ? fetchType($pdo, $id) : null;
    if (!$t) fail('Type not found');

    if ($action === 'edit') {   // slug never changes, so old reports keep matching
        $label = trim($_POST['label'] ?? ''); $icon = trim($_POST['icon'] ?? '') ?: '📋';
        if ($label === '' || mb_strlen($label) > 100) fail('Enter a name (max 100 characters)');
        if (mb_strlen($icon) > 8) fail('Icon should be a single emoji');
        $pdo->prepare("UPDATE incident_types SET label=?, icon=? WHERE id=?")->execute([$label, $icon, $id]);
        echo json_encode(['success'=>true,'type'=>fetchType($pdo, $id)]); exit();
    }

    if ($action === 'toggle') {
        if ($t['slug'] === 'other') fail('"Other" must stay available');
        $pdo->prepare("UPDATE incident_types SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true,'type'=>fetchType($pdo, $id)]); exit();
    }

    if ($action === 'delete') {
        if ($t['slug'] === 'other') fail('"Other" cannot be deleted');
        $c = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE incident_type=?"); $c->execute([$t['slug']]);
        $n = (int)$c->fetchColumn();
        if ($n > 0) fail("$n report(s) use this type. Hide it instead of deleting.");
        $pdo->prepare("DELETE FROM incident_types WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]); exit();
    }
} catch (PDOException $e) {
    error_log('types_ajax: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Database error. Did you import incident_types.sql?']); exit();
}
echo json_encode(['success'=>false,'message'=>'Unknown action']);