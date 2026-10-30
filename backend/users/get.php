
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Block users who are not logged in
if (empty($_SESSION["user_id"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

// Connect to the database
require_once __DIR__ . "/../config/database.php";

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

} catch (Throwable $e) {

    http_response_code(500);

    error_log("Users fetch error: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "message" => "Unable to load user accounts."
    ]);
}
