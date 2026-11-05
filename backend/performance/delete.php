
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond(int $status, array $data): void
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

// Check permissions
if (!in_array($_SESSION["role"] ?? "", ["admin", "hr_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied."
    ]);
}

// Only accept POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Method not allowed."
    ]);
}

// Read JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    respond(400, [
        "success" => false,
        "message" => "Invalid request data."
    ]);
}

// Validate review ID
$rating_id = filter_var(
    $data["rating_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$rating_id) {
    respond(422, [
        "success" => false,
        "message" => "Invalid performance review ID."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    // Check whether the review exists
    $check = $conn->prepare("
        SELECT rating_id
        FROM performance
        WHERE rating_id = :rating_id
    ");

    $check->execute([
        ":rating_id" => $rating_id
    ]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            "success" => false,
            "message" => "Performance review not found."
        ]);
    }

    // Delete only the selected review
    $stmt = $conn->prepare("
        DELETE FROM performance
        WHERE rating_id = :rating_id
    ");

    $stmt->execute([
        ":rating_id" => $rating_id
    ]);

    respond(200, [
        "success" => true,
        "message" => "Performance review deleted successfully."
    ]);

} catch (PDOException $e) {

    error_log("Performance delete error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "A database error occurred while deleting the review."
    ]);
}
