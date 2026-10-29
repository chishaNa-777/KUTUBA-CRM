<?php

require_once "../config/database.php";

header("Content-Type: application/json");

try {

    $sql = "SELECT 
                id,
                company_name,
                contact_person,
                phone,
                email,
                address,
                status,
                created_at
            FROM clients
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "clients" => $clients
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}
?>