
<?php

require_once __DIR__ . "/../config/database.php";

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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request method.", 405);
}

$full_name = trim($_POST["full_name"] ?? "");
$email = strtolower(trim($_POST["email"] ?? ""));
$phone = trim($_POST["phone"] ?? "");
$password = $_POST["password"] ?? "";
$confirm_password = $_POST["confirm_password"] ?? "";

if ($full_name === "" || $email === "" || $password === "") {
    respond(false, "Please fill in all required fields.", 400);
}

if (!function_exists("mb_strlen")) {
    respond(false, "A required PHP extension is unavailable.", 500);
}

if (mb_strlen($full_name) > 120) {
    respond(false, "Your full name must not exceed 120 characters.", 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    respond(false, "Please enter a valid email address.", 400);
}

if (strlen($phone) > 30) {
    respond(false, "The phone number must not exceed 30 characters.", 400);
}

if (strlen($password) < 8) {
    respond(false, "Your password must contain at least 8 characters.", 400);
}

if ($password !== $confirm_password) {
    respond(false, "The passwords do not match.", 400);
}

/*
 * PROFILE PHOTO VALIDATION
 * Photos are optional. Maximum size: 2 MB.
 */

$photoPath = null;
$photoFullPath = null;
$photoUploaded = false;

if (
    isset($_FILES["profile_photo"]) &&
    $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_NO_FILE
) {
    $photo = $_FILES["profile_photo"];

    if ($photo["error"] !== UPLOAD_ERR_OK) {
        respond(false, "The photo upload failed. Please try again.", 400);
    }

    if ($photo["size"] > 2 * 1024 * 1024) {
        respond(false, "The photo must not be larger than 2 MB.", 400);
    }

    if (!is_uploaded_file($photo["tmp_name"])) {
        respond(false, "Invalid photo upload.", 400);
    }

    if (!class_exists("finfo")) {
        respond(false, "Photo validation is unavailable on this server.", 500);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($photo["tmp_name"]);

    $allowedTypes = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];

    if (!isset($allowedTypes[$mimeType])) {
        respond(false, "Please upload a valid JPG, PNG, or WebP image.", 400);
    }

    // Confirm that the uploaded file contains readable image data.
    if (@getimagesize($photo["tmp_name"]) === false) {
        respond(false, "The uploaded file is not a valid image.", 400);
    }

    // Store photos outside the backend PHP folders.
    $uploadDirectory = dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . "uploads"
        . DIRECTORY_SEPARATOR . "user_photos";

    if (
        !is_dir($uploadDirectory) &&
        !mkdir($uploadDirectory, 0755, true) &&
        !is_dir($uploadDirectory)
    ) {
        respond(false, "The photo folder could not be created.", 500);
    }

    if (!is_writable($uploadDirectory)) {
        respond(false, "The photo folder is not writable.", 500);
    }

    try {
        $filename = bin2hex(random_bytes(24))
            . "." . $allowedTypes[$mimeType];
    } catch (Throwable $e) {
        respond(false, "Could not prepare the uploaded photo.", 500);
    }

    $photoFullPath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
    $photoPath = "uploads/user_photos/" . $filename;
}

/*
 * REGISTER USER
 * Public registration cannot choose the administrator role.
 * New accounts remain inactive and pending approval.
 */

try {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    if ($password_hash === false) {
        respond(false, "Could not secure the password.", 500);
    }

    $role = "hr_officer";
    $status = "inactive";
    $approval_status = "pending";

    $sql = "INSERT INTO users (
                full_name,
                email,
                phone,
                password_hash,
                role,
                status,
                approval_status,
                profile_photo
            ) VALUES (
                :full_name,
                :email,
                :phone,
                :password_hash,
                :role,
                :status,
                :approval_status,
                :profile_photo
            )";

    $stmt = $conn->prepare($sql);

    /*
     * Save the image first. If the database insert fails,
     * the uploaded file will be removed.
     */

    if ($photoFullPath !== null) {
        if (!move_uploaded_file(
            $_FILES["profile_photo"]["tmp_name"],
            $photoFullPath
        )) {
            respond(false, "Could not save the uploaded photo.", 500);
        }

        $photoUploaded = true;
    }

    $stmt->execute([
        ":full_name" => $full_name,
        ":email" => $email,
        ":phone" => $phone !== "" ? $phone : null,
        ":password_hash" => $password_hash,
        ":role" => $role,
        ":status" => $status,
        ":approval_status" => $approval_status,
        ":profile_photo" => $photoPath
    ]);

    respond(
        true,
        "Registration submitted successfully. Please wait for administrator approval before logging in.",
        201
    );

} catch (PDOException $e) {
    // Remove the uploaded file if registration fails.
    if ($photoUploaded && $photoFullPath !== null && is_file($photoFullPath)) {
        @unlink($photoFullPath);
    }

    if ($e->getCode() === "23000") {
        respond(
            false,
            "This email address may already be registered. Please use another email address or contact the administrator.",
            409
        );
    }

    error_log("KATUBA CRM registration error: " . $e->getMessage());

    respond(
        false,
        "Registration could not be completed. Please try again later.",
        500
    );

} catch (Throwable $e) {
    if ($photoUploaded && $photoFullPath !== null && is_file($photoFullPath)) {
        @unlink($photoFullPath);
    }

    error_log("KATUBA CRM registration error: " . $e->getMessage());

    respond(
        false,
        "Registration could not be completed. Please try again later.",
        500
    );
}
?>
