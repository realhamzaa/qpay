<?php
require_once 'includes/functions.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false]); exit(); }
$userId = $_SESSION['user_id'];

// جلب عدد التنبيهات غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetchColumn();

// جلب آخر 5 تنبيهات
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

// جلب أي تحذير إداري نشط
$stmt = $pdo->prepare("SELECT message FROM notifications WHERE user_id = ? AND type = 'warning' AND is_read = 0 ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$warning = $stmt->fetchColumn();

echo json_encode([
    'success' => true,
    'unreadCount' => $unreadCount,
    'notifications' => $notifications,
    'activeWarning' => $warning
]);
