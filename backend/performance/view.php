
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

// GET requests only
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(405, [
        "success" => false,
        "message" => "GET method required."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    $ratingId = filter_var(
        $_GET["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$ratingId || $ratingId < 1) {
        respond(422, [
            "success" => false,
            "message" => "A valid performance review ID is required."
        ]);
    }

    $stmt = $conn->prepare("
        SELECT
            p.rating_id,
            p.worker_id,
            w.full_name AS worker_name,
            p.client_id,
            c.full_name AS customer_name,
            p.rating,
            p.review,
            p.rated_by,
            p.rating_date,
            p.created_at
        FROM performance p

        LEFT JOIN workers w
            ON p.worker_id = w.worker_id

        LEFT JOIN clients c
            ON p.client_id = c.client_id

        WHERE p.rating_id = :rating_id
        LIMIT 1
    ");

    $stmt->execute([
        ":rating_id" => $ratingId
    ]);

    $rating = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rating) {
        respond(404, [
            "success" => false,
            "message" => "Performance review not found."
        ]);
    }

    respond(200, [
        "success" => true,
        "rating" => $rating
    ]);

} catch (PDOException $e) {

    error_log("Performance view error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to load performance review."
    ]);
}
