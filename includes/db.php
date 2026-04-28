<?php
// 🕒 ضبط مدة الجلسة لـ ساعتين
$session_lifetime = 7200;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', $session_lifetime);
    ini_set('session.cookie_lifetime', $session_lifetime);
    session_set_cookie_params($session_lifetime);
    session_start();
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'qpay_db';
$charset = 'utf8mb4';

try {
    $pdo_root = new PDO("mysql:host=$host;charset=$charset", $user, $pass);
    $pdo_root->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 1. جدول المستخدمين (تحديث الأعمدة)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(20) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        pin_hash VARCHAR(255) NOT NULL,
        full_name_ar VARCHAR(255) NOT NULL,
        dob DATE NOT NULL,
        is_admin TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $u_cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_frozen', $u_cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN is_frozen TINYINT(1) DEFAULT 0"); }
    if (!in_array('daily_limit', $u_cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN daily_limit DECIMAL(15, 2) DEFAULT 1000.00"); }
    if (!in_array('current_daily_usage', $u_cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN current_daily_usage DECIMAL(15, 2) DEFAULT 0.00"); }

    // 2. جدول العمليات (تحديث أعمدة العمولة)
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_wallet_id INT,
        receiver_wallet_id INT,
        amount DECIMAL(15, 2) NOT NULL,
        type ENUM('transfer', 'deposit', 'withdrawal') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $t_cols = $pdo->query("DESCRIBE transactions")->fetchAll(PDO::FETCH_COLUMN);
    // 🛡️ الميزة المصلحة: إضافة عمود fee_amount ليعمل داشبورد الأدمن
    if (!in_array('fee_amount', $t_cols)) { 
        $pdo->exec("ALTER TABLE transactions ADD COLUMN fee_amount DECIMAL(15, 2) DEFAULT 0.00 AFTER amount"); 
    }

    // 3. بقية الجداول
    $pdo->exec("CREATE TABLE IF NOT EXISTS wallets (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, balance DECIMAL(15, 2) DEFAULT 0.00, currency ENUM('ILS', 'USD', 'JOD') DEFAULT 'ILS',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, message TEXT NOT NULL, type ENUM('warning', 'info', 'transaction') DEFAULT 'info', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(50) UNIQUE NOT NULL, setting_value TEXT)");
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('commission_type', 'step'), ('commission_step_amount', '50'), ('commission_step_fee', '0.5')");

} catch (\PDOException $e) {
    die("❌ خطأ: " . $e->getMessage());
}
?>
