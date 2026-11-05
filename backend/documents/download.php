
<?php

session_start();

if (empty($_SESSION["user_id"])) {
    http_response_code(401);
    exit("Not authenticated.");
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    exit("Method not allowed.");
}

$documentId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$documentId || $documentId < 1) {
    http_response_code(400);
    exit("Invalid document ID.");
}

try {

    require_once __DIR__ . "/../config/database.php";

    $stmt = $conn->prepare("
        SELECT title, file_path, mime_type
        FROM documents
        WHERE document_id = :document_id
        LIMIT 1
    ");

    $stmt->execute([
        ":document_id" => $documentId
    ]);

    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        http_response_code(404);
        exit("Document not found.");
    }

    $projectRoot = dirname(__DIR__, 2);

    $uploadDir = realpath($projectRoot . "/uploads/documents");

    $filePath = realpath(
        $projectRoot . "/" . $document["file_path"]
    );

    // Ensure the file is inside the documents upload folder
    if (
        !$uploadDir ||
        !$filePath ||
        !str_starts_with(
            $filePath,
            $uploadDir . DIRECTORY_SEPARATOR
        ) ||
        !is_file($filePath)
    ) {
        http_response_code(404);
        exit("Document file not found.");
    }

    $safeName = preg_replace(
        '/[^A-Za-z0-9._ -]/',
        '_',
        $document["title"]
    );

    $extension = pathinfo($filePath, PATHINFO_EXTENSION);

    $downloadName = $safeName . "." . $extension;

    header("Content-Type: " . $document["mime_type"]);
    header("Content-Length: " . filesize($filePath));
    header(
        "Content-Disposition: attachment; filename=\"" .
        str_replace('"', '', $downloadName) . "\""
    );
    header("X-Content-Type-Options: nosniff");

    readfile($filePath);
    exit;

} catch (Throwable $e) {

    error_log("Document download error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to download document.");
}