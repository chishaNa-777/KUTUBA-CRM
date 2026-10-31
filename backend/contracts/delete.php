
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

$contract_id = filter_var(
    $_POST["contract_id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$contract_id || $contract_id <= 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid contract ID is required."
    ]);

    exit;
}

try {
    $stmt = $conn->prepare("
        DELETE FROM contracts
        WHERE contract_id = :contract_id
        RETURNING contract_id
    ");

    $stmt->execute([
        ":contract_id" => $contract_id
    ]);

    $deletedId = $stmt->fetchColumn();

    if (!$deletedId) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Contract not found."
        ]);

        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Contract deleted successfully."
    ]);

} catch (PDOException $e) {

    error_log("Contract deletion error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete this contract."
    ]);
}
