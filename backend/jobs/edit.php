
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    echo json_encode([
        "success" => false,
        "message" => "A valid Job ID is required."
    ]);
    exit;
}

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
        WHERE jobs.job_id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        echo json_encode([
            "success" => false,
            "message" => "Job not found."
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "job" => $job
    ]);

} catch (PDOException $e) {
    error_log("Job details error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load job details."
    ]);
}
