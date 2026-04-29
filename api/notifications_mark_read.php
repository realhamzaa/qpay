<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$uptoId = max(0, (int)($_POST['upto_id'] ?? 0));
if ($uptoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid upto_id']);
    exit;
}

$stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id <= ?');
$stmt->execute([$userId, $uptoId]);

echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
