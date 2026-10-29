
<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

// Collect worker details
$full_name = trim($_POST["full_name"] ?? "");
$nrc_number = trim($_POST["nrc_number"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$email = trim($_POST["email"] ?? "");
$address = trim($_POST["address"] ?? "");
$worker_role = trim($_POST["worker_role"] ?? "");
$experience_years = trim($_POST["experience_years"] ?? "");
$skills = trim($_POST["skills"] ?? "");
$availability = trim($_POST["availability"] ?? "");
$napsa_number = trim($_POST["napsa_number"] ?? "");
$nhima_number = trim($_POST["nhima_number"] ?? "");
$payment_method = trim($_POST["payment_method"] ?? "");
$payment_details = trim($_POST["payment_details"] ?? "");
$start_date = trim($_POST["start_date"] ?? "");
$status = trim($_POST["status"] ?? "Active");
$notes = trim($_POST["notes"] ?? "");

// Validate required fields
if ($full_name === "" || $phone === "" || $worker_role === "") {
    echo json_encode([
        "success" => false,
        "message" => "Full name, phone number and worker role are required."
    ]);
    exit;
}

// Validate email
if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "Please enter a valid email address."
    ]);
    exit;
}

// Validate experience
if (
    $experience_years !== "" &&
    (!ctype_digit($experience_years) || (int)$experience_years < 0)
) {
    echo json_encode([
        "success" => false,
        "message" => "Experience must be a non-negative whole number."
    ]);
    exit;
}

// Validate status
if (!in_array($status, ["Active", "Inactive"], true)) {
    echo json_encode([
        "success" => false,
        "message" => "Please select a valid worker status."
    ]);
    exit;
}

// Validate date
if ($start_date !== "") {
    $date = DateTime::createFromFormat("!Y-m-d", $start_date);

    if (!$date || $date->format("Y-m-d") !== $start_date) {
        echo json_encode([
            "success" => false,
            "message" => "Please enter a valid start date."
        ]);
        exit;
    }
}

// Photo upload settings
$photo_path = null;
$saved_photo_file = null;

$upload_directory = __DIR__ . "/../../uploads/workers/";
$allowed_mime_types = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp"
];

try {
    // Process a photo only when one was selected
    if (
        isset($_FILES["worker_photo"]) &&
        $_FILES["worker_photo"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $photo = $_FILES["worker_photo"];

        if ($photo["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                "Photo upload failed. Please select the photo again."
            );
        }

        if ($photo["size"] > 2 * 1024 * 1024) {
            throw new RuntimeException(
                "The photo must not exceed 2 MB."
            );
        }

        if (!is_uploaded_file($photo["tmp_name"])) {
            throw new RuntimeException("Invalid photo upload.");
        }

        // Check the actual image type, not just its filename
        $image_info = @getimagesize($photo["tmp_name"]);

        if ($image_info === false) {
            throw new RuntimeException(
                "The selected file is not a valid image."
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($photo["tmp_name"]);

        if (!isset($allowed_mime_types[$mime_type])) {
            throw new RuntimeException(
                "Only JPG, PNG and WebP photos are allowed."
            );
        }

        // Create the upload folder if it does not exist
        if (
            !is_dir($upload_directory) &&
            !mkdir($upload_directory, 0755, true) &&
            !is_dir($upload_directory)
        ) {
            throw new RuntimeException(
                "Unable to create the worker photo folder."
            );
        }

        // Generate a random filename to avoid overwriting photos
        $filename = bin2hex(random_bytes(16))
            . "." . $allowed_mime_types[$mime_type];

        $saved_photo_file = $upload_directory . $filename;

        if (!move_uploaded_file($photo["tmp_name"], $saved_photo_file)) {
            $saved_photo_file = null;

            throw new RuntimeException(
                "Unable to save the worker photo."
            );
        }

        // Store a relative path in the database
        $photo_path = "uploads/workers/" . $filename;
    }

    // Save worker details and photo path
    $sql = "INSERT INTO workers (
        full_name,
        nrc_number,
        phone,
        email,
        address,
        photo_path,
        worker_role,
        experience_years,
        skills,
        availability,
        napsa_number,
        nhima_number,
        payment_method,
        payment_details,
        start_date,
        status,
        notes
    ) VALUES (
        :full_name,
        :nrc_number,
        :phone,
        :email,
        :address,
        :photo_path,
        :worker_role,
        :experience_years,
        :skills,
        :availability,
        :napsa_number,
        :nhima_number,
        :payment_method,
        :payment_details,
        :start_date,
        :status,
        :notes
    )";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":full_name" => $full_name,
        ":nrc_number" => $nrc_number ?: null,
        ":phone" => $phone,
        ":email" => $email ?: null,
        ":address" => $address ?: null,
        ":photo_path" => $photo_path,
        ":worker_role" => $worker_role,
        ":experience_years" => $experience_years === ""
            ? null : (int)$experience_years,
        ":skills" => $skills ?: null,
        ":availability" => $availability ?: null,
        ":napsa_number" => $napsa_number ?: null,
        ":nhima_number" => $nhima_number ?: null,
        ":payment_method" => $payment_method ?: null,
        ":payment_details" => $payment_details ?: null,
        ":start_date" => $start_date ?: null,
        ":status" => $status,
        ":notes" => $notes ?: null
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Worker added successfully.",
        "worker_id" => (int)$conn->lastInsertId(),
        "photo_path" => $photo_path
    ]);

} catch (RuntimeException $e) {
    // Remove the uploaded file if validation or saving fails
    if ($saved_photo_file && is_file($saved_photo_file)) {
        unlink($saved_photo_file);
    }

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

} catch (Throwable $e) {
    // Avoid leaving an unused photo if the database insert fails
    if ($saved_photo_file && is_file($saved_photo_file)) {
        unlink($saved_photo_file);
    }

    error_log("Worker add error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to add worker. Please check the entered details and database requirements."
    ]);
}
?>
