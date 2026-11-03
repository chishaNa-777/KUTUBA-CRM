
<?php

session_start();

header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Check whether the user is logged in
if (empty($_SESSION["user_id"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}

// Check whether the logged-in user is an administrator
$role = strtolower(trim($_SESSION["role"] ?? ""));

if ($role !== "admin") {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Access denied. Administrator privileges required."
    ]);

    exit;
}

// Connect to the database
require_once __DIR__ . "/../config/database.php";

try {

    // Count clients
    $clientStmt = $conn->query("SELECT COUNT(*) FROM clients");
    $clients = (int) $clientStmt->fetchColumn();

    // Count workers
    $workerStmt = $conn->query("SELECT COUNT(*) FROM workers");
    $workers = (int) $workerStmt->fetchColumn();

    // Count users
    $userStmt = $conn->query("SELECT COUNT(*) FROM users");
    $users = (int) $userStmt->fetchColumn();

    // Count jobs
    $jobStmt = $conn->query("SELECT COUNT(*) FROM jobs");
    $jobs = (int) $jobStmt->fetchColumn();

    // Count contracts
    $contractStmt = $conn->query("SELECT COUNT(*) FROM contracts");
    $contracts = (int) $contractStmt->fetchColumn();

    // Count pending jobs
    $pendingStmt = $conn->query("
        SELECT COUNT(*) FROM jobs
        WHERE status = 'Pending'
    ");
    $pending = (int) $pendingStmt->fetchColumn();

    // Count in-progress jobs
    $progressStmt = $conn->query("
        SELECT COUNT(*) FROM jobs
        WHERE status = 'In Progress'
    ");
    $inProgress = (int) $progressStmt->fetchColumn();

    // Count completed jobs
    $completedStmt = $conn->query("
        SELECT COUNT(*) FROM jobs
        WHERE status = 'Completed'
    ");
    $completed = (int) $completedStmt->fetchColumn();

    // Count cancelled jobs
    $cancelledStmt = $conn->query("
        SELECT COUNT(*) FROM jobs
        WHERE status = 'Cancelled'
    ");
    $cancelled = (int) $cancelledStmt->fetchColumn();

    // Calculate total records
    $total = $clients + $workers + $users + $jobs + $contracts;

    echo json_encode([
        "success" => true,
        "clients" => $clients,
        "workers" => $workers,
        "users" => $users,
        "jobs" => $jobs,
        "contracts" => $contracts,
        "pending" => $pending,
        "in_progress" => $inProgress,
        "completed" => $completed,
        "cancelled" => $cancelled,
        "total" => $total
    ]);

} catch (PDOException $e) {

    error_log("Dashboard statistics error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load dashboard statistics."
    ]);
}
