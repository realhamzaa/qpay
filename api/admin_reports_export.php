<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    exit('Forbidden');
}

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = '';
$params = [];
if ($dateFrom !== '') { $where .= ($where ? ' AND ' : ' WHERE ') . 'DATE(t.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where .= ($where ? ' AND ' : ' WHERE ') . 'DATE(t.created_at) <= ?'; $params[] = $dateTo; }

$stmt = $pdo->prepare("SELECT t.id, t.created_at, t.amount, t.fee_amount, COALESCE(sw.currency, rw.currency) as currency,
    su.full_name_ar as sender_name, ru.full_name_ar as receiver_name
    FROM transactions t
    LEFT JOIN wallets sw ON t.sender_wallet_id = sw.id
    LEFT JOIN users su ON sw.user_id = su.id
    LEFT JOIN wallets rw ON t.receiver_wallet_id = rw.id
    LEFT JOIN users ru ON rw.user_id = ru.id
    $where
    ORDER BY t.created_at DESC LIMIT 5000");
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=QPay_Admin_Report_' . date('Y-m-d') . '.csv');
$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($out, ['ID','Date','Sender','Receiver','Currency','Amount','Fee']);
foreach ($rows as $r) {
    fputcsv($out, [$r['id'], $r['created_at'], $r['sender_name'], $r['receiver_name'], $r['currency'], $r['amount'], $r['fee_amount']]);
}
fclose($out);
exit;
