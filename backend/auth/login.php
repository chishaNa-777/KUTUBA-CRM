
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);
    exit;
}

// Argon2id configuration.
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

    // Verify the supplied password.
    if (
        !$user ||
        !password_verify($password, $user["password_hash"])
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password."
        ]);
        exit;
    }

    // Check account approval.
    if ($user["approval_status"] === "pending") {
        echo json_encode([
            "success" => false,
            "message" => "Your account is awaiting administrator approval."
        ]);
        exit;
    }

    if ($user["approval_status"] === "rejected") {
        echo json_encode([
            "success" => false,
            "message" => "Your registration was not approved. Please contact the administrator."
        ]);
        exit;
    }

    // Check account status.
    if (strtolower($user["status"]) !== "active") {
        echo json_encode([
            "success" => false,
            "message" => "Your account is inactive."
        ]);
        exit;
    }

    /*
     * Automatically upgrade old password hashes.
     * Existing passwords remain unchanged.
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

        try {

            $rehashSql = "
                UPDATE users
                SET
                    password_hash = :new_hash,
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

        } catch (PDOException $rehashError) {

            // Do not prevent a valid login if rehashing fails.
            error_log(
                "KATUBA CRM password rehash error: " .
                $rehashError->getMessage()
            );
        }
    }

    // Select whichever profile photo path is available.
    $profilePhoto = !empty($user["profile_photo"])
        ? $user["profile_photo"]
        : ($user["profile_picture"] ?? null);

    // Regenerate session ID after successful authentication.
    session_regenerate_id(true);

    // Store user information in the session.
    $_SESSION["user_id"] = $user["user_id"];
    $_SESSION["full_name"] = $user["full_name"];
    $_SESSION["email"] = $user["email"];
    $_SESSION["role"] = $user["role"];
    $_SESSION["profile_photo"] = $profilePhoto;

    // Return successful login information.
    echo json_encode([
        "success" => true,
        "message" => "Login successful.",
        "user" => [
            "id" => $user["user_id"],
            "full_name" => $user["full_name"],
            "email" => $user["email"],
            "role" => $user["role"],
            "profile_photo" => $profilePhoto
        ]
    ]);

} catch (PDOException $e) {

    error_log(
        "KATUBA CRM login database error: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database error. Check the PHP error log."
    ]);
}

?>
