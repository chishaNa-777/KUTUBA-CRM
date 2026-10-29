<?php

require_once "../config/database.php";

header("Content-Type: application/json");

try {

    // Count clients
    $clientStmt = $conn->query("SELECT COUNT(*) FROM clients");
    $clients = $clientStmt->fetchColumn();


    // Count workers
    $workerStmt = $conn->query("SELECT COUNT(*) FROM workers");
    $workers = $workerStmt->fetchColumn();


    // Count users
    $userStmt = $conn->query("SELECT COUNT(*) FROM users");
    $users = $userStmt->fetchColumn();


    // Count jobs
    $jobStmt = $conn->query("SELECT COUNT(*) FROM jobs");
    $jobs = $jobStmt->fetchColumn();


    // Count Pending jobs
    $pendingStmt = $conn->query("
        SELECT COUNT(*)
        FROM jobs
        WHERE status = 'Pending'
    ");

    $pending = $pendingStmt->fetchColumn();


    // Count In Progress jobs
    $progressStmt = $conn->query("
        SELECT COUNT(*)
        FROM jobs
        WHERE status = 'In Progress'
    ");

    $inProgress = $progressStmt->fetchColumn();


    // Count Completed jobs
    $completedStmt = $conn->query("
        SELECT COUNT(*)
        FROM jobs
        WHERE status = 'Completed'
    ");

    $completed = $completedStmt->fetchColumn();


    // Count Cancelled jobs
    $cancelledStmt = $conn->query("
        SELECT COUNT(*)
        FROM jobs
        WHERE status = 'Cancelled'
    ");

    $cancelled = $cancelledStmt->fetchColumn();


    // Calculate total records
    $total = $clients + $workers + $users + $jobs;


    echo json_encode([

        "success" => true,

        "clients" => (int)$clients,

        "workers" => (int)$workers,

        "users" => (int)$users,

        "jobs" => (int)$jobs,

        "pending" => (int)$pending,

        "in_progress" => (int)$inProgress,

        "completed" => (int)$completed,

        "cancelled" => (int)$cancelled,

        "total" => (int)$total

    ]);


} catch (PDOException $e) {

    echo json_encode([

        "success" => false,

        "message" => $e->getMessage()

    ]);

}

?>