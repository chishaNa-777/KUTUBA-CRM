
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
        "message" => "Method not allowed."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    $attendanceId = filter_var(
        $_POST["attendance_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $workerId = filter_var(
        $_POST["worker_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $attendanceDate = trim($_POST["attendance_date"] ?? "");
    $checkIn = trim($_POST["check_in"] ?? "");
    $checkOut = trim($_POST["check_out"] ?? "");
    $status = strtolower(trim($_POST["status"] ?? ""));
    $notes = trim($_POST["notes"] ?? "");

    if (!$attendanceId || $attendanceId < 1) {
        respond(422, [
            "success" => false,
            "message" => "Invalid attendance record ID."
        ]);
    }

    if (!$workerId || $workerId < 1) {
        respond(422, [
            "success" => false,
            "message" => "Please select a valid worker."
        ]);
    }

    $date = DateTime::createFromFormat("!Y-m-d", $attendanceDate);

    if (!$date || $date->format("Y-m-d") !== $attendanceDate) {
        respond(422, [
            "success" => false,
            "message" => "Please provide a valid attendance date."
        ]);
    }

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

    $validTime = function ($time) {
        return $time === "" ||
            preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time);
    };

    if (!$validTime($checkIn) || !$validTime($checkOut)) {
        respond(422, [
            "success" => false,
            "message" => "Please enter valid attendance times."
        ]);
    }

    if (in_array($status, ["absent", "on_leave"], true)) {
        $checkIn = "";
        $checkOut = "";
    }

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

    // Confirm the attendance record exists.
    $existingStmt = $pdo->prepare(
        "SELECT attendance_id FROM attendance
         WHERE attendance_id = :attendance_id"
    );

    $existingStmt->execute([
        ":attendance_id" => $attendanceId
    ]);

    if (!$existingStmt->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            "success" => false,
            "message" => "Attendance record not found."
        ]);
    }

    // Confirm the selected worker exists.
    $workerStmt = $pdo->prepare(
        "SELECT worker_id FROM workers
         WHERE worker_id = :worker_id"
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

    $sql = "
        UPDATE attendance
        SET
            worker_id = :worker_id,
            attendance_date = :attendance_date,
            check_in = :check_in,
            check_out = :check_out,
            status = :status,
            notes = :notes,
            updated_at = CURRENT_TIMESTAMP
        WHERE attendance_id = :attendance_id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":worker_id" => $workerId,
        ":attendance_date" => $attendanceDate,
        ":check_in" => $checkIn !== "" ? $checkIn : null,
        ":check_out" => $checkOut !== "" ? $checkOut : null,
        ":status" => $status,
        ":notes" => $notes !== "" ? $notes : null,
        ":attendance_id" => $attendanceId
    ]);

    respond(200, [
        "success" => true,
        "message" => "Attendance updated successfully."
    ]);

} catch (PDOException $e) {

    if ($e->getCode() === "23505") {
        respond(409, [
            "success" => false,
            "message" => "This worker already has an attendance record for that date."
        ]);
    }

    error_log("Attendance update error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to update attendance."
    ]);
}
