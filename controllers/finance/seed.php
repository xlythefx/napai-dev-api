<?php
/**
 * Seed finance_transactions with realistic sample data.
 * POST { requester_email, force? }
 * Skips if rows already exist (unless force=true).
 * -> { success, inserted, message }
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB fail']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$admin = require_admin($conn, $input['requester_email'] ?? '');
$force = !empty($input['force']);
$adminId = (int) $admin['id'];

// Check if data already exists
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM finance_transactions WHERE tags LIKE '%seed%'");
mysqli_stmt_execute($stmt);
$existingCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];
mysqli_stmt_close($stmt);

if ($existingCount > 0 && !$force) {
    echo json_encode([
        'success' => true,
        'inserted' => 0,
        'message'  => "Seed data already present ($existingCount rows). Pass force=true to re-seed.",
    ]);
    exit;
}

if ($force && $existingCount > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM finance_transactions WHERE tags LIKE '%seed%'");
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// --- Seed data ---
// PHP ~56 per USD (approx)
$rows = [
    // ===== MARCH 2026 =====
    // Income
    ['income', 'payroll',        null,               80000.00, 'PHP', '2026-03-30', 'Monthly payroll from company',           'high', 'bank_transfer', null],
    ['income', 'client_payment', 'NapAI Website',    45000.00, 'PHP', '2026-03-15', 'NapAI company website build',            'high', 'gcash',         null],
    ['income', 'profit_share',   'Q1 Digital Tools', 15500.00, 'PHP', '2026-03-31', '10% profit share - Q1 digital solutions','mid',  'bank_transfer', null],

    // Expenses
    ['expense', 'developer_salary', 'Dev 1',  45000.00, 'PHP', '2026-03-30', 'Monthly developer salary - March',      'high', 'gcash',    null],
    ['expense', 'ai_tools',  'ChatGPT Plus',   1120.00, 'PHP', '2026-03-05', 'OpenAI ChatGPT Plus subscription',      'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'Claude Pro',     1120.00, 'PHP', '2026-03-05', 'Anthropic Claude Pro subscription',     'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'Midjourney',      560.00, 'PHP', '2026-03-05', 'Midjourney image generation',           'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'GitHub Copilot',  560.00, 'PHP', '2026-03-05', 'GitHub Copilot AI coding assistant',   'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'Cursor AI',      1120.00, 'PHP', '2026-03-05', 'Cursor AI IDE subscription',           'mid',  'credit_card', null],
    ['expense', 'subscription', 'Figma',         672.00, 'PHP', '2026-03-10', 'Figma design tool subscription',       'mid',  'credit_card', null],
    ['expense', 'subscription', 'Notion',        448.00, 'PHP', '2026-03-10', 'Notion workspace subscription',        'low',  'credit_card', null],
    ['expense', 'subscription', 'Vercel Pro',    896.00, 'PHP', '2026-03-10', 'Vercel Pro hosting subscription',      'mid',  'credit_card', null],

    // ===== APRIL 2026 =====
    // Income
    ['income', 'payroll',        null,              80000.00, 'PHP', '2026-04-30', 'Monthly payroll from company',           'high', 'bank_transfer', null],
    ['income', 'client_payment', 'Mobile App Dev',  80000.00, 'PHP', '2026-04-10', 'Mobile app development - full payment',  'high', 'bank_transfer', null],
    ['income', 'client_payment', 'E-comm DP',       20000.00, 'PHP', '2026-04-22', 'E-commerce integration - 50% downpayment','high','gcash',        null],
    ['income', 'profit_share',   'April Tools',      8000.00, 'PHP', '2026-04-30', '10% profit share - April digital tools', 'mid',  'gcash',        null],

    // Expenses
    ['expense', 'developer_salary', 'Dev 1',  45000.00, 'PHP', '2026-04-30', 'Monthly developer salary - April',      'high', 'gcash',    null],
    ['expense', 'developer_salary', 'Dev 2',  35000.00, 'PHP', '2026-04-30', 'Monthly developer salary 2 - April',   'high', 'gcash',    null],
    ['expense', 'ai_tools',  'ChatGPT Plus',   1120.00, 'PHP', '2026-04-05', 'OpenAI ChatGPT Plus subscription',     'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'Claude Pro',     1120.00, 'PHP', '2026-04-05', 'Anthropic Claude Pro subscription',    'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'Midjourney',      560.00, 'PHP', '2026-04-05', 'Midjourney image generation',          'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'GitHub Copilot',  560.00, 'PHP', '2026-04-05', 'GitHub Copilot AI coding assistant',  'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'Cursor AI',      1120.00, 'PHP', '2026-04-05', 'Cursor AI IDE subscription',          'mid',  'credit_card', null],
    ['expense', 'subscription', 'Figma',         672.00, 'PHP', '2026-04-10', 'Figma design tool subscription',      'mid',  'credit_card', null],
    ['expense', 'subscription', 'Notion',        448.00, 'PHP', '2026-04-10', 'Notion workspace subscription',       'low',  'credit_card', null],
    ['expense', 'subscription', 'Vercel Pro',    896.00, 'PHP', '2026-04-10', 'Vercel Pro hosting subscription',     'mid',  'credit_card', null],

    // ===== MAY 2026 =====
    // Income
    ['income', 'payroll',        null,              80000.00, 'PHP', '2026-05-02', 'Monthly payroll from company',           'high', 'bank_transfer', null],
    ['income', 'client_payment', 'E-comm Final',   20000.00, 'PHP', '2026-05-01', 'E-commerce integration - final 50%',     'high', 'gcash',         null],
    ['income', 'client_payment', 'RAG Chatbot',    35000.00, 'PHP', '2026-05-02', 'RAG chatbot build for client',           'high', 'bank_transfer', null],

    // Expenses
    ['expense', 'developer_salary', 'Dev 1',  22500.00, 'PHP', '2026-05-01', 'Developer salary May (partial)',        'high', 'gcash',      null],
    ['expense', 'ai_tools',  'ChatGPT Plus',   1120.00, 'PHP', '2026-05-05', 'OpenAI ChatGPT Plus subscription',     'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'Claude Pro',     1120.00, 'PHP', '2026-05-05', 'Anthropic Claude Pro subscription',    'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'Midjourney',      560.00, 'PHP', '2026-05-05', 'Midjourney image generation',          'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'GitHub Copilot',  560.00, 'PHP', '2026-05-05', 'GitHub Copilot AI coding assistant',  'mid',  'credit_card', null],
    ['expense', 'ai_tools',  'Cursor AI',      1120.00, 'PHP', '2026-05-05', 'Cursor AI IDE subscription',          'mid',  'credit_card', null],
    ['expense', 'subscription', 'Figma',         672.00, 'PHP', '2026-05-10', 'Figma design tool subscription',      'mid',  'credit_card', null],
    ['expense', 'subscription', 'Notion',        448.00, 'PHP', '2026-05-10', 'Notion workspace subscription',       'low',  'credit_card', null],
    ['expense', 'subscription', 'Vercel Pro',    896.00, 'PHP', '2026-05-10', 'Vercel Pro hosting subscription',     'mid',  'credit_card', null],
];

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO finance_transactions
       (type, category, subcategory, amount, currency, transaction_date,
        description, priority, payment_method, tags, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "seed", ?)'
);

$inserted = 0;
foreach ($rows as $r) {
    [$type, $category, $subcategory, $amount, $currency, $date, $description, $priority, $paymentMethod, $_] = $r;
    mysqli_stmt_bind_param(
        $stmt, 'sssdssssssi',
        $type, $category, $subcategory, $amount, $currency, $date,
        $description, $priority, $paymentMethod, $adminId
    );
    if (mysqli_stmt_execute($stmt)) $inserted++;
}
mysqli_stmt_close($stmt);

echo json_encode([
    'success'  => true,
    'inserted' => $inserted,
    'message'  => "Seeded $inserted transactions.",
]);
