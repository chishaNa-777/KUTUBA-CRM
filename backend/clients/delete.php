<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// 1. Require the user to be logged in.
if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);
    exit;
}

// 2. Only allow POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

// 3. Connect to the database.
require_once __DIR__ . "/../config/database.php";

// 4. Accept the ID sent by the Customers page.
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

try {

    // 5. Confirm that this exact customer exists.
    $check = $conn->prepare(
        "SELECT client_id FROM clients WHERE client_id = :id"
    );

    $check->execute([
        ":id" => $id
    ]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Customer not found. No records were deleted."
        ]);
        exit;
    }

    // 6. Delete only the selected customer.
    $stmt = $conn->prepare(
        "DELETE FROM clients WHERE client_id = :id"
    );

    $stmt->execute([
        ":id" => $id
    ]);

    if ($stmt->rowCount() === 1) {
        echo json_encode([
            "success" => true,
            "message" => "Customer deleted successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Customer was not deleted. Please try again."
        ]);
    }

} catch (PDOException $e) {

    error_log("Customer deletion error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete this customer. Check whether another record depends on this customer."
    ]);
}