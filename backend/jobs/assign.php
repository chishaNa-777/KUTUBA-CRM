
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

$job_id = filter_var(
    $_POST["job_id"] ?? "",
    FILTER_VALIDATE_INT
);

$worker_id = filter_var(
    $_POST["worker_id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$job_id || !$worker_id) {
    echo json_encode([
        "success" => false,
        "message" => "A valid job and worker are required."
    ]);
    exit;
}

try {
    // Confirm that the job exists.
    $jobCheck = $conn->prepare(
        "SELECT job_id, worker_id
         FROM jobs
         WHERE job_id = ?"
    );
    $jobCheck->execute([$job_id]);
    $job = $jobCheck->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        echo json_encode([
            "success" => false,
            "message" => "Job not found."
        ]);
        exit;
    }

    // Confirm that the worker exists and is active.
    $workerCheck = $conn->prepare(
        "SELECT worker_id, full_name, availability, status
         FROM workers
         WHERE worker_id = ?"
    );
    $workerCheck->execute([$worker_id]);
    $worker = $workerCheck->fetch(PDO::FETCH_ASSOC);

    if (!$worker) {
        echo json_encode([
            "success" => false,
            "message" => "Worker not found."
        ]);
        exit;
    }

    if ($worker["status"] !== "active") {
        echo json_encode([
            "success" => false,
            "message" => "This worker is inactive and cannot be assigned."
        ]);
        exit;
    }

    // Do not update the job if this worker is already assigned.
    if ((int)$job["worker_id"] === (int)$worker_id) {
        echo json_encode([
            "success" => true,
            "message" => "This worker is already assigned to this job."
        ]);
        exit;
    }

    // Assign the worker to the job.
    $stmt = $conn->prepare(
        "UPDATE jobs
         SET worker_id = ?
         WHERE job_id = ?"
    );
    $stmt->execute([$worker_id, $job_id]);

    // Mark the newly assigned worker as assigned.
    $updateWorker = $conn->prepare(
        "UPDATE workers
         SET availability = 'assigned'
         WHERE worker_id = ?"
    );
    $updateWorker->execute([$worker_id]);

    echo json_encode([
        "success" => true,
        "message" => "Worker assigned successfully.",
        "job_id" => $job_id,
        "worker_id" => $worker_id,
        "worker_name" => $worker["full_name"]
    ]);

} catch (PDOException $e) {
    error_log("Job assignment error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "A database error occurred while assigning the worker."
    ]);
}
