<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

// Accept the worker ID from the form
$id = filter_var(
    $_POST["worker_id"] ?? $_POST["id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid worker ID is required."
    ]);

    exit;
}

try {
    // Check that the selected worker exists
    $check = $conn->prepare(
        "SELECT worker_id FROM workers WHERE worker_id = :id"
    );

    $check->execute([
        ":id" => $id
    ]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Worker not found."
        ]);

        exit;
    }

    // Delete only the selected worker
    $stmt = $conn->prepare(
        "DELETE FROM workers WHERE worker_id = :id"
    );

    $stmt->execute([
        ":id" => $id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Worker deleted successfully.",
        "worker_id" => $id
    ]);

} catch (PDOException $e) {
    error_log("Worker deletion error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete worker. The worker may be linked to existing jobs or other records."
    ]);
}
?>
