<?php

$databaseUrl = getenv("DATABASE_URL");

if ($databaseUrl === false || $databaseUrl === "") {
    $localEnvFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ".env";

    if (is_file($localEnvFile)) {
        $localEnvironment = @parse_ini_file($localEnvFile, false, INI_SCANNER_RAW);

        if ($localEnvironment === false) {
            databaseError("The local .env file could not be parsed.");
        }

        $databaseUrl = $localEnvironment["DATABASE_URL"] ?? "";
    }
}

if ($databaseUrl !== false && $databaseUrl !== "") {
    $connection = parse_url($databaseUrl);

    if (
        $connection === false ||
        !isset($connection["scheme"], $connection["host"], $connection["user"], $connection["pass"], $connection["path"]) ||
        !in_array(strtolower($connection["scheme"]), ["postgres", "postgresql"], true)
    ) {
        $connection = null;
    }

    if ($connection === null) {
        $host = null;
        $port = null;
        $dbname = null;
        $username = null;
        $password = null;
        $sslmode = null;
    } else {
        $host = $connection["host"];
        $port = $connection["port"] ?? "5432";
        $dbname = rawurldecode(ltrim($connection["path"], "/"));
        $username = rawurldecode($connection["user"]);
        $password = rawurldecode($connection["pass"]);

        $urlOptions = [];
        parse_str($connection["query"] ?? "", $urlOptions);
        $sslmode = $urlOptions["sslmode"] ?? (getenv("DB_SSLMODE") ?: "require");
    }
} else {
    $host = getenv("DB_HOST");
    $port = getenv("DB_PORT") ?: "5432";
    $dbname = getenv("DB_NAME") ?: "postgres";
    $username = getenv("DB_USER");
    $password = getenv("DB_PASSWORD");
    $sslmode = getenv("DB_SSLMODE") ?: "require";
}

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

// Check required values and prevent malformed values from changing the PDO DSN.
if (
    !$host ||
    !$dbname ||
    $username === false ||
    $username === null ||
    $password === false ||
    $password === null ||
    !ctype_digit((string) $port) ||
    preg_match('/[;\x00]/', (string) $host) ||
    preg_match('/[;\x00]/', (string) $dbname) ||
    !in_array($sslmode, ["disable", "allow", "prefer", "require", "verify-ca", "verify-full"], true)
) {
    databaseError("Database configuration is incomplete or invalid.");
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
