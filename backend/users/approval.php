
<?php

session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Please log in first."
    ]);
    exit;
}

if (
    !isset($_SESSION["role"]) ||
    strtolower($_SESSION["role"]) !== "admin"
) {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Administrator access required."
    ]);
    exit;
}

$user_id = filter_var(
    $_POST["user_id"] ?? "",
    FILTER_VALIDATE_INT
);

$decision = $_POST["decision"] ?? "";

if (!$user_id || $user_id === (int) $_SESSION["user_id"]) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid staff account ID."
    ]);
    exit;
}

if (!in_array($decision, ["approve", "reject"], true)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Choose approve or reject."
    ]);
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare(
        "SELECT user_id, approval_status
         FROM users
         WHERE user_id = :user_id
         FOR UPDATE"
    );

    $stmt->execute([":user_id" => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user["approval_status"] !== "pending") {
        $conn->rollBack();

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Pending staff registration not found."
        ]);
        exit;
    }

    if ($decision === "approve") {
        $sql = "UPDATE users
                SET approval_status = 'approved',
                    status = 'active'
                WHERE user_id = :user_id
                  AND approval_status = 'pending'";

        $message = "Staff account approved successfully.";
    } else {
        $sql = "UPDATE users
                SET approval_status = 'rejected',
                    status = 'inactive'
                WHERE user_id = :user_id
                  AND approval_status = 'pending'";

        $message = "Staff registration rejected.";
    }

    $update = $conn->prepare($sql);
    $update->execute([":user_id" => $user_id]);

    if ($update->rowCount() !== 1) {
        $conn->rollBack();

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "The registration could not be updated."
        ]);
        exit;
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => $message
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("KATUBA CRM approval error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to process this registration."
    ]);
}
?>
