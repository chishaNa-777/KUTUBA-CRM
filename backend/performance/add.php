
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
$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "hr_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied. HR privileges required."
    ]);
}

// POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "POST method required."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    // Read JSON request
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        respond(400, [
            "success" => false,
            "message" => "Invalid request data."
        ]);
    }

    $workerId = filter_var(
        $data["worker_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $clientId = filter_var(
        $data["client_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $rating = filter_var(
        $data["rating"] ?? null,
        FILTER_VALIDATE_INT
    );

    $review = trim($data["review"] ?? "");

    // Validate IDs
    if (!$workerId || $workerId < 1 || !$clientId || $clientId < 1) {
        respond(422, [
            "success" => false,
            "message" => "Please select a valid worker and customer."
        ]);
    }

    // Validate rating
    if ($rating === false || $rating < 1 || $rating > 5) {
        respond(422, [
            "success" => false,
            "message" => "Rating must be between 1 and 5."
        ]);
    }

    // Validate review
    if ($review === "" || strlen($review) > 5000) {
        respond(422, [
            "success" => false,
            "message" => "Please enter a review of up to 5000 characters."
        ]);
    }

    // Check worker exists
    $workerCheck = $conn->prepare("
        SELECT worker_id
        FROM workers
        WHERE worker_id = :worker_id
    ");

    $workerCheck->execute([
        ":worker_id" => $workerId
    ]);

    if (!$workerCheck->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            "success" => false,
            "message" => "Selected worker was not found."
        ]);
    }

    // Check customer exists
    $clientCheck = $conn->prepare("
        SELECT client_id
        FROM clients
        WHERE client_id = :client_id
    ");

    $clientCheck->execute([
        ":client_id" => $clientId
    ]);

    if (!$clientCheck->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            "success" => false,
            "message" => "Selected customer was not found."
        ]);
    }

    // Record the review
    $stmt = $conn->prepare("
        INSERT INTO performance (
            worker_id,
            client_id,
            rating,
            review,
            rated_by,
            rating_date,
            created_at
        )
        VALUES (
            :worker_id,
            :client_id,
            :rating,
            :review,
            :rated_by,
            CURRENT_DATE,
            CURRENT_TIMESTAMP
        )
        RETURNING rating_id
    ");

    $stmt->execute([
        ":worker_id" => $workerId,
        ":client_id" => $clientId,
        ":rating" => $rating,
        ":review" => $review,
        ":rated_by" => $_SESSION["user_id"]
    ]);

    $ratingId = $stmt->fetchColumn();

    respond(201, [
        "success" => true,
        "message" => "Performance review recorded successfully.",
        "rating_id" => $ratingId
    ]);

} catch (PDOException $e) {

    error_log("Performance recording error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to record performance review."
    ]);

} catch (Throwable $e) {

    error_log("Performance recording error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "An unexpected error occurred."
    ]);
}
