
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

function respond($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request method.");
}

$client_id = filter_var(
    $_POST["client_id"] ?? "",
    FILTER_VALIDATE_INT
);

$workerInput = trim($_POST["worker_id"] ?? "");
$worker_id = null;

if ($workerInput !== "") {
    $worker_id = filter_var($workerInput, FILTER_VALIDATE_INT);

    if ($worker_id === false || $worker_id < 1) {
        respond(false, "Invalid worker selected.");
    }
}

$start_date = trim($_POST["start_date"] ?? "");
$end_date = trim($_POST["end_date"] ?? "");
$schedule = trim($_POST["schedule"] ?? "");
$monthly_rate = trim($_POST["monthly_rate"] ?? "0");
$status = strtolower(trim($_POST["status"] ?? "active"));
$notes = trim($_POST["notes"] ?? "");

if ($client_id === false || $client_id === null || $client_id < 1) {
    respond(false, "Please select a valid customer.");
}

if ($start_date === "") {
    respond(false, "Start date is required.");
}

foreach ([
    "start date" => $start_date,
    "end date" => $end_date
] as $field => $date) {
    if ($date !== "") {
        $parsed = DateTime::createFromFormat("!Y-m-d", $date);

        if (!$parsed || $parsed->format("Y-m-d") !== $date) {
            respond(false, "Invalid " . $field . ".");
        }
    }
}

if ($end_date !== "" && $end_date < $start_date) {
    respond(false, "End date cannot be before the start date.");
}

if (!is_numeric($monthly_rate) || (float)$monthly_rate < 0) {
    respond(false, "Enter a valid monthly rate.");
}

$monthly_rate = (float)$monthly_rate;

$allowedStatuses = [
    "active",
    "paused",
    "completed",
    "cancelled"
];

if (!in_array($status, $allowedStatuses, true)) {
    respond(false, "Invalid job status.");
}

try {
    $conn->beginTransaction();

    // Confirm that the selected client exists.
    $stmt = $conn->prepare(
        "SELECT client_id FROM clients WHERE client_id = ?"
    );
    $stmt->execute([$client_id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception("The selected customer does not exist.");
    }

    // Save the job using the actual database columns.
    $stmt = $conn->prepare("
        INSERT INTO jobs (
            client_id,
            worker_id,
            start_date,
            end_date,
            schedule,
            monthly_rate,
            status,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $client_id,
        $worker_id,
        $start_date,
        $end_date !== "" ? $end_date : null,
        $schedule !== "" ? $schedule : null,
        $monthly_rate,
        $status,
        $notes !== "" ? $notes : null
    ]);

    $job_id = (int)$conn->lastInsertId();

    $conn->commit();

    respond(true, "Job added successfully.", [
        "job_id" => $job_id
    ]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Add job error: " . $e->getMessage());

    respond(
        false,
        "Unable to add job. Check the server error log for details."
    );
}
?>
