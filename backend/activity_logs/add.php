
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

// Check whether the user is logged in.
if (empty($_SESSION["user_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Not authenticated."
    ]);
}

// POST requests only.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Invalid request method."
    ]);
}

// Accept JSON or normal form submissions.
$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

if (stripos($contentType, "application/json") !== false) {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        respond(400, [
            "success" => false,
            "message" => "Invalid JSON request."
        ]);
    }
} else {
    $input = $_POST;
}

$action = trim($input["action"] ?? "");
$description = trim($input["description"] ?? "");

if ($action === "") {
    respond(400, [
        "success" => false,
        "message" => "Activity action is required."
    ]);
}

if (strlen($action) > 100) {
    respond(400, [
        "success" => false,
        "message" => "Activity action is too long."
    ]);
}

if (strlen($description) > 5000) {
    respond(400, [
        "success" => false,
        "message" => "Activity description is too long."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    $userId = (int) $_SESSION["user_id"];

    $ipAddress = $_SERVER["REMOTE_ADDR"] ?? null;
    $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO activity_logs (
            user_id,
            action,
            description,
            ip_address,
            user_agent
        )
        VALUES (?, ?, ?, ?, ?)
        RETURNING log_id
    ");

    $stmt->execute([
        $userId,
        $action,
        $description !== "" ? $description : null,
        $ipAddress,
        $userAgent
    ]);

    $logId = $stmt->fetchColumn();

    respond(201, [
        "success" => true,
        "message" => "Activity recorded successfully.",
        "log_id" => (int) $logId
    ]);

} catch (Throwable $e) {

    error_log("Activity log error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to record activity."
    ]);
}
