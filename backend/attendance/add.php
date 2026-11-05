
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond($status, $data)
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

// Allow Administrator and HR only
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
        "message" => "Method not allowed."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    $workerId = filter_var(
        $_POST["worker_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $attendanceDate = trim($_POST["attendance_date"] ?? "");
    $checkIn = trim($_POST["check_in"] ?? "");
    $checkOut = trim($_POST["check_out"] ?? "");
    $status = strtolower(trim($_POST["status"] ?? "present"));
    $notes = trim($_POST["notes"] ?? "");

    // Validate worker
    if (!$workerId || $workerId < 1) {
        respond(422, [
            "success" => false,
            "message" => "Please select a valid worker."
        ]);
    }

    // Validate date
    $date = DateTime::createFromFormat("!Y-m-d", $attendanceDate);

    if (!$date || $date->format("Y-m-d") !== $attendanceDate) {
        respond(422, [
            "success" => false,
            "message" => "Please provide a valid attendance date."
        ]);
    }

    // Validate status
    $allowedStatuses = [
        "present",
        "absent",
        "late",
        "on_leave"
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        respond(422, [
            "success" => false,
            "message" => "Invalid attendance status."
        ]);
    }

    // Validate time format
    $validTime = function ($time) {
        return $time === "" ||
            preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time);
    };

    if (!$validTime($checkIn) || !$validTime($checkOut)) {
        respond(422, [
            "success" => false,
            "message" => "Please enter valid check-in and check-out times."
        ]);
    }

    // Absent and on-leave records should not have attendance times
    if (in_array($status, ["absent", "on_leave"], true)) {
        $checkIn = "";
        $checkOut = "";
    }

    // Check-out cannot be earlier than check-in
    if (
        $checkIn !== "" &&
        $checkOut !== "" &&
        $checkOut < $checkIn
    ) {
        respond(422, [
            "success" => false,
            "message" => "Check-out time cannot be earlier than check-in time."
        ]);
    }

    // Confirm worker exists
    $workerStmt = $pdo->prepare(
        "SELECT worker_id FROM workers WHERE worker_id = :worker_id"
    );

    $workerStmt->execute([
        ":worker_id" => $workerId
    ]);

    if (!$workerStmt->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            "success" => false,
            "message" => "Selected worker was not found."
        ]);
    }

    // Insert attendance
    $sql = "
        INSERT INTO attendance (
            worker_id,
            attendance_date,
            check_in,
            check_out,
            status,
            notes,
            recorded_by
        ) VALUES (
            :worker_id,
            :attendance_date,
            :check_in,
            :check_out,
            :status,
            :notes,
            :recorded_by
        )
        RETURNING attendance_id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":worker_id" => $workerId,
        ":attendance_date" => $attendanceDate,
        ":check_in" => $checkIn !== "" ? $checkIn : null,
        ":check_out" => $checkOut !== "" ? $checkOut : null,
        ":status" => $status,
        ":notes" => $notes !== "" ? $notes : null,
        ":recorded_by" => $_SESSION["user_id"]
    ]);

    $attendanceId = $stmt->fetchColumn();

    respond(201, [
        "success" => true,
        "message" => "Attendance recorded successfully.",
        "attendance_id" => $attendanceId
    ]);

} catch (PDOException $e) {

    // PostgreSQL unique violation: same worker and date
    if ($e->getCode() === "23505") {
        respond(409, [
            "success" => false,
            "message" => "Attendance has already been recorded for this worker on this date."
        ]);
    }

    error_log("Attendance recording error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to record attendance."
    ]);
}
