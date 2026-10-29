
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=utf-8");

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

$invoice_number = trim($_POST["invoice_number"] ?? "");
$period_start = trim($_POST["period_start"] ?? "");
$period_end = trim($_POST["period_end"] ?? "");
$amount = filter_var($_POST["amount"] ?? "", FILTER_VALIDATE_FLOAT);
$due_date = trim($_POST["due_date"] ?? "");

if (
    !$invoice_id ||
    $invoice_number === "" ||
    $period_start === "" ||
    $period_end === "" ||
    $amount === false ||
    $amount <= 0 ||
    $due_date === ""
) {
    echo json_encode([
        "success" => false,
        "message" => "Please provide all required invoice details."
    ]);
    exit;
}

$start = DateTime::createFromFormat("!Y-m-d", $period_start);
$end = DateTime::createFromFormat("!Y-m-d", $period_end);
$due = DateTime::createFromFormat("!Y-m-d", $due_date);

if (
    !$start || $start->format("Y-m-d") !== $period_start ||
    !$end || $end->format("Y-m-d") !== $period_end ||
    !$due || $due->format("Y-m-d") !== $due_date ||
    $period_end < $period_start
) {
    echo json_encode([
        "success" => false,
        "message" => "Please enter valid invoice dates."
    ]);
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT invoice_id, amount_paid
        FROM invoices
        WHERE invoice_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        $conn->rollBack();

        echo json_encode([
            "success" => false,
            "message" => "Invoice not found."
        ]);
        exit;
    }

    $amount_paid = (float) $invoice["amount_paid"];

    if ((float) $amount < $amount_paid) {
        $conn->rollBack();

        echo json_encode([
            "success" => false,
            "message" => "The invoice amount cannot be less than the amount already paid."
        ]);
        exit;
    }

    $duplicate = $conn->prepare("
        SELECT invoice_id
        FROM invoices
        WHERE invoice_number = ?
          AND invoice_id <> ?
        LIMIT 1
    ");
    $duplicate->execute([$invoice_number, $invoice_id]);

    if ($duplicate->fetch()) {
        $conn->rollBack();

        echo json_encode([
            "success" => false,
            "message" => "That invoice number is already in use."
        ]);
        exit;
    }

    if ((float) $amount_paid >= (float) $amount) {
        $status = "paid";
    } elseif ($amount_paid > 0) {
        $status = "partial";
    } else {
        $today = date("Y-m-d");
        $status = ($due_date < $today) ? "overdue" : "unpaid";
    }

    $update = $conn->prepare("
        UPDATE invoices
        SET invoice_number = ?,
            period_start = ?,
            period_end = ?,
            amount = ?,
            due_date = ?,
            status = ?
        WHERE invoice_id = ?
    ");

    $update->execute([
        $invoice_number,
        $period_start,
        $period_end,
        $amount,
        $due_date,
        $status,
        $invoice_id
    ]);

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Invoice updated successfully."
    ]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Invoice update error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to update invoice. Please check the server error log."
    ]);
}
