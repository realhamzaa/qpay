<?php
require_once 'includes/db.php';

// هذا الملف مخصص لترقية أول حساب إلى مسؤول وتفعيله
// يرجى استخدامه مرة واحدة ثم حذفه لأسباب أمنية

if (isset($_GET['phone'])) {
    $phone = $_GET['phone'];
    
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1, kyc_status = 'verified' WHERE phone = ?");
    if ($stmt->execute([$phone])) {
        echo "<div style='color: green; font-family: sans-serif;'>✅ تم بنجاح! رقم الهاتف $phone أصبح الآن مسؤولاً (Admin) ومفعلاً. يمكنك تسجيل الدخول الآن.</div>";
    } else {
        echo "<div style='color: red; font-family: sans-serif;'>❌ فشل في تحديث الحساب. تأكد أن الرقم مسجل بالفعل في النظام.</div>";
    }
} else {
    echo "<div style='font-family: sans-serif;'>
            <h3>ترقية حساب مسؤول</h3>
            <p>يرجى إدخال رقم الهاتف المسجل لترقيته:</p>
            <form method='GET'>
                <input type='text' name='phone' placeholder='05XXXXXXXX' required>
                <button type='submit'>ترقية الآن</button>
            </form>
          </div>";
}
?>
