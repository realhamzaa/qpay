<?php
require_once 'includes/functions.php';
if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = $user['is_admin'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = '❌ بيانات الدخول غير صحيحة';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QPay | تسجيل الدخول</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #000;">
    <div class="fade-in" style="width: 100%; max-width: 400px; padding: 20px;">
        <div style="text-align: center; margin-bottom: 3rem;">
            <div class="logo-circle" style="margin: 0 auto 15px; width: 70px; height: 70px; font-size: 2rem;">Q</div>
            <h1 style="font-weight: 800; font-size: 2.2rem; letter-spacing: 2px;">QPAY</h1>
            <p style="color: var(--ios-gray); margin-top: 10px;">أهلاً بك في مستقبل الدفع الرقمي</p>
        </div>

        <div class="glass-card">
            <?php if ($error): ?>
                <div style="background: rgba(255,59,48,0.1); color: var(--ios-red); padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; font-weight: 600;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <label style="font-size: 0.85rem; color: var(--ios-gray); margin-bottom: 8px; display: block; text-align: right;">رقم الهاتف</label>
                <input type="text" name="phone" class="form-input" required placeholder="05XXXXXXXX">
                
                <label style="font-size: 0.85rem; color: var(--ios-gray); margin-bottom: 8px; display: block; text-align: right;">كلمة المرور</label>
                <input type="password" name="password" class="form-input" required placeholder="••••••••">

                <button type="submit" class="btn btn-primary" style="margin-top: 10px; padding: 16px;">تسجيل الدخول</button>
            </form>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <p style="color: var(--ios-gray); font-size: 0.9rem;">ليس لديك حساب؟ <a href="register.php" style="color: var(--ios-blue); text-decoration: none; font-weight: 700;">إنشاء حساب جديد</a></p>
        </div>
    </div>
</body>
</html>
