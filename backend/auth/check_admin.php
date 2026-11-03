
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if (empty($_SESSION["user_id"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

$role = strtolower(trim($_SESSION["role"] ?? ""));

if ($role !== "admin") {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Access denied. Administrator privileges required."
    ]);

    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Administrator access granted.",
    "user" => [
        "id" => $_SESSION["user_id"],
        "full_name" => $_SESSION["full_name"] ?? "",
        "email" => $_SESSION["email"] ?? "",
        "role" => $role,
        "profile_photo" => $_SESSION["profile_photo"] ?? null
    ]
]);

exit;

?>
