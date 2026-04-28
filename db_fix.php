<?php
require_once 'includes/db.php';

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h3>🛠️ مصلح قاعدة بيانات QPay</h3>";

try {
    // 1. إضافة الأعمدة الناقصة لجدول users
    $columns = [
        "is_admin" => "TINYINT(1) DEFAULT 0",
        "full_name_ar" => "VARCHAR(255) NOT NULL",
        "full_name_en" => "VARCHAR(255) NOT NULL",
        "id_number" => "VARCHAR(20) UNIQUE NOT NULL",
        "dob" => "DATE NOT NULL",
        "email" => "VARCHAR(100) NULL",
        "whatsapp_phone" => "VARCHAR(20) NULL",
        "address" => "TEXT NOT NULL",
        "profession" => "VARCHAR(100) NOT NULL",
        "account_type" => "ENUM('personal', 'merchant', 'small_business') DEFAULT 'personal'",
        "id_card_path" => "VARCHAR(255) NULL",
        "selfie_path" => "VARCHAR(255) NULL",
        "last_login" => "TIMESTAMP NULL"
    ];

    foreach ($columns as $col => $type) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $type");
            echo "<p style='color: green;'>✅ تمت إضافة العمود: $col</p>";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠️ العمود $col موجود بالفعل أو حدث خطأ بسيط.</p>";
        }
    }

    // 2. إنشاء جدول الإعدادات إذا لم يكن موجوداً
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value VARCHAR(255)
    )");
    
    // إضافة إعداد الواتساب الافتراضي
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('whatsapp_notifications', '0')");
    echo "<p style='color: green;'>✅ تم تجهيز جدول الإعدادات بنجاح.</p>";

    echo "<hr><p style='color: blue; font-weight: bold;'>🎉 اكتمل الإصلاح! يمكنك الآن استخدام bootstrap_admin.php</p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ خطأ فادح: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>
