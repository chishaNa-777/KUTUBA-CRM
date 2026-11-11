
<?php

$host = getenv("DB_HOST");
$port = getenv("DB_PORT") ?: "5432";
$dbname = getenv("DB_NAME") ?: "postgres";
$username = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$sslmode = getenv("DB_SSLMODE") ?: "require";

// Return a JSON error if the database connection fails.
function databaseError($message)
{
    error_log($message);

    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed. Please check the server configuration."
    ]);

    exit;
}

// Check required environment variables.
if (!$host || !$username || !$password) {
    databaseError("Database configuration is incomplete.");
}

try {

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";

    $conn = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Support both variable names used in the CRM.
    $pdo = $conn;

} catch (PDOException $e) {

    databaseError("PostgreSQL connection failed: " . $e->getMessage());

}

?>
