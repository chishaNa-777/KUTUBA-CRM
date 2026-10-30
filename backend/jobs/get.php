
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Require an authenticated session
if (empty($_SESSION["user_id"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

// Connect to the database
require_once __DIR__ . "/../config/database.php";

try {

    $stmt = $conn->prepare("
        SELECT
            jobs.job_id,
            jobs.client_id,
            jobs.worker_id,
            jobs.start_date,
            jobs.end_date,
            jobs.schedule,
            jobs.monthly_rate,
            jobs.status,
            jobs.notes,
            jobs.created_at,
            jobs.updated_at,
            clients.full_name
        FROM jobs
        LEFT JOIN clients
            ON jobs.client_id = clients.client_id
        ORDER BY jobs.job_id DESC
    ");

    $stmt->execute();

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "jobs" => $jobs
    ]);

} catch (PDOException $e) {

    error_log("Jobs fetch error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve jobs."
    ]);

}
