
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

// Allow GET requests only
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(405, [
        "success" => false,
        "message" => "Method not allowed."
    ]);
}

try {

    require_once __DIR__ . "/../config/database.php";

    $sql = "
        SELECT
            d.document_id,
            d.owner_type,
            d.owner_id,
            d.doc_type,
            d.title,
            d.file_path,
            d.file_size,
            d.mime_type,
            d.uploaded_by,
            d.uploaded_at,

            CASE
                WHEN d.owner_type = 'customer'
                    THEN c.full_name

                WHEN d.owner_type = 'worker'
                    THEN w.full_name

                WHEN d.owner_type = 'job'
                    THEN CONCAT('Job #', j.job_id)

                ELSE 'Unknown'
            END AS owner_name

        FROM documents d

        LEFT JOIN clients c
            ON d.owner_type = 'customer'
            AND d.owner_id = c.client_id

        LEFT JOIN workers w
            ON d.owner_type = 'worker'
            AND d.owner_id = w.worker_id

        LEFT JOIN jobs j
            ON d.owner_type = 'job'
            AND d.owner_id = j.job_id

        ORDER BY d.uploaded_at DESC, d.document_id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(200, [
        "success" => true,
        "documents" => $documents
    ]);

} catch (PDOException $e) {

    error_log("Documents get error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to retrieve documents."
    ]);
}
