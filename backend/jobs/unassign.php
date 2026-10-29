
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
    $_POST["job_id"] ?? $_POST["assignment_id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$job_id || $job_id < 1) {
    echo json_encode([
        "success" => false,
        "message" => "A valid Job ID is required."
    ]);
    exit;
}

try {
    $conn->beginTransaction();

    // Find the job and lock it while processing.
    $check = $conn->prepare("
        SELECT job_id, worker_id
        FROM jobs
        WHERE job_id = ?
        FOR UPDATE
    ");

    $check->execute([$job_id]);
    $job = $check->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $conn->rollBack();

        echo json_encode([
            "success" => false,
            "message" => "Job not found."
        ]);
        exit;
    }

    if ($job["worker_id"] === null) {
        $conn->rollBack();

        echo json_encode([
            "success" => false,
            "message" => "This job has no assigned worker."
        ]);
        exit;
    }

    $old_worker_id = (int) $job["worker_id"];

    // Remove the assignment without deleting the job.
    $unassign = $conn->prepare("
        UPDATE jobs
        SET worker_id = NULL
        WHERE job_id = ?
    ");

    $unassign->execute([$job_id]);

    // Check whether this worker has another active job.
    $otherJobs = $conn->prepare("
        SELECT COUNT(*)
        FROM jobs
        WHERE worker_id = ?
          AND status = 'active'
    ");

    $otherJobs->execute([$old_worker_id]);
    $activeAssignments = (int) $otherJobs->fetchColumn();

    // Mark the worker available only if no active jobs remain.
    // Do not reactivate workers whose status is inactive.
    if ($activeAssignments === 0) {
        $updateWorker = $conn->prepare("
            UPDATE workers
            SET availability = 'available'
            WHERE worker_id = ?
              AND status = 'active'
        ");

        $updateWorker->execute([$old_worker_id]);
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Worker unassigned successfully.",
        "job_id" => (int) $job_id,
        "worker_id" => $old_worker_id
    ]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Worker unassignment error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "A database error occurred while unassigning the worker."
    ]);
}
