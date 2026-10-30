
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// 1. Require login.
if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);
    exit;
}

// 2. Allow POST requests only.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

// 3. Connect to PostgreSQL.
require_once __DIR__ . "/../config/database.php";

// 4. Accept the ID sent by the existing edit form.
$id = filter_var(
    $_POST["id"] ?? $_POST["client_id"] ?? "",
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id < 1) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid customer ID is required."
    ]);
    exit;
}

// 5. Support the existing form fields.
$companyName = trim($_POST["company_name"] ?? "");
$contactPerson = trim($_POST["contact_person"] ?? "");
$fullName = trim($_POST["full_name"] ?? "");

if ($fullName === "") {
    $fullName = $contactPerson !== "" ? $contactPerson : $companyName;
}

$phone = trim($_POST["phone"] ?? "");
$email = trim($_POST["email"] ?? "");
$address = trim($_POST["address"] ?? "");
$status = trim($_POST["status"] ?? "Active");

if ($fullName === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Customer name is required."
    ]);
    exit;
}

if (!in_array($status, ["Active", "Inactive"], true)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Please select a valid customer status."
    ]);
    exit;
}

try {
    // 6. Confirm that the customer exists.
    $check = $conn->prepare(
        "SELECT client_id FROM clients WHERE client_id = :id"
    );

    $check->execute([":id" => $id]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Customer not found."
        ]);
        exit;
    }

    // 7. Update the selected customer.
    $sql = "
        UPDATE clients
        SET
            full_name = :full_name,
            phone = :phone,
            email = :email,
            address = :address,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE client_id = :id
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":full_name" => $fullName,
        ":phone" => $phone,
        ":email" => $email,
        ":address" => $address,
        ":status" => $status,
        ":id" => $id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Customer details updated successfully."
    ]);

} catch (PDOException $e) {
    error_log("Customer update error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to update customer details."
    ]);
}
