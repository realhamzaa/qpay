<?php
require_once 'includes/db.php';

echo "<div style='font-family: sans-serif; padding: 20px; background: #05050a; color: #fff;'>";
echo "<h3>🚀 معالج ترقية المحافظ الفضائية</h3>";

try {
    // 1. الحصول على كل المستخدمين
    $users = $pdo->query("SELECT id FROM users")->fetchAll();
    
    foreach ($users as $u) {
        $userId = $u['id'];
        
        // التحقق من العملات الموجودة
        $existing = $pdo->prepare("SELECT currency FROM wallets WHERE user_id = ?");
        $existing->execute([$userId]);
        $currencies = $existing->fetchAll(PDO::FETCH_COLUMN);
        
        $toAdd = array_diff(['ILS', 'USD', 'JOD'], $currencies);
        
        foreach ($toAdd as $curr) {
            $pdo->prepare("INSERT INTO wallets (user_id, balance, currency) VALUES (?, 0, ?)")->execute([$userId, $curr]);
            echo "<p>✅ تم إضافة محفظة $curr للمستخدم رقم $userId</p>";
        }
    }
    
    echo "<hr><p style='color: #00d2ff;'>✨ اكتملت عملية الترقية! جميع المستخدمين لديهم الآن 3 عملات.</p>";

} catch (Exception $e) {
    echo "<p style='color: #ff007f;'>❌ خطأ: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>
