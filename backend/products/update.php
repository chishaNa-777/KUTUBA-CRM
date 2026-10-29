<?php

require_once "../config/database.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

$id = $_POST["id"] ?? "";
$product_name = trim($_POST["product_name"] ?? "");
$description = trim($_POST["description"] ?? "");
$category = trim($_POST["category"] ?? "");
$price = $_POST["price"] ?? "";
$quantity = $_POST["quantity"] ?? "";
$status = trim($_POST["status"] ?? "");

if (
    $id === "" ||
    $product_name === "" ||
    $price === "" ||
    $quantity === "" ||
    $status === ""
) {

    echo json_encode([
        "success" => false,
        "message" => "Please fill in all required fields."
    ]);

    exit;
}

try {

    $stmt = $conn->prepare("
        UPDATE products
        SET
            product_name = ?,
            description = ?,
            category = ?,
            price = ?,
            quantity = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $product_name,
        $description,
        $category,
        $price,
        $quantity,
        $status,
        $id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Product updated successfully."
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}

?>