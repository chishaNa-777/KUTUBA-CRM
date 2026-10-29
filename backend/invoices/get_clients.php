
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
            client_id,
            full_name
        FROM clients
        ORDER BY full_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($clients),
        "clients" => $clients
    ]);

} catch (PDOException $e) {
    error_log("Invoice customer retrieval error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve customers."
    ]);
}
