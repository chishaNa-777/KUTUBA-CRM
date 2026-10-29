
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
        ORDER BY i.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($invoices),
        "invoices" => $invoices
    ]);

} catch (PDOException $e) {
    error_log("Invoice retrieval error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve invoices."
    ]);
}
