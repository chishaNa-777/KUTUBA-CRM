
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

// Authentication
if (empty($_SESSION["user_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Not authenticated."
    ]);
}

require_once __DIR__ . "/../config/database.php";

// Request method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Invalid request method."
    ]);
}

// Read form fields
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

// Validate required fields
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
    respond(400, [
        "success" => false,
        "message" => "Please provide all required fields correctly."
    ]);
}

// Validate date format
function validDate(string $date): bool
{
    $parsed = DateTime::createFromFormat("!Y-m-d", $date);

    return $parsed !== false &&
        $parsed->format("Y-m-d") === $date;
}

if (!validDate($start_date)) {
    respond(400, [
        "success" => false,
        "message" => "Please provide a valid contract start date."
    ]);
}

if ($end_date !== "") {

    if (!validDate($end_date)) {
        respond(400, [
            "success" => false,
            "message" => "Please provide a valid contract end date."
        ]);
    }

    if ($end_date < $start_date) {
        respond(400, [
            "success" => false,
            "message" => "The contract end date cannot be earlier than the start date."
        ]);
    }
}

// Validate status
$allowedStatuses = [
    "Draft",
    "Active",
    "Expired",
    "Terminated"
];

if (!in_array($status, $allowedStatuses, true)) {
    respond(400, [
        "success" => false,
        "message" => "Invalid contract status."
    ]);
}

try {

    // Check that the customer exists
    $clientStmt = $conn->prepare("
        SELECT client_id
        FROM clients
        WHERE client_id = :client_id
    ");

    $clientStmt->execute([
        ":client_id" => $client_id
    ]);

    if (!$clientStmt->fetchColumn()) {
        respond(404, [
            "success" => false,
            "message" => "Selected customer was not found."
        ]);
    }

    // Update contract
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
        respond(404, [
            "success" => false,
            "message" => "Contract not found."
        ]);
    }

    respond(200, [
        "success" => true,
        "message" => "Contract updated successfully.",
        "contract_id" => $updatedId
    ]);

} catch (PDOException $e) {

    error_log("Contract update error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to update contract. Check the contract number and customer details."
    ]);
}

?>
