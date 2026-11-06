
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Require login.
if (empty($_SESSION["user_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Not authenticated."
    ]);
}

// Only administrators and IT officers may view activity logs.
$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "it"], true)) {
    respond(403, [
        "success" => false,
        "message" => "You are not authorised to view activity logs."
    ]);
}

// GET requests only.
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(405, [
        "success" => false,
        "message" => "Invalid request method."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    $stmt = $conn->prepare("
        SELECT
            al.log_id,
            al.user_id,
            u.full_name,
            al.action,
            al.description,
            al.ip_address,
            al.created_at
        FROM activity_logs al
        LEFT JOIN users u
            ON al.user_id = u.user_id
        ORDER BY al.created_at DESC, al.log_id DESC
        LIMIT 1000
    ");

    $stmt->execute();

    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(200, [
        "success" => true,
        "activities" => $activities
    ]);

} catch (Throwable $e) {

    error_log("Get activity logs error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to retrieve activity logs."
    ]);
}
