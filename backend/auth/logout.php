
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

// Get the current user's information before destroying the session.
$userId = $_SESSION["user_id"] ?? null;
$fullName = $_SESSION["full_name"] ?? "User";

// Record logout activity.
if (!empty($userId)) {

    try {

        require_once __DIR__ . "/../config/database.php";

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (
                user_id,
                action,
                description,
                ip_address,
                user_agent
            )
            VALUES (
                :user_id,
                :action,
                :description,
                :ip_address,
                :user_agent
            )
        ");

        $stmt->execute([
            ":user_id" => $userId,
            ":action" => "LOGOUT",
            ":description" => $fullName . " logged out of KATUBA CRM.",
            ":ip_address" => $_SERVER["REMOTE_ADDR"] ?? null,
            ":user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? null
        ]);

    } catch (Throwable $e) {

        // Do not prevent logout if activity recording fails.
        error_log(
            "KATUBA CRM logout activity log error: " .
            $e->getMessage()
        );
    }
}

// Destroy the session.
$_SESSION = [];

session_unset();
session_destroy();

echo json_encode([
    "success" => true,
    "message" => "Logout successful."
]);

?>
