<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$sinceId = max(0, (int)($_GET['since_id'] ?? 0));
$limit = min(30, max(1, (int)($_GET['limit'] ?? 10)));

$stmt = $pdo->prepare('SELECT id, message, type, is_read, created_at FROM notifications WHERE user_id = ? AND id > ? ORDER BY id DESC LIMIT ?');
$stmt->bindValue(1, $userId, PDO::PARAM_INT);
$stmt->bindValue(2, $sinceId, PDO::PARAM_INT);
$stmt->bindValue(3, $limit, PDO::PARAM_INT);
$stmt->execute();
$newItems = $stmt->fetchAll();

$unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unreadStmt->execute([$userId]);
$unread = (int)$unreadStmt->fetchColumn();

$lastIdStmt = $pdo->prepare('SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id = ?');
$lastIdStmt->execute([$userId]);
$latestId = (int)$lastIdStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'unread_count' => $unread,
    'latest_id' => $latestId,
    'items' => $newItems
], JSON_UNESCAPED_UNICODE);
