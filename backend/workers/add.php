
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

// Check login
if (empty($_SESSION["user_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Not authenticated."
    ]);
}

// Check HR permissions
$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["admin", "hr_officer"], true)) {
    respond(403, [
        "success" => false,
        "message" => "Access denied. HR privileges required."
    ]);
}

// Accept POST only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Method not allowed."
    ]);
}

// Connect to PostgreSQL
require_once __DIR__ . "/../config/database.php";

// Collect form information
$full_name = trim($_POST["full_name"] ?? "");
$nrc_number = trim($_POST["nrc_number"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$email = trim($_POST["email"] ?? "");
$address = trim($_POST["address"] ?? "");
$worker_role = strtolower(trim($_POST["worker_role"] ?? "maid"));
$experience_years = $_POST["experience_years"] ?? "0";
$skills = trim($_POST["skills"] ?? "");
$availability = strtolower(trim($_POST["availability"] ?? "standby"));
$napsa_number = trim($_POST["napsa_number"] ?? "");
$nhima_number = trim($_POST["nhima_number"] ?? "");
$payment_method = strtolower(trim($_POST["payment_method"] ?? "cash"));
$payment_details = trim($_POST["payment_details"] ?? "");
$start_date = trim($_POST["start_date"] ?? "");
$status = strtolower(trim($_POST["status"] ?? "active"));
$notes = trim($_POST["notes"] ?? "");

// Normalize payment method
$payment_method = str_replace(" ", "_", $payment_method);

// Validate required fields
if ($full_name === "" || $phone === "" || $worker_role === "") {
    respond(422, [
        "success" => false,
        "message" => "Full name, phone number and worker role are required."
    ]);
}

// Validate field lengths
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
    "Payment details" => [$payment_details, 255],
    "Status" => [$status, 10]
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
    filter_var($experience_years, FILTER_VALIDATE_INT) === false ||
    (int)$experience_years < 0 ||
    (int)$experience_years > 32767
) {
    respond(422, [
        "success" => false,
        "message" => "Experience years must be a valid non-negative number."
    ]);
}

$experience_years = (int)$experience_years;

// Validate start date
if ($start_date !== "") {
    $date = DateTime::createFromFormat("!Y-m-d", $start_date);

    if (!$date || $date->format("Y-m-d") !== $start_date) {
        respond(422, [
            "success" => false,
            "message" => "Please enter a valid start date."
        ]);
    }
} else {
    $start_date = null;
}

// Normalize empty select values
if ($availability === "") {
    $availability = "standby";
}

if ($payment_method === "") {
    $payment_method = "cash";
}

if ($status === "") {
    $status = "active";
}

// Handle optional worker photo
$photo_path = null;
$uploadedFile = null;

if (isset($_FILES["worker_photo"]) &&
    $_FILES["worker_photo"]["error"] !== UPLOAD_ERR_NO_FILE) {

    $photo = $_FILES["worker_photo"];

    if ($photo["error"] !== UPLOAD_ERR_OK) {
        respond(400, [
            "success" => false,
            "message" => "Photo upload failed. Please try again."
        ]);
    }

    if ($photo["size"] > 2 * 1024 * 1024) {
        respond(422, [
            "success" => false,
            "message" => "Worker photo must not exceed 2 MB."
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

    // Store photos in the project uploads folder
    $uploadDirectory = dirname(__DIR__, 2) . "/uploads/workers/";

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0755, true)) {
            respond(500, [
                "success" => false,
                "message" => "Unable to create worker photo folder."
            ]);
        }
    }

    $filename = bin2hex(random_bytes(16)) . "." . $allowedTypes[$mime];

    $destination = $uploadDirectory . $filename;

    if (!move_uploaded_file($photo["tmp_name"], $destination)) {
        respond(500, [
            "success" => false,
            "message" => "Unable to save worker photo."
        ]);
    }

    $photo_path = "uploads/workers/" . $filename;
    $uploadedFile = $destination;
}

// Save worker details
try {

    $stmt = $conn->prepare("
        INSERT INTO workers (
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
        )
        RETURNING worker_id
    ");

    $stmt->execute([
        ":full_name" => $full_name,
        ":nrc_number" => $nrc_number ?: null,
        ":phone" => $phone,
        ":email" => $email ?: null,
        ":address" => $address ?: null,
        ":photo_path" => $photo_path,
        ":worker_role" => $worker_role,
        ":experience_years" => $experience_years,
        ":skills" => $skills ?: null,
        ":availability" => $availability,
        ":napsa_number" => $napsa_number ?: null,
        ":nhima_number" => $nhima_number ?: null,
        ":payment_method" => $payment_method,
        ":payment_details" => $payment_details ?: null,
        ":start_date" => $start_date,
        ":status" => $status,
        ":notes" => $notes ?: null
    ]);

    $worker_id = $stmt->fetchColumn();

    respond(201, [
        "success" => true,
        "message" => "Worker added successfully.",
        "worker_id" => $worker_id
    ]);

} catch (PDOException $e) {

    // Remove newly uploaded photo if database saving fails
    if ($uploadedFile && file_exists($uploadedFile)) {
        unlink($uploadedFile);
    }

    error_log("Workers add.php error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to add worker. Please check the submitted information."
    ]);
}
