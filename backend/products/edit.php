
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

$id = filter_var(
    $_GET["id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid Product ID is required."
    ]);

    exit;
}

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            product_name,
            description,
            category,
            price,
            quantity,
            status
        FROM products
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Product not found."
        ]);

        exit;
    }

    echo json_encode([
        "success" => true,
        "product" => $product
    ]);

} catch (Throwable $e) {

    error_log("Product retrieval error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve product details."
    ]);
}
