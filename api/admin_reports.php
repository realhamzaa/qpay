<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = '';
$params = [];
if ($dateFrom !== '') { $where .= ($where ? ' AND ' : ' WHERE ') . 'DATE(t.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where .= ($where ? ' AND ' : ' WHERE ') . 'DATE(t.created_at) <= ?'; $params[] = $dateTo; }

$totalStmt = $pdo->prepare("SELECT COUNT(*) as total_count, COALESCE(SUM(amount),0) as total_amount, COALESCE(SUM(fee_amount),0) as total_fees FROM transactions t $where");
$totalStmt->execute($params);
$summary = $totalStmt->fetch();

$byCurrStmt = $pdo->prepare("SELECT COALESCE(sw.currency, rw.currency) as currency, COUNT(*) as tx_count, COALESCE(SUM(t.amount),0) as total_amount, COALESCE(SUM(t.fee_amount),0) as total_fees
    FROM transactions t
    LEFT JOIN wallets sw ON t.sender_wallet_id = sw.id
    LEFT JOIN wallets rw ON t.receiver_wallet_id = rw.id
    $where
    GROUP BY COALESCE(sw.currency, rw.currency)");
$byCurrStmt->execute($params);
$byCurrency = $byCurrStmt->fetchAll();

$kycStats = $pdo->query("SELECT kyc_status, COUNT(*) as count FROM users GROUP BY kyc_status")->fetchAll();

echo json_encode([
    'success' => true,
    'summary' => $summary,
    'by_currency' => $byCurrency,
    'kyc' => $kycStats
], JSON_UNESCAPED_UNICODE);
