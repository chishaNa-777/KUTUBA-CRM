
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

try {

    $stmt = $conn->prepare("
        SELECT
            user_id AS id,
            full_name,
            email,
            role,
            status,
            profile_picture
        FROM users
        ORDER BY user_id DESC
    ");

    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "users" => $users
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    error_log("Users fetch error: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "message" => "Unable to load user accounts."
    ]);

}
