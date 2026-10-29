<?php

require_once "../config/database.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

$email = trim($_POST["email"] ?? "");

if ($email === "") {

    echo json_encode([
        "success" => false,
        "message" => "Please enter your email address."
    ]);

    exit;
}

try {

    // Find the user
    $stmt = $conn->prepare("
        SELECT id, full_name, email
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        echo json_encode([
            "success" => false,
            "message" => "No account was found with that email address."
        ]);

        exit;
    }

    // Generate a secure random token
    $reset_token = bin2hex(random_bytes(32));

    // Token expires after 30 minutes
    $expiry = date(
        "Y-m-d H:i:s",
        strtotime("+30 minutes")
    );

    // Save token and expiry
    $update = $conn->prepare("
        UPDATE users
        SET reset_token = ?,
            reset_token_expiry = ?
        WHERE id = ?
    ");

    $update->execute([
        $reset_token,
        $expiry,
        $user["id"]
    ]);

    // Local reset link for testing
    $reset_link =
        "http://localhost/KUTUBA-CRM/frontend/reset-password.html?token="
        . urlencode($reset_token);

    echo json_encode([
        "success" => true,
        "message" => "Email verified successfully.",
        "user" => [
            "id" => $user["id"],
            "full_name" => $user["full_name"],
            "email" => $user["email"]
        ],
        "reset_link" => $reset_link
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);

}

?>