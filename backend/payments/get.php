
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            i.invoice_id,
            i.client_id,
            i.invoice_number,
            i.period_start,
            i.period_end,
            i.amount,
            i.amount_paid,
            i.status,
            i.due_date,
            i.created_at,
            c.full_name AS customer_name
        FROM invoices i
        LEFT JOIN clients c
            ON c.client_id = i.client_id
        ORDER BY i.invoice_id DESC
    ");

    $stmt->execute();

    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invoices as &$invoice) {
        $invoice["invoice_id"] = (int) $invoice["invoice_id"];
        $invoice["client_id"] = (int) $invoice["client_id"];
        $invoice["amount"] = (float) $invoice["amount"];
        $invoice["amount_paid"] = (float) $invoice["amount_paid"];
    }

    unset($invoice);

    echo json_encode([
        "success" => true,
        "invoices" => $invoices
    ]);

} catch (Throwable $e) {
    error_log("Invoice retrieval error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve invoices."
    ]);
}
