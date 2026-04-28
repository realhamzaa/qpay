<?php
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$draw = (int)($_GET['draw'] ?? 1);
$start = max(0, (int)($_GET['start'] ?? 0));
$length = max(10, min(100, (int)($_GET['length'] ?? 10)));
$search = trim($_GET['search']['value'] ?? '');

$columns = ['id', 'full_name_ar', 'phone', 'daily_limit', 'is_frozen', 'is_admin', 'created_at'];
$orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$orderBy = $columns[$orderIndex] ?? 'id';

$baseWhere = 'WHERE id != ?';
$params = [$_SESSION['user_id']];

if ($search !== '') {
    $baseWhere .= ' AND (full_name_ar LIKE ? OR phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id != ?');
$totalStmt->execute([$_SESSION['user_id']]);
$recordsTotal = (int)$totalStmt->fetchColumn();

$filteredStmt = $pdo->prepare("SELECT COUNT(*) FROM users $baseWhere");
$filteredStmt->execute($params);
$recordsFiltered = (int)$filteredStmt->fetchColumn();

$sql = "SELECT id, full_name_ar, phone, daily_limit, is_frozen, is_admin, created_at FROM users $baseWhere ORDER BY $orderBy $orderDir LIMIT $start, $length";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = array_map(function ($u) {
    $userId = (int)$u['id'];
    $safeName = htmlspecialchars($u['full_name_ar'], ENT_QUOTES, 'UTF-8');
    $safePhone = htmlspecialchars($u['phone'], ENT_QUOTES, 'UTF-8');
    $limit = number_format((float)($u['daily_limit'] ?? 0), 0);
    $status = !empty($u['is_frozen']) ? 'مجمد' : 'نشط';
    $admin = !empty($u['is_admin']) ? 'Admin' : 'User';

    $actions = '<div class="dt-actions">'
        . '<form method="POST"><input type="hidden" name="user_id" value="'.$userId.'"><input type="hidden" name="action" value="'.(!empty($u['is_frozen']) ? 'unfreeze' : 'freeze').'"><button class="btn" style="padding:6px 10px; background:'.(!empty($u['is_frozen']) ? 'var(--ios-green)' : 'var(--ios-red)').';">'.(!empty($u['is_frozen']) ? 'فك' : 'تجميد').'</button></form>'
        . '<form method="POST"><input type="hidden" name="user_id" value="'.$userId.'"><input type="hidden" name="action" value="toggle_admin"><button class="btn" style="padding:6px 10px; background:#0A84FF;">'.(!empty($u['is_admin']) ? 'سحب أدمن' : 'منح أدمن').'</button></form>'
        . '<form method="POST" onsubmit="return confirm(\'حذف المستخدم؟\')"><input type="hidden" name="user_id" value="'.$userId.'"><input type="hidden" name="action" value="delete_user"><button class="btn" style="padding:6px 10px; background:#8E8E93;">حذف</button></form>'
        . '</div>';

    return [
        $userId,
        $safeName,
        $safePhone,
        $limit . ' ₪',
        $status,
        $admin,
        date('Y-m-d', strtotime($u['created_at'])),
        $actions
    ];
}, $rows);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
], JSON_UNESCAPED_UNICODE);
