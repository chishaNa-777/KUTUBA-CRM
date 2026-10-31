
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

require_once __DIR__ . "/../config/database.php";

try {

    $stmt = $conn->prepare("
        SELECT
            c.contract_id,
            c.contract_number,
            c.client_id,
            cl.full_name AS client_name,
            cl.phone AS client_phone,
            cl.email AS client_email,
            c.service_type,
            c.package,
            c.start_date,
            c.end_date,
            c.monthly_fee,
            c.payment_terms,
            c.terms_conditions,
            c.status,
            c.created_at,
            c.updated_at
        FROM contracts c
        INNER JOIN clients cl
            ON c.client_id = cl.client_id
        ORDER BY c.created_at DESC
    ");

    $stmt->execute();

    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Contracts retrieved successfully.",
        "data" => $contracts
    ]);

} catch (PDOException $e) {

    error_log("Contracts retrieval error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve contracts."
    ]);
}
