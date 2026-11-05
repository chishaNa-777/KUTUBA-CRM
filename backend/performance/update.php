
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

// Check login
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

// Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Method not allowed."
    ]);
}

// Read JSON data
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

// Validate worker ID
$worker_id = filter_var(
    $data["worker_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

// Validate customer ID
$client_id = filter_var(
    $data["client_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

// Validate rating
$rating = filter_var(
    $data["rating"] ?? null,
    FILTER_VALIDATE_INT
);

$review = trim($data["review"] ?? "");

if (
    !$rating_id ||
    !$worker_id ||
    !$client_id ||
    $rating === false ||
    $rating < 1 ||
    $rating > 5 ||
    $review === "" ||
    strlen($review) > 5000
) {
    respond(422, [
        "success" => false,
        "message" => "Please provide valid review details."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    // Check that the review exists
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

    // Check that the worker exists
    $workerCheck = $conn->prepare("
        SELECT worker_id
        FROM workers
        WHERE worker_id = :worker_id
    ");

    $workerCheck->execute([
        ":worker_id" => $worker_id
    ]);

    if (!$workerCheck->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            "success" => false,
            "message" => "Selected worker was not found."
        ]);
    }

    // Check that the customer exists
    $clientCheck = $conn->prepare("
        SELECT client_id
        FROM clients
        WHERE client_id = :client_id
    ");

    $clientCheck->execute([
        ":client_id" => $client_id
    ]);

    if (!$clientCheck->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            "success" => false,
            "message" => "Selected customer was not found."
        ]);
    }

    // Update the existing review
    // Original rating_date and rated_by are preserved
    $stmt = $conn->prepare("
        UPDATE performance
        SET
            worker_id = :worker_id,
            client_id = :client_id,
            rating = :rating,
            review = :review
        WHERE rating_id = :rating_id
        RETURNING rating_id
    ");

    $stmt->execute([
        ":worker_id" => $worker_id,
        ":client_id" => $client_id,
        ":rating" => $rating,
        ":review" => $review,
        ":rating_id" => $rating_id
    ]);

    respond(200, [
        "success" => true,
        "message" => "Performance review updated successfully.",
        "rating_id" => $rating_id
    ]);

} catch (PDOException $e) {

    error_log("Performance update error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "A database error occurred while updating the review."
    ]);
}
