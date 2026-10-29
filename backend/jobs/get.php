
<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

try {

    $stmt = $conn->prepare("
        SELECT
            jobs.job_id,
            jobs.client_id,
            jobs.worker_id,
            jobs.start_date,
            jobs.end_date,
            jobs.schedule,
            jobs.monthly_rate,
            jobs.status,
            jobs.notes,
            jobs.created_at,
            jobs.updated_at,
            clients.full_name
        FROM jobs
        LEFT JOIN clients
            ON jobs.client_id = clients.client_id
        ORDER BY jobs.job_id DESC
    ");

    $stmt->execute();

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "jobs" => $jobs
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    error_log("Jobs fetch error: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}
?>
