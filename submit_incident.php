<?php
// submit_incident.php
// Handles POST from the Report Incident modal
// Returns JSON response

session_start();
header('Content-Type: application/json');

// --- Auth check ---
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

// --- Only accept POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// -------------------------------------------------------
// DB CONFIG — update these to match your database
// -------------------------------------------------------
$host   = 'localhost';
$dbname = 'campus_system';
$user   = 'root';          // ← your DB username
$pass   = '';              // ← your DB password
// -------------------------------------------------------

// --- Collect & sanitize inputs ---
$incident_type = trim($_POST['incident_type'] ?? '');
$severity      = trim($_POST['severity']      ?? '');
$location      = trim($_POST['location']      ?? '');
$description   = trim($_POST['description']   ?? '');
$anonymous     = isset($_POST['anonymous']) && $_POST['anonymous'] == '1';
$reported_by   = $anonymous ? 'Anonymous' : $_SESSION['user'];

// Validate required fields
$allowed_types     = ['fire','medical','accident','suspicious','theft','flooding','earthquake','other'];
$allowed_severities= ['low','medium','high','critical'];
$allowed_locations = ['engineering','admin','library','cafeteria','gymnasium','parking',
                      'entrance','laboratory','clinic','comfort_room','grounds','other'];

$errors = [];
if (!in_array($incident_type, $allowed_types))     $errors[] = 'Invalid incident type.';
if (!in_array($severity,      $allowed_severities)) $errors[] = 'Invalid severity.';
if (!in_array($location,      $allowed_locations))  $errors[] = 'Invalid location.';
if (strlen($description) < 10)                      $errors[] = 'Description too short.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit();
}

// --- Handle optional photo upload ---
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
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext        = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename   = uniqid('inc_', true) . '.' . strtolower($ext);
    $dest       = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        $photo_path = $dest;
    }
}

// --- Insert into DB ---
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO incidents 
            (incident_type, severity, location, description, reported_by, photo_path, reported_at)
        VALUES 
            (:incident_type, :severity, :location, :description, :reported_by, :photo_path, NOW())
    ");

    $stmt->execute([
        ':incident_type' => $incident_type,
        ':severity'      => $severity,
        ':location'      => $location,
        ':description'   => $description,
        ':reported_by'   => $reported_by,
        ':photo_path'    => $photo_path,
    ]);

    $incident_id = $pdo->lastInsertId();

    echo json_encode([
        'success'     => true,
        'message'     => 'Incident reported successfully.',
        'incident_id' => $incident_id,
    ]);

} catch (PDOException $e) {
    // Don't expose DB errors to users in production
    error_log('DB Error [submit_incident]: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please contact the administrator.']);
}