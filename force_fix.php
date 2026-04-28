<?php
require_once 'includes/db.php';

try {
    // إضافة عمود last_login بشكل مباشر ومضمون
    $pdo->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL");
    echo "✅ تم إضافة عمود last_login بنجاح. يمكنك تسجيل الدخول الآن.";
} catch (Exception $e) {
    echo "⚠️ العمود موجود بالفعل أو حدث خطأ: " . $e->getMessage();
}
?>
