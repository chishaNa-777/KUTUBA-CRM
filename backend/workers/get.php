
<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

try {

    $stmt = $conn->prepare("SELECT * FROM workers");

    $stmt->execute();

    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "workers" => $workers
    ]);

} catch (PDOException $e) {

    error_log("Workers get.php error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load workers. Please check the database configuration and workers table."
    ]);

}

?>
