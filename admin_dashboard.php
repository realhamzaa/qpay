<?php
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) { header("Location: login.php"); exit(); }

$adminId = $_SESSION['user_id'];

$defaultCities = ['غزة','النصيرات','دير البلح','خانيونس','رفح','جباليا','بيت لاهيا','بيت حانون','الزوايدة','البريج','المغازي'];
$defaultCurrencies = ['ILS','USD','JOD'];

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


    if (isset($_POST['entity']) && $_POST['entity'] === 'city') {
        $citiesRaw = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'available_cities'");
        $citiesRaw->execute();
        $cities = json_decode($citiesRaw->fetchColumn() ?: '[]', true);
        if (!is_array($cities) || empty($cities)) { $cities = $defaultCities; }

        if ($_POST['crud'] === 'create' && !empty($_POST['city_name'])) {
            $city = trim($_POST['city_name']);
            if (!in_array($city, $cities)) { $cities[] = $city; }
        } elseif ($_POST['crud'] === 'update' && isset($_POST['old_city'], $_POST['city_name'])) {
            foreach ($cities as &$c) { if ($c === $_POST['old_city']) { $c = trim($_POST['city_name']); } }
        } elseif ($_POST['crud'] === 'delete' && !empty($_POST['old_city'])) {
            $cities = array_values(array_filter($cities, fn($c) => $c !== $_POST['old_city']));
        }
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('available_cities', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([json_encode(array_values(array_unique($cities)), JSON_UNESCAPED_UNICODE)]);
    }

    if (isset($_POST['entity']) && $_POST['entity'] === 'currency') {
        $currRaw = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'available_currencies'");
        $currRaw->execute();
        $currencies = json_decode($currRaw->fetchColumn() ?: '[]', true);
        if (!is_array($currencies) || empty($currencies)) { $currencies = $defaultCurrencies; }

        if ($_POST['crud'] === 'create' && !empty($_POST['currency_code'])) {
            $code = strtoupper(trim($_POST['currency_code']));
            if (!in_array($code, $currencies)) { $currencies[] = $code; }
        } elseif ($_POST['crud'] === 'update' && isset($_POST['old_currency'], $_POST['currency_code'])) {
            foreach ($currencies as &$c) { if ($c === $_POST['old_currency']) { $c = strtoupper(trim($_POST['currency_code'])); } }
        } elseif ($_POST['crud'] === 'delete' && !empty($_POST['old_currency'])) {
            $currencies = array_values(array_filter($currencies, fn($c) => $c !== $_POST['old_currency']));
        }
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('available_currencies', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([json_encode(array_values(array_unique($currencies)), JSON_UNESCAPED_UNICODE)]);
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_user' && !empty($_POST['user_id'])) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_POST['user_id']]);
    }

    if (isset($_POST['action']) && $_POST['action'] === 'toggle_admin' && !empty($_POST['user_id'])) {
        $pdo->prepare("UPDATE users SET is_admin = IF(is_admin = 1, 0, 1) WHERE id = ?")->execute([$_POST['user_id']]);
    }

    if (isset($_POST['action']) && $_POST['action'] === 'approve_kyc' && !empty($_POST['kyc_id'])) {
        $kycId = (int)$_POST['kyc_id'];
        $note = trim($_POST['review_note'] ?? '');
        $stmt = $pdo->prepare("SELECT user_id FROM kyc_requests WHERE id = ?");
        $stmt->execute([$kycId]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("UPDATE kyc_requests SET status='approved', reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?")->execute([$adminId, $note, $kycId]);
            $pdo->prepare("UPDATE users SET kyc_status='approved' WHERE id=?")->execute([$row['user_id']]);
            notifyUser((int)$row['user_id'], 'تمت الموافقة على طلب KYC الخاص بك', 'info');
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'reject_kyc' && !empty($_POST['kyc_id'])) {
        $kycId = (int)$_POST['kyc_id'];
        $note = trim($_POST['review_note'] ?? '');
        $stmt = $pdo->prepare("SELECT user_id FROM kyc_requests WHERE id = ?");
        $stmt->execute([$kycId]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("UPDATE kyc_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?")->execute([$adminId, $note, $kycId]);
            $pdo->prepare("UPDATE users SET kyc_status='rejected' WHERE id=?")->execute([$row['user_id']]);
            notifyUser((int)$row['user_id'], 'تم رفض طلب KYC. راجع الإدارة للمزيد من التفاصيل.', 'warning');
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'send_warning_bulk') {
        $msg = trim($_POST['warning_msg'] ?? '');
        $kycFilter = trim($_POST['kyc_filter'] ?? 'all');
        if ($msg !== '') {
            if ($kycFilter === 'all') {
                $usersStmt = $pdo->prepare("SELECT id FROM users WHERE id != ?");
                $usersStmt->execute([$adminId]);
            } else {
                $usersStmt = $pdo->prepare("SELECT id FROM users WHERE id != ? AND kyc_status = ?");
                $usersStmt->execute([$adminId, $kycFilter]);
            }
            foreach ($usersStmt->fetchAll() as $u) {
                notifyUser((int)$u['id'], $msg, 'warning');
            }
        }
    }
}


$citiesSetting = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'available_cities'")->fetchColumn();
$currenciesSetting = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'available_currencies'")->fetchColumn();
$availableCities = json_decode($citiesSetting ?: '[]', true);
$availableCurrencies = json_decode($currenciesSetting ?: '[]', true);
if (!is_array($availableCities) || empty($availableCities)) { $availableCities = $defaultCities; }
if (!is_array($availableCurrencies) || empty($availableCurrencies)) { $availableCurrencies = $defaultCurrencies; }

// جلب الإحصائيات
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalTrans = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$totalFees = $pdo->query("SELECT SUM(fee_amount) FROM transactions")->fetchColumn() ?: 0;

$kycFilter = $_GET['kyc_filter'] ?? 'pending';
if (!in_array($kycFilter, ['pending','approved','rejected','all'], true)) { $kycFilter = 'pending'; }
if ($kycFilter === 'all') {
    $pendingKycStmt = $pdo->query("SELECT k.*, u.full_name_ar, u.phone FROM kyc_requests k JOIN users u ON u.id = k.user_id ORDER BY k.created_at DESC LIMIT 100");
} else {
    $pendingKycStmt = $pdo->prepare("SELECT k.*, u.full_name_ar, u.phone FROM kyc_requests k JOIN users u ON u.id = k.user_id WHERE k.status = ? ORDER BY k.created_at DESC LIMIT 100");
    $pendingKycStmt->execute([$kycFilter]);
}
$pendingKyc = $pendingKycStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="manifest.json">
    <title>QPay Admin | لوحة الإدارة</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
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


                <h3 class="ios-list-header" style="margin-top:2rem;">تقارير الإدارة (KPIs)</h3>
                <div class="glass-card">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap:10px; margin-bottom:12px;">
                        <input type="date" id="reportFrom" class="form-input" style="margin-bottom:0;">
                        <input type="date" id="reportTo" class="form-input" style="margin-bottom:0;">
                        <button type="button" id="loadReportsBtn" class="btn" style="padding:10px 14px; background:#0A84FF;">تحديث التقرير</button>
                        <button type="button" id="exportReportsBtn" class="btn" style="padding:10px 14px; background:#34C759;">تصدير CSV</button>
                    </div>
                    <div id="reportsSummary" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:10px;"></div>
                    <div id="reportsByCurrency" style="margin-top:12px;"></div>
                    <div id="reportsKyc" style="margin-top:12px;"></div>
                </div>

                <!-- Users Management -->
                <h3 class="ios-list-header">إدارة الحسابات (DataTables + Server-side)</h3>
                <div class="glass-card" style="overflow:auto;">
                    <table id="usersTable" class="display" style="width:100%; color:#fff;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>الاسم</th>
                                <th>الهاتف</th>
                                <th>الحد اليومي</th>
                                <th>الحالة</th>
                                <th>الصلاحية</th>
                                <th>التسجيل</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                    </table>
                </div>


                <h3 class="ios-list-header" style="margin-top:2rem;">طلبات KYC (المعلقة/المقبولة/المرفوضة)</h3>
                <div class="glass-card" style="margin-bottom:1rem;">
                    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
                        <select name="kyc_filter" class="form-input" style="margin-bottom:0; max-width:260px;">
                            <option value="pending" <?php echo $kycFilter==='pending' ? "selected" : ""; ?>>Pending</option>
                            <option value="approved" <?php echo $kycFilter==='approved' ? "selected" : ""; ?>>Approved</option>
                            <option value="rejected" <?php echo $kycFilter==='rejected' ? "selected" : ""; ?>>Rejected</option>
                            <option value="all" <?php echo $kycFilter==='all' ? "selected" : ""; ?>>All</option>
                        </select>
                        <button type="submit" class="btn" style="width:auto; padding:10px 14px; background:#0A84FF;">تطبيق الفلتر</button>
                    </form>
                </div>
                <div class="ios-list">
                    <?php if (empty($pendingKyc)): ?>
                        <div class="ios-item"><div class="ios-label"><span class="ios-title">لا يوجد طلبات معلقة حالياً</span></div></div>
                    <?php endif; ?>
                    <?php foreach ($pendingKyc as $k): ?>
                    <div class="ios-item" style="align-items:flex-start; flex-direction:column; gap:10px;">
                        <div style="width:100%; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <div>
                                <div class="ios-title"><?php echo htmlspecialchars($k['full_name_ar']); ?> (<?php echo htmlspecialchars($k['phone']); ?>)</div>
                                <div class="ios-subtitle">ID: <?php echo htmlspecialchars($k['id_number']); ?> • <?php echo htmlspecialchars($k['usage_type']); ?> • الحالة: <?php echo strtoupper($k['status']); ?><?php if(!empty($k['reviewed_at'])) echo ' • مراجعة: ' . htmlspecialchars($k['reviewed_at']); ?></div>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <a href="<?php echo htmlspecialchars($k['id_image_path']); ?>" target="_blank" class="btn" style="width:auto; padding:8px 12px; background:#2c2c2e;">صورة الهوية</a>
                                <a href="<?php echo htmlspecialchars($k['selfie_image_path']); ?>" target="_blank" class="btn" style="width:auto; padding:8px 12px; background:#2c2c2e;">السيلفي</a>
                            </div>
                        </div>
                        <form method="POST" style="width:100%; display:flex; gap:10px; flex-wrap:wrap;">
                            <input type="hidden" name="kyc_id" value="<?php echo (int)$k['id']; ?>">
                            <input type="text" name="review_note" class="form-input" placeholder="ملاحظة المراجعة (اختياري)" style="margin-bottom:0; flex:1; min-width:240px;">
                            <button type="submit" name="action" value="approve_kyc" class="btn" style="width:auto; background:var(--ios-green); padding:10px 14px;">قبول</button>
                            <button type="submit" name="action" value="reject_kyc" class="btn" style="width:auto; background:var(--ios-red); padding:10px 14px;">رفض</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="ios-list-header" style="margin-top:2rem;">إرسال تحذير جماعي (فلترة)</h3>
                <div class="glass-card">
                    <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
                        <input type="hidden" name="action" value="send_warning_bulk">
                        <select name="kyc_filter" class="form-input" style="margin-bottom:0; max-width:240px;">
                            <option value="all">كل المستخدمين</option>
                            <option value="pending">KYC Pending</option>
                            <option value="approved">KYC Approved</option>
                            <option value="rejected">KYC Rejected</option>
                        </select>
                        <input type="text" name="warning_msg" class="form-input" placeholder="نص التحذير" style="margin-bottom:0; flex:1; min-width:240px;" required>
                        <button type="submit" class="btn" style="width:auto; background:#5856D6; padding:10px 14px;">إرسال</button>
                    </form>
                </div>

                <h3 class="ios-list-header" style="margin-top:2rem;">إدارة المدن (CRUD)</h3>
                <div class="glass-card">
                    <?php foreach ($availableCities as $city): ?>
                    <form method="POST" style="display:flex; gap:10px; margin-bottom:10px; flex-wrap:wrap;">
                        <input type="hidden" name="entity" value="city">
                        <input type="hidden" name="old_city" value="<?php echo htmlspecialchars($city); ?>">
                        <input type="text" name="city_name" value="<?php echo htmlspecialchars($city); ?>" class="form-input" style="margin-bottom:0; flex:1; min-width:180px;">
                        <button type="submit" name="crud" value="update" class="btn" style="width:auto; background:#0A84FF; padding:10px 14px;">تعديل</button>
                        <button type="submit" name="crud" value="delete" class="btn" style="width:auto; background:#FF3B30; padding:10px 14px;">حذف</button>
                    </form>
                    <?php endforeach; ?>
                    <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                        <input type="hidden" name="entity" value="city">
                        <input type="text" name="city_name" placeholder="إضافة مدينة جديدة" class="form-input" style="margin-bottom:0; flex:1; min-width:180px;">
                        <button type="submit" name="crud" value="create" class="btn" style="width:auto; background:#34C759; padding:10px 14px;">إضافة</button>
                    </form>
                </div>

                <h3 class="ios-list-header" style="margin-top:2rem;">إدارة العملات (CRUD)</h3>
                <div class="glass-card">
                    <?php foreach ($availableCurrencies as $currency): ?>
                    <form method="POST" style="display:flex; gap:10px; margin-bottom:10px; flex-wrap:wrap;">
                        <input type="hidden" name="entity" value="currency">
                        <input type="hidden" name="old_currency" value="<?php echo htmlspecialchars($currency); ?>">
                        <input type="text" name="currency_code" value="<?php echo htmlspecialchars($currency); ?>" class="form-input" style="margin-bottom:0; flex:1; min-width:180px;">
                        <button type="submit" name="crud" value="update" class="btn" style="width:auto; background:#0A84FF; padding:10px 14px;">تعديل</button>
                        <button type="submit" name="crud" value="delete" class="btn" style="width:auto; background:#FF3B30; padding:10px 14px;">حذف</button>
                    </form>
                    <?php endforeach; ?>
                    <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                        <input type="hidden" name="entity" value="currency">
                        <input type="text" name="currency_code" placeholder="إضافة عملة جديدة" class="form-input" style="margin-bottom:0; flex:1; min-width:180px; text-transform:uppercase;">
                        <button type="submit" name="crud" value="create" class="btn" style="width:auto; background:#34C759; padding:10px 14px;">إضافة</button>
                    </form>
                </div>

            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('sw.js'));
        }

        async function loadAdminReports() {
            const from = document.getElementById('reportFrom')?.value || '';
            const to = document.getElementById('reportTo')?.value || '';
            const res = await fetch(`api/admin_reports.php?date_from=${encodeURIComponent(from)}&date_to=${encodeURIComponent(to)}`);
            const out = await res.json();
            if (!out.success) return;

            const s = out.summary || {};
            document.getElementById('reportsSummary').innerHTML = `
                <div class="glass-card" style="padding:12px; margin:0;"><div>عدد العمليات</div><strong>${Number(s.total_count||0)}</strong></div>
                <div class="glass-card" style="padding:12px; margin:0;"><div>إجمالي المبالغ</div><strong>${Number(s.total_amount||0).toFixed(2)}</strong></div>
                <div class="glass-card" style="padding:12px; margin:0;"><div>إجمالي العمولات</div><strong>${Number(s.total_fees||0).toFixed(2)}</strong></div>
            `;

            document.getElementById('reportsByCurrency').innerHTML = (out.by_currency||[]).map(c =>
                `<div class="ios-item" style="padding:10px;">${c.currency}: ${Number(c.total_amount).toFixed(2)} (${c.tx_count} عمليات)</div>`
            ).join('') || '<div class="ios-subtitle">لا يوجد بيانات حسب العملة</div>';

            document.getElementById('reportsKyc').innerHTML = (out.kyc||[]).map(k =>
                `<div class="ios-item" style="padding:10px;">KYC ${k.kyc_status}: ${k.count}</div>`
            ).join('') || '<div class="ios-subtitle">لا يوجد بيانات KYC</div>';
        }

        $(function () {
            $('#usersTable').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                ajax: 'admin_users_data.php',
                language: {
                    search: 'بحث:',
                    processing: 'جاري التحميل...',
                    paginate: { previous: 'السابق', next: 'التالي' },
                    lengthMenu: 'عرض _MENU_'
                },
                columnDefs: [
                    { targets: -1, orderable: false, searchable: false }
                ]
            });

            document.getElementById('loadReportsBtn')?.addEventListener('click', loadAdminReports);
            document.getElementById('exportReportsBtn')?.addEventListener('click', () => {
                const from = document.getElementById('reportFrom')?.value || '';
                const to = document.getElementById('reportTo')?.value || '';
                window.open(`api/admin_reports_export.php?date_from=${encodeURIComponent(from)}&date_to=${encodeURIComponent(to)}`, '_blank');
            });
            loadAdminReports();
        });
    </script>

</body>
</html>
