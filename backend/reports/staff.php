
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

// Check HR permissions
$role = $_SESSION["role"] ?? "";

if (!in_array($role, ["admin", "hr_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {

    // Worker statistics
    $workers = $conn->query("
        SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status = 'active') AS active,
            COUNT(*) FILTER (WHERE status = 'inactive') AS inactive
        FROM workers
    ")->fetch(PDO::FETCH_ASSOC);

    // Attendance statistics for the current month
    $attendance = $conn->query("
        SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status = 'present') AS present,
            COUNT(*) FILTER (WHERE status = 'absent') AS absent,
            COUNT(*) FILTER (WHERE status = 'late') AS late,
            COUNT(*) FILTER (WHERE status = 'on_leave') AS on_leave
        FROM attendance
        WHERE attendance_date >= DATE_TRUNC('month', CURRENT_DATE)::date
          AND attendance_date < (DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month')::date
    ")->fetch(PDO::FETCH_ASSOC);

    // Assignment statistics
    $assignments = $conn->query("
        SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status = 'active') AS active,
            COUNT(*) FILTER (WHERE status = 'completed') AS completed,
            COUNT(*) FILTER (WHERE status = 'cancelled') AS cancelled
        FROM jobs
    ")->fetch(PDO::FETCH_ASSOC);

    // Performance statistics
    $performance = $conn->query("
        SELECT
            COUNT(*) AS total_reviews,
            COALESCE(ROUND(AVG(rating), 2), 0) AS average_rating,
            COUNT(*) FILTER (WHERE rating = 5) AS five_star_reviews
        FROM performance
    ")->fetch(PDO::FETCH_ASSOC);

    respond(200, [
        "success" => true,
        "workers" => $workers,
        "attendance" => $attendance,
        "assignments" => $assignments,
        "performance" => $performance
    ]);

} catch (PDOException $e) {

    error_log("HR Report Error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to generate HR reports."
    ]);
}
