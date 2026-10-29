
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

echo json_encode([
    "success" => true,
    "user" => [
        "id" => $_SESSION["user_id"],
        "full_name" => $_SESSION["full_name"] ?? "",
        "email" => $_SESSION["email"] ?? "",
        "role" => $_SESSION["role"] ?? "",
        "profile_photo" => $_SESSION["profile_photo"] ?? null
    ]
]);

exit;

?>
