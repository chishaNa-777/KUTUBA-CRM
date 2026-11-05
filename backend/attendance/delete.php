
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

    if (!$attendanceId || $attendanceId < 1) {
        respond(422, [
            "success" => false,
            "message" => "Invalid attendance record ID."
        ]);
    }

    $stmt = $pdo->prepare(
        "DELETE FROM attendance
         WHERE attendance_id = :attendance_id"
    );

    $stmt->execute([
        ":attendance_id" => $attendanceId
    ]);

    if ($stmt->rowCount() === 0) {
        respond(404, [
            "success" => false,
            "message" => "Attendance record not found."
        ]);
    }

    respond(200, [
        "success" => true,
        "message" => "Attendance record deleted successfully."
    ]);

} catch (PDOException $e) {

    error_log("Attendance deletion error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to delete attendance record."
    ]);
}
