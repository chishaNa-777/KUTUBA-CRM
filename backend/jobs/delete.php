
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

$id = filter_var(
    $_POST["id"] ?? "",
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id < 1) {
    echo json_encode([
        "success" => false,
        "message" => "A valid Job ID is required."
    ]);
    exit;
}

try {
    // Check whether the job exists.
    $check = $conn->prepare("
        SELECT job_id
        FROM jobs
        WHERE job_id = ?
        LIMIT 1
    ");

    $check->execute([$id]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            "success" => false,
            "message" => "Job not found."
        ]);
        exit;
    }

    // Delete the job using the correct primary key.
    $delete = $conn->prepare("
        DELETE FROM jobs
        WHERE job_id = ?
    ");

    $delete->execute([$id]);

    echo json_encode([
        "success" => true,
        "message" => "Job deleted successfully."
    ]);

} catch (PDOException $e) {
    error_log("Job deletion error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete this job. It may be linked to other records."
    ]);
}
?>
