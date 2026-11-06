
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond($success, $message, $statusCode = 200)
{
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "message" => $message
    ]);

    exit;
}

// Require an authenticated session.
if (empty($_SESSION["user_id"])) {
    respond(false, "Not authenticated.", 401);
}

// Only administrators can delete user accounts.
if (($_SESSION["role"] ?? "") !== "admin") {
    respond(false, "You are not authorised to delete user accounts.", 403);
}

// Only allow POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request method.", 405);
}

require_once __DIR__ . "/../config/database.php";

// Get the user ID sent by the frontend.
$id = filter_var(
    $_POST["id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    respond(false, "A valid User ID is required.", 400);
}

try {

    $loggedInUserId = (int) $_SESSION["user_id"];

    // Prevent deleting the currently logged-in account.
    if ($loggedInUserId === (int) $id) {
        respond(
            false,
            "You cannot delete your own account while logged in.",
            400
        );
    }

    // Confirm that the account exists and get its details.
    $check = $conn->prepare("
        SELECT user_id, full_name, email, role
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $check->execute([$id]);

    $userToDelete = $check->fetch(PDO::FETCH_ASSOC);

    if (!$userToDelete) {
        respond(false, "User account not found.", 404);
    }

    // Start transaction so logging and deletion succeed together.
    $conn->beginTransaction();

    // Record the deletion before removing the account.
    $log = $conn->prepare("
        INSERT INTO activity_logs
            (user_id, action, description, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");

    $description =
        "Deleted user account: " . $userToDelete["full_name"] .
        " | Email: " . $userToDelete["email"] .
        " | Role: " . $userToDelete["role"] .
        " | User ID: " . $userToDelete["user_id"];

    $log->execute([
        $loggedInUserId,
        "USER_DELETED",
        $description,
        $_SERVER["REMOTE_ADDR"] ?? null,
        $_SERVER["HTTP_USER_AGENT"] ?? null
    ]);

    // Delete the account using the correct primary key.
    $stmt = $conn->prepare("
        DELETE FROM users
        WHERE user_id = ?
    ");

    $stmt->execute([$id]);

    $conn->commit();

    respond(true, "User deleted successfully.");

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("User delete error: " . $e->getMessage());

    respond(
        false,
        "Unable to delete the user account.",
        500
    );
}
