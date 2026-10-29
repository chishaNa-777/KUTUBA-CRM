
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Please log in first."
    ]);
    exit;
}

if (
    !isset($_SESSION["role"]) ||
    strtolower($_SESSION["role"]) !== "admin"
) {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Administrator access required."
    ]);
    exit;
}

try {
    $sql = "SELECT
                user_id,
                full_name,
                email,
                phone,
                role,
                status,
                approval_status,
                created_at
            FROM users
            WHERE approval_status = 'pending'
            ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($users),
        "users" => $users
    ]);

} catch (PDOException $e) {
    error_log("KATUBA CRM pending users error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve pending registrations."
    ]);
}
?>
