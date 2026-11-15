
<?php

$envFile = __DIR__ . "/.env";

echo "<pre>";

echo "ENV FILE EXISTS: ";
var_dump(is_file($envFile));

if (is_file($envFile)) {

    $env = parse_ini_file(
        $envFile,
        false,
        INI_SCANNER_RAW
    );

    echo "\nENV FILE READABLE: ";
    var_dump($env !== false);

    if ($env !== false) {

        $required = [
            "DATABASE_URL",
            "SUPABASE_DB_URL",
            "DB_HOST",
            "DB_PORT",
            "DB_NAME",
            "DB_USER",
            "DB_PASSWORD",
            "DB_SSLMODE"
        ];

        echo "\nCONFIGURATION CHECK:\n";

        foreach ($required as $key) {
            $exists = isset($env[$key]) &&
                      trim((string)$env[$key]) !== "";

            echo $key . ": " .
                 ($exists ? "FOUND" : "MISSING") .
                 "\n";
        }
    }
}

echo "</pre>";

?>
