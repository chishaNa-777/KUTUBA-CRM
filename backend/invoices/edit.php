<?php

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Check authentication
if (empty($_SESSION['user_id'])) {
    respond(401, [
        'success' => false,
        'message' => 'Not authenticated.'
    ]);
}

// Check permissions
$role = strtolower(trim($_SESSION['role'] ?? ''));

if (!in_array($role, ['admin', 'finance_officer'], true)) {
    respond(403, [
        'success' => false,
        'message' => 'Access denied. Finance privileges required.'
    ]);
}

// Accept POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    respond(405, [
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
}

// Read JSON request
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    respond(400, [
        'success' => false,
        'message' => 'Invalid JSON request.'
    ]);
}

// Collect invoice information
$invoiceId = filter_var(
    $input['invoice_id'] ?? null,
    FILTER_VALIDATE_INT
);

$clientId = filter_var(
    $input['client_id'] ?? null,
    FILTER_VALIDATE_INT
);

$periodStart = trim($input['period_start'] ?? '');
$periodEnd = trim($input['period_end'] ?? '');
$amount = $input['amount'] ?? null;
$dueDate = trim($input['due_date'] ?? '');

// Validate required fields
if (!$invoiceId || !$clientId || !$periodStart ||
    !$periodEnd || $amount === null || $dueDate === '') {

    respond(422, [
        'success' => false,
        'message' => 'Please provide all required invoice information.'
    ]);
}

// Validate amount
if (!is_numeric($amount) || (float)$amount <= 0) {
    respond(422, [
        'success' => false,
        'message' => 'Invoice amount must be greater than zero.'
    ]);
}

// Validate dates
function validDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed && $parsed->format('Y-m-d') === $date;
}

if (!validDate($periodStart) ||
    !validDate($periodEnd) ||
    !validDate($dueDate)) {

    respond(422, [
        'success' => false,
        'message' => 'Please provide valid dates.'
    ]);
}

if ($periodEnd < $periodStart) {
    respond(422, [
        'success' => false,
        'message' => 'Period end cannot be before period start.'
    ]);
}

require_once dirname(__DIR__) . '/config/database.php';

try {

    $pdo->beginTransaction();

    // Lock the invoice while editing
    $stmt = $pdo->prepare(
        "SELECT invoice_id, amount_paid
         FROM public.invoices
         WHERE invoice_id = :invoice_id
         FOR UPDATE"
    );

    $stmt->execute([
        'invoice_id' => $invoiceId
    ]);

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        $pdo->rollBack();

        respond(404, [
            'success' => false,
            'message' => 'Invoice not found.'
        ]);
    }

    // Confirm customer exists
    $stmt = $pdo->prepare(
        "SELECT client_id
         FROM public.clients
         WHERE client_id = :client_id"
    );

    $stmt->execute([
        'client_id' => $clientId
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();

        respond(404, [
            'success' => false,
            'message' => 'Selected customer was not found.'
        ]);
    }

    $amountPaid = (float)$invoice['amount_paid'];
    $newAmount = (float)$amount;

    // Do not allow invoice amount below payments already received
    if ($newAmount < $amountPaid) {
        $pdo->rollBack();

        respond(422, [
            'success' => false,
            'message' => 'Invoice amount cannot be less than the amount already paid.'
        ]);
    }

    // Recalculate invoice status
    if ($amountPaid >= $newAmount) {
        $status = 'paid';
    } elseif ($amountPaid > 0) {
        $status = 'partial';
    } else {
        $status = 'unpaid';
    }

    // Update invoice details without changing payment records
    $stmt = $pdo->prepare(
        "UPDATE public.invoices
         SET client_id = :client_id,
             period_start = :period_start,
             period_end = :period_end,
             amount = :amount,
             due_date = :due_date,
             status = :status,
             updated_at = CURRENT_TIMESTAMP
         WHERE invoice_id = :invoice_id"
    );

    $stmt->execute([
        'client_id' => $clientId,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'amount' => $newAmount,
        'due_date' => $dueDate,
        'status' => $status,
        'invoice_id' => $invoiceId
    ]);

    $pdo->commit();

    respond(200, [
        'success' => true,
        'message' => 'Invoice updated successfully.',
        'invoice_id' => $invoiceId,
        'status' => $status
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Invoice edit error: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'Unable to update invoice. Please check the server error log.'
    ]);
}
