<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$userId = (int)$_SESSION['user_id'];
$q = trim($_GET['q'] ?? '');
$currency = trim($_GET['currency'] ?? '');
$type = trim($_GET['type'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$sql = "SELECT t.created_at, t.amount, sw.currency as sender_curr, rw.currency as receiver_curr,
               su.full_name_ar as sender_name, ru.full_name_ar as receiver_name,
               sw.user_id as sender_user_id, rw.user_id as receiver_user_id
        FROM transactions t
        LEFT JOIN wallets sw ON t.sender_wallet_id = sw.id
        LEFT JOIN wallets rw ON t.receiver_wallet_id = rw.id
        LEFT JOIN users su ON sw.user_id = su.id
        LEFT JOIN users ru ON rw.user_id = ru.id
        WHERE sw.user_id = ? OR rw.user_id = ?";
$params = [$userId, $userId];

if ($q !== '') {
    $sql .= " AND (su.full_name_ar LIKE ? OR ru.full_name_ar LIKE ? OR su.phone LIKE ? OR ru.phone LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($currency !== '' && in_array($currency, ['ILS','USD','JOD'], true)) {
    $sql .= " AND (sw.currency = ? OR rw.currency = ?)";
    $params[] = $currency;
    $params[] = $currency;
}
if ($type !== '' && in_array($type, ['incoming','outgoing'], true)) {
    if ($type === 'incoming') { $sql .= " AND rw.user_id = ?"; $params[] = $userId; }
    else { $sql .= " AND sw.user_id = ?"; $params[] = $userId; }
}
if ($dateFrom !== '') { $sql .= " AND DATE(t.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo !== '') { $sql .= " AND DATE(t.created_at) <= ?"; $params[] = $dateTo; }

$sql .= " ORDER BY t.created_at DESC LIMIT 80";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$userStmt = $pdo->prepare("SELECT full_name_ar, phone FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$u = $userStmt->fetch();

function pdfEscape($text) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

$lines = [];
$lines[] = 'QPAY - Financial History';
$lines[] = 'Account: ' . ($u['full_name_ar'] ?? '-');
$lines[] = 'Phone: ' . ($u['phone'] ?? '-');
$lines[] = 'Generated: ' . date('Y-m-d H:i:s');
$lines[] = '---------------------------------------------';

foreach ($rows as $r) {
    $isSender = ((int)$r['sender_user_id'] === $userId);
    $curr = $r['sender_curr'] ?: $r['receiver_curr'];
    $symbol = ['ILS'=>'₪','USD'=>'$','JOD'=>'JD'][$curr] ?? $curr;
    $dir = $isSender ? 'OUT' : 'IN';
    $other = $isSender ? ($r['receiver_name'] ?: '-') : ($r['sender_name'] ?: '-');
    $lines[] = sprintf('%s | %s %s %.2f | %s', date('Y-m-d H:i', strtotime($r['created_at'])), $dir, $symbol, $r['amount'], $other);
}

$y = 800;
$content = "BT\n/F1 10 Tf\n50 $y Td\n";
$first = true;
foreach ($lines as $line) {
    if (!$first) $content .= "T*\n";
    $content .= '(' . pdfEscape($line) . ') Tj\n';
    $first = false;
}
$content .= "ET";

$len = strlen($content);
$pdf = "%PDF-1.4\n";
$offsets = [];

$offsets[] = strlen($pdf);
$pdf .= "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n";
$offsets[] = strlen($pdf);
$pdf .= "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n";
$offsets[] = strlen($pdf);
$pdf .= "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>endobj\n";
$offsets[] = strlen($pdf);
$pdf .= "4 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n";
$offsets[] = strlen($pdf);
$pdf .= "5 0 obj<< /Length $len >>stream\n$content\nendstream\nendobj\n";

$xrefPos = strlen($pdf);
$pdf .= "xref\n0 6\n0000000000 65535 f \n";
foreach ($offsets as $off) {
    $pdf .= sprintf("%010d 00000 n \n", $off);
}
$pdf .= "trailer<< /Size 6 /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="QPay_History_' . date('Y-m-d') . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
