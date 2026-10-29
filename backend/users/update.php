
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}

$id = filter_var(
    $_POST["id"] ?? "",
    FILTER_VALIDATE_INT
);

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
    echo json_encode([
        "success" => false,
        "message" => "Please provide valid values for all required fields."
    ]);

    exit;
}

/*
 * Only accept roles supported by the current CRM database.
 */
$allowedRoles = [
    "admin",
    "hr_officer",
    "operations_manager"
];

if (!in_array($role, $allowedRoles, true)) {
    echo json_encode([
        "success" => false,
        "message" => "Please select a valid user role."
    ]);

    exit;
}

if (!in_array($status, ["active", "inactive"], true)) {
    echo json_encode([
        "success" => false,
        "message" => "Please select a valid account status."
    ]);

    exit;
}

if ($password !== "" && strlen($password) < 8) {
    echo json_encode([
        "success" => false,
        "message" => "The new password must contain at least 8 characters."
    ]);

    exit;
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
        echo json_encode([
            "success" => false,
            "message" => "Another user already uses this email."
        ]);

        exit;
    }

    // Get the current user and preserve their existing picture.
    $exists = $conn->prepare("
        SELECT user_id, profile_picture
        FROM users
        WHERE user_id = ?
    ");

    $exists->execute([$id]);

    $currentUser = $exists->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        echo json_encode([
            "success" => false,
            "message" => "User account not found."
        ]);

        exit;
    }

    $currentPicture = $currentUser["profile_picture"];

    /*
     * Process a replacement picture only when a file was selected.
     */
    if (
        isset($_FILES["profile_picture"]) &&
        $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES["profile_picture"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                "The picture upload failed. Please try again."
            );
        }

        if ($file["size"] > 2 * 1024 * 1024) {
            throw new RuntimeException(
                "The picture must not exceed 2 MB."
            );
        }

        if (!is_uploaded_file($file["tmp_name"])) {
            throw new RuntimeException(
                "Invalid picture upload."
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file["tmp_name"]);

        $allowedTypes = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        if (!isset($allowedTypes[$mimeType])) {
            throw new RuntimeException(
                "Only JPG, PNG, and WebP pictures are allowed."
            );
        }

        $uploadDirectory = __DIR__ . "/../../uploads/users";

        if (
            !is_dir($uploadDirectory) &&
            !mkdir($uploadDirectory, 0755, true) &&
            !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException(
                "Unable to prepare the picture upload folder."
            );
        }

        $fileName = bin2hex(random_bytes(16))
            . "."
            . $allowedTypes[$mimeType];

        $newPictureFullPath = $uploadDirectory
            . DIRECTORY_SEPARATOR
            . $fileName;

        $newPicturePath = "uploads/users/" . $fileName;

        if (!move_uploaded_file(
            $file["tmp_name"],
            $newPictureFullPath
        )) {
            throw new RuntimeException(
                "Unable to save the new profile picture."
            );
        }
    }

    /*
     * Build the update query.
     * If no picture was uploaded, profile_picture is not changed.
     */
    $conn->beginTransaction();

    if ($password !== "") {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

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

    /*
     * Only after a successful database update, remove the old
     * picture if a replacement was uploaded.
     */
    if ($newPicturePath !== null && !empty($currentPicture)) {

        // Only delete files inside the application's users upload folder.
        $uploadDirectory = realpath(__DIR__ . "/../../uploads/users");
        $oldPictureFullPath = realpath(
            __DIR__ . "/../../" . $currentPicture
        );

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

    echo json_encode([
        "success" => true,
        "message" => "User updated successfully."
    ]);

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
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    } else {
        error_log("User update error: " . $e->getMessage());

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Unable to update the user account."
        ]);
    }
}
