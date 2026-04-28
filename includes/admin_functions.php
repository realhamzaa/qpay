<?php
require_once 'db.php';

function getSystemStats() {
    global $pdo;
    
    $stats = [];
    
    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Total System Balance
    $stmt = $pdo->query("SELECT SUM(balance) FROM wallets");
    $stats['total_balance'] = $stmt->fetchColumn() ?: 0;
    
    // Total Transactions
    $stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
    $stats['total_transactions'] = $stmt->fetchColumn();
    
    return $stats;
}

function getAllUsers() {
    global $pdo;
    $stmt = $pdo->query("SELECT u.*, w.balance FROM users u JOIN wallets w ON u.id = w.user_id ORDER BY u.created_at DESC");
    return $stmt->fetchAll();
}

function toggleKYC($userId, $status) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET kyc_status = ? WHERE id = ?");
    return $stmt->execute([$status, $userId]);
}

function getAllTransactions() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT t.*, 
               su.full_name as sender_name, 
               ru.full_name as receiver_name 
        FROM transactions t
        LEFT JOIN wallets sw ON t.sender_wallet_id = sw.id
        LEFT JOIN users su ON sw.user_id = su.id
        LEFT JOIN wallets rw ON t.receiver_wallet_id = rw.id
        LEFT JOIN users ru ON rw.user_id = ru.id
        ORDER BY t.created_at DESC
    ");
    return $stmt->fetchAll();
}
?>
