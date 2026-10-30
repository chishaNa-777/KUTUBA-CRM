
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

// 1. Require an authenticated session.
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

// 3. Connect to PostgreSQL.
require_once __DIR__ . "/../config/database.php";

try {

    // Validate worker ID.
    $worker_id = filter_var(
        $_POST["worker_id"] ?? $_POST["id"] ?? "",
        FILTER_VALIDATE_INT
    );

    if ($worker_id === false || $worker_id === null || $worker_id <= 0) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "A valid worker ID is required."
        ]);
        exit;
    }

    // Check whether worker exists.
    $check = $conn->prepare("
        SELECT worker_id, full_name, status
        FROM workers
        WHERE worker_id = :worker_id
    ");

    $check->execute([
        ":worker_id" => $worker_id
    ]);

    $worker = $check->fetch(PDO::FETCH_ASSOC);

    if (!$worker) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Worker not found. The worker may already have been deleted."
        ]);
        exit;
    }

    // Start transaction.
    $conn->beginTransaction();

    // Find foreign keys referencing workers.worker_id.
    $foreignKeys = $conn->query("
        SELECT
            child_ns.nspname AS child_schema,
            child.relname AS child_table,
            child_column.attname AS child_column

        FROM pg_constraint fk

        JOIN pg_class parent
            ON parent.oid = fk.confrelid

        JOIN pg_class child
            ON child.oid = fk.conrelid

        JOIN pg_namespace child_ns
            ON child_ns.oid = child.relnamespace

        JOIN LATERAL
            unnest(fk.conkey) WITH ORDINALITY
            AS child_key(attnum, position)
            ON TRUE

        JOIN LATERAL
            unnest(fk.confkey) WITH ORDINALITY
            AS parent_key(attnum, position)
            ON parent_key.position = child_key.position

        JOIN pg_attribute parent_column
            ON parent_column.attrelid = parent.oid
            AND parent_column.attnum = parent_key.attnum

        JOIN pg_attribute child_column
            ON child_column.attrelid = child.oid
            AND child_column.attnum = child_key.attnum

        WHERE fk.contype = 'f'
          AND parent.oid = 'workers'::regclass
          AND parent_column.attname = 'worker_id'
    ");

    $references = $foreignKeys->fetchAll(PDO::FETCH_ASSOC);

    // Check whether related records exist.
    $hasRelatedRecords = false;
    $relatedTables = [];

    foreach ($references as $reference) {

        $schema = str_replace(
            '"',
            '""',
            $reference["child_schema"]
        );

        $table = str_replace(
            '"',
            '""',
            $reference["child_table"]
        );

        $column = str_replace(
            '"',
            '""',
            $reference["child_column"]
        );

        $sql = '
            SELECT EXISTS (
                SELECT 1
                FROM "' . $schema . '"."' . $table . '"
                WHERE "' . $column . '" = :worker_id
            )
        ';

        $relatedCheck = $conn->prepare($sql);

        $relatedCheck->execute([
            ":worker_id" => $worker_id
        ]);

        if ($relatedCheck->fetchColumn()) {
            $hasRelatedRecords = true;
            $relatedTables[] = $reference["child_table"];
        }
    }

    // Deactivate worker if related records exist.
    if ($hasRelatedRecords) {

        $deactivate = $conn->prepare("
            UPDATE workers
            SET
                status = 'inactive',
                availability = 'inactive',
                updated_at = CURRENT_TIMESTAMP
            WHERE worker_id = :worker_id
        ");

        $deactivate->execute([
            ":worker_id" => $worker_id
        ]);

        $conn->commit();

        echo json_encode([
            "success" => true,
            "action" => "deactivated",
            "message" => $worker["full_name"]
                . " has related records and has been deactivated instead of deleted. Existing records have been preserved.",
            "worker_id" => $worker_id,
            "related_tables" => array_values(
                array_unique($relatedTables)
            )
        ]);

        exit;
    }

    // Permanently delete only if no related records exist.
    $delete = $conn->prepare("
        DELETE FROM workers
        WHERE worker_id = :worker_id
    ");

    $delete->execute([
        ":worker_id" => $worker_id
    ]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException(
            "The worker could not be deleted."
        );
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "action" => "deleted",
        "message" => "Worker deleted successfully.",
        "worker_id" => $worker_id
    ]);

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        "Worker deletion database error: " . $e->getMessage()
    );

    // Safely deactivate if a foreign-key violation occurs.
    if ($e->getCode() === "23503" && isset($worker_id)) {

        try {

            $fallback = $conn->prepare("
                UPDATE workers
                SET
                    status = 'inactive',
                    availability = 'inactive',
                    updated_at = CURRENT_TIMESTAMP
                WHERE worker_id = :worker_id
            ");

            $fallback->execute([
                ":worker_id" => $worker_id
            ]);

            if ($fallback->rowCount() > 0) {

                echo json_encode([
                    "success" => true,
                    "action" => "deactivated",
                    "message" => "The worker has linked records and was deactivated instead of deleted.",
                    "worker_id" => $worker_id
                ]);

                exit;
            }

        } catch (Throwable $fallbackError) {

            error_log(
                "Worker deactivation fallback error: "
                . $fallbackError->getMessage()
            );
        }
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "A database error occurred while processing this worker. Please check the server error log."
    ]);

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        "Worker deletion error: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "An unexpected error occurred while processing this worker."
    ]);
}
