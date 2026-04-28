<?php
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Access Denied.");
}

$userId = $_SESSION['user_id'];

// جلب العمليات
$stmt = $pdo->prepare("
    SELECT t.created_at, t.amount, t.type, 
           su.full_name_ar as sender, ru.full_name_ar as receiver,
           sw.currency as currency
    FROM transactions t
    LEFT JOIN wallets sw ON t.sender_wallet_id = sw.id
    LEFT JOIN users su ON sw.user_id = su.id
    LEFT JOIN wallets rw ON t.receiver_wallet_id = rw.id
    LEFT JOIN users ru ON rw.user_id = ru.id
    WHERE sw.user_id = ? OR rw.user_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$userId, $userId]);
$transactions = $stmt->fetchAll();

// إعداد ملف CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=QPay_History_'.date('Y-m-d').'.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // لدعم اللغة العربية في Excel

fputcsv($output, ['التاريخ', 'المرسل', 'المستقبل', 'المبلغ', 'العملة', 'النوع']);

foreach ($transactions as $t) {
    fputcsv($output, [
        $t['created_at'],
        $t['sender'] ?? 'نظام',
        $t['receiver'] ?? 'نظام',
        $t['amount'],
        $t['currency'],
        $t['type']
    ]);
}

fclose($output);
exit();
?>
