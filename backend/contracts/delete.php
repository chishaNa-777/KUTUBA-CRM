
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond(int $status, array $data): void
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

// Allow administrators only
if (($_SESSION["role"] ?? "") !== "admin") {
    respond(403, [
        "success" => false,
        "message" => "You are not authorized to delete contracts."
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Invalid request method."
    ]);
}

require_once __DIR__ . "/../config/database.php";

$contract_id = filter_var(
    $_POST["contract_id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$contract_id || $contract_id <= 0) {
    respond(400, [
        "success" => false,
        "message" => "A valid contract ID is required."
    ]);
}

try {

    $stmt = $conn->prepare("
        SELECT contract_id
        FROM contracts
        WHERE contract_id = :contract_id
    ");

    $stmt->execute([
        ":contract_id" => $contract_id
    ]);

    if (!$stmt->fetchColumn()) {
        respond(404, [
            "success" => false,
            "message" => "Contract not found."
        ]);
    }

    // Delete only the selected contract.
    // Related invoices and payments are not deleted by this code.
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
        respond(404, [
            "success" => false,
            "message" => "Contract could not be deleted."
        ]);
    }

    respond(200, [
        "success" => true,
        "message" => "Contract deleted successfully."
    ]);

} catch (PDOException $e) {

    error_log("Contract deletion error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to delete this contract. Check whether another record is linked to it."
    ]);
}
