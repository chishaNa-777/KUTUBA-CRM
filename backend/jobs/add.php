
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

if (empty($_SESSION["user_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Not authenticated."
    ]);
}

$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "hr_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied. HR privileges required."
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "POST method required."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        respond(400, [
            "success" => false,
            "message" => "Invalid request data."
        ]);
    }

    $clientId = filter_var($data["client_id"] ?? null, FILTER_VALIDATE_INT);
    $workerId = filter_var($data["worker_id"] ?? null, FILTER_VALIDATE_INT);

    $startDate = trim($data["start_date"] ?? "");
    $endDate = trim($data["end_date"] ?? "");
    $schedule = trim($data["schedule"] ?? "");
    $monthlyRate = $data["monthly_rate"] ?? null;
    $status = strtolower(trim($data["status"] ?? "active"));
    $notes = trim($data["notes"] ?? "");

    if (!$clientId || !$workerId) {
        respond(422, [
            "success" => false,
            "message" => "Please select a customer and worker."
        ]);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        respond(422, [
            "success" => false,
            "message" => "A valid start date is required."
        ]);
    }

    $start = DateTime::createFromFormat("!Y-m-d", $startDate);

    if (!$start || $start->format("Y-m-d") !== $startDate) {
        respond(422, [
            "success" => false,
            "message" => "Invalid start date."
        ]);
    }

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

    if ($schedule === "" || strlen($schedule) > 120) {
        respond(422, [
            "success" => false,
            "message" => "Enter a schedule of no more than 120 characters."
        ]);
    }

    if (!is_numeric($monthlyRate) || (float)$monthlyRate < 0) {
        respond(422, [
            "success" => false,
            "message" => "Enter a valid monthly rate."
        ]);
    }

    if ((float)$monthlyRate > 99999999.99) {
        respond(422, [
            "success" => false,
            "message" => "Monthly rate is too large."
        ]);
    }

    if (!in_array($status, ["active", "completed", "cancelled"], true)) {
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

    $clientCheck = $conn->prepare("
        SELECT client_id
        FROM clients
        WHERE client_id = :client_id
    ");

    $clientCheck->execute([
        ":client_id" => $clientId
    ]);

    if (!$clientCheck->fetch()) {
        respond(404, [
            "success" => false,
            "message" => "Selected customer was not found."
        ]);
    }

    $workerCheck = $conn->prepare("
        SELECT worker_id
        FROM workers
        WHERE worker_id = :worker_id
    ");

    $workerCheck->execute([
        ":worker_id" => $workerId
    ]);

    if (!$workerCheck->fetch()) {
        respond(404, [
            "success" => false,
            "message" => "Selected worker was not found."
        ]);
    }

    $stmt = $conn->prepare("
        INSERT INTO jobs (
            client_id,
            worker_id,
            start_date,
            end_date,
            schedule,
            monthly_rate,
            status,
            notes,
            created_at,
            updated_at
        )
        VALUES (
            :client_id,
            :worker_id,
            :start_date,
            :end_date,
            :schedule,
            :monthly_rate,
            :status,
            :notes,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
        RETURNING job_id
    ");

    $stmt->execute([
        ":client_id" => $clientId,
        ":worker_id" => $workerId,
        ":start_date" => $startDate,
        ":end_date" => $endDate,
        ":schedule" => $schedule,
        ":monthly_rate" => $monthlyRate,
        ":status" => $status,
        ":notes" => $notes !== "" ? $notes : null
    ]);

    $jobId = $stmt->fetchColumn();

    respond(201, [
        "success" => true,
        "message" => "Staff assignment created successfully.",
        "job_id" => $jobId
    ]);

} catch (PDOException $e) {

    error_log("Job creation error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to create staff assignment."
    ]);

} catch (Throwable $e) {

    error_log("Job creation error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "An unexpected error occurred."
    ]);
}
