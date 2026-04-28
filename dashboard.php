<?php
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$daily_limit = (float)($user['daily_limit'] ?? 1000.00);
$daily_usage = (float)($user['current_daily_usage'] ?? 0.00);

$walletStmt = $pdo->prepare("SELECT currency, balance FROM wallets WHERE user_id = ?");
$walletStmt->execute([$userId]);
$walletsRaw = $walletStmt->fetchAll();
$wallets = [];
foreach ($walletsRaw as $w) { $wallets[$w['currency']] = $w['balance']; }

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'wallet';

// جلب المستلمين والمفضلين
$recentStmt = $pdo->prepare("SELECT DISTINCT ru.full_name_ar, ru.phone, MAX(t.created_at) as last_time FROM transactions t JOIN wallets rw ON t.receiver_wallet_id = rw.id JOIN users ru ON rw.user_id = ru.id JOIN wallets sw ON t.sender_wallet_id = sw.id WHERE sw.user_id = ? GROUP BY ru.phone ORDER BY last_time DESC LIMIT 10");
$recentStmt->execute([$userId]); $recents = $recentStmt->fetchAll();

$favStmt = $pdo->prepare("SELECT ru.full_name_ar, ru.phone, COUNT(t.id) as trans_count FROM transactions t JOIN wallets rw ON t.receiver_wallet_id = rw.id JOIN users ru ON rw.user_id = ru.id JOIN wallets sw ON t.sender_wallet_id = sw.id WHERE sw.user_id = ? GROUP BY ru.phone HAVING trans_count >= 1 ORDER BY trans_count DESC LIMIT 10");
$favStmt->execute([$userId]); $favorites = $favStmt->fetchAll();

// تحذيرات إدارية
$warnStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND type = 'warning' AND is_read = 0 ORDER BY created_at DESC LIMIT 1");
$warnStmt->execute([$userId]); $active_warning = $warnStmt->fetch();

if ($tab == 'notifications') { $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]); }

$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$notifStmt->execute([$userId]); $notifications = $notifStmt->fetchAll();

$transStmt = $pdo->prepare("SELECT t.*, su.full_name_ar as sender_name, ru.full_name_ar as receiver_name, su.phone as sender_phone, ru.phone as receiver_phone, sw.currency as sender_curr, rw.currency as receiver_curr FROM transactions t LEFT JOIN wallets sw ON t.sender_wallet_id = sw.id LEFT JOIN users su ON sw.user_id = su.id LEFT JOIN wallets rw ON t.receiver_wallet_id = rw.id LEFT JOIN users ru ON rw.user_id = ru.id WHERE sw.user_id = ? OR rw.user_id = ? ORDER BY t.created_at DESC LIMIT 50");
$transStmt->execute([$userId, $userId]); $transactions = $transStmt->fetchAll();

$comm_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'commission_step_fee'");
$fee_val = (float)($comm_stmt->fetchColumn() ?: 0.5);

function currency_symbol($currency) {
    $map = ['ILS' => '₪', 'USD' => '$', 'JOD' => 'JD'];
    return $map[$currency] ?? $currency;
}

$success_data = null; $error = ''; $pin_msg = ''; $password_msg = ''; $otp_msg = '';

