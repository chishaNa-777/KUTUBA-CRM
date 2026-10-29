
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

$id = filter_var(
    $_POST["id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    echo json_encode([
        "success" => false,
        "message" => "A valid Product ID is required."
    ]);

    exit;
}

try {

    // Check whether the product exists.
    $check = $conn->prepare("
        SELECT id
        FROM products
        WHERE id = ?
        LIMIT 1
    ");

    $check->execute([$id]);

    $product = $check->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode([
            "success" => false,
            "message" => "Product not found."
        ]);

        exit;
    }

    // Delete the product.
    $delete = $conn->prepare("
        DELETE FROM products
        WHERE id = ?
    ");

    $delete->execute([$id]);

    echo json_encode([
        "success" => true,
        "message" => "Product deleted successfully."
    ]);

} catch (PDOException $e) {

    error_log("Product deletion error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete the product. It may be linked to another record."
    ]);
}
