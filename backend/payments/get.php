
<?php

session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Confirm the user is logged in
if (empty($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);
    exit;
}

// Allow only Administrator and Finance users
$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "finance_officer"], true)) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Access denied. Finance privileges required."
    ]);
    exit;
}

// Only allow GET requests
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

require_once __DIR__ . "/../config/database.php";

try {
    $stmt = $conn->prepare("
        SELECT
            p.payment_id,
            p.client_id,
            p.invoice_id,
            p.amount,
            p.payment_date,
            p.method,
            p.type,
            p.reference,
            p.received_by,
            p.notes,
            p.created_at,
            c.full_name AS customer_name
        FROM payments p
        LEFT JOIN clients c
            ON c.client_id = p.client_id
        ORDER BY p.payment_id DESC
    ");

    $stmt->execute();

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($payments as &$payment) {
        $payment["payment_id"] = (int) $payment["payment_id"];
        $payment["client_id"] = (int) $payment["client_id"];
        $payment["invoice_id"] = $payment["invoice_id"] !== null
            ? (int) $payment["invoice_id"]
            : null;
        $payment["amount"] = (float) $payment["amount"];
    }

    unset($payment);

    echo json_encode([
        "success" => true,
        "payments" => $payments
    ]);

} catch (Throwable $e) {

    error_log("Payment retrieval error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve payment records."
    ]);
}
