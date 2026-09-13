<?php
// medai_emergency.php
// Read-only endpoint: active emergency contacts, grouped by category.
// Used by the Medai chat widget for emergency escalation + citing real
// numbers instead of hardcoded ones. Same auth pattern as medai_incidents.php.

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$host   = 'localhost';
$dbname = 'campus_system';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $rows = $pdo->query("
        SELECT category, name, number, address, description
        FROM emergency_services
        WHERE is_active = 1
        ORDER BY category, sort_order, id
    ")->fetchAll();

    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['category']][] = $r;
    }

    echo json_encode(['contacts' => $grouped]);

} catch (PDOException $e) {
    // DB/table unavailable — fall back to the same static numbers
    // emergency.php uses, so the chat widget still has *something* to show.
    echo json_encode(['contacts' => [
        'fire'    => [['category'=>'fire','name'=>'Bureau of Fire Protection','number'=>'160','address'=>null,'description'=>'National fire emergency hotline']],
        'medical' => [['category'=>'medical','name'=>'National Emergency Hotline','number'=>'911','address'=>null,'description'=>'All-in-one emergency dispatch']],
        'police'  => [['category'=>'police','name'=>'PNP Emergency Hotline','number'=>'117','address'=>null,'description'=>'Philippine National Police']],
    ]]);
}