// معالجة تغيير PIN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pin'])) {
    $old = $_POST['old_pin']; $new = $_POST['new_pin']; $conf = $_POST['confirm_pin'];
    if (!password_verify($old, $user['pin_hash'])) { $pin_msg = ['type'=>'error', 'text'=>'❌ الرمز الحالي غير صحيح']; }
    elseif (strlen($new) !== 4 || !ctype_digit($new)) { $pin_msg = ['type'=>'error', 'text'=>'❌ الرمز الجديد يجب أن يكون 4 أرقام']; }
    elseif ($new !== $conf) { $pin_msg = ['type'=>'error', 'text'=>'❌ الرمزين غير متطابقين']; }
    else { $pdo->prepare("UPDATE users SET pin_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $userId]); $pin_msg = ['type'=>'success', 'text'=>'✅ تم تغيير رمز PIN بنجاح']; }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password_old'])) {
    $oldPass = $_POST['old_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_new_password'] ?? '';

    if (!password_verify($oldPass, $user['password_hash'])) { $password_msg = ['type'=>'error', 'text'=>'❌ كلمة المرور الحالية غير صحيحة']; }
    elseif (strlen($newPass) < 6) { $password_msg = ['type'=>'error', 'text'=>'❌ كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل']; }
    elseif ($newPass !== $confirmPass) { $password_msg = ['type'=>'error', 'text'=>'❌ كلمتا المرور غير متطابقتين']; }
    else {
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
        $password_msg = ['type'=>'success', 'text'=>'✅ تم تغيير كلمة المرور بنجاح عبر التحقق بكلمة المرور القديمة'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_password_otp'])) {
    $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $pdo->prepare("INSERT INTO otp_codes (phone, code, expires_at, is_used) VALUES (?, ?, ?, 0)")->execute([$user['phone'], $otpCode, $expiresAt]);
    notifyUser($userId, "رمز OTP لتغيير كلمة المرور: $otpCode (صالح لمدة 10 دقائق)", 'warning');
    $otp_msg = ['type'=>'success', 'text'=>'✅ تم إرسال رمز OTP إلى هاتفك المسجل'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password_otp'])) {
    $otpCode = trim($_POST['otp_code'] ?? '');
    $newPass = $_POST['otp_new_password'] ?? '';
    $confirmPass = $_POST['otp_confirm_password'] ?? '';

    $otpStmt = $pdo->prepare("SELECT * FROM otp_codes WHERE phone = ? AND code = ? AND is_used = 0 AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
    $otpStmt->execute([$user['phone'], $otpCode]);
    $otpRow = $otpStmt->fetch();

    if (!$otpRow) { $otp_msg = ['type'=>'error', 'text'=>'❌ رمز OTP غير صحيح أو منتهي الصلاحية']; }
    elseif (strlen($newPass) < 6) { $otp_msg = ['type'=>'error', 'text'=>'❌ كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل']; }
    elseif ($newPass !== $confirmPass) { $otp_msg = ['type'=>'error', 'text'=>'❌ كلمتا المرور غير متطابقتين']; }
    else {
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
        $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$otpRow['id']]);
        $otp_msg = ['type'=>'success', 'text'=>'✅ تم تغيير كلمة المرور بنجاح عبر OTP'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_transfer'])) {
    $res = transferFunds($userId, $_POST['receiver_phone'], (float)$_POST['amount'], $_POST['currency'], $_POST['pin']);
    if ($res === true) { $success_data = ['amount' => $_POST['amount'], 'currency' => $_POST['currency'], 'receiver' => $_POST['receiver_phone'], 'date' => date('j M Y, H:i'), 'id' => rand(100000, 999999)]; } else { $error = $res; }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QPay | المحفظة الرقمية</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <div class="app-container">
        <?php if ($active_warning): ?>
        <div class="warning-banner fade-in">
            <i class="fa fa-triangle-exclamation"></i> <span>تحذير: <?php echo $active_warning['message']; ?></span>
            <a href="?tab=notifications" style="color: #fff; margin-right: 15px; text-decoration: underline;">عرض التفاصيل</a>
        </div>
        <?php endif; ?>

        <div class="app-logo-fixed desktop-logo-fixed"><span class="app-logo-text">QPAY</span></div>
        <div class="main-wrapper">
            <nav class="desktop-nav desktop-only">
                <div class="logo-box" style="margin-bottom: 4rem; text-align: center;"><div class="logo-circle" style="margin: 0 auto 10px;">Q</div><span style="font-size: 1.6rem; font-weight: 800;">QPAY</span></div>
                <a href="?tab=wallet" class="desktop-link <?php echo $tab=='wallet'?'active':''; ?>"><i class="fa fa-wallet"></i> المحفظة</a>
                <a href="?tab=transfers" class="desktop-link <?php echo $tab=='transfers'?'active':''; ?>"><i class="fa fa-exchange-alt"></i> التحويل</a>
                <a href="?tab=history" class="desktop-link <?php echo $tab=='history'?'active':''; ?>"><i class="fa fa-history"></i> السجل</a>
                <a href="?tab=notifications" class="desktop-link <?php echo $tab=='notifications'?'active':''; ?>"><i class="fa fa-bell"></i> التنبيهات</a>
                <a href="?tab=settings" class="desktop-link <?php echo $tab=='settings'?'active':''; ?>"><i class="fa fa-gear"></i> الإعدادات</a>
            </nav>

            <div class="main-content">
                <!-- 📱 Mobile Minimal Header -->
                <div class="mobile-only app-logo-fixed">
                    <span class="app-logo-text">QPAY</span>
                </div>

                <?php if ($tab == 'wallet'): ?>
                    <h1 style="font-weight: 800; font-size: 2rem; margin-bottom: 2rem; text-align: right; width: 100%;">أهلاً، <?php echo explode(' ', $user['full_name_ar'])[0]; ?></h1>
                    <div class="segmented-control currency-strip"><div class="segment-indicator" id="indicator"></div><button class="segment-btn active" onclick="switchWallet(0, 'ILS')">ILS</button><button class="segment-btn" onclick="switchWallet(1, 'USD')">USD</button><button class="segment-btn" onclick="switchWallet(2, 'JOD')">JOD</button></div>
                    <div class="wallet-card" id="card-bg" style="background: linear-gradient(135deg, #007AFF, #00C7FF);">
                        <span id="curr-label" style="font-weight: 700; opacity: 0.8; letter-spacing: 1px; font-size: 0.9rem;">ILS WALLET</span>
                        <div class="wallet-balance">
                            <span id="curr-balance"><?php echo number_format($wallets['ILS'] ?? 0, 0); ?></span>
                            <span class="wallet-currency-symbol" id="curr-symbol">₪</span>
                        </div>
                    </div>
                    <h3 class="ios-list-header" style="font-weight: 400; text-align: right; margin-right: 5px;">المعاملات الأخيرة</h3>
                    <div class="ios-list">
                        <?php foreach (array_slice($transactions, 0, 5) as $t): $isSender = ($t['sender_phone'] == $user['phone']); ?>
                            <div class="ios-item" onclick="viewTransaction(<?php echo htmlspecialchars(json_encode([
                                'id' => $t['id'], 'amount' => $t['amount'], 'curr' => $t['sender_curr'] ?? $t['receiver_curr'], 
                                'sender' => $t['sender_name'], 'sender_phone' => $t['sender_phone'],
                                'receiver' => $t['receiver_name'], 'receiver_phone' => $t['receiver_phone'],
                                'date' => date('j M Y, H:i', strtotime($t['created_at']))
                            ])); ?>)" style="cursor: pointer;">
                                <div class="ios-icon" style="background: <?php echo $isSender?'rgba(255,59,48,0.1)':'rgba(48, 209, 88, 0.1)'; ?>;"><i class="fa <?php echo $isSender?'fa-arrow-up':'fa-arrow-down'; ?>" style="color: <?php echo $isSender?'var(--ios-red)':'var(--ios-green)'; ?>;"></i></div>
                                <div class="ios-label"><span class="ios-title"><?php echo $isSender ? 'إلى ' . $t['receiver_name'] : 'من ' . $t['sender_name']; ?></span><span class="ios-subtitle"><?php echo time_elapsed_string($t['created_at']); ?></span></div>
                                <div class="ios-value" style="color: <?php echo $isSender?'var(--ios-red)':'var(--ios-green)'; ?>; font-weight: 700;"><?php echo ($isSender?'-':'+') . currency_symbol($t['sender_curr'] ?? $t['receiver_curr']) . ' ' . number_format($t['amount'], 1); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($tab == 'transfers'): ?>
                    <h1 style="font-weight: 800; margin-bottom: 2rem;">التحويل المالي</h1>
                    <div class="glass-card">
                        <div class="segmented-control" style="margin-bottom: 1.5rem;"><div class="segment-indicator" id="rec_indicator" style="width: 50%; right: 2px;"></div><button class="segment-btn active" onclick="switchRecTab(0, 'recent_list')">الأخيرة</button><button class="segment-btn" onclick="switchRecTab(1, 'fav_list')">المفضلون</button></div>
                        <div id="recent_list" class="chip-list"><?php foreach ($recents as $r): ?><div class="recipient-chip" onclick="selectRecipient('<?php echo $r['phone']; ?>')"><span><?php echo explode(' ', $r['full_name_ar'])[0]; ?></span></div><?php endforeach; ?></div>
                        <div id="fav_list" class="chip-list" style="display: none;"><?php foreach ($favorites as $f): ?><div class="recipient-chip" onclick="selectRecipient('<?php echo $f['phone']; ?>')"><span><?php echo explode(' ', $f['full_name_ar'])[0]; ?></span></div><?php endforeach; ?></div>
                    </div>
                    <?php if ($error): ?><div class="transfer-error"><?php echo $error; ?></div><?php endif; ?>
                    <div class="glass-card" style="max-width: 550px; margin: 0 auto;">
                        <form onsubmit="showConfirmation(event, 'full')">
                            <label style="font-size: 0.85rem; color: var(--ios-gray); margin-bottom: 8px; display: block;">رقم هاتف المستلم</label>
                            <input type="text" id="full_phone" class="form-input" required placeholder="05XXXXXXXX">
                            <div id="recipient_name_display" style="margin-top: -10px; margin-bottom: 15px; font-size: 0.9rem; color: var(--ios-green); font-weight: 700; min-height: 1.2rem;"></div>
                            <div style="display: flex; gap: 12px;">
                                <input type="number" id="full_amount" class="form-input" style="flex: 2; font-weight: 700;" required placeholder="0.00">
                                <select id="full_curr" class="form-input" style="flex: 1.2; font-weight: 700; text-align: center;">
                                    <option value="ILS">ILS</option><option value="USD">USD</option><option value="JOD">JOD</option>
                                </select>
                            </div>
                            <input type="password" id="full_pin" class="form-input" required placeholder="رمز PIN" maxlength="4" style="text-align: center; letter-spacing: 12px; font-size: 1.5rem;">
                            <button type="submit" class="btn btn-primary">تأكيد وإرسال الأموال</button>
                        </form>
                    </div>

                <?php elseif ($tab == 'history'): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;"><h1 style="font-weight: 800;">السجل المالي</h1><button type="button" onclick="exportHistoryPdf()" class="btn history-export-btn"><i class="fa fa-file-pdf" style="margin-left: 5px;"></i> تصدير PDF</button></div>
                    <div class="ios-list">
                        <?php foreach ($transactions as $t): $isSender = ($t['sender_phone'] == $user['phone']); ?>
                            <div class="ios-item" onclick="viewTransaction(<?php echo htmlspecialchars(json_encode([
                                'id' => $t['id'], 'amount' => $t['amount'], 'curr' => $t['sender_curr'] ?? $t['receiver_curr'], 
                                'sender' => $t['sender_name'], 'sender_phone' => $t['sender_phone'],
                                'receiver' => $t['receiver_name'], 'receiver_phone' => $t['receiver_phone'],
                                'date' => date('j M Y, H:i', strtotime($t['created_at']))
                            ])); ?>)" style="cursor: pointer;">
                                <div class="ios-icon" style="background: <?php echo $isSender?'rgba(255,59,48,0.1)':'rgba(48, 209, 88, 0.1)'; ?>;"><i class="fa <?php echo $isSender?'fa-arrow-up':'fa-arrow-down'; ?>" style="color: <?php echo $isSender?'var(--ios-red)':'var(--ios-green)'; ?>;"></i></div>
                                <div class="ios-label"><span class="ios-title"><?php echo $isSender ? 'إلى ' . $t['receiver_name'] : 'من ' . $t['sender_name']; ?></span><span class="ios-subtitle"><?php echo time_elapsed_string($t['created_at']); ?></span></div>
                                <div class="ios-value" style="color: <?php echo $isSender?'var(--ios-red)':'var(--ios-green)'; ?>; font-weight: 700; font-size: 1.1rem;"><?php echo ($isSender?'-':'+') . currency_symbol($t['sender_curr'] ?? $t['receiver_curr']) . ' ' . number_format($t['amount'], 1); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($tab == 'notifications'): ?>
                    <h1 style="font-weight: 800; margin-bottom: 2rem;">التنبيهات</h1>
                    <div class="ios-list">
                        <?php if (empty($notifications)) echo "<div style='padding:2rem; text-align:center; color:var(--ios-gray);'>لا يوجد تنبيهات حالياً</div>"; ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="ios-item" style="align-items: flex-start; background: <?php echo ($n['type']=='warning' && !$n['is_read'])?'rgba(255, 59, 48, 0.05)':''; ?>;">
                                <div class="ios-icon" style="background: <?php echo $n['type']=='warning'?'var(--ios-red)':'#5856D6'; ?>;"><i class="fa <?php echo $n['type']=='warning'?'fa-triangle-exclamation':'fa-info-circle'; ?>"></i></div>
                                <div class="ios-label"><span class="ios-title" style="white-space: normal; line-height: 1.5; font-size: 0.95rem;"><?php echo $n['message']; ?></span><span class="ios-subtitle"><?php echo time_elapsed_string($n['created_at']); ?></span></div>
                                <?php if (!$n['is_read']): ?><span style="width: 8px; height: 8px; background: var(--ios-blue); border-radius: 50%; margin-top: 10px;"></span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($tab == 'settings'): ?>
                    <h1 style="font-weight: 800; margin-bottom: 2.5rem; text-align: right;">الإعدادات</h1>
                    
                    <!-- 👤 Profile Section -->
                    <div class="glass-card" style="display: flex; align-items: center; gap: 1.5rem; padding: 2rem; border-radius: 30px; margin-bottom: 2.5rem;">
                        <div style="width: 75px; height: 75px; background: linear-gradient(135deg, var(--ios-blue), #5856D6); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 800; color: #fff; box-shadow: 0 10px 20px rgba(0,122,255,0.3);">
                            <?php echo mb_substr($user['full_name_ar'], 0, 1, 'utf-8'); ?>
                        </div>
                        <div style="flex: 1; text-align: right;">
                            <h2 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 5px;"><?php echo $user['full_name_ar']; ?></h2>
                            <p style="color: var(--ios-gray); font-size: 1rem;"><?php echo $user['phone']; ?></p>
                        </div>
                    </div>

                    <!-- 📊 Account Status -->
                    <div class="ios-list-group">
                        <p class="ios-list-header" style="text-align: right; margin-right: 15px; font-weight: 400; color: var(--ios-gray);">حالة الحساب</p>
                        <div class="glass-card" style="padding: 1.5rem; border-radius: 24px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                                <span style="font-size: 0.95rem; color: #fff; font-weight: 600;">الاستهلاك اليومي</span>
                                <span style="font-weight: 700; font-size: 0.95rem; direction: ltr; display: inline-block;">₪ <?php echo number_format($daily_usage, 0); ?> / <?php echo number_format($daily_limit, 0); ?></span>
                            </div>
                            <div style="width: 100%; height: 10px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
                                <div style="width: <?php echo min(100, ($daily_usage/$daily_limit)*100); ?>%; height: 100%; background: linear-gradient(90deg, var(--ios-blue), #5AC8FA); box-shadow: 0 0 15px var(--ios-blue); transition: width 1s ease;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 🔐 Security & Integration -->
                    <div class="ios-list-group" style="margin-top: 2.5rem;">
                        <p class="ios-list-header" style="text-align: right; margin-right: 15px; font-weight: 400;">الأمان والتكامل</p>
                        <div class="ios-list">
                            <a href="javascript:void(0)" onclick="document.getElementById('pinModal').style.display='flex'" class="ios-item">
                                <div class="ios-icon" style="background: #5856D6;"><i class="fa fa-key"></i></div>
                                <div class="ios-label"><span class="ios-title">تغيير رمز PIN</span><span class="ios-subtitle">تأمين حسابك برمز جديد</span></div>
                                <i class="fa fa-chevron-left ios-arrow"></i>
                            </a>
                            <a href="#" class="ios-item">
                                <div class="ios-icon" style="background: #25D366;"><i class="fab fa-whatsapp"></i></div>
                                <div class="ios-label"><span class="ios-title">ربط الواتساب</span><span class="ios-subtitle"><?php echo $user['whatsapp_phone'] ?: 'غير مربوط حالياً'; ?></span></div>
                                <i class="fa fa-chevron-left ios-arrow"></i>
                            </a>
                            <a href="javascript:void(0)" onclick="document.getElementById('passwordOldModal').style.display='flex'" class="ios-item">
                                <div class="ios-icon" style="background: #FF9500;"><i class="fa fa-lock"></i></div>
                                <div class="ios-label"><span class="ios-title">تغيير كلمة المرور (بالقديمة)</span><span class="ios-subtitle">مسار تحقق باستخدام كلمة المرور الحالية</span></div>
                                <i class="fa fa-chevron-left ios-arrow"></i>
                            </a>
                            <a href="javascript:void(0)" onclick="document.getElementById('passwordOtpModal').style.display='flex'" class="ios-item">
                                <div class="ios-icon" style="background: #34C759;"><i class="fa fa-mobile-alt"></i></div>
                                <div class="ios-label"><span class="ios-title">تغيير كلمة المرور (OTP)</span><span class="ios-subtitle">مسار منفصل عبر رمز للهاتف</span></div>
                                <i class="fa fa-chevron-left ios-arrow"></i>
                            </a>
                            <?php if (!empty($user['is_admin'])): ?>
                            <a href="admin_dashboard.php" class="ios-item">
                                <div class="ios-icon" style="background: #FF3B30;"><i class="fa fa-user-shield"></i></div>
                                <div class="ios-label"><span class="ios-title">دخول لوحة الإدارة</span><span class="ios-subtitle">وصول سريع لحساب الآدمن</span></div>
                                <i class="fa fa-chevron-left ios-arrow"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 🚪 Logout -->
                    <div class="ios-list" style="margin-top: 3rem; border: none; background: transparent;">
                        <a href="logout.php" class="ios-item" style="background: rgba(255, 59, 48, 0.1); border: 1px solid rgba(255, 59, 48, 0.2); border-radius: 20px; justify-content: center;">
                            <div class="ios-icon" style="background: var(--ios-red); width: 35px; height: 35px;"><i class="fa fa-sign-out-alt" style="font-size: 1rem;"></i></div>
                            <span style="color: var(--ios-red); font-weight: 800; font-size: 1.1rem;">تسجيل الخروج</span>
                        </a>
                    </div>
                    <p style="text-align: center; color: var(--ios-gray); font-size: 0.8rem; margin-top: 2rem; opacity: 0.5;">QPay Version 1.2.0 • Build 2026</p>
                <?php endif; ?>
            </div>
        </div>

        <nav class="bottom-nav mobile-only">
            <a href="?tab=wallet" class="nav-item <?php echo $tab=='wallet'?'active':''; ?>"><i class="fa fa-wallet"></i><span>المحفظة</span></a>
            <a href="?tab=transfers" class="nav-item <?php echo $tab=='transfers'?'active':''; ?>"><i class="fa fa-exchange-alt"></i><span>تحويل</span></a>
            <a href="?tab=history" class="nav-item <?php echo $tab=='history'?'active':''; ?>"><i class="fa fa-history"></i><span>السجل</span></a>
            <a href="?tab=notifications" class="nav-item <?php echo $tab=='notifications'?'active':''; ?>"><i class="fa fa-bell"></i><span>التنبيهات</span></a>
            <a href="?tab=settings" class="nav-item <?php echo $tab=='settings'?'active':''; ?>"><i class="fa fa-gear"></i><span>الإعدادات</span></a>
        </nav>
    </div>

    <!-- 🧾 Receipt Modal (Success & View) -->
    <div id="receiptModal" class="receipt-modal" style="display: none;">
        <div id="receiptContent" class="receipt-card fade-in">
            <div class="receipt-success-icon"><i class="fa fa-check" id="receipt_icon"></i></div>
            <h2 style="margin-bottom: 0.5rem; font-weight: 800;" id="receipt_title">تفاصيل العملية</h2>
            <p style="color: #8E8E93; margin-bottom: 1.5rem;" id="receipt_id">#000000</p>
            <div class="receipt-row"><span class="receipt-label">من</span><span class="receipt-value" id="receipt_sender">-</span></div>
            <div class="receipt-row"><span class="receipt-label">إلى</span><span class="receipt-value" id="receipt_receiver">-</span></div>
            <div class="receipt-row"><span class="receipt-label">المبلغ</span><span class="receipt-value" id="receipt_amount">-</span></div>
            <div class="receipt-row"><span class="receipt-label">التاريخ</span><span class="receipt-value" id="receipt_date">-</span></div>
            <div style="margin-top: 2.5rem; display: flex; gap: 12px;">
                <button onclick="downloadReceipt()" class="btn" style="flex: 1; background: #000; color: #fff;">حفظ كصورة</button>
                <button onclick="document.getElementById('receiptModal').style.display='none'" class="btn btn-primary" style="flex: 1;">إغلاق</button>
            </div>
        </div>
    </div>

    <!-- Other Modals -->
    <div id="pinModal" class="confirm-modal" <?php if($pin_msg) echo 'style="display:flex"'; ?>>
        <div class="confirm-card fade-in">
            <h3 style="margin-bottom: 1.5rem;">تغيير رمز PIN</h3>
            <?php if ($pin_msg): ?><div style="padding: 12px; border-radius: 12px; margin-bottom: 15px; font-weight: 600; font-size: 0.9rem; background: <?php echo $pin_msg['type']==='success' ? 'rgba(48,209,88,0.15)' : 'rgba(255,59,48,0.15)'; ?>; color: <?php echo $pin_msg['type']==='success' ? 'var(--ios-green)' : 'var(--ios-red)'; ?>; text-align: center;"><?php echo $pin_msg['text']; ?></div><?php endif; ?>
            <form method="POST" action="?tab=settings"><input type="password" name="old_pin" class="form-input" placeholder="الرمز الحالي" required><input type="password" name="new_pin" class="form-input" placeholder="الرمز الجديد" maxlength="4" required><input type="password" name="confirm_pin" class="form-input" placeholder="تأكيد الجديد" maxlength="4" required><div style="display: flex; gap: 12px;"><button type="button" onclick="this.closest('.confirm-modal').style.display='none'" class="btn" style="flex: 1; background: rgba(255,255,255,0.05);">إلغاء</button><button type="submit" name="update_pin" class="btn btn-primary" style="flex: 1;">حفظ</button></div></form>
        </div>
    </div>

    <div id="passwordOldModal" class="confirm-modal" <?php if($password_msg) echo 'style="display:flex"'; ?>>
        <div class="confirm-card fade-in">
            <h3 style="margin-bottom: 1rem;">تغيير كلمة المرور - عبر كلمة المرور الحالية</h3>
            <?php if ($password_msg): ?><div style="padding: 12px; border-radius: 12px; margin-bottom: 15px; font-weight: 600; font-size: 0.9rem; background: <?php echo $password_msg['type']==='success' ? 'rgba(48,209,88,0.15)' : 'rgba(255,59,48,0.15)'; ?>; color: <?php echo $password_msg['type']==='success' ? 'var(--ios-green)' : 'var(--ios-red)'; ?>; text-align: center;"><?php echo $password_msg['text']; ?></div><?php endif; ?>
            <form method="POST" action="?tab=settings">
                <input type="password" name="old_password" class="form-input" placeholder="كلمة المرور الحالية" required>
                <input type="password" name="new_password" class="form-input" placeholder="كلمة المرور الجديدة" required>
                <input type="password" name="confirm_new_password" class="form-input" placeholder="تأكيد كلمة المرور الجديدة" required>
                <div style="display:flex; gap:12px;"><button type="button" onclick="this.closest('.confirm-modal').style.display='none'" class="btn" style="flex:1; background: rgba(255,255,255,0.05);">إلغاء</button><button type="submit" name="update_password_old" class="btn btn-primary" style="flex:1;">تحديث</button></div>
            </form>
        </div>
    </div>

    <div id="passwordOtpModal" class="confirm-modal" <?php if($otp_msg) echo 'style="display:flex"'; ?>>
        <div class="confirm-card fade-in">
            <h3 style="margin-bottom: 1rem;">تغيير كلمة المرور - عبر OTP</h3>
            <?php if ($otp_msg): ?><div style="padding: 12px; border-radius: 12px; margin-bottom: 15px; font-weight: 600; font-size: 0.9rem; background: <?php echo $otp_msg['type']==='success' ? 'rgba(48,209,88,0.15)' : 'rgba(255,59,48,0.15)'; ?>; color: <?php echo $otp_msg['type']==='success' ? 'var(--ios-green)' : 'var(--ios-red)'; ?>; text-align: center;"><?php echo $otp_msg['text']; ?></div><?php endif; ?>
            <form method="POST" action="?tab=settings">
                <button type="submit" name="request_password_otp" class="btn" style="background:#5856D6; margin-bottom:1rem;">إرسال رمز OTP للهاتف</button>
            </form>
            <form method="POST" action="?tab=settings">
                <input type="text" name="otp_code" class="form-input" placeholder="أدخل رمز OTP" maxlength="6" required>
                <input type="password" name="otp_new_password" class="form-input" placeholder="كلمة المرور الجديدة" required>
                <input type="password" name="otp_confirm_password" class="form-input" placeholder="تأكيد كلمة المرور الجديدة" required>
                <div style="display:flex; gap:12px;"><button type="button" onclick="this.closest('.confirm-modal').style.display='none'" class="btn" style="flex:1; background: rgba(255,255,255,0.05);">إلغاء</button><button type="submit" name="update_password_otp" class="btn btn-primary" style="flex:1;">تحديث عبر OTP</button></div>
            </form>
        </div>
    </div>

    <div id="confirmModal" class="confirm-modal"><div class="confirm-card fade-in"><h3 style="margin-bottom: 1.5rem;">تأكيد التحويل</h3><div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 24px; margin-bottom: 2rem;"><p style="margin-bottom: 10px;">إلى: <span id="conf_name" style="color: var(--ios-green); font-weight: 800;"></span></p><p style="margin-bottom: 10px;">المبلغ: <span id="conf_amt" style="font-weight: 700;"></span></p><p style="margin-bottom: 10px;">العمولة: <span id="conf_fee" style="color: var(--ios-blue);"></span></p><hr style="border: 0.5px solid var(--border); margin: 15px 0;"><p style="font-weight: 800; font-size: 1.2rem;">الإجمالي: <span id="conf_total"></span></p></div><form method="POST"><input type="hidden" name="receiver_phone" id="post_phone"><input type="hidden" name="amount" id="post_amt"><input type="hidden" name="currency" id="post_curr"><input type="hidden" name="pin" id="post_pin"><div style="display: flex; gap: 12px;"><button type="button" onclick="closeConfirm()" class="btn" style="flex: 1; background: rgba(255,255,255,0.05);">تراجع</button><button type="submit" name="confirm_transfer" class="btn btn-primary" style="flex: 1;">تأكيد وإرسال</button></div></form></div></div>

    <script>
        function viewTransaction(data) {
            document.getElementById('receipt_id').innerText = '#' + data.id;
            document.getElementById('receipt_sender').innerText = data.sender + ' (' + data.sender_phone + ')';
            document.getElementById('receipt_receiver').innerText = data.receiver + ' (' + data.receiver_phone + ')';
            document.getElementById('receipt_amount').innerText = data.amount + ' ' + data.curr;
            document.getElementById('receipt_date').innerText = data.date;
            document.getElementById('receiptModal').style.display = 'flex';
        }
        function downloadReceipt() { html2canvas(document.querySelector("#receiptContent")).then(canvas => { const link=document.createElement('a'); link.download='Receipt_QPay.png'; link.href=canvas.toDataURL(); link.click(); }); }
        
        <?php if ($success_data): ?>
            viewTransaction(<?php echo json_encode([
                'id' => $success_data['id'], 'amount' => $success_data['amount'], 'curr' => $success_data['currency'],
                'sender' => $user['full_name_ar'], 'sender_phone' => $user['phone'],
                'receiver' => $success_data['receiver'], 'receiver_phone' => $success_data['receiver'],
                'date' => $success_data['date']
            ]); ?>);
            for(let i=0; i<15; i++){ setTimeout(()=>{ const el=document.createElement('div'); el.className='money-fly'; el.innerHTML='💸'; el.style.left=Math.random()*100+'vw'; el.style.top='100vh'; document.body.appendChild(el); setTimeout(()=>el.remove(), 2000); }, i*100); }
        <?php endif; ?>

        function selectRecipient(phone) { document.getElementById('full_phone').value = phone; fetchRecipientName(phone); }
        function switchRecTab(index, listId) { 
            document.getElementById('rec_indicator').style.right = (index * 50) + '%'; 
            document.getElementById('recent_list').style.display = listId === 'recent_list' ? 'flex' : 'none'; 
            document.getElementById('fav_list').style.display = listId === 'fav_list' ? 'flex' : 'none'; 
        }
        const walletsData = { 
            'ILS': { balance: '<?php echo number_format($wallets['ILS'] ?? 0, 0); ?>', symbol: '₪', bg: 'linear-gradient(135deg, #007AFF, #00C7FF)', label: 'ILS WALLET' }, 
            'USD': { balance: '<?php echo number_format($wallets['USD'] ?? 0, 2); ?>', symbol: '$', bg: 'linear-gradient(135deg, #2C2C2E, #1C1C1E)', label: 'USD WALLET' }, 
            'JOD': { balance: '<?php echo number_format($wallets['JOD'] ?? 0, 2); ?>', symbol: 'JD', bg: 'linear-gradient(135deg, #FF9500, #FFCC00)', label: 'JOD WALLET' } 
        };
        function switchWallet(index, currency) { 
            document.getElementById('indicator').style.right = (index * 33.33) + '%'; 
            const card = walletsData[currency]; 
            document.getElementById('card-bg').style.background = card.bg; 
            document.getElementById('curr-label').innerText = card.label; 
            document.getElementById('curr-balance').innerText = card.balance; 
            document.getElementById('curr-symbol').innerText = card.symbol;
            document.querySelectorAll('.segmented-control .segment-btn').forEach((b, i) => { if(i === index) b.classList.add('active'); else b.classList.remove('active'); });
        }
        async function fetchRecipientName(phone) { if (phone.length < 10) return null; const res = await fetch('get_recipient.php?phone=' + phone); const data = await res.json(); const display = document.getElementById('recipient_name_display'); if (data.success) { display.innerText = '✅ ' + data.name; return data.name; } else { display.innerText = '❌ غير مسجل'; return null; } }
        async function showConfirmation(e, type) { e.preventDefault(); const phone = document.getElementById('full_phone').value; const amt = parseFloat(document.getElementById('full_amount').value); const curr = document.getElementById('full_curr').value; const pin = document.getElementById('full_pin').value; const name = await fetchRecipientName(phone); if (!name) { alert("المستلم غير موجود!"); return; } const fee = Math.ceil(amt/50)*<?php echo $fee_val; ?>; document.getElementById('conf_name').innerText = name; document.getElementById('conf_amt').innerText = amt + " " + curr; document.getElementById('conf_fee').innerText = fee + " " + curr; document.getElementById('conf_total').innerText = (amt+fee) + " " + curr; document.getElementById('post_phone').value = phone; document.getElementById('post_amt').value = amt; document.getElementById('post_curr').value = curr; document.getElementById('post_pin').value = pin; document.getElementById('confirmModal').style.display = 'flex'; }

        function exportHistoryPdf() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'p', unit: 'pt', format: 'a4' });
            doc.setFontSize(16);
            doc.text('QPAY - Financial History Report', 40, 40);
            doc.setFontSize(11);
            doc.text('Account: <?php echo addslashes($user['full_name_ar']); ?>', 40, 65);
            doc.text('Phone: <?php echo addslashes($user['phone']); ?>', 40, 82);
            doc.text('Generated: <?php echo date('Y-m-d H:i'); ?>', 40, 99);

            const rows = <?php echo json_encode(array_map(function($t) use ($user) {
                $isSender = ($t['sender_phone'] == $user['phone']);
                $curr = $t['sender_curr'] ?? $t['receiver_curr'];
                $symbol = currency_symbol($curr);
                return [
                    date('Y-m-d H:i', strtotime($t['created_at'])),
                    $isSender ? 'Outgoing' : 'Incoming',
                    ($isSender ? '-' : '+') . $symbol . ' ' . number_format($t['amount'], 1),
                    $t['sender_name'] ?? '-',
                    $t['receiver_name'] ?? '-'
                ];
            }, $transactions), JSON_UNESCAPED_UNICODE); ?>;

            doc.autoTable({
                startY: 120,
                head: [['Date', 'Type', 'Amount', 'Sender', 'Receiver']],
                body: rows,
                theme: 'grid',
                headStyles: { fillColor: [0, 122, 255] },
                styles: { fontSize: 9, cellPadding: 6 }
            });
            doc.save('QPay_History_<?php echo date('Y-m-d'); ?>.pdf');
        }

        function closeConfirm() { document.getElementById('confirmModal').style.display = 'none'; }
    </script>
</body>
</html>
