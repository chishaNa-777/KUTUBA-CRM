
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

try {

    $stmt = $conn->prepare("
        SELECT
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
        ORDER BY client_id DESC
    ");

    $stmt->execute();

    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "clients" => $clients
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load customers."
    ]);

}

?>
