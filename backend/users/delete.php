
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

// Only allow POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

// Get the user ID sent by the frontend.
$id = filter_var(
    $_POST["id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    echo json_encode([
        "success" => false,
        "message" => "A valid User ID is required."
    ]);

    exit;
}

try {

    // Prevent deleting the currently logged-in account.
    // Check common session keys used for the logged-in user ID.
    $loggedInUserId = $_SESSION["user_id"]
        ?? $_SESSION["user"]["user_id"]
        ?? $_SESSION["user"]["id"]
        ?? null;

    if (
        $loggedInUserId !== null &&
        (int) $loggedInUserId === (int) $id
    ) {
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

} catch (PDOException $e) {

    error_log("User delete error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete the user account."
    ]);
}
