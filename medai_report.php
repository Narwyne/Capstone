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

require_once 'includes/db.php';
require_once 'includes/types.php';

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database unavailable']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Types come from the admin-managed list (active types only)
$allowed_types      = array_column(incidentTypes($pdo), 'slug');
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
if ($location === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Location is required']);
    exit();
}
if ($description === '') $description = 'Reported via Medai chat.';

$branch = trim($data['branch'] ?? '');

try {
    // Location MUST be one of the admin-managed, active locations (matched by name, and branch if given)
    $norm = fn($x) => str_replace(' ', '_', strtolower(trim($x)));
    $location_id = null;
    $rows = $pdo->query("
        SELECT l.id, l.name, b.name AS branch
        FROM locations l JOIN branches b ON b.id = l.branch_id
        WHERE l.is_active = 1
    ")->fetchAll();
    foreach ($rows as $r) {
        if ($norm($r['name']) === $norm($location) && ($branch === '' || strcasecmp($r['branch'], $branch) === 0)) {
            $location_id = (int)$r['id'];
            $location    = $r['name'];   // canonical name
            break;
        }
    }
    if ($location_id === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown location. Please choose a location from the list.']);
        exit();
    }

    // Max 5 Medai reports per user per hour
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $rl = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reported_by_user_id = ? AND description LIKE '[via Medai]%' AND reported_at > (NOW() - INTERVAL 1 HOUR)");
    $rl->execute([$uid]);
    if ((int)$rl->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many reports through Medai. Please try again later.']);
        exit();
    }

    $description = '[via Medai] ' . $description;

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
    error_log('medai_report: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save incident.']);
}