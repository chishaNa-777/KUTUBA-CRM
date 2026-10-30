
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

// Only allow POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

require_once __DIR__ . "/../config/database.php";

$id = filter_var(
    $_POST["id"] ?? "",
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
        http_response_code(404);

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

} catch (Throwable $e) {

    error_log("Product deletion error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete the product. It may be linked to another record."
    ]);
}
