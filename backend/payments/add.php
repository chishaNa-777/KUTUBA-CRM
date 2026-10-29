
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

$invoice_id = filter_var(
    $_POST["invoice_id"] ?? "",
    FILTER_VALIDATE_INT
);

$amount = filter_var(
    $_POST["amount"] ?? "",
    FILTER_VALIDATE_FLOAT
);

$payment_date = trim($_POST["payment_date"] ?? "");
$method = trim($_POST["method"] ?? "");
$type = trim($_POST["type"] ?? "monthly");
$reference = trim($_POST["reference"] ?? "");
$notes = trim($_POST["notes"] ?? "");

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

if (
    !$invoice_id ||
    $amount === false ||
    $amount <= 0 ||
    !is_finite((float) $amount) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date) ||
    !in_array($method, $allowed_methods, true) ||
    !in_array($type, $allowed_types, true)
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Please provide a valid invoice, amount, date, payment method and payment type."
    ]);
    exit;
}

$date = DateTime::createFromFormat("!Y-m-d", $payment_date);

if (!$date || $date->format("Y-m-d") !== $payment_date) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "The payment date is invalid."
    ]);
    exit;
}

if (strlen($reference) > 100) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "The payment reference cannot exceed 100 characters."
    ]);
    exit;
}

try {
    $conn->beginTransaction();

    /*
     * Lock the invoice so simultaneous payments cannot
     * accidentally exceed its outstanding balance.
     */
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

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Invoice not found."
        ]);
        exit;
    }

    if ($invoice["status"] === "cancelled") {
        $conn->rollBack();

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Payments cannot be recorded against a cancelled invoice."
        ]);
        exit;
    }

    $invoice_amount = (float) $invoice["amount"];
    $amount_paid = (float) $invoice["amount_paid"];
    $payment_amount = round((float) $amount, 2);
    $balance = round($invoice_amount - $amount_paid, 2);

    if ($payment_amount > $balance) {
        $conn->rollBack();

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Payment exceeds the outstanding balance of " .
                number_format(max(0, $balance), 2, ".", "")
        ]);
        exit;
    }

    if ($balance <= 0) {
        $conn->rollBack();

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "This invoice has already been fully paid."
        ]);
        exit;
    }

    /*
     * Store the payment and link it to the selected invoice.
     * The client_id comes from the invoice, not the browser.
     */
    $stmt = $conn->prepare("
        INSERT INTO payments (
            invoice_id,
            client_id,
            amount,
            payment_date,
            method,
            type,
            reference,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $invoice["invoice_id"],
        $invoice["client_id"],
        number_format($payment_amount, 2, ".", ""),
        $payment_date,
        $method,
        $type,
        $reference !== "" ? $reference : null,
        $notes !== "" ? $notes : null
    ]);

    $new_amount_paid = round($amount_paid + $payment_amount, 2);

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
        $invoice["invoice_id"]
    ]);

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Payment recorded successfully.",
        "payment_id" => (int) $conn->lastInsertId(),
        "invoice_id" => (int) $invoice["invoice_id"],
        "amount_paid" => number_format($new_amount_paid, 2, ".", ""),
        "balance" => number_format(
            max(0, $invoice_amount - $new_amount_paid),
            2,
            ".",
            ""
        ),
        "status" => $new_status
    ]);

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Payment recording error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to record the payment. Please check the server error log."
    ]);
}
