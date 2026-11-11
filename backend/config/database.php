
<?php

$host = getenv("DB_HOST");
$port = getenv("DB_PORT") ?: "5432";
$dbname = getenv("DB_NAME") ?: "postgres";
$username = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$sslmode = getenv("DB_SSLMODE") ?: "require";

// Check required environment variables.
if (!$host || !$username || !$password) {
    error_log("Database configuration is incomplete.");
    http_response_code(500);
    die("Database configuration is incomplete.");
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

    error_log("PostgreSQL connection failed: " . $e->getMessage());

    http_response_code(500);
    die("Database connection failed. Please contact the administrator.");

}

?>
