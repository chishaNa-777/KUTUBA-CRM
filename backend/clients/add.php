
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

require_once __DIR__ . "/../config/database.php";

$company_name = trim($_POST["company_name"] ?? "");
$contact_person = trim($_POST["contact_person"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$email = trim($_POST["email"] ?? "");
$address = trim($_POST["address"] ?? "");
$status = strtolower(trim($_POST["status"] ?? "active"));

if ($company_name === "" || $contact_person === "" || $phone === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Company name, contact person, and phone number are required."
    ]);

    exit;
}

if (!in_array($status, ["active", "inactive"], true)) {
    $status = "active";
}

/*
 * Preserve the existing database mapping.
 * The clients table uses full_name rather than
 * separate company_name and contact_person fields.
 */
$full_name = $company_name . " - " . $contact_person;

try {

    $sql = "INSERT INTO clients
            (full_name, phone, email, address, status)
            VALUES
            (:full_name, :phone, :email, :address, :status)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":full_name" => $full_name,
        ":phone" => $phone,
        ":email" => ($email === "" ? null : $email),
        ":address" => ($address === "" ? null : $address),
        ":status" => $status
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Customer added successfully.",
        "id" => $conn->lastInsertId()
    ]);

} catch (Throwable $e) {

    error_log("Add customer error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to save customer. Please check the submitted details."
    ]);
}
