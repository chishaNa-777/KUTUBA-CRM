<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method. Please submit the form."
    ]);

    exit;
}

try {
    // 1. Get and validate worker ID
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

    // 2. Get form fields
    $full_name = trim($_POST["full_name"] ?? "");
    $nrc_number = trim($_POST["nrc_number"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $worker_role = trim($_POST["worker_role"] ?? "");
    $experience_years = $_POST["experience_years"] ?? "";
    $skills = trim($_POST["skills"] ?? "");
    $availability = trim($_POST["availability"] ?? "");
    $napsa_number = trim($_POST["napsa_number"] ?? "");
    $nhima_number = trim($_POST["nhima_number"] ?? "");
    $payment_method = trim($_POST["payment_method"] ?? "");
    $payment_details = trim($_POST["payment_details"] ?? "");
    $start_date = trim($_POST["start_date"] ?? "");
    $status = trim($_POST["status"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    // 3. Validate required fields
    if (
        $full_name === "" ||
        $phone === "" ||
        $worker_role === ""
    ) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Full name, phone number and worker role are required."
        ]);

        exit;
    }

    if (!in_array($status, ["Active", "Inactive"], true)) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Please select a valid worker status."
        ]);

        exit;
    }

    // 4. Validate email
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

    // 5. Validate experience
    if (
        $experience_years !== "" &&
        !ctype_digit((string) $experience_years)
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

    // 6. Convert empty optional values to NULL
    $nrc_number = $nrc_number === "" ? null : $nrc_number;
    $email = $email === "" ? null : $email;
    $address = $address === "" ? null : $address;
    $skills = $skills === "" ? null : $skills;
    $availability = $availability === "" ? null : $availability;
    $napsa_number = $napsa_number === "" ? null : $napsa_number;
    $nhima_number = $nhima_number === "" ? null : $nhima_number;
    $payment_method = $payment_method === "" ? null : $payment_method;
    $payment_details = $payment_details === "" ? null : $payment_details;
    $notes = $notes === "" ? null : $notes;

    // 7. Validate optional start date
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

    // 8. Find the worker and retrieve the existing photo path
    $check = $conn->prepare(
        "SELECT worker_id, photo_path
         FROM workers
         WHERE worker_id = :worker_id"
    );

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

    // Keep the existing photo unless a new photo is uploaded.
    $photo_path = $existingWorker["photo_path"] ?? null;

    // 9. Process an optional photo upload
    if (isset($_FILES["photo"])) {
        $photo = $_FILES["photo"];

        if ($photo["error"] !== UPLOAD_ERR_NO_FILE) {

            if ($photo["error"] !== UPLOAD_ERR_OK) {
                http_response_code(400);

                echo json_encode([
                    "success" => false,
                    "message" => "The photo upload failed. Please try again."
                ]);

                exit;
            }

            // Maximum file size: 2 MB
            if ($photo["size"] > 2 * 1024 * 1024) {
                http_response_code(400);

                echo json_encode([
                    "success" => false,
                    "message" => "The photo must not exceed 2 MB."
                ]);

                exit;
            }

            if (!is_uploaded_file($photo["tmp_name"])) {
                http_response_code(400);

                echo json_encode([
                    "success" => false,
                    "message" => "Invalid photo upload."
                ]);

                exit;
            }

            // Check the actual file type instead of trusting its filename.
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($photo["tmp_name"]);

            $allowedTypes = [
                "image/jpeg" => "jpg",
                "image/png"  => "png",
                "image/webp" => "webp"
            ];

            if (!isset($allowedTypes[$mimeType])) {
                http_response_code(400);

                echo json_encode([
                    "success" => false,
                    "message" => "Only JPG, PNG and WebP photos are allowed."
                ]);

                exit;
            }

            // Confirm the uploaded file contains valid image data.
            $imageInfo = @getimagesize($photo["tmp_name"]);

            if (
                $imageInfo === false ||
                $imageInfo["mime"] !== $mimeType
            ) {
                http_response_code(400);

                echo json_encode([
                    "success" => false,
                    "message" => "The uploaded file is not a valid image."
                ]);

                exit;
            }

            // Project folder: C:\xampp\htdocs\KUTUBA-CRM
            $projectRoot = dirname(__DIR__, 2);

            // Store worker photos in the project's uploads/workers folder.
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

            // Generate a unique filename.
            $extension = $allowedTypes[$mimeType];

            $filename = bin2hex(random_bytes(16))
                . "."
                . $extension;

            $destination = $uploadDirectory
                . DIRECTORY_SEPARATOR
                . $filename;

            if (!move_uploaded_file($photo["tmp_name"], $destination)) {
                throw new RuntimeException(
                    "Unable to save the new worker photo."
                );
            }

            // Store a relative path in the database.
            $photo_path = "uploads/workers/" . $filename;
        }
    }

    // 10. Update worker details and photo path
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

    // 11. Return success response
    echo json_encode([
        "success" => true,
        "message" => "Worker updated successfully.",
        "worker_id" => $worker_id,
        "photo_path" => $photo_path
    ]);

} catch (PDOException $e) {
    error_log("Worker update database error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database error while updating worker. Please check the server error log."
    ]);

} catch (Throwable $e) {
    error_log("Worker update error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "An unexpected error occurred while updating the worker."
    ]);
}
?>