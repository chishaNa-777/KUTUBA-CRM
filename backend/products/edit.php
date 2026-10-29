<?php

require_once "../config/database.php";

header("Content-Type: application/json");

$id = $_GET["id"] ?? "";

if ($id === "") {

    echo json_encode([
        "success" => false,
        "message" => "Product ID is required."
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

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}

?>