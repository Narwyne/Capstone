<?php
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

$allowed_types     = ['fire','medical','accident','suspicious','theft','flooding','earthquake','other'];
$allowed_severities = ['low','medium','high','critical'];

$type        = trim($data['incident_type'] ?? '');
$severity    = trim($data['severity'] ?? '');
$location    = trim($data['location'] ?? '');
$description = trim($data['description'] ?? '');

if (!in_array($type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid incident type: ' . $type]);
    exit();
}
if (!in_array($severity, $allowed_severities)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid severity']);
    exit();
}
if (empty($location)) {
    http_response_code(400);
    echo json_encode(['error' => 'Location is required']);
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

    // Resolve the FK counterparts. Medai doesn't validate location against
    // the admin-managed list as strictly as the report modal does, so a
    // miss here just leaves location_id NULL — the text still saves fine.
    $location_id = null;
    try {
        $locStmt = $pdo->prepare("SELECT id FROM locations WHERE name = ? AND is_active = 1");
        $locStmt->execute([$location]);
        $found = $locStmt->fetchColumn();
        if ($found) $location_id = (int)$found;
    } catch (PDOException $e) { /* locations table unavailable — ignore */ }

    $reported_by_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    $stmt = $pdo->prepare("
        INSERT INTO incidents
            (incident_type, severity, location, location_id, description,
             reported_by, reported_by_user_id, status, reported_at)
        VALUES
            (:type, :severity, :location, :location_id, :description,
             :reported_by, :reported_by_user_id, 'open', NOW())
    ");

    $stmt->execute([
        ':type'                => $type,
        ':severity'            => $severity,
        ':location'            => $location,
        ':location_id'         => $location_id,
        ':description'         => $description,
        ':reported_by'         => $_SESSION['user'],
        ':reported_by_user_id' => $reported_by_user_id,
    ]);

    echo json_encode(['success' => true, 'message' => 'Incident reported successfully.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save incident: ' . $e->getMessage()]);
}
