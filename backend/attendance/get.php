
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

// Check login
if (empty($_SESSION["user_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Not authenticated."
    ]);
}

// Only Administrator and HR can access attendance
$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "hr_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied. HR privileges required."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    $sql = "
        SELECT
            a.attendance_id,
            a.worker_id,
            w.full_name,
            w.phone,
            a.attendance_date,
            a.check_in,
            a.check_out,
            a.status,
            a.notes,
            a.recorded_by,
            a.created_at,
            a.updated_at
        FROM attendance a
        INNER JOIN workers w
            ON a.worker_id = w.worker_id
        ORDER BY
            a.attendance_date DESC,
            a.attendance_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(200, [
        "success" => true,
        "attendance" => $attendance
    ]);

} catch (PDOException $e) {

    error_log("Attendance retrieval error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to retrieve attendance records."
    ]);
}
