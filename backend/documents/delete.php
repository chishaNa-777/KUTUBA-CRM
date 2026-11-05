
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function respond(int $status, array $data): void
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

// POST only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Method not allowed."
    ]);
}

// Read JSON request
$data = json_decode(file_get_contents("php://input"), true);

$documentId = filter_var(
    $data["document_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$documentId || $documentId < 1) {
    respond(400, [
        "success" => false,
        "message" => "Invalid document ID."
    ]);
}

try {

    require_once __DIR__ . "/../config/database.php";

    // Find the document
    $stmt = $conn->prepare("
        SELECT document_id, file_path
        FROM documents
        WHERE document_id = :document_id
        LIMIT 1
    ");

    $stmt->execute([
        ":document_id" => $documentId
    ]);

    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        respond(404, [
            "success" => false,
            "message" => "Document not found."
        ]);
    }

    // Resolve the upload directory and file safely
    $projectRoot = dirname(__DIR__, 2);

    $uploadDir = realpath(
        $projectRoot . "/uploads/documents"
    );

    $filePath = realpath(
        $projectRoot . "/" . $document["file_path"]
    );

    $safeFilePath = (
        $uploadDir &&
        $filePath &&
        str_starts_with(
            $filePath,
            $uploadDir . DIRECTORY_SEPARATOR
        ) &&
        is_file($filePath)
    ) ? $filePath : null;

    // Delete the database record
    $delete = $conn->prepare("
        DELETE FROM documents
        WHERE document_id = :document_id
    ");

    $delete->execute([
        ":document_id" => $documentId
    ]);

    // Delete the uploaded file if it exists
    if ($safeFilePath && !unlink($safeFilePath)) {
        error_log(
            "Could not delete uploaded document file: " . $safeFilePath
        );
    }

    respond(200, [
        "success" => true,
        "message" => "Document deleted successfully."
    ]);

} catch (Throwable $e) {

    error_log("Document delete error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to delete the document."
    ]);
}
