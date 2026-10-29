
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

// Accept either job_id or id from the edit form.
$job_id = filter_var(
    $_POST["job_id"] ?? $_POST["id"] ?? "",
    FILTER_VALIDATE_INT
);

$client_id = filter_var(
    $_POST["client_id"] ?? "",
    FILTER_VALIDATE_INT
);

$workerValue = trim($_POST["worker_id"] ?? "");
$worker_id = null;

if ($workerValue !== "") {
    $worker_id = filter_var($workerValue, FILTER_VALIDATE_INT);

    if ($worker_id === false || $worker_id < 1) {
        echo json_encode([
            "success" => false,
            "message" => "Please select a valid worker."
        ]);
        exit;
    }
}

$start_date = trim($_POST["start_date"] ?? "");
$end_date = trim($_POST["end_date"] ?? "");
$schedule = trim($_POST["schedule"] ?? "");
$monthly_rate = $_POST["monthly_rate"] ?? "";
$status = strtolower(trim($_POST["status"] ?? "active"));
$notes = trim($_POST["notes"] ?? "");

$allowedStatuses = [
    "active",
    "paused",
    "completed",
    "cancelled"
];

if (!$job_id || $job_id < 1 || !$client_id || $client_id < 1) {
    echo json_encode([
        "success" => false,
        "message" => "A valid job ID and customer are required."
    ]);
    exit;
}

$dateCheck = DateTime::createFromFormat("!Y-m-d", $start_date);

if (
    !$dateCheck ||
    $dateCheck->format("Y-m-d") !== $start_date
) {
    echo json_encode([
        "success" => false,
        "message" => "Please enter a valid start date."
    ]);
    exit;
}

if ($end_date !== "") {
    $endDateCheck = DateTime::createFromFormat("!Y-m-d", $end_date);

    if (
        !$endDateCheck ||
        $endDateCheck->format("Y-m-d") !== $end_date
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Please enter a valid end date."
        ]);
        exit;
    }

    if ($end_date < $start_date) {
        echo json_encode([
            "success" => false,
            "message" => "End date cannot be before start date."
        ]);
        exit;
    }
}

if (
    !is_numeric($monthly_rate) ||
    (float)$monthly_rate < 0
) {
    echo json_encode([
        "success" => false,
        "message" => "Please enter a valid monthly rate."
    ]);
    exit;
}

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode([
        "success" => false,
        "message" => "Please select a valid job status."
    ]);
    exit;
}

try {
    // Confirm that the customer exists.
    $clientCheck = $conn->prepare(
        "SELECT client_id FROM clients WHERE client_id = ? LIMIT 1"
    );
    $clientCheck->execute([$client_id]);

    if (!$clientCheck->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            "success" => false,
            "message" => "The selected customer was not found."
        ]);
        exit;
    }

    // Confirm that the job exists.
    $jobCheck = $conn->prepare(
        "SELECT job_id FROM jobs WHERE job_id = ? LIMIT 1"
    );
    $jobCheck->execute([$job_id]);

    if (!$jobCheck->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            "success" => false,
            "message" => "Job not found."
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE jobs
        SET
            client_id = :client_id,
            worker_id = :worker_id,
            start_date = :start_date,
            end_date = :end_date,
            schedule = :schedule,
            monthly_rate = :monthly_rate,
            status = :status,
            notes = :notes
        WHERE job_id = :job_id
    ");

    $stmt->execute([
        ":client_id" => $client_id,
        ":worker_id" => $worker_id,
        ":start_date" => $start_date,
        ":end_date" => $end_date !== "" ? $end_date : null,
        ":schedule" => $schedule !== "" ? $schedule : null,
        ":monthly_rate" => (float)$monthly_rate,
        ":status" => $status,
        ":notes" => $notes !== "" ? $notes : null,
        ":job_id" => $job_id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Job updated successfully."
    ]);

} catch (PDOException $e) {
    error_log("Job update error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to update job. Please check the submitted details."
    ]);
}
