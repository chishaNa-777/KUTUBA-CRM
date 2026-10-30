
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Require an authenticated session.
if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);
    exit;
}

// Only allow POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

// Connect to PostgreSQL.
require_once __DIR__ . "/../config/database.php";

$saved_photo_file = null;

function normalizeWorkerRole($value) {
    $value = strtolower(trim($value));

    $roles = [
        "maid" => "maid",
        "gardener" => "gardener",
        "daycare" => "daycare",
        "supervisor" => "supervisor",
        "other" => "other",
        "it specialist" => "other"
    ];

    return $roles[$value] ?? "other";
}

function normalizeAvailability($value) {
    $value = strtolower(trim($value));

    $values = [
        "available" => "available",
        "assigned" => "assigned",
        "standby" => "standby",
        "inactive" => "inactive"
    ];

    return $values[$value] ?? "";
}

function normalizePaymentMethod($value) {
    $value = strtolower(trim($value));

    $values = [
        "cash" => "cash",
        "airtel money" => "airtel_money",
        "airtel_money" => "airtel_money",
        "mtn money" => "mtn_money",
        "mtn_money" => "mtn_money",
        "bank" => "bank",
        "bank transfer" => "bank"
    ];

    if ($value === "") {
        return null;
    }

    return $values[$value] ?? "";
}

try {

    // 1. Validate worker ID.
    $worker_id = filter_var(
        $_POST["worker_id"] ?? $_POST["id"] ?? "",
        FILTER_VALIDATE_INT
    );

    if ($worker_id === false || $worker_id === null || $worker_id <= 0) {
        throw new InvalidArgumentException(
            "A valid worker ID is required."
        );
    }

    // 2. Retrieve existing worker.
    $check = $conn->prepare("
        SELECT worker_id, photo_path, availability, status
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

    // 3. Collect form values.
    $full_name = trim($_POST["full_name"] ?? "");
    $nrc_number = trim($_POST["nrc_number"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $original_role = trim($_POST["worker_role"] ?? "");
    $experience_years = trim((string)($_POST["experience_years"] ?? ""));
    $skills = trim($_POST["skills"] ?? "");
    $availability_input = trim($_POST["availability"] ?? "");
    $napsa_number = trim($_POST["napsa_number"] ?? "");
    $nhima_number = trim($_POST["nhima_number"] ?? "");
    $payment_input = trim($_POST["payment_method"] ?? "");
    $payment_details = trim($_POST["payment_details"] ?? "");
    $start_date = trim($_POST["start_date"] ?? "");
    $status_input = trim($_POST["status"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    // 4. Validate required fields.
    if ($full_name === "" || $phone === "" || $original_role === "") {
        throw new InvalidArgumentException(
            "Full name, phone number and worker role are required."
        );
    }

    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(
            "Please enter a valid email address."
        );
    }

    if (
        $experience_years !== "" &&
        (
            !ctype_digit($experience_years) ||
            (int)$experience_years > 255
        )
    ) {
        throw new InvalidArgumentException(
            "Experience must be a whole number between 0 and 255."
        );
    }

    $experience_years = $experience_years === ""
        ? null
        : (int)$experience_years;

    // 5. Normalize worker role.
    $worker_role = normalizeWorkerRole($original_role);

    $role_key = strtolower($original_role);

    if (
        $worker_role === "other" &&
        $role_key !== "other" &&
        stripos($skills, $original_role) === false
    ) {
        $skills = $skills === ""
            ? $original_role
            : $skills . ", " . $original_role;
    }

    // 6. Normalize availability and status.
    $availability = $availability_input === ""
        ? $existingWorker["availability"]
        : normalizeAvailability($availability_input);

    if ($availability === "") {
        throw new InvalidArgumentException(
            "Please select a valid worker availability."
        );
    }

    $status = strtolower(
        $status_input !== ""
            ? $status_input
            : $existingWorker["status"]
    );

    if (!in_array($status, ["active", "inactive"], true)) {
        throw new InvalidArgumentException(
            "Please select a valid worker status."
        );
    }

    // 7. Normalize payment method.
    $payment_method = normalizePaymentMethod($payment_input);

    if ($payment_method === "") {
        throw new InvalidArgumentException(
            "Please select a valid payment method."
        );
    }

    // 8. Validate start date.
    if ($start_date === "") {
        $start_date = null;
    } else {
        $date = DateTime::createFromFormat("!Y-m-d", $start_date);

        if (!$date || $date->format("Y-m-d") !== $start_date) {
            throw new InvalidArgumentException(
                "Please enter a valid start date."
            );
        }
    }

    // 9. Keep existing photo unless a new one is uploaded.
    $photo_path = $existingWorker["photo_path"];

    $photo = $_FILES["photo"] ?? $_FILES["worker_photo"] ?? null;

    if ($photo && $photo["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($photo["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Photo upload failed.");
        }

        if ($photo["size"] > 2 * 1024 * 1024) {
            throw new RuntimeException(
                "The photo must not exceed 2 MB."
            );
        }

        if (!is_uploaded_file($photo["tmp_name"])) {
            throw new RuntimeException("Invalid photo upload.");
        }

        $imageInfo = @getimagesize($photo["tmp_name"]);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($photo["tmp_name"]);

        $allowedTypes = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        if (
            $imageInfo === false ||
            !isset($allowedTypes[$mimeType]) ||
            $imageInfo["mime"] !== $mimeType
        ) {
            throw new RuntimeException(
                "Only valid JPG, PNG and WebP photos are allowed."
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
                "Unable to create the worker photo folder."
            );
        }

        $filename = bin2hex(random_bytes(16))
            . "." . $allowedTypes[$mimeType];

        $saved_photo_file = $uploadDirectory
            . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file(
            $photo["tmp_name"],
            $saved_photo_file
        )) {
            $saved_photo_file = null;

            throw new RuntimeException(
                "Unable to save the new worker photo."
            );
        }

        $photo_path = "uploads/workers/" . $filename;
    }

    // 10. Update worker.
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
        ":nrc_number" => $nrc_number ?: null,
        ":phone" => $phone,
        ":email" => $email ?: null,
        ":address" => $address ?: null,
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
        ":notes" => $notes ?: null,
        ":photo_path" => $photo_path,
        ":worker_id" => $worker_id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Worker updated successfully.",
        "worker_id" => $worker_id,
        "photo_path" => $photo_path
    ]);

} catch (InvalidArgumentException $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

} catch (RuntimeException $e) {

    if ($saved_photo_file && is_file($saved_photo_file)) {
        unlink($saved_photo_file);
    }

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

} catch (Throwable $e) {

    if ($saved_photo_file && is_file($saved_photo_file)) {
        unlink($saved_photo_file);
    }

    error_log("Worker update error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to update worker. Please check the server error log."
    ]);
}
