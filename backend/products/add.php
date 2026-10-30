
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

$product_name = trim($_POST["product_name"] ?? "");
$description = trim($_POST["description"] ?? "");
$category = trim($_POST["category"] ?? "");
$price = $_POST["price"] ?? "";
$quantity = $_POST["quantity"] ?? 0;
$status = trim($_POST["status"] ?? "Active");

if ($product_name === "" || $price === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Product name and price are required."
    ]);

    exit;
}

// Validate numeric values.
if (!is_numeric($price) || (float) $price < 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Please enter a valid product price."
    ]);

    exit;
}

if (
    filter_var($quantity, FILTER_VALIDATE_INT) === false ||
    (int) $quantity < 0
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Please enter a valid product quantity."
    ]);

    exit;
}

try {

    $stmt = $conn->prepare("
        INSERT INTO products
        (
            product_name,
            description,
            category,
            price,
            quantity,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $product_name,
        $description,
        $category,
        (float) $price,
        (int) $quantity,
        $status
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Product added successfully."
    ]);

} catch (Throwable $e) {

    error_log("Add product error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to add the product."
    ]);
}
