
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

// Accept the ID sent by the Customers page.
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
    // Confirm that this exact customer exists first.
    $check = $conn->prepare(
        "SELECT client_id FROM clients WHERE client_id = :id"
    );
    $check->execute([":id" => $id]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Customer not found. No records were deleted."
        ]);
        exit;
    }

    // Delete only the selected customer.
    $stmt = $conn->prepare(
        "DELETE FROM clients WHERE client_id = :id"
    );
    $stmt->execute([":id" => $id]);

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
    http_response_code(500);

    // Keep database details private.
    echo json_encode([
        "success" => false,
        "message" => "Unable to delete this customer. Check whether another record depends on this customer."
    ]);
}
