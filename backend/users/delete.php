
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Require an authenticated session.
if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

// Only administrators can delete user accounts.
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "You are not authorised to delete user accounts."
    ]);

    exit;
}

// Only allow POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

require_once __DIR__ . "/../config/database.php";

// Get the user ID sent by the frontend.
$id = filter_var(
    $_POST["id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid User ID is required."
    ]);

    exit;
}

try {

    // Prevent deleting the currently logged-in account.
    $loggedInUserId = (int) $_SESSION["user_id"];

    if ($loggedInUserId === (int) $id) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "You cannot delete your own account while logged in."
        ]);

        exit;
    }

    // Confirm that the account exists.
    $check = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $check->execute([$id]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "User account not found."
        ]);

        exit;
    }

    // Delete the account using the correct primary key.
    $stmt = $conn->prepare("
        DELETE FROM users
        WHERE user_id = ?
    ");

    $stmt->execute([$id]);

    echo json_encode([
        "success" => true,
        "message" => "User deleted successfully."
    ]);

} catch (Throwable $e) {

    error_log("User delete error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete the user account."
    ]);
}
