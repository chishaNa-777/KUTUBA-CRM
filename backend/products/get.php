
<?php

session_start();

header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Require an authenticated session
if (empty($_SESSION["user_id"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

// Connect to the database
require_once __DIR__ . "/../config/database.php";

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            product_name,
            description,
            category,
            price,
            quantity,
            status,
            created_at
        FROM products
        ORDER BY id DESC
    ");

    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "products" => $products
    ]);

} catch (PDOException $e) {

    error_log("Products retrieval error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve products."
    ]);

}
