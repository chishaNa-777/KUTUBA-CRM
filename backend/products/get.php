<?php

require_once "../config/database.php";

header("Content-Type: application/json");

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

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}

?>