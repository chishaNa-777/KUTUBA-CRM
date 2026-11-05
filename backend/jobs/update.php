
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

// Accept POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "POST method required."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    // Read JSON data
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        respond(400, [
            "success" => false,
            "message" => "Invalid request data."
        ]);
    }

    $jobId = filter_var(
        $data["job_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $clientId = filter_var(
        $data["client_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $workerId = filter_var(
        $data["worker_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $startDate = trim($data["start_date"] ?? "");
    $endDate = trim($data["end_date"] ?? "");
    $schedule = trim($data["schedule"] ?? "");
    $monthlyRate = $data["monthly_rate"] ?? null;
    $status = strtolower(trim($data["status"] ?? ""));
    $notes = trim($data["notes"] ?? "");

    // Validate IDs
    if (!$jobId || $jobId < 1 || !$clientId || $clientId < 1 ||
        !$workerId || $workerId < 1) {
        respond(422, [
            "success" => false,
            "message" => "Valid assignment, customer and worker are required."
        ]);
    }

    // Validate start date
    $start = DateTime::createFromFormat("!Y-m-d", $startDate);

    if (
        !$start ||
        $start->format("Y-m-d") !== $startDate
    ) {
        respond(422, [
            "success" => false,
            "message" => "Please enter a valid start date."
        ]);
    }

    // Validate end date
    if ($endDate !== "") {

        $end = DateTime::createFromFormat("!Y-m-d", $endDate);

        if (
            !$end ||
            $end->format("Y-m-d") !== $endDate ||
            $endDate < $startDate
        ) {
            respond(422, [
                "success" => false,
                "message" => "End date must be valid and cannot be before the start date."
            ]);
        }

    } else {
        $endDate = null;
    }

    // Validate schedule
    if ($schedule === "" || strlen($schedule) > 120) {
        respond(422, [
            "success" => false,
            "message" => "Enter a valid working schedule."
        ]);
    }

    // Validate monthly rate
    if (
        !is_numeric($monthlyRate) ||
        (float)$monthlyRate < 0 ||
        (float)$monthlyRate > 99999999.99
    ) {
        respond(422, [
            "success" => false,
            "message" => "Enter a valid monthly rate."
        ]);
    }

    // Validate status
    $allowedStatuses = [
        "active",
        "completed",
        "cancelled"
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        respond(422, [
            "success" => false,
            "message" => "Invalid assignment status."
        ]);
    }

    if (strlen($notes) > 10000) {
        respond(422, [
            "success" => false,
            "message" => "Notes are too long."
        ]);
    }

    // Check that assignment exists
    $jobCheck = $conn->prepare("
        SELECT job_id
        FROM jobs
        WHERE job_id = :job_id
    ");

    $jobCheck->execute([
        ":job_id" => $jobId
    ]);

    if (!$jobCheck->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            "success" => false,
            "message" => "Assignment not found."
        ]);
    }

    // Check customer
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

    // Check worker
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

    // Update assignment
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
            notes = :notes,
            updated_at = CURRENT_TIMESTAMP
        WHERE job_id = :job_id
    ");

    $stmt->execute([
        ":client_id" => $clientId,
        ":worker_id" => $workerId,
        ":start_date" => $startDate,
        ":end_date" => $endDate,
        ":schedule" => $schedule,
        ":monthly_rate" => (float)$monthlyRate,
        ":status" => $status,
        ":notes" => $notes !== "" ? $notes : null,
        ":job_id" => $jobId
    ]);

    respond(200, [
        "success" => true,
        "message" => "Staff assignment updated successfully."
    ]);

} catch (PDOException $e) {

    error_log("Job update error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to update staff assignment."
    ]);

} catch (Throwable $e) {

    error_log("Job update error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "An unexpected error occurred."
    ]);
}
