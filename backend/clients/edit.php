
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Require an authenticated session.
if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

require_once __DIR__ . "/../config/database.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid customer ID is required."
    ]);

    exit;
}

try {

    $stmt = $conn->prepare(
        "SELECT
            client_id,
            full_name,
            phone,
            email,
            location,
            address,
            service_type,
            package,
            schedule,
            monthly_fee,
            start_date,
            payment_status,
            status,
            notes,
            created_at,
            updated_at
         FROM clients
         WHERE client_id = :id
         LIMIT 1"
    );

    $stmt->execute([":id" => $id]);

    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($client) {

        echo json_encode([
            "success" => true,
            "client" => $client
        ]);

    } else {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Customer not found."
        ]);
    }

} catch (Throwable $e) {

    error_log("Customer retrieval error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve customer details."
    ]);
}
