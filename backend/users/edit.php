
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

$id = filter_var(
    $_GET["id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$id) {
    echo json_encode([
        "success" => false,
        "message" => "A valid User ID is required."
    ]);

    exit;
}

try {

    $stmt = $conn->prepare("
        SELECT
            user_id AS id,
            full_name,
            email,
            role,
            status
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            "success" => false,
            "message" => "User not found."
        ]);

        exit;
    }

    echo json_encode([
        "success" => true,
        "user" => $user
    ]);

} catch (PDOException $e) {

    error_log("User fetch error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load user details."
    ]);
}
