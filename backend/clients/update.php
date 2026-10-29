
<?php

require_once "../config/database.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

// Accept the ID sent by the existing edit form.
$id = filter_var(
    $_POST["id"] ?? $_POST["client_id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    echo json_encode([
        "success" => false,
        "message" => "A valid customer ID is required."
    ]);
    exit;
}

// Support the existing form fields.
$companyName = trim($_POST["company_name"] ?? "");
$contactPerson = trim($_POST["contact_person"] ?? "");
$fullName = trim($_POST["full_name"] ?? "");

if ($fullName === "") {
    if ($contactPerson !== "") {
        $fullName = $contactPerson;
    } else {
        $fullName = $companyName;
    }
}

$phone = trim($_POST["phone"] ?? "");
$email = trim($_POST["email"] ?? "");
$address = trim($_POST["address"] ?? "");
$status = trim($_POST["status"] ?? "Active");

if ($fullName === "") {
    echo json_encode([
        "success" => false,
        "message" => "Customer name is required."
    ]);
    exit;
}

if (!in_array($status, ["Active", "Inactive"], true)) {
    echo json_encode([
        "success" => false,
        "message" => "Please select a valid customer status."
    ]);
    exit;
}

try {
    // Use the real primary key: client_id, not id.
    $check = $conn->prepare(
        "SELECT client_id FROM clients WHERE client_id = :id"
    );

    $check->execute([":id" => $id]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            "success" => false,
            "message" => "Customer not found."
        ]);
        exit;
    }

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
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to update customer details. Please check the database configuration and customer fields."
    ]);
}
