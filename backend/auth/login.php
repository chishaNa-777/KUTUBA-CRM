
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond($success, $message, $statusCode = 200, $extra = [])
{
    http_response_code($statusCode);

    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request method.", 405);
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    respond(false, "Email and password are required.", 400);
}

// Argon2id security configuration.
$hashOptions = [
    "memory_cost" => 19456,
    "time_cost" => 2,
    "threads" => 1
];

try {

    // Find the user account.
    $sql = "SELECT
                user_id,
                full_name,
                email,
                password_hash,
                role,
                status,
                approval_status,
                profile_picture,
                profile_photo
            FROM users
            WHERE email = :email
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":email" => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify password.
    if (
        !$user ||
        !password_verify($password, $user["password_hash"])
    ) {
        respond(false, "Invalid email or password.", 401);
    }

    // Check approval.
    if (($user["approval_status"] ?? "") === "pending") {
        respond(
            false,
            "Your account is awaiting administrator approval."
        );
    }

    if (($user["approval_status"] ?? "") === "rejected") {
        respond(
            false,
            "Your registration was not approved. Please contact the administrator."
        );
    }

    // Check account status.
    if (strtolower($user["status"] ?? "") !== "active") {
        respond(false, "Your account is inactive.");
    }

    /*
     * Upgrade old password hashes to Argon2id.
     * The user's actual password does not change.
     */
    if (
        password_needs_rehash(
            $user["password_hash"],
            PASSWORD_ARGON2ID,
            $hashOptions
        )
    ) {

        $newHash = password_hash(
            $password,
            PASSWORD_ARGON2ID,
            $hashOptions
        );

        if ($newHash === false) {
            throw new RuntimeException(
                "Unable to create the new password hash."
            );
        }

        $rehashSql = "
            UPDATE users
            SET password_hash = :new_hash,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id
              AND password_hash = :old_hash
        ";

        $rehashStmt = $conn->prepare($rehashSql);

        $rehashStmt->execute([
            ":new_hash" => $newHash,
            ":user_id" => $user["user_id"],
            ":old_hash" => $user["password_hash"]
        ]);

        if ($rehashStmt->rowCount() !== 1) {
            throw new RuntimeException(
                "Password hash upgrade could not be confirmed."
            );
        }
    }

    // Select the available profile photo.
    $profilePhoto = !empty($user["profile_photo"])
        ? $user["profile_photo"]
        : ($user["profile_picture"] ?? null);

    // Regenerate session ID after successful authentication.
    session_regenerate_id(true);

    // Store user information in session.
    $_SESSION["user_id"] = $user["user_id"];
    $_SESSION["full_name"] = $user["full_name"];
    $_SESSION["email"] = $user["email"];
    $_SESSION["role"] = $user["role"];
    $_SESSION["profile_photo"] = $profilePhoto;

    // Record successful login.
    try {

        $logStmt = $conn->prepare("
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

        $logStmt->execute([
            ":user_id" => $user["user_id"],
            ":action" => "LOGIN",
            ":description" => "User successfully logged into KATUBA CRM.",
            ":ip_address" => $_SERVER["REMOTE_ADDR"] ?? null,
            ":user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? null
        ]);

    } catch (Throwable $logError) {

        error_log(
            "KATUBA CRM login activity log error: " .
            $logError->getMessage()
        );
    }

    // Return successful login.
    respond(true, "Login successful.", 200, [
        "user" => [
            "id" => $user["user_id"],
            "full_name" => $user["full_name"],
            "email" => $user["email"],
            "role" => $user["role"],
            "profile_photo" => $profilePhoto
        ]
    ]);

} catch (Throwable $e) {

    error_log(
        "KATUBA CRM login error: " . $e->getMessage()
    );

    respond(
        false,
        "Unable to complete login. Check the PHP error log.",
        500
    );
}
