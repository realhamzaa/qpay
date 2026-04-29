<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userPhoneStmt = $pdo->prepare('SELECT phone FROM users WHERE id = ?');
$userPhoneStmt->execute([$userId]);
$userPhone = (string)$userPhoneStmt->fetchColumn();
$mode = $_GET['mode'] ?? 'history';
$limit = min(50, max(1, (int)($_GET['limit'] ?? ($mode === 'recent' ? 5 : 20))));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$since = $_GET['since'] ?? '';
$sinceId = max(0, (int)($_GET['since_id'] ?? 0));
$q = trim($_GET['q'] ?? '');
$currency = trim($_GET['currency'] ?? '');
$type = trim($_GET['type'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$sql = "SELECT t.*, su.full_name_ar as sender_name, ru.full_name_ar as receiver_name, su.phone as sender_phone, ru.phone as receiver_phone, sw.currency as sender_curr, rw.currency as receiver_curr
        FROM transactions t
        LEFT JOIN wallets sw ON t.sender_wallet_id = sw.id
        LEFT JOIN users su ON sw.user_id = su.id
        LEFT JOIN wallets rw ON t.receiver_wallet_id = rw.id
        LEFT JOIN users ru ON rw.user_id = ru.id
        WHERE sw.user_id = ? OR rw.user_id = ?";
$params = [$userId, $userId];

if ($since !== '') {
    $sql .= " AND t.created_at > ?";
    $params[] = $since;
}
if ($sinceId > 0) {
    $sql .= " AND t.id > ?";
    $params[] = $sinceId;
}


if ($q !== '') {
    $sql .= " AND (su.full_name_ar LIKE ? OR ru.full_name_ar LIKE ? OR su.phone LIKE ? OR ru.phone LIKE ?)";
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}


if ($currency !== '' && in_array($currency, ['ILS','USD','JOD'], true)) {
    $sql .= " AND (sw.currency = ? OR rw.currency = ?)";
    $params[] = $currency;
    $params[] = $currency;
}

if ($type !== '' && in_array($type, ['incoming','outgoing'], true)) {
    if ($type === 'incoming') {
        $sql .= " AND rw.user_id = ?";
        $params[] = $userId;
    } else {
        $sql .= " AND sw.user_id = ?";
        $params[] = $userId;
    }
}

if ($dateFrom !== '') {
    $sql .= " AND DATE(t.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND DATE(t.created_at) <= ?";
    $params[] = $dateTo;
}


$sql .= " ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalStmt = $pdo->prepare("SELECT COUNT(*)
    FROM transactions t
    LEFT JOIN wallets sw ON t.sender_wallet_id = sw.id
    LEFT JOIN wallets rw ON t.receiver_wallet_id = rw.id
    WHERE sw.user_id = ? OR rw.user_id = ?");
$totalStmt->execute([$userId, $userId]);
$total = (int)$totalStmt->fetchColumn();

function curr_symbol($c) {
    $m = ['ILS'=>'₪','USD'=>'$','JOD'=>'JD'];
    return $m[$c] ?? $c;
}

$data = array_map(function($t) use ($userPhone) {
    $curr = $t['sender_curr'] ?? $t['receiver_curr'];
    $isSender = ($t['sender_phone'] ?? '') === $userPhone;
    return [
        'id' => (int)$t['id'],
        'amount' => (float)$t['amount'],
        'symbol' => curr_symbol($curr),
        'curr' => $curr,
        'sender_name' => $t['sender_name'] ?? '-',
        'receiver_name' => $t['receiver_name'] ?? '-',
        'sender_phone' => $t['sender_phone'] ?? '',
        'receiver_phone' => $t['receiver_phone'] ?? '',
        'created_at' => $t['created_at'],
        'time_ago' => time_elapsed_string($t['created_at']),
        'is_sender' => $isSender
    ];
}, $rows);

echo json_encode([
    'success' => true,
    'items' => $data,
    'has_more' => ($offset + count($rows)) < $total,
    'next_offset' => $offset + count($rows),
    'latest_id' => !empty($rows[0]['id']) ? (int)$rows[0]['id'] : $sinceId,
    'server_time' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
