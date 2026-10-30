
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Require login.
if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

// Only administrators can view user account details through this endpoint.
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "You are not authorised to access these user details."
    ]);

    exit;
}

require_once __DIR__ . "/../config/database.php";

$id = filter_var(
    $_GET["id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$id) {
    http_response_code(400);

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
        http_response_code(404);

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

} catch (Throwable $e) {

    error_log("User fetch error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load user details."
    ]);
}
