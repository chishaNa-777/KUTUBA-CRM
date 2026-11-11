
<?php

$host = getenv("DB_HOST") ?: "localhost";
$port = getenv("DB_PORT") ?: "5432";
$dbname = getenv("DB_NAME") ?: "katuba_crm";
$username = getenv("DB_USER") ?: "postgres";

// ENTER YOUR POSTGRESQL PASSWORD BELOW
$password = "Chisha@22$$";

$sslmode = getenv("DB_SSLMODE") ?: "prefer";

try {

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";

    $conn = new PDO(
        $dsn,
        $username,
        $password
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $conn->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    // Make the connection available as both variables
    $pdo = $conn;

} catch (PDOException $e) {

    error_log("PostgreSQL connection failed: " . $e->getMessage());

    http_response_code(500);
    die("Database connection failed. Please contact the administrator.");

}

?>
