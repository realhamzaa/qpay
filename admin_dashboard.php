<?php
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) { header("Location: login.php"); exit(); }

$adminId = $_SESSION['user_id'];

// معالجة العمليات الإدارية
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $targetId = $_POST['user_id'];
        if ($_POST['action'] == 'freeze') {
            $pdo->prepare("UPDATE users SET is_frozen = 1 WHERE id = ?")->execute([$targetId]);
        } elseif ($_POST['action'] == 'unfreeze') {
            $pdo->prepare("UPDATE users SET is_frozen = 0 WHERE id = ?")->execute([$targetId]);
        } elseif ($_POST['action'] == 'set_limit') {
            $pdo->prepare("UPDATE users SET daily_limit = ? WHERE id = ?")->execute([$_POST['limit'], $targetId]);
        } elseif ($_POST['action'] == 'send_warning') {
            notifyUser($targetId, $_POST['warning_msg'], 'warning');
        } elseif ($_POST['action'] == 'reset_pin') {
            $pdo->prepare("UPDATE users SET pin_hash = ? WHERE id = ?")->execute([password_hash('1234', PASSWORD_DEFAULT), $targetId]);
            notifyUser($targetId, "تم إعادة تعيين رمز PIN الخاص بك إلى 1234 من قبل الإدارة.", 'warning');
        }
    }
}

// جلب الإحصائيات
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalTrans = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$totalFees = $pdo->query("SELECT SUM(fee_amount) FROM transactions")->fetchColumn() ?: 0;

// جلب قائمة المستخدمين
$users = $pdo->query("SELECT * FROM users WHERE id != $adminId ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QPay Admin | لوحة الإدارة</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <div class="main-wrapper">
            <nav class="desktop-nav desktop-only">
                <div class="logo-box" style="margin-bottom: 3rem;"><div class="logo-circle" style="background: var(--ios-red);">A</div><span style="font-size: 1.4rem; font-weight: 800;">ADMIN PANEL</span></div>
                <a href="admin_dashboard.php" class="desktop-link active"><i class="fa fa-chart-line"></i> الإحصائيات</a>
                <a href="dashboard.php" class="desktop-link"><i class="fa fa-user"></i> لوحة المستخدم</a>
                <a href="logout.php" class="desktop-link" style="margin-top: auto; color: var(--ios-red);"><i class="fa fa-sign-out-alt"></i> خروج</a>
            </nav>

            <main class="main-content container fade-in">
                <header style="margin-bottom: 2.5rem;">
                    <h1 style="font-weight: 800; font-size: 2.2rem;">نظام إدارة QPay</h1>
                    <p style="color: var(--ios-gray);">مرحباً بك في لوحة التحكم المركزية</p>
                </header>

                <!-- Stats Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
                    <div class="glass-card" style="text-align: center;">
                        <i class="fa fa-users" style="font-size: 2rem; color: var(--ios-blue); margin-bottom: 15px;"></i>
                        <h4 style="color: var(--ios-gray); font-size: 0.9rem;">إجمالي المستخدمين</h4>
                        <h2 style="font-size: 2rem; font-weight: 800;"><?php echo $totalUsers; ?></h2>
                    </div>
                    <div class="glass-card" style="text-align: center;">
                        <i class="fa fa-exchange-alt" style="font-size: 2rem; color: var(--ios-green); margin-bottom: 15px;"></i>
                        <h4 style="color: var(--ios-gray); font-size: 0.9rem;">إجمالي العمليات</h4>
                        <h2 style="font-size: 2rem; font-weight: 800;"><?php echo $totalTrans; ?></h2>
                    </div>
                    <div class="glass-card" style="text-align: center;">
                        <i class="fa fa-coins" style="font-size: 2rem; color: #FFCC00; margin-bottom: 15px;"></i>
                        <h4 style="color: var(--ios-gray); font-size: 0.9rem;">أرباح العمولات</h4>
                        <h2 style="font-size: 2rem; font-weight: 800;"><?php echo number_format($totalFees, 1); ?> ₪</h2>
                    </div>
                </div>

                <!-- Users Management -->
                <h3 class="ios-list-header">إدارة الحسابات</h3>
                <div class="ios-list">
                    <?php foreach ($users as $u): ?>
                        <div class="ios-item" style="flex-wrap: wrap; gap: 20px;">
                            <div class="ios-icon" style="background: rgba(255,255,255,0.05); font-size: 1.2rem;"><?php echo mb_substr($u['full_name_ar'], 0, 1, 'utf-8'); ?></div>
                            <div class="ios-label" style="min-width: 200px;">
                                <span class="ios-title"><?php echo $u['full_name_ar']; ?> <?php if($u['is_frozen']) echo "<span style='color:var(--ios-red); font-size:0.7rem;'>(مجمد)</span>"; ?></span>
                                <span class="ios-subtitle"><?php echo $u['phone']; ?> | الليمت: <?php echo number_format($u['daily_limit'], 0); ?> ₪</span>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <form method="POST" style="display: inline-flex; gap: 5px;">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="number" name="limit" value="<?php echo $u['daily_limit']; ?>" style="width: 80px; padding: 8px; border-radius: 8px; border: none; background: #2c2c2e; color: #fff;">
                                    <button type="submit" name="action" value="set_limit" class="btn" style="padding: 8px 12px; font-size: 0.8rem; background: var(--ios-blue);">تعديل</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <?php if ($u['is_frozen']): ?>
                                        <button type="submit" name="action" value="unfreeze" class="btn" style="padding: 8px 12px; font-size: 0.8rem; background: var(--ios-green);">فك التجميد</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="freeze" class="btn" style="padding: 8px 12px; font-size: 0.8rem; background: var(--ios-red);">تجميد</button>
                                    <?php endif; ?>
                                </form>
                                <form method="POST" style="display: inline-flex; gap: 5px;">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="text" name="warning_msg" placeholder="نص التحذير..." style="padding: 8px; border-radius: 8px; border: none; background: #2c2c2e; color: #fff; width: 150px;">
                                    <button type="submit" name="action" value="send_warning" class="btn" style="padding: 8px 12px; font-size: 0.8rem; background: #5856D6;"><i class="fa fa-triangle-exclamation"></i></button>
                                <form method="POST" style="display: inline-flex; gap: 5px;" onsubmit="return confirm('هل أنت متأكد من إعادة تعيين PIN لـ 1234؟')">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" name="action" value="reset_pin" class="btn" style="padding: 8px 12px; font-size: 0.8rem; background: #FF9500;"><i class="fa fa-key"></i></button>
                                </form>
                            </div>
                        </div>
<?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
