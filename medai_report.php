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

    $stmt = $pdo->prepare("
        INSERT INTO incidents (incident_type, severity, location, description, reported_by, status, reported_at)
        VALUES (:type, :severity, :location, :description, :reported_by, 'open', NOW())
    ");

    $stmt->execute([
        ':type'        => $type,
        ':severity'    => $severity,
        ':location'    => $location,
        ':description' => $description,
        ':reported_by' => $_SESSION['user'],
    ]);

    echo json_encode(['success' => true, 'message' => 'Incident reported successfully.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save incident: ' . $e->getMessage()]);
}
