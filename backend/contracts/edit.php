
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

$contract_number = trim($_POST["contract_number"] ?? "");
$client_id = filter_var($_POST["client_id"] ?? "", FILTER_VALIDATE_INT);
$service_type = trim($_POST["service_type"] ?? "");
$package = trim($_POST["package"] ?? "");
$start_date = trim($_POST["start_date"] ?? "");
$end_date = trim($_POST["end_date"] ?? "");
$monthly_fee = $_POST["monthly_fee"] ?? "";
$payment_terms = trim($_POST["payment_terms"] ?? "");
$terms_conditions = trim($_POST["terms_conditions"] ?? "");
$status = trim($_POST["status"] ?? "");

if (
    !$contract_id ||
    !$client_id ||
    $contract_number === "" ||
    $service_type === "" ||
    $package === "" ||
    $start_date === "" ||
    $monthly_fee === "" ||
    !is_numeric($monthly_fee) ||
    !is_finite((float)$monthly_fee) ||
    (float)$monthly_fee < 0
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please provide all required fields correctly."
    ]);
    exit;
}

if (!in_array($status, ["Draft", "Active", "Expired", "Terminated"], true)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid contract status."
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("
        UPDATE contracts
        SET
            contract_number = :contract_number,
            client_id = :client_id,
            service_type = :service_type,
            package = :package,
            start_date = :start_date,
            end_date = :end_date,
            monthly_fee = :monthly_fee,
            payment_terms = :payment_terms,
            terms_conditions = :terms_conditions,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE contract_id = :contract_id
        RETURNING contract_id
    ");

    $stmt->execute([
        ":contract_number" => $contract_number,
        ":client_id" => $client_id,
        ":service_type" => $service_type,
        ":package" => $package,
        ":start_date" => $start_date,
        ":end_date" => $end_date !== "" ? $end_date : null,
        ":monthly_fee" => $monthly_fee,
        ":payment_terms" => $payment_terms !== "" ? $payment_terms : null,
        ":terms_conditions" => $terms_conditions !== "" ? $terms_conditions : null,
        ":status" => $status,
        ":contract_id" => $contract_id
    ]);

    $updatedId = $stmt->fetchColumn();

    if (!$updatedId) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Contract not found."
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Contract updated successfully.",
        "contract_id" => $updatedId
    ]);

} catch (PDOException $e) {
    error_log("Contract update error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to update contract. Check the contract number and customer details."
    ]);
}
