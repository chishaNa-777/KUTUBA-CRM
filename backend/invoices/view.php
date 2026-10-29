
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

$invoice_id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$invoice_id || $invoice_id < 1) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid invoice ID is required."
    ]);

    exit;
}

try {
    $sql = "
        SELECT
            i.invoice_id,
            i.client_id,
            i.invoice_number,
            c.full_name AS customer_name,
            i.period_start,
            i.period_end,
            i.amount,
            i.amount_paid,
            (i.amount - i.amount_paid) AS balance,
            i.status,
            i.due_date,
            i.created_at
        FROM invoices i
        INNER JOIN clients c
            ON c.client_id = i.client_id
        WHERE i.invoice_id = :invoice_id
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":invoice_id" => $invoice_id
    ]);

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Invoice not found."
        ]);

        exit;
    }

    echo json_encode([
        "success" => true,
        "invoice" => $invoice
    ]);

} catch (PDOException $e) {
    error_log("Invoice view error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve invoice details."
    ]);
}
