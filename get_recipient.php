<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$phone = $_GET['phone'] ?? '';

if (empty($phone)) {
    echo json_encode(['error' => 'Phone required']);
    exit();
}

$stmt = $pdo->prepare("SELECT full_name_ar FROM users WHERE phone = ?");
$stmt->execute([$phone]);
$user = $stmt->fetch();

if ($user) {
    echo json_encode(['success' => true, 'name' => $user['full_name_ar']]);
} else {
    echo json_encode(['success' => false, 'error' => 'رقم غير مسجل']);
}
?>
