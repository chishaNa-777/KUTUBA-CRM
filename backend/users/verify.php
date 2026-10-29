
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);
    exit;
}

if (!isset($_SESSION["role"]) ||
    strtolower($_SESSION["role"]) !== "admin") {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Administrator access required."
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Administrator authenticated.",
    "user" => [
        "id" => $_SESSION["user_id"],
        "full_name" => $_SESSION["full_name"] ?? "",
        "email" => $_SESSION["email"] ?? "",
        "role" => $_SESSION["role"]
    ]
]);

?>
