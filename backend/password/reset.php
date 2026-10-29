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


// Get token and new password

$token = trim($_POST["token"] ?? "");

$new_password = $_POST["new_password"] ?? "";


// Check token

if ($token === "") {

    echo json_encode([
        "success" => false,
        "message" => "Password reset token is missing."
    ]);

    exit;
}


// Check password

if ($new_password === "") {

    echo json_encode([
        "success" => false,
        "message" => "Please enter a new password."
    ]);

    exit;
}


// Check password length

if (strlen($new_password) < 6) {

    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 6 characters."
    ]);

    exit;
}


try {

    // Find user using the reset token
    // and make sure the token has not expired

    $stmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE reset_token = ?
        AND reset_token_expiry > NOW()
        LIMIT 1
    ");

    $stmt->execute([$token]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);


    // Token invalid or expired

    if (!$user) {

        echo json_encode([
            "success" => false,
            "message" => "This password reset link is invalid or has expired."
        ]);

        exit;
    }


    // Securely hash the new password

    $hashed_password = password_hash(
        $new_password,
        PASSWORD_DEFAULT
    );


    // Update password
    // Clear token so it cannot be used again

    $update = $conn->prepare("
        UPDATE users
        SET password = ?,
            reset_token = NULL,
            reset_token_expiry = NULL
        WHERE id = ?
    ");

    $update->execute([
        $hashed_password,
        $user["id"]
    ]);


    echo json_encode([
        "success" => true,
        "message" => "Password changed successfully."
    ]);


} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);

}

?>