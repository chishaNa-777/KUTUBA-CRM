<?php

// Return a safe JSON error message.
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

// Load the .env file from the project root.
$envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ".env";
$localEnvironment = [];

if (is_file($envFile)) {
    $localEnvironment = @parse_ini_file(
        $envFile,
        false,
        INI_SCANNER_RAW
    );

    if ($localEnvironment === false) {
        databaseError("The .env file could not be read.");
    }
}

// Read a setting from the server environment first,
// then fall back to the local .env file.
function getDatabaseSetting($name, $default = null)
{
    global $localEnvironment;

    $value = getenv($name);

    if ($value !== false && $value !== "") {
        return $value;
    }

    return $localEnvironment[$name] ?? $default;
}

// Read database settings.
$host = getDatabaseSetting("DB_HOST");
$port = getDatabaseSetting("DB_PORT", "5432");
$dbname = getDatabaseSetting("DB_NAME", "postgres");
$username = getDatabaseSetting("DB_USER");
$password = getDatabaseSetting("DB_PASSWORD");
$sslmode = getDatabaseSetting("DB_SSLMODE", "require");

// Validate settings.
if (
    !$host ||
    !$dbname ||
    $username === null ||
    $username === "" ||
    $password === null ||
    $password === "" ||
    !ctype_digit((string) $port) ||
    (int) $port < 1 ||
    (int) $port > 65535 ||
    preg_match('/[;\x00]/', (string) $host) ||
    preg_match('/[;\x00]/', (string) $dbname) ||
    !in_array($sslmode, [
        "disable",
        "allow",
        "prefer",
        "require",
        "verify-ca",
        "verify-full"
    ], true)
) {
    databaseError("Database configuration is incomplete or invalid.");
}

// Connect to PostgreSQL.
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
