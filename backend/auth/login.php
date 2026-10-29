
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

try {
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

    if (strtolower($user["status"]) !== "active") {
        echo json_encode([
            "success" => false,
            "message" => "Your account is inactive."
        ]);
        exit;
    }

    // Select whichever profile photo path is available.
    $profilePhoto = !empty($user["profile_photo"])
        ? $user["profile_photo"]
        : ($user["profile_picture"] ?? null);

    // Create a new session ID after successful authentication.
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
