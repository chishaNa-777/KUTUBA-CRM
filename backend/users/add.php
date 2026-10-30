
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Require login.
if (empty($_SESSION["user_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Not authenticated."
    ]);
}

// Only administrators can create user accounts.
if (($_SESSION["role"] ?? "") !== "admin") {
    respond(403, [
        "success" => false,
        "message" => "You are not authorised to create user accounts."
    ]);
}

// Allow POST requests only.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Invalid request method."
    ]);
}

require_once __DIR__ . "/../config/database.php";

$full_name = trim($_POST["full_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$password = $_POST["password"] ?? "";
$role = trim($_POST["role"] ?? "");
$status = trim($_POST["status"] ?? "active");

if (
    $full_name === "" ||
    $email === "" ||
    $password === "" ||
    $role === ""
) {
    respond(400, [
        "success" => false,
        "message" => "Please fill in all required fields."
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, [
        "success" => false,
        "message" => "Please enter a valid email address."
    ]);
}

if (strlen($password) < 8) {
    respond(400, [
        "success" => false,
        "message" => "The password must be at least 8 characters long."
    ]);
}

$allowedRoles = [
    "admin",
    "hr_officer",
    "operations_manager"
];

if (!in_array($role, $allowedRoles, true)) {
    respond(400, [
        "success" => false,
        "message" => "Invalid user role."
    ]);
}

if (!in_array($status, ["active", "inactive"], true)) {
    respond(400, [
        "success" => false,
        "message" => "Invalid account status."
    ]);
}

$profilePicturePath = null;
$uploadedFilePath = null;

$uploadDirectory = __DIR__ . "/../../uploads/users";

$allowedMimeTypes = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp"
];

try {

    $check = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $check->execute([$email]);

    if ($check->fetch(PDO::FETCH_ASSOC)) {
        respond(409, [
            "success" => false,
            "message" => "A user with this email already exists."
        ]);
    }

    if (isset($_FILES["profile_picture"])) {

        $file = $_FILES["profile_picture"];

        if ($file["error"] !== UPLOAD_ERR_NO_FILE) {

            if ($file["error"] !== UPLOAD_ERR_OK) {
                respond(400, [
                    "success" => false,
                    "message" => "The picture upload failed. Please try again."
                ]);
            }

            if ($file["size"] > 2 * 1024 * 1024) {
                respond(400, [
                    "success" => false,
                    "message" => "The picture must not exceed 2 MB."
                ]);
            }

            if (!is_uploaded_file($file["tmp_name"])) {
                respond(400, [
                    "success" => false,
                    "message" => "Invalid picture upload."
                ]);
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file["tmp_name"]);

            if (!isset($allowedMimeTypes[$mimeType])) {
                respond(400, [
                    "success" => false,
                    "message" => "Only JPG, PNG, and WebP pictures are allowed."
                ]);
            }

            if (
                !is_dir($uploadDirectory) &&
                !mkdir($uploadDirectory, 0755, true) &&
                !is_dir($uploadDirectory)
            ) {
                throw new RuntimeException(
                    "Unable to create the profile picture directory."
                );
            }

            $filename = bin2hex(random_bytes(16))
                . "."
                . $allowedMimeTypes[$mimeType];

            $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($file["tmp_name"], $destination)) {
                throw new RuntimeException(
                    "Unable to save the uploaded profile picture."
                );
            }

            $uploadedFilePath = $destination;
            $profilePicturePath = "uploads/users/" . $filename;
        }
    }

    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    if ($hashedPassword === false) {
        throw new RuntimeException("Unable to secure the password.");
    }

    $stmt = $conn->prepare("
        INSERT INTO users (
            full_name,
            email,
            phone,
            password_hash,
            role,
            status,
            profile_picture
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $full_name,
        $email,
        $phone !== "" ? $phone : null,
        $hashedPassword,
        $role,
        $status,
        $profilePicturePath
    ]);

    respond(201, [
        "success" => true,
        "message" => "User added successfully.",
        "user_id" => (int) $conn->lastInsertId(),
        "profile_picture" => $profilePicturePath
    ]);

} catch (Throwable $e) {

    if (
        $uploadedFilePath !== null &&
        is_file($uploadedFilePath)
    ) {
        @unlink($uploadedFilePath);
    }

    error_log("Add user error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to add the user. Check the PHP error log for details."
    ]);
}
