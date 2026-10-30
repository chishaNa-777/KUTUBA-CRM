<?php

$host = "localhost";
$port = "5432";
$dbname = "katuba_crm";
$username = "postgres";
$password = "Chisha@22$$";

try {

    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $username,
        $password
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    echo "PostgreSQL connection successful!<br><br>";

    $stmt = $conn->query("
        SELECT client_id, full_name, phone, email
        FROM clients
        ORDER BY client_id
    ");

    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Customers retrieved from PostgreSQL:</h3>";

    foreach ($clients as $client) {
        echo htmlspecialchars($client["full_name"]) . " - ";
        echo htmlspecialchars($client["phone"]) . "<br>";
    }

    echo "<br>Total customers retrieved: " . count($clients);

} catch (PDOException $e) {

    echo "PostgreSQL test failed: " .
         htmlspecialchars($e->getMessage());

}