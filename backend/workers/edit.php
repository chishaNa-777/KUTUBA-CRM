<?php

require_once "../config/database.php";

header("Content-Type: application/json");


$id = $_GET["id"] ?? "";


if ($id === "") {

    echo json_encode([
        "success" => false,
        "message" => "Worker ID is required."
    ]);

    exit;
}


try {

    $stmt = $conn->prepare(
        "SELECT * FROM workers WHERE id = ?"
    );

    $stmt->execute([$id]);


    $worker = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($worker) {

        echo json_encode([
            "success" => true,
            "worker" => $worker
        ]);

    } else {

        echo json_encode([
            "success" => false,
            "message" => "Worker not found."
        ]);

    }


} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}

?>