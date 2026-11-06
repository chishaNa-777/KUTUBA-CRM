
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

function respond($success, $message, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode([
        "success" => $success,
        "message" => $message
    ]);
    exit;
}

// Require login.
if (empty($_SESSION["user_id"])) {
    respond(false, "Please log in first.", 401);
}

// Only administrators can update user accounts.
if (($_SESSION["role"] ?? "") !== "admin") {
    respond(false, "Access denied.", 403);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request method.", 405);
}

require_once __DIR__ . "/../config/database.php";

$id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);

$full_name = trim($_POST["full_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$role = trim($_POST["role"] ?? "");
$status = trim($_POST["status"] ?? "");

if (
    !$id ||
    $full_name === "" ||
    $email === "" ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $role === "" ||
    $status === ""
) {
    respond(false, "Please provide valid values for all required fields.");
}

// All supported CRM roles.
$allowedRoles = [
    "admin",
    "finance_officer",
    "hr_officer",
    "it",
    "operations_manager"
];

if (!in_array($role, $allowedRoles, true)) {
    respond(false, "Please select a valid user role.");
}

if (!in_array($status, ["active", "inactive"], true)) {
    respond(false, "Please select a valid account status.");
}

if ($password !== "" && strlen($password) < 8) {
    respond(false, "The new password must contain at least 8 characters.");
}

$newPicturePath = null;
$newPictureFullPath = null;

try {

    // Check whether another user already uses this email.
    $check = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE email = ?
          AND user_id != ?
    ");

    $check->execute([$email, $id]);

    if ($check->fetch(PDO::FETCH_ASSOC)) {
        respond(false, "Another user already uses this email.");
    }

    // Get the current user and preserve their existing picture.
    $exists = $conn->prepare("
        SELECT user_id, full_name, email, role, status, profile_picture
        FROM users
        WHERE user_id = ?
    ");

    $exists->execute([$id]);
    $currentUser = $exists->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        respond(false, "User account not found.");
    }

    $currentPicture = $currentUser["profile_picture"];

    // Process a replacement picture only when selected.
    if (
        isset($_FILES["profile_picture"]) &&
        $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES["profile_picture"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("The picture upload failed. Please try again.");
        }

        if ($file["size"] > 2 * 1024 * 1024) {
            throw new RuntimeException("The picture must not exceed 2 MB.");
        }

        if (!is_uploaded_file($file["tmp_name"])) {
            throw new RuntimeException("Invalid picture upload.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file["tmp_name"]);

        $allowedTypes = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        if (!isset($allowedTypes[$mimeType])) {
            throw new RuntimeException("Only JPG, PNG, and WebP pictures are allowed.");
        }

        $uploadDirectory = __DIR__ . "/../../uploads/users";

        if (
            !is_dir($uploadDirectory) &&
            !mkdir($uploadDirectory, 0755, true) &&
            !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException("Unable to prepare the picture upload folder.");
        }

        $fileName = bin2hex(random_bytes(16)) . "." . $allowedTypes[$mimeType];

        $newPictureFullPath = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
        $newPicturePath = "uploads/users/" . $fileName;

        if (!move_uploaded_file($file["tmp_name"], $newPictureFullPath)) {
            throw new RuntimeException("Unable to save the new profile picture.");
        }
    }

    // Update the user account.
    $conn->beginTransaction();

    if ($password !== "") {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        if ($newPicturePath !== null) {
            $stmt = $conn->prepare("
                UPDATE users
                SET full_name = ?,
                    email = ?,
                    password_hash = ?,
                    role = ?,
                    status = ?,
                    profile_picture = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $full_name,
                $email,
                $hashedPassword,
                $role,
                $status,
                $newPicturePath,
                $id
            ]);

        } else {
            $stmt = $conn->prepare("
                UPDATE users
                SET full_name = ?,
                    email = ?,
                    password_hash = ?,
                    role = ?,
                    status = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $full_name,
                $email,
                $hashedPassword,
                $role,
                $status,
                $id
            ]);
        }

    } else {

        if ($newPicturePath !== null) {
            $stmt = $conn->prepare("
                UPDATE users
                SET full_name = ?,
                    email = ?,
                    role = ?,
                    status = ?,
                    profile_picture = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $full_name,
                $email,
                $role,
                $status,
                $newPicturePath,
                $id
            ]);

        } else {
            $stmt = $conn->prepare("
                UPDATE users
                SET full_name = ?,
                    email = ?,
                    role = ?,
                    status = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $full_name,
                $email,
                $role,
                $status,
                $id
            ]);
        }
    }

    $conn->commit();

    // Record the update in Activity Logs.
    try {
        $log = $conn->prepare("
            INSERT INTO activity_logs
                (user_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");

        $description =
            "Updated user account: " . $full_name .
            " | Email: " . $email .
            " | Role: " . $role .
            " | Status: " . $status;

        $log->execute([
            $_SESSION["user_id"],
            "USER_UPDATED",
            $description,
            $_SERVER["REMOTE_ADDR"] ?? null,
            $_SERVER["HTTP_USER_AGENT"] ?? null
        ]);

    } catch (Throwable $logError) {
        error_log("User update activity log error: " . $logError->getMessage());
    }

    // Remove the old picture only after a successful update.
    if ($newPicturePath !== null && !empty($currentPicture)) {

        $uploadDirectory = realpath(__DIR__ . "/../../uploads/users");
        $oldPictureFullPath = realpath(__DIR__ . "/../../" . $currentPicture);

        if (
            $uploadDirectory !== false &&
            $oldPictureFullPath !== false &&
            strpos(
                $oldPictureFullPath,
                $uploadDirectory . DIRECTORY_SEPARATOR
            ) === 0 &&
            is_file($oldPictureFullPath)
        ) {
            @unlink($oldPictureFullPath);
        }
    }

    respond(true, "User updated successfully.");

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Remove only the newly uploaded file if the update failed.
    if (
        $newPictureFullPath !== null &&
        is_file($newPictureFullPath)
    ) {
        @unlink($newPictureFullPath);
    }

    if ($e instanceof RuntimeException) {
        respond(false, $e->getMessage());
    }

    error_log("User update error: " . $e->getMessage());

    respond(false, "Unable to update the user account.", 500);
}
