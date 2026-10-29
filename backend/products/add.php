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

$product_name = trim($_POST["product_name"] ?? "");
$description = trim($_POST["description"] ?? "");
$category = trim($_POST["category"] ?? "");
$price = $_POST["price"] ?? "";
$quantity = $_POST["quantity"] ?? 0;
$status = trim($_POST["status"] ?? "Active");


if ($product_name === "" || $price === "") {

    echo json_encode([
        "success" => false,
        "message" => "Product name and price are required."
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
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");

    $stmt->execute([
        $product_name,
        $description,
        $category,
        $price,
        $quantity,
        $status
    ]);


    echo json_encode([
        "success" => true,
        "message" => "Product added successfully."
    ]);


} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}

?>