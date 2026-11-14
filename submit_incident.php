<?php
// submit_incident.php — handles POST from the Report Incident modal (JSON response)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

require_once 'includes/db.php';
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database error. Please contact the administrator.']);
    exit();
}

$incident_type = trim($_POST['incident_type'] ?? '');
$severity      = trim($_POST['severity']      ?? '');
$location      = trim($_POST['location']      ?? '');
$branch        = trim($_POST['branch']        ?? '');
$description   = trim($_POST['description']   ?? '');
$anonymous     = isset($_POST['anonymous']) && $_POST['anonymous'] == '1';
$reported_by   = $anonymous ? 'Anonymous' : $_SESSION['user'];
$reported_by_user_id = $anonymous ? null : ((int)($_SESSION['user_id'] ?? 0) ?: null);

require_once 'includes/types.php';
$allowed_types      = array_column(incidentTypes($pdo), 'slug');
$allowed_severities = ['low','medium','high','critical'];

// Location names can repeat across branches, so validate the (branch, name)
// pair and resolve the FK from that pair.
$location_id   = null;
$location_ok   = false;
$locTableFound = false;
try {
    $locRows = $pdo->query("
        SELECT l.id, l.name, b.name AS branch
        FROM locations l JOIN branches b ON b.id = l.branch_id
        WHERE l.is_active = 1
    ")->fetchAll();
    $locTableFound = !empty($locRows);
    foreach ($locRows as $r) {
        if ($r['name'] === $location && ($branch === '' || $r['branch'] === $branch)) {
            $location_id = (int)$r['id'];
            $location_ok = true;
            break;
        }
    }
} catch (PDOException $e) {}

// Fallback to the old static list if the locations table is unavailable/empty
if (!$locTableFound) {
    $location_ok = in_array($location, ['engineering','admin','library','cafeteria','gymnasium','parking',
                                        'entrance','laboratory','clinic','comfort_room','grounds','other']);
}

$errors = [];
if (!in_array($incident_type, $allowed_types))      $errors[] = 'Invalid incident type.';
if (!in_array($severity,      $allowed_severities)) $errors[] = 'Invalid severity.';
if (!$location_ok)                                  $errors[] = 'Invalid location.';
if (strlen($description) < 10)                      $errors[] = 'Description too short.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit();
}

// --- Optional photo upload ---
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_mime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images allowed.']);
        exit();
    }
    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB.']);
        exit();
    }

    $upload_dir = 'uploads/incidents/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext      = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('inc_', true) . '.' . strtolower($ext);
    $dest     = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        $photo_path = $dest;
    }
}

// --- Insert ---
try {
    $stmt = $pdo->prepare("
        INSERT INTO incidents
            (incident_type, severity, location, location_id, description,
             reported_by, reported_by_user_id, photo_path, reported_at)
        VALUES
            (:incident_type, :severity, :location, :location_id, :description,
             :reported_by, :reported_by_user_id, :photo_path, NOW())
    ");
    $stmt->execute([
        ':incident_type'       => $incident_type,
        ':severity'            => $severity,
        ':location'            => $location,
        ':location_id'         => $location_id,
        ':description'         => $description,
        ':reported_by'         => $reported_by,
        ':reported_by_user_id' => $reported_by_user_id,
        ':photo_path'          => $photo_path,
    ]);

    echo json_encode([
        'success'     => true,
        'message'     => 'Incident reported successfully.',
        'incident_id' => $pdo->lastInsertId(),
    ]);
} catch (PDOException $e) {
    error_log('DB Error [submit_incident]: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please contact the administrator.']);
}