
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond($status, $data) {
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

// Check permissions
$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "finance_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied. Finance privileges required."
    ]);
}

// POST only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Invalid request method."
    ]);
}

// Read JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    respond(400, [
        "success" => false,
        "message" => "Invalid request data."
    ]);
}

// Collect fields
$client_id = filter_var(
    $data["client_id"] ?? null,
    FILTER_VALIDATE_INT
);

$period_start = trim($data["period_start"] ?? "");
$period_end = trim($data["period_end"] ?? "");
$amount = $data["amount"] ?? null;
$due_date = trim($data["due_date"] ?? "");

// Validate required fields
if (
    !$client_id ||
    !$period_start ||
    !$period_end ||
    $amount === null ||
    $amount === "" ||
    !$due_date
) {
    respond(422, [
        "success" => false,
        "message" => "Please complete all required invoice fields."
    ]);
}

// Validate amount
if (
    !is_numeric($amount) ||
    !is_finite((float)$amount) ||
    (float)$amount <= 0
) {
    respond(422, [
        "success" => false,
        "message" => "Invoice amount must be greater than zero."
    ]);
}

// Validate dates
function validDate($date) {
    $parsed = DateTime::createFromFormat("!Y-m-d", $date);
    return $parsed && $parsed->format("Y-m-d") === $date;
}

if (
    !validDate($period_start) ||
    !validDate($period_end) ||
    !validDate($due_date)
) {
    respond(422, [
        "success" => false,
        "message" => "Please provide valid dates."
    ]);
}

if ($period_end < $period_start) {
    respond(422, [
        "success" => false,
        "message" => "Period end cannot be before period start."
    ]);
}

// Connect to database
require_once __DIR__ . "/../config/database.php";

try {

    // Confirm customer exists
    $customerCheck = $conn->prepare("
        SELECT client_id
        FROM clients
        WHERE client_id = ?
    ");

    $customerCheck->execute([$client_id]);

    if (!$customerCheck->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            "success" => false,
            "message" => "Selected customer was not found."
        ]);
    }

    // Generate invoice number using the current year
    $year = date("Y");
    $prefix = "INV-" . $year . "-";

    $conn->beginTransaction();

    // Prevent two users generating the same number simultaneously
    $lock = $conn->prepare("
        SELECT pg_advisory_xact_lock(hashtext(?))
    ");

    $lock->execute(["katuba_invoice_number_" . $year]);

    // Find the highest existing number for this year
    $findNumbers = $conn->prepare("
        SELECT invoice_number
        FROM invoices
        WHERE invoice_number LIKE ?
    ");

    $findNumbers->execute([$prefix . "%"]);

    $highestNumber = 0;

    while ($row = $findNumbers->fetch(PDO::FETCH_ASSOC)) {

        if (preg_match(
            '/^INV-' . preg_quote($year, '/') . '-([0-9]+)$/',
            $row["invoice_number"],
            $matches
        )) {
            $number = (int)$matches[1];

            if ($number > $highestNumber) {
                $highestNumber = $number;
            }
        }
    }

    // Generate the next invoice number
    $nextNumber = $highestNumber + 1;

    $invoice_number = $prefix . str_pad(
        $nextNumber,
        4,
        "0",
        STR_PAD_LEFT
    );

    // Insert invoice
    $sql = "
        INSERT INTO invoices (
            client_id,
            invoice_number,
            period_start,
            period_end,
            amount,
            amount_paid,
            status,
            due_date,
            issued_by,
            file_path,
            created_at,
            updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, 0, 'unpaid', ?, ?, NULL,
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )
        RETURNING invoice_id
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $client_id,
        $invoice_number,
        $period_start,
        $period_end,
        (float)$amount,
        $due_date,
        $_SESSION["user_id"]
    ]);

    $invoice_id = $stmt->fetchColumn();

    $conn->commit();

    respond(201, [
        "success" => true,
        "message" => "Invoice created successfully.",
        "invoice_id" => $invoice_id,
        "invoice_number" => $invoice_number
    ]);

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Invoice creation error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to create invoice. Please try again."
    ]);
}
