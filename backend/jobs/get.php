
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
            clients.full_name AS customer_name,
            workers.full_name AS worker_name
        FROM jobs

        LEFT JOIN clients
            ON jobs.client_id = clients.client_id

        LEFT JOIN workers
            ON jobs.worker_id = workers.worker_id

        ORDER BY jobs.job_id DESC
    ");

    $stmt->execute();

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(200, [
        "success" => true,
        "jobs" => $jobs
    ]);

} catch (PDOException $e) {

    error_log("Jobs fetch error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to retrieve staff assignments."
    ]);
}
