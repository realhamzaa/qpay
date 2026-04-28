<?php
require_once 'includes/functions.php';
if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullName = $_POST['full_name'];
    $phone = $_POST['phone'];
    $country = $_POST['country'] ?? 'فلسطين';
    $city = $_POST['city'] ?? '';
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $pin = password_hash($_POST['pin'], PASSWORD_DEFAULT);

    if ($country !== 'فلسطين') { $error = '❌ الدولة المتاحة للتسجيل هي فلسطين فقط'; }
    if (!in_array($city, ['غزة','النصيرات','دير البلح','خانيونس','رفح','جباليا','بيت لاهيا','بيت حانون','الزوايدة','البريج','المغازي'])) { $error = '❌ يرجى اختيار مدينة صحيحة من قطاع غزة'; }

    if ($error) {
        // منع المتابعة عند فشل التحقق
    } else

    {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) { throw new Exception("❌ هذا الرقم مسجل مسبقاً"); }

        $stmt = $pdo->prepare("INSERT INTO users (full_name_ar, phone, password_hash, pin_hash) VALUES (?, ?, ?, ?)");
        $stmt->execute([$fullName, $phone, $password, $pin]);
        $userId = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO wallets (user_id, currency, balance) VALUES (?, 'ILS', 0)")->execute([$userId]);
        $pdo->prepare("INSERT INTO wallets (user_id, currency, balance) VALUES (?, 'USD', 0)")->execute([$userId]);
        $pdo->prepare("INSERT INTO wallets (user_id, currency, balance) VALUES (?, 'JOD', 0)")->execute([$userId]);

        $pdo->commit();
        $_SESSION['user_id'] = $userId;
        header("Location: dashboard.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QPay | إنشاء حساب جديد</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #000; padding: 40px 0;">
    <div class="fade-in" style="width: 100%; max-width: 450px; padding: 20px;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-weight: 800; font-size: 2.2rem; letter-spacing: 1px;">QPAY</h1>
            <p style="color: var(--ios-gray); margin-top: 8px;">إنشاء حساب جديد</p>
        </div>

        <div class="glass-card">
            <?php if ($error): ?>
                <div style="background: rgba(255,59,48,0.1); color: var(--ios-red); padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; font-weight: 600;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <label style="font-size: 0.85rem; color: var(--ios-gray); margin-bottom: 6px; display: block; text-align: right;">الاسم الكامل (بالعربية)</label>
                <input type="text" name="full_name" class="form-input" required placeholder="مثال: وليد زهدي">

                <label style="font-size: 0.85rem; color: var(--ios-gray); margin-bottom: 6px; display: block; text-align: right;">رقم الهاتف</label>
                <input type="text" name="phone" class="form-input" required placeholder="05XXXXXXXX">
                

                <label style="font-size: 0.85rem; color: var(--ios-gray); margin-bottom: 6px; display: block; text-align: right;">الدولة</label>
                <input type="text" name="country" class="form-input" value="فلسطين" readonly>

                <label style="font-size: 0.85rem; color: var(--ios-gray); margin-bottom: 6px; display: block; text-align: right;">المدينة (قطاع غزة)</label>
                <select name="city" class="form-input" required>
                    <option value="">اختر المدينة</option>
                    <option value="غزة">غزة</option>
                    <option value="النصيرات">النصيرات</option>
                    <option value="دير البلح">دير البلح</option>
                    <option value="خانيونس">خانيونس</option>
                    <option value="رفح">رفح</option>
                    <option value="جباليا">جباليا</option>
                    <option value="بيت لاهيا">بيت لاهيا</option>
                    <option value="بيت حانون">بيت حانون</option>
                    <option value="الزوايدة">الزوايدة</option>
                    <option value="البريج">البريج</option>
                    <option value="المغازي">المغازي</option>
                </select>

                <label style="font-size: 0.85rem; color: var(--ios-gray); margin-bottom: 6px; display: block; text-align: right;">كلمة المرور</label>
                <input type="password" name="password" class="form-input" required placeholder="أدخل كلمة مرور قوية">

                <label style="font-size: 0.85rem; color: var(--ios-gray); margin-bottom: 6px; display: block; text-align: right;">رمز PIN (4 أرقام للتحويل)</label>
                <input type="password" name="pin" class="form-input" required placeholder="••••" maxlength="4" style="text-align: center; letter-spacing: 10px; font-size: 1.2rem;">

                <button type="submit" class="btn btn-primary" style="margin-top: 15px; padding: 16px;">تأكيد وإنشاء الحساب</button>
            </form>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <p style="color: var(--ios-gray); font-size: 0.9rem;">لديك حساب بالفعل؟ <a href="login.php" style="color: var(--ios-blue); text-decoration: none; font-weight: 700;">تسجيل الدخول</a></p>
        </div>
    </div>
</body>
</html>
