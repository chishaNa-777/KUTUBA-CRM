
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond($status, $data)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Check authentication
if (empty($_SESSION["user_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Not authenticated."
    ]);
}

// Allow only Administrator and HR officers
$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "hr_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied. HR privileges required."
    ]);
}

// Connect to PostgreSQL
require_once __DIR__ . "/../config/database.php";

try {

    $stmt = $conn->prepare("
        SELECT *
        FROM workers
        ORDER BY worker_id DESC
    ");

    $stmt->execute();

    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(200, [
        "success" => true,
        "workers" => $workers
    ]);

} catch (PDOException $e) {

    error_log("Workers get.php error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to load workers."
    ]);
}
