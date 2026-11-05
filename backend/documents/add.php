
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

// Check required fields
$ownerType = trim($_POST["owner_type"] ?? "");
$ownerId = filter_var($_POST["owner_id"] ?? null, FILTER_VALIDATE_INT);
$docType = trim($_POST["doc_type"] ?? "");
$title = trim($_POST["title"] ?? "");

if (
    !in_array($ownerType, ["client", "worker", "job"], true) ||
    !$ownerId ||
    $ownerId < 1 ||
    $docType === "" ||
    strlen($docType) > 20 ||
    $title === "" ||
    strlen($title) > 150
) {
    respond(400, [
        "success" => false,
        "message" => "Please provide valid document details."
    ]);
}

// Check uploaded file
if (!isset($_FILES["document_file"])) {
    respond(400, [
        "success" => false,
        "message" => "Please select a document to upload."
    ]);
}

$file = $_FILES["document_file"];

if ($file["error"] !== UPLOAD_ERR_OK) {
    respond(400, [
        "success" => false,
        "message" => "The file upload failed."
    ]);
}

// Maximum file size: 10 MB
$maxSize = 10 * 1024 * 1024;

if ($file["size"] <= 0 || $file["size"] > $maxSize) {
    respond(400, [
        "success" => false,
        "message" => "The file must not exceed 10 MB."
    ]);
}

// Allowed file extensions and MIME types
$allowedTypes = [
    "pdf" => ["application/pdf"],
    "jpg" => ["image/jpeg"],
    "jpeg" => ["image/jpeg"],
    "png" => ["image/png"],
    "doc" => ["application/msword"],
    "docx" => [
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
    ],
    "xls" => ["application/vnd.ms-excel"],
    "xlsx" => [
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    ]
];

$originalName = basename($file["name"]);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!isset($allowedTypes[$extension])) {
    respond(400, [
        "success" => false,
        "message" => "This file type is not allowed."
    ]);
}

try {

    require_once __DIR__ . "/../config/database.php";

    // Verify actual file MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file["tmp_name"]);

    if (!in_array($mimeType, $allowedTypes[$extension], true)) {
        respond(400, [
            "success" => false,
            "message" => "The uploaded file type is invalid."
        ]);
    }

    // Verify that the selected owner exists
    $ownerTables = [
        "client" => ["clients", "client_id"],
        "worker" => ["workers", "worker_id"],
        "job" => ["jobs", "job_id"]
    ];

    [$table, $idColumn] = $ownerTables[$ownerType];

    $check = $conn->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE {$idColumn} = :owner_id"
    );

    $check->execute([
        ":owner_id" => $ownerId
    ]);

    if ((int) $check->fetchColumn() === 0) {
        respond(404, [
            "success" => false,
            "message" => "The selected customer, worker or job was not found."
        ]);
    }

    // Create upload directory if it does not exist
    $uploadDir = dirname(__DIR__, 2) . "/uploads/documents";

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            respond(500, [
                "success" => false,
                "message" => "Unable to create the document upload folder."
            ]);
        }
    }

    // Generate a unique safe filename
    $newFileName = bin2hex(random_bytes(16)) . "." . $extension;

    $destination = $uploadDir . "/" . $newFileName;

    $relativePath = "uploads/documents/" . $newFileName;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        respond(500, [
            "success" => false,
            "message" => "Unable to save the uploaded document."
        ]);
    }

    // Save document details in the database
    try {

        $sql = "
            INSERT INTO documents (
                owner_type,
                owner_id,
                doc_type,
                title,
                file_path,
                file_size,
                mime_type,
                uploaded_by,
                uploaded_at
            )
            VALUES (
                :owner_type,
                :owner_id,
                :doc_type,
                :title,
                :file_path,
                :file_size,
                :mime_type,
                :uploaded_by,
                CURRENT_TIMESTAMP
            )
            RETURNING document_id
        ";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            ":owner_type" => $ownerType,
            ":owner_id" => $ownerId,
            ":doc_type" => $docType,
            ":title" => $title,
            ":file_path" => $relativePath,
            ":file_size" => $file["size"],
            ":mime_type" => $mimeType,
            ":uploaded_by" => $_SESSION["user_id"]
        ]);

        $documentId = $stmt->fetchColumn();

    } catch (Throwable $e) {

        // Remove uploaded file if database saving fails
        if (file_exists($destination)) {
            unlink($destination);
        }

        throw $e;
    }

    respond(201, [
        "success" => true,
        "message" => "Document uploaded successfully.",
        "document_id" => $documentId
    ]);

} catch (Throwable $e) {

    error_log("Document upload error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Document upload failed.",
        "debug_error" => $e->getMessage()
    ]);
}
