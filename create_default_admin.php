<?php
require_once 'includes/db.php';

echo "<div style='font-family:sans-serif; padding:20px; background:#000; color:#fff; border-radius:15px; border:1px solid #007AFF;'>";
echo "<h2>🛠️ تأمين وصول المسؤول (Admin Recovery)</h2>";

try {
    $phone = '0599000000';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $pin = password_hash('1234', PASSWORD_DEFAULT);

    // التحقق من وجود الحساب
    $check = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $check->execute([$phone]);
    $user = $check->fetch();

    if ($user) {
        // تحديث الحساب الموجود لضمان صحة البيانات وكلمة المرور
        $sql = "UPDATE users SET password_hash = ?, pin_hash = ?, kyc_status = 'verified', is_admin = 1 WHERE phone = ?";
        $pdo->prepare($sql)->execute([$password, $pin, $phone]);
        echo "<p style='color:#34C759;'>✅ تم تحديث بيانات الأدمن وتصفير كلمة المرور بنجاح!</p>";
    } else {
        // إنشاء حساب جديد إذا لم يكن موجوداً أصلاً
        $sql = "INSERT INTO users (phone, password_hash, pin_hash, full_name_ar, full_name_en, id_number, dob, address, profession, kyc_status, is_admin) 
                VALUES (?, ?, ?, 'مدير النظام', 'System Admin', '123456789', '1990-01-01', 'Gaza', 'Administrator', 'verified', 1)";
        $pdo->prepare($sql)->execute([$phone, $password, $pin]);
        $userId = $pdo->lastInsertId();

        foreach (['ILS', 'USD', 'JOD'] as $curr) {
            $pdo->prepare("INSERT INTO wallets (user_id, balance, currency) VALUES (?, 5000, ?)")->execute([$userId, $curr]);
        }
        echo "<p style='color:#34C759;'>✅ تم إنشاء حساب أدمن جديد بنجاح!</p>";
    }

    echo "<b>بيانات الدخول:</b><br>";
    echo "رقم الهاتف: <span style='color:#007AFF;'>0599000000</span><br>";
    echo "كلمة المرور: <span style='color:#007AFF;'>admin123</span><br><br>";
    echo "<a href='login.php' style='color:#fff; background:#007AFF; padding:10px 20px; border-radius:8px; text-decoration:none;'>جرب تسجيل الدخول الآن</a>";

} catch (Exception $e) {
    echo "<p style='color:#FF3B30;'>❌ خطأ: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>
