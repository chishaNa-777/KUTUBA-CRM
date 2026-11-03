
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

// Only Administrators and Finance Officers can record payments
$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "finance_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied. Finance privileges required."
    ]);
}

// Only POST requests are allowed
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

require_once __DIR__ . "/../config/database.php";

// Read JSON sent by the frontend
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    respond(400, [
        "success" => false,
        "message" => "Invalid payment data received."
    ]);
}

// Extract submitted values
$invoice_id = filter_var(
    $data["invoice_id"] ?? null,
    FILTER_VALIDATE_INT
);

$client_id = filter_var(
    $data["client_id"] ?? null,
    FILTER_VALIDATE_INT
);

$amount = filter_var(
    $data["amount"] ?? "",
    FILTER_VALIDATE_FLOAT
);

$payment_date = trim($data["payment_date"] ?? "");
$method = trim($data["method"] ?? "");
$type = trim($data["type"] ?? "");
$reference = trim($data["reference"] ?? "");
$notes = trim($data["notes"] ?? "");

$allowed_methods = [
    "cash",
    "airtel_money",
    "mtn_money",
    "bank"
];

$allowed_types = [
    "prepayment",
    "monthly",
    "adjustment"
];

// Validate common payment information
if (
    !$client_id ||
    $amount === false ||
    !is_finite((float) $amount) ||
    $amount <= 0 ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date) ||
    !in_array($method, $allowed_methods, true) ||
    !in_array($type, $allowed_types, true)
) {
    respond(400, [
        "success" => false,
        "message" => "Please provide a valid customer, amount, date, payment method and payment type."
    ]);
}

// Validate date
$date = DateTime::createFromFormat("!Y-m-d", $payment_date);

if (!$date || $date->format("Y-m-d") !== $payment_date) {
    respond(400, [
        "success" => false,
        "message" => "The payment date is invalid."
    ]);
}

// Validate optional invoice ID
if (
    isset($data["invoice_id"]) &&
    $data["invoice_id"] !== null &&
    $data["invoice_id"] !== ""
    && $invoice_id === false
) {
    respond(400, [
        "success" => false,
        "message" => "Invalid invoice ID."
    ]);
}

if (strlen($reference) > 100) {
    respond(400, [
        "success" => false,
        "message" => "The payment reference cannot exceed 100 characters."
    ]);
}

try {

    $conn->beginTransaction();

    $payment_amount = round((float) $amount, 2);

    // If an invoice was selected, lock and validate it
    if ($invoice_id) {

        $stmt = $conn->prepare("
            SELECT
                invoice_id,
                client_id,
                amount,
                amount_paid,
                status
            FROM invoices
            WHERE invoice_id = ?
            FOR UPDATE
        ");

        $stmt->execute([$invoice_id]);

        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            $conn->rollBack();

            respond(404, [
                "success" => false,
                "message" => "Invoice not found."
            ]);
        }

        // Confirm invoice belongs to selected customer
        if ((int) $invoice["client_id"] !== (int) $client_id) {
            $conn->rollBack();

            respond(400, [
                "success" => false,
                "message" => "This invoice does not belong to the selected customer."
            ]);
        }

        if (strtolower($invoice["status"]) === "cancelled") {
            $conn->rollBack();

            respond(400, [
                "success" => false,
                "message" => "Payments cannot be recorded against a cancelled invoice."
            ]);
        }

        $invoice_amount = (float) $invoice["amount"];
        $amount_paid = (float) $invoice["amount_paid"];

        $balance = round(
            $invoice_amount - $amount_paid,
            2
        );

        if ($balance <= 0) {
            $conn->rollBack();

            respond(400, [
                "success" => false,
                "message" => "This invoice has already been fully paid."
            ]);
        }

        if ($payment_amount > $balance) {
            $conn->rollBack();

            respond(400, [
                "success" => false,
                "message" => "Payment exceeds the outstanding balance of " .
                    number_format($balance, 2, ".", "")
            ]);
        }

        // Use the customer ID stored on the invoice
        $client_id = (int) $invoice["client_id"];
    }

    // Confirm customer exists
    $stmt = $conn->prepare("
        SELECT client_id
        FROM clients
        WHERE client_id = ?
    ");

    $stmt->execute([$client_id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $conn->rollBack();

        respond(404, [
            "success" => false,
            "message" => "Customer not found."
        ]);
    }

    // Insert payment
    $stmt = $conn->prepare("
        INSERT INTO payments (
            client_id,
            invoice_id,
            amount,
            payment_date,
            method,
            type,
            reference,
            received_by,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        RETURNING payment_id
    ");

    $stmt->execute([
        $client_id,
        $invoice_id ?: null,
        number_format($payment_amount, 2, ".", ""),
        $payment_date,
        $method,
        $type,
        $reference !== "" ? $reference : null,
        $_SESSION["user_id"],
        $notes !== "" ? $notes : null
    ]);

    $payment_id = $stmt->fetchColumn();

    $responseData = [
        "success" => true,
        "message" => "Payment recorded successfully.",
        "payment_id" => (int) $payment_id
    ];

    // Update invoice only when an invoice was selected
    if ($invoice_id) {

        $new_amount_paid = round(
            $amount_paid + $payment_amount,
            2
        );

        $new_status = ($new_amount_paid >= $invoice_amount)
            ? "paid"
            : "partial";

        $stmt = $conn->prepare("
            UPDATE invoices
            SET amount_paid = ?, status = ?
            WHERE invoice_id = ?
        ");

        $stmt->execute([
            number_format($new_amount_paid, 2, ".", ""),
            $new_status,
            $invoice_id
        ]);

        $responseData["invoice_id"] = (int) $invoice_id;
        $responseData["amount_paid"] = number_format(
            $new_amount_paid, 2, ".", ""
        );
        $responseData["balance"] = number_format(
            max(0, $invoice_amount - $new_amount_paid), 2, ".", ""
        );
        $responseData["status"] = $new_status;
    }

    $conn->commit();

    respond(200, $responseData);

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Payment recording error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to record the payment. Please check the server error log."
    ]);
}
