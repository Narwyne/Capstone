<?php
// includes/db.php
// Shared database connection — include this wherever a PDO connection is needed.

define('DB_HOST',   'localhost');
define('DB_NAME',   'campus_system');
define('DB_USER',   'root');
define('DB_PASS',   '');

$pdo      = null;
$db_error = null;

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
