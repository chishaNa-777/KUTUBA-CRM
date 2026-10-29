
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

$job_id = filter_var(
    $_GET["job_id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$job_id) {
    echo json_encode([
        "success" => false,
        "message" => "A valid Job ID is required."
    ]);
    exit;
}

try {
    // Get the job and its assigned worker, if any.
    $stmt = $conn->prepare("
        SELECT
            j.job_id,
            j.client_id,
            j.worker_id,
            j.start_date,
            j.end_date,
            j.schedule,
            j.status AS job_status,
            c.full_name AS client_name,
            w.full_name AS worker_name,
            w.phone,
            w.email,
            w.worker_role,
            w.availability,
            w.status AS worker_status
        FROM jobs j
        LEFT JOIN clients c
            ON j.client_id = c.client_id
        LEFT JOIN workers w
            ON j.worker_id = w.worker_id
        WHERE j.job_id = ?
    ");

    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        echo json_encode([
            "success" => false,
            "message" => "Job not found."
        ]);
        exit;
    }

    $workers = [];

    // Return an empty workers array if no worker is assigned.
    if ($job["worker_id"] !== null) {
        $workers[] = [
            "assignment_id" => (int)$job["job_id"],
            "job_id" => (int)$job["job_id"],
            "worker_id" => (int)$job["worker_id"],
            "full_name" => $job["worker_name"],
            "phone" => $job["phone"],
            "email" => $job["email"],
            "worker_role" => $job["worker_role"],
            "availability" => $job["availability"],
            "employment_status" => $job["worker_status"]
        ];
    }

    echo json_encode([
        "success" => true,
        "job_id" => (int)$job_id,
        "job" => $job,
        "workers" => $workers
    ]);

} catch (PDOException $e) {
    error_log("Assigned workers error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to load the assigned worker."
    ]);
}
