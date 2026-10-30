
<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function normalizeWorkerRole($value)
{
    $value = strtolower(trim((string) $value));

    $allowed = [
        "maid",
        "gardener",
        "daycare",
        "supervisor",
        "other"
    ];

    if (in_array($value, $allowed, true)) {
        return $value;
    }

    return "other";
}

function normalizeAvailability($value)
{
    $value = strtolower(trim((string) $value));

    $aliases = [
        "available" => "available",
        "assigned"  => "assigned",
        "standby"   => "standby",
        "inactive"  => "inactive"
    ];

    return $aliases[$value] ?? "";
}

function normalizePaymentMethod($value)
{
    $value = strtolower(trim((string) $value));

    $aliases = [
        "cash"          => "cash",
        "airtel money"  => "airtel_money",
        "airtel_money"  => "airtel_money",
        "mtn money"     => "mtn_money",
        "mtn_money"     => "mtn_money",
        "bank"          => "bank",
        "bank transfer" => "bank",
        "bank_transfer" => "bank"
    ];

    return $aliases[$value] ?? null;
}

function normalizeStatus($value)
{
    $value = strtolower(trim((string) $value));

    return in_array($value, ["active", "inactive"], true)
        ? $value
        : "";
}

/*
|--------------------------------------------------------------------------
| Only accept POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method. Please submit the form."
    ]);

    exit;
}

$newPhotoPath = null;

try {

    /*
    |--------------------------------------------------------------------------
    | 1. Get worker ID
    |--------------------------------------------------------------------------
    */

    $worker_id = filter_var(
        $_POST["worker_id"] ?? $_POST["id"] ?? "",
        FILTER_VALIDATE_INT
    );

    if (!$worker_id || $worker_id <= 0) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "A valid worker ID is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Retrieve existing worker
    |--------------------------------------------------------------------------
    */

    $check = $conn->prepare("
        SELECT worker_id, photo_path, availability
        FROM workers
        WHERE worker_id = :worker_id
    ");

    $check->execute([
        ":worker_id" => $worker_id
    ]);

    $existingWorker = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existingWorker) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Worker not found."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Get submitted form values
    |--------------------------------------------------------------------------
    */

    $full_name = trim($_POST["full_name"] ?? "");
    $nrc_number = trim($_POST["nrc_number"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");

    $submittedRole = trim($_POST["worker_role"] ?? "");
    $worker_role = normalizeWorkerRole($submittedRole);

    $experience_years = trim((string) (
        $_POST["experience_years"] ?? ""
    ));

    $skills = trim($_POST["skills"] ?? "");

    $submittedAvailability = trim(
        $_POST["availability"] ?? ""
    );

    $availability = normalizeAvailability(
        $submittedAvailability
    );

    // Preserve the existing availability if the form omitted it.
    if ($submittedAvailability === "") {
        $availability = normalizeAvailability(
            $existingWorker["availability"] ?? ""
        );
    }

    // Final fallback for an empty or invalid legacy value.
    if ($availability === "") {
        $availability = "available";
    }

    $napsa_number = trim($_POST["napsa_number"] ?? "");
    $nhima_number = trim($_POST["nhima_number"] ?? "");

    $payment_method = normalizePaymentMethod(
        $_POST["payment_method"] ?? ""
    );

    $payment_details = trim(
        $_POST["payment_details"] ?? ""
    );

    $start_date = trim($_POST["start_date"] ?? "");

    $status = normalizeStatus($_POST["status"] ?? "");

    $notes = trim($_POST["notes"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | 4. Validate required fields
    |--------------------------------------------------------------------------
    */

    if ($full_name === "" || $phone === "" || $submittedRole === "") {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Full name, phone number and worker role are required."
        ]);

        exit;
    }

    if ($status === "") {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Please select a valid worker status."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Handle custom worker roles
    |--------------------------------------------------------------------------
    */

    $standardRoles = [
        "maid",
        "gardener",
        "daycare",
        "supervisor",
        "other"
    ];

    if (!in_array(strtolower($submittedRole), $standardRoles, true)) {

        if ($skills !== "") {
            $skills .= ", ";
        }

        $skills .= "Custom role: " . $submittedRole;
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Validate email
    |--------------------------------------------------------------------------
    */

    if (
        $email !== "" &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Please enter a valid email address."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Validate experience
    |--------------------------------------------------------------------------
    */

    if (
        $experience_years !== "" &&
        !ctype_digit($experience_years)
    ) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Experience must be a whole number of years."
        ]);

        exit;
    }

    $experience_years = $experience_years === ""
        ? null
        : (int) $experience_years;

    if (
        $experience_years !== null &&
        ($experience_years < 0 || $experience_years > 255)
    ) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Experience must be between 0 and 255 years."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Convert optional empty values to NULL
    |--------------------------------------------------------------------------
    */

    $nrc_number = $nrc_number === "" ? null : $nrc_number;
    $email = $email === "" ? null : $email;
    $address = $address === "" ? null : $address;
    $skills = $skills === "" ? null : $skills;

    $napsa_number = $napsa_number === "" ? null : $napsa_number;
    $nhima_number = $nhima_number === "" ? null : $nhima_number;
    $payment_details = $payment_details === "" ? null : $payment_details;
    $notes = $notes === "" ? null : $notes;

    /*
    |--------------------------------------------------------------------------
    | 9. Validate start date
    |--------------------------------------------------------------------------
    */

    if ($start_date === "") {
        $start_date = null;
    } else {
        $date = DateTime::createFromFormat("!Y-m-d", $start_date);

        if (!$date || $date->format("Y-m-d") !== $start_date) {
            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Please enter a valid start date."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 10. Preserve existing worker photo
    |--------------------------------------------------------------------------
    */

    $photo_path = $existingWorker["photo_path"] ?? null;

    /*
    |--------------------------------------------------------------------------
    | 11. Process optional photo upload
    |--------------------------------------------------------------------------
    */

    $photo = $_FILES["photo"] ?? $_FILES["worker_photo"] ?? null;

    if ($photo && $photo["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($photo["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                "The photo upload failed. Please try again."
            );
        }

        if ($photo["size"] > 2 * 1024 * 1024) {
            throw new RuntimeException(
                "The photo must not exceed 2 MB."
            );
        }

        if (!is_uploaded_file($photo["tmp_name"])) {
            throw new RuntimeException(
                "Invalid photo upload."
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($photo["tmp_name"]);

        $allowedTypes = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp"
        ];

        if (!isset($allowedTypes[$mimeType])) {
            throw new RuntimeException(
                "Only JPG, PNG and WebP photos are allowed."
            );
        }

        $imageInfo = @getimagesize($photo["tmp_name"]);

        if (
            $imageInfo === false ||
            $imageInfo["mime"] !== $mimeType
        ) {
            throw new RuntimeException(
                "The uploaded file is not a valid image."
            );
        }

        $projectRoot = dirname(__DIR__, 2);

        $uploadDirectory = $projectRoot
            . DIRECTORY_SEPARATOR . "uploads"
            . DIRECTORY_SEPARATOR . "workers";

        if (
            !is_dir($uploadDirectory) &&
            !mkdir($uploadDirectory, 0755, true) &&
            !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException(
                "Unable to create the worker photo upload folder."
            );
        }

        $filename = bin2hex(random_bytes(16))
            . "."
            . $allowedTypes[$mimeType];

        $destination = $uploadDirectory
            . DIRECTORY_SEPARATOR
            . $filename;

        if (!move_uploaded_file($photo["tmp_name"], $destination)) {
            throw new RuntimeException(
                "Unable to save the new worker photo."
            );
        }

        $newPhotoPath = $destination;

        $photo_path = "uploads/workers/" . $filename;
    }

    /*
    |--------------------------------------------------------------------------
    | 12. Update worker in PostgreSQL
    |--------------------------------------------------------------------------
    */

    $sql = "
        UPDATE workers SET
            full_name = :full_name,
            nrc_number = :nrc_number,
            phone = :phone,
            email = :email,
            address = :address,
            worker_role = :worker_role,
            experience_years = :experience_years,
            skills = :skills,
            availability = :availability,
            napsa_number = :napsa_number,
            nhima_number = :nhima_number,
            payment_method = :payment_method,
            payment_details = :payment_details,
            start_date = :start_date,
            status = :status,
            notes = :notes,
            photo_path = :photo_path,
            updated_at = CURRENT_TIMESTAMP
        WHERE worker_id = :worker_id
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":full_name" => $full_name,
        ":nrc_number" => $nrc_number,
        ":phone" => $phone,
        ":email" => $email,
        ":address" => $address,
        ":worker_role" => $worker_role,
        ":experience_years" => $experience_years,
        ":skills" => $skills,
        ":availability" => $availability,
        ":napsa_number" => $napsa_number,
        ":nhima_number" => $nhima_number,
        ":payment_method" => $payment_method,
        ":payment_details" => $payment_details,
        ":start_date" => $start_date,
        ":status" => $status,
        ":notes" => $notes,
        ":photo_path" => $photo_path,
        ":worker_id" => $worker_id
    ]);

    /*
    |--------------------------------------------------------------------------
    | 13. Return success
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,
        "message" => "Worker updated successfully.",
        "worker_id" => $worker_id,
        "photo_path" => $photo_path
    ]);

} catch (PDOException $e) {

    // Remove a newly uploaded photo if the database update failed.
    if ($newPhotoPath && file_exists($newPhotoPath)) {
        unlink($newPhotoPath);
    }

    error_log(
        "Worker update database error: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database error while updating worker. Please check the server error log."
    ]);

} catch (Throwable $e) {

    if ($newPhotoPath && file_exists($newPhotoPath)) {
        unlink($newPhotoPath);
    }

    error_log(
        "Worker update error: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

?>
