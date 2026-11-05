
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond($status, $data)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Authentication
if (empty($_SESSION["user_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Not authenticated."
    ]);
}

// HR permissions
$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "hr_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied. HR privileges required."
    ]);
}

// POST only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Method not allowed."
    ]);
}

require_once __DIR__ . "/../config/database.php";

$newPhotoPath = null;

try {

    // Worker ID
    $worker_id = filter_var(
        $_POST["worker_id"] ?? $_POST["id"] ?? "",
        FILTER_VALIDATE_INT
    );

    if (!$worker_id || $worker_id <= 0) {
        respond(400, [
            "success" => false,
            "message" => "A valid worker ID is required."
        ]);
    }

    // Retrieve existing worker
    $check = $conn->prepare("
        SELECT worker_id, photo_path
        FROM workers
        WHERE worker_id = :worker_id
    ");

    $check->execute([
        ":worker_id" => $worker_id
    ]);

    $existingWorker = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existingWorker) {
        respond(404, [
            "success" => false,
            "message" => "Worker not found."
        ]);
    }

    // Form values
    $full_name = trim($_POST["full_name"] ?? "");
    $nrc_number = trim($_POST["nrc_number"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");

    $submittedRole = strtolower(trim($_POST["worker_role"] ?? ""));
    $worker_role = $submittedRole;

    $experience_years = trim(
        (string)($_POST["experience_years"] ?? "0")
    );

    $skills = trim($_POST["skills"] ?? "");

    $availability = strtolower(
        trim($_POST["availability"] ?? "")
    );

    $napsa_number = trim($_POST["napsa_number"] ?? "");
    $nhima_number = trim($_POST["nhima_number"] ?? "");

    $payment_method = strtolower(
        trim($_POST["payment_method"] ?? "")
    );

    $payment_details = trim($_POST["payment_details"] ?? "");
    $start_date = trim($_POST["start_date"] ?? "");
    $status = strtolower(trim($_POST["status"] ?? ""));
    $notes = trim($_POST["notes"] ?? "");

    // Normalize payment method
    $paymentAliases = [
        "cash" => "cash",
        "airtel money" => "airtel_money",
        "airtel_money" => "airtel_money",
        "mtn money" => "mtn_money",
        "mtn_money" => "mtn_money",
        "mobile money" => "mobile_money",
        "mobile_money" => "mobile_money",
        "bank transfer" => "bank_transfer",
        "bank_transfer" => "bank_transfer",
        "bank" => "bank"
    ];

    if ($payment_method !== "") {
        if (!isset($paymentAliases[$payment_method])) {
            respond(422, [
                "success" => false,
                "message" => "Please select a valid payment method."
            ]);
        }

        $payment_method = $paymentAliases[$payment_method];
    } else {
        $payment_method = "cash";
    }

    // Required fields
    if ($full_name === "" || $phone === "" || $submittedRole === "") {
        respond(422, [
            "success" => false,
            "message" => "Full name, phone number and worker role are required."
        ]);
    }

    // Field length validation
    $lengths = [
        "Full name" => [$full_name, 120],
        "NRC number" => [$nrc_number, 30],
        "Phone number" => [$phone, 30],
        "Email" => [$email, 150],
        "Address" => [$address, 255],
        "Worker role" => [$worker_role, 20],
        "Availability" => [$availability, 20],
        "NAPSA number" => [$napsa_number, 50],
        "NHIMA number" => [$nhima_number, 50],
        "Payment method" => [$payment_method, 20],
        "Payment details" => [$payment_details, 255]
    ];

    foreach ($lengths as $label => $field) {
        if (strlen($field[0]) > $field[1]) {
            respond(422, [
                "success" => false,
                "message" => $label . " is too long."
            ]);
        }
    }

    // Validate email
    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(422, [
            "success" => false,
            "message" => "Please enter a valid email address."
        ]);
    }

    // Validate experience
    if (
        $experience_years === "" ||
        !ctype_digit($experience_years) ||
        (int)$experience_years > 32767
    ) {
        respond(422, [
            "success" => false,
            "message" => "Experience must be a whole number between 0 and 32767."
        ]);
    }

    $experience_years = (int)$experience_years;

    // Validate availability
    $allowedAvailability = [
        "available",
        "assigned",
        "standby",
        "unavailable",
        "inactive"
    ];

    if (!in_array($availability, $allowedAvailability, true)) {
        respond(422, [
            "success" => false,
            "message" => "Please select a valid availability."
        ]);
    }

    // Validate worker status
    if (!in_array($status, ["active", "inactive"], true)) {
        respond(422, [
            "success" => false,
            "message" => "Please select a valid worker status."
        ]);
    }

    // Validate start date
    if ($start_date === "") {
        $start_date = null;
    } else {
        $date = DateTime::createFromFormat("!Y-m-d", $start_date);

        if (!$date || $date->format("Y-m-d") !== $start_date) {
            respond(422, [
                "success" => false,
                "message" => "Please enter a valid start date."
            ]);
        }
    }

    // Convert optional empty values to NULL
    $nrc_number = $nrc_number ?: null;
    $email = $email ?: null;
    $address = $address ?: null;
    $skills = $skills ?: null;
    $napsa_number = $napsa_number ?: null;
    $nhima_number = $nhima_number ?: null;
    $payment_details = $payment_details ?: null;
    $notes = $notes ?: null;

    // Preserve the existing photo
    $photo_path = $existingWorker["photo_path"];

    // Handle optional replacement photo
    $photo = $_FILES["photo"] ?? $_FILES["worker_photo"] ?? null;

    if ($photo && $photo["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($photo["error"] !== UPLOAD_ERR_OK) {
            respond(400, [
                "success" => false,
                "message" => "Photo upload failed."
            ]);
        }

        if ($photo["size"] > 2 * 1024 * 1024) {
            respond(422, [
                "success" => false,
                "message" => "Photo must not exceed 2 MB."
            ]);
        }

        if (!is_uploaded_file($photo["tmp_name"])) {
            respond(422, [
                "success" => false,
                "message" => "Invalid photo upload."
            ]);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($photo["tmp_name"]);

        $allowedTypes = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        if (!isset($allowedTypes[$mime])) {
            respond(422, [
                "success" => false,
                "message" => "Only JPG, PNG and WebP photos are allowed."
            ]);
        }

        $imageInfo = @getimagesize($photo["tmp_name"]);

        if ($imageInfo === false || $imageInfo["mime"] !== $mime) {
            respond(422, [
                "success" => false,
                "message" => "The uploaded file is not a valid image."
            ]);
        }

        $uploadDirectory = dirname(__DIR__, 2)
            . "/uploads/workers/";

        if (
            !is_dir($uploadDirectory) &&
            !mkdir($uploadDirectory, 0755, true) &&
            !is_dir($uploadDirectory)
        ) {
            respond(500, [
                "success" => false,
                "message" => "Unable to create worker photo folder."
            ]);
        }

        $filename = bin2hex(random_bytes(16))
            . "." . $allowedTypes[$mime];

        $destination = $uploadDirectory . $filename;

        if (!move_uploaded_file($photo["tmp_name"], $destination)) {
            respond(500, [
                "success" => false,
                "message" => "Unable to save worker photo."
            ]);
        }

        $newPhotoPath = $destination;
        $photo_path = "uploads/workers/" . $filename;
    }

    // Update worker
    $stmt = $conn->prepare("
        UPDATE workers SET
            full_name = :full_name,
            nrc_number = :nrc_number,
            phone = :phone,
            email = :email,
            address = :address,
            photo_path = :photo_path,
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
            updated_at = CURRENT_TIMESTAMP
        WHERE worker_id = :worker_id
    ");

    $stmt->execute([
        ":full_name" => $full_name,
        ":nrc_number" => $nrc_number,
        ":phone" => $phone,
        ":email" => $email,
        ":address" => $address,
        ":photo_path" => $photo_path,
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
        ":worker_id" => $worker_id
    ]);

    respond(200, [
        "success" => true,
        "message" => "Worker updated successfully.",
        "worker_id" => $worker_id,
        "photo_path" => $photo_path
    ]);

} catch (PDOException $e) {

    if ($newPhotoPath && file_exists($newPhotoPath)) {
        unlink($newPhotoPath);
    }

    error_log("Worker update database error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Database error while updating worker."
    ]);

} catch (Throwable $e) {

    if ($newPhotoPath && file_exists($newPhotoPath)) {
        unlink($newPhotoPath);
    }

    error_log("Worker update error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
