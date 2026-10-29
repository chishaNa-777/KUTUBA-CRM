
<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: text/plain; charset=UTF-8");

$email = "admin@katuba.com";
$password = "Katuba@2026!";

try {
    $stmt = $conn->prepare(
        "SELECT email, password_hash, status
         FROM users
         WHERE email = :email
         LIMIT 1"
    );

    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "RESULT: Account not found in the connected database.";
        exit;
    }

    echo "Account found: YES\n";
    echo "Account status: " . $user["status"] . "\n";
    echo "Password matches saved hash: ";
    echo password_verify($password, $user["password_hash"])
        ? "YES"
        : "NO";

} catch (PDOException $e) {
    http_response_code(500);
    echo "Database test failed. Check the PHP error log.";
}
