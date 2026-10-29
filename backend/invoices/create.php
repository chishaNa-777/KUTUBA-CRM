
<?php

session_start();

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

function validInvoiceDate($date)
{
    if (!is_string($date) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $parts = explode("-", $date);

    return checkdate(
        (int) $parts[1],
        (int) $parts[2],
        (int) $parts[0]
    );
}

try {
    $client_id = filter_var(
        $_POST["client_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    $invoice_number = trim($_POST["invoice_number"] ?? "");
    $period_start = $_POST["period_start"] ?? "";
    $period_end = $_POST["period_end"] ?? "";
    $amount_input = trim($_POST["amount"] ?? "");
    $due_date = trim($_POST["due_date"] ?? "");

    if (!$client_id || $client_id < 1) {
        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "Please select a valid customer."
        ]);

        exit;
    }

    if (
        $invoice_number === "" ||
        strlen($invoice_number) > 50
    ) {
        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "Enter an invoice number of up to 50 characters."
        ]);

        exit;
    }

    if (
        !validInvoiceDate($period_start) ||
        !validInvoiceDate($period_end)
    ) {
        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "Enter valid billing period dates."
        ]);

        exit;
    }

    if ($period_end < $period_start) {
        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "The billing period end cannot be before the start."
        ]);

        exit;
    }

    if (
        $due_date !== "" &&
        !validInvoiceDate($due_date)
    ) {
        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "Enter a valid payment due date."
        ]);

        exit;
    }

    if (
        !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount_input) ||
        (float) $amount_input <= 0
    ) {
        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "Enter a valid amount greater than zero."
        ]);

        exit;
    }

    $amount = number_format((float) $amount_input, 2, ".", "");

    // Confirm that the selected customer exists.
    $customerStmt = $conn->prepare("
        SELECT client_id
        FROM clients
        WHERE client_id = ?
        LIMIT 1
    ");

    $customerStmt->execute([$client_id]);

    if (!$customerStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "The selected customer does not exist."
        ]);

        exit;
    }

    // Prevent duplicate invoice numbers.
    $duplicateStmt = $conn->prepare("
        SELECT invoice_id
        FROM invoices
        WHERE invoice_number = ?
        LIMIT 1
    ");

    $duplicateStmt->execute([$invoice_number]);

    if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "That invoice number already exists. Please use a different one."
        ]);

        exit;
    }

    // Save a new invoice without changing existing invoices.
    $sql = "
        INSERT INTO invoices (
            client_id,
            invoice_number,
            period_start,
            period_end,
            amount,
            amount_paid,
            status,
            due_date
        ) VALUES (
            :client_id,
            :invoice_number,
            :period_start,
            :period_end,
            :amount,
            0.00,
            'unpaid',
            :due_date
        )
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":client_id" => $client_id,
        ":invoice_number" => $invoice_number,
        ":period_start" => $period_start,
        ":period_end" => $period_end,
        ":amount" => $amount,
        ":due_date" => $due_date !== "" ? $due_date : null
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Invoice created successfully.",
        "invoice_id" => $conn->lastInsertId(),
        "invoice_number" => $invoice_number
    ]);

} catch (PDOException $e) {
    error_log("Invoice creation error: " . $e->getMessage());

    if ($e->getCode() === "23000") {
        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "The invoice could not be created because a duplicate or conflicting record exists."
        ]);

        exit;
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to create the invoice. Please check the server error log."
    ]);
}
