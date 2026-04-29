<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

function fail($msg) {
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$draft = $_SESSION['kyc_draft'] ?? [];
if (empty($draft['step1']) || empty($draft['step2']) || empty($draft['step3'])) {
    fail('الرجاء إكمال جميع خطوات التسجيل أولاً');
}

$password = $_POST['password'] ?? '';
$pin = $_POST['pin'] ?? '';
$agree = $_POST['agree_data'] ?? '';
if (strlen($password) < 6) fail('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
if (!preg_match('/^\d{4}$/', $pin)) fail('رمز PIN يجب أن يكون 4 أرقام');
if ($agree !== '1') fail('يجب الموافقة على التعهد بصحة البيانات');

if (empty($_FILES['id_image']['name']) || empty($_FILES['selfie_image']['name'])) {
    fail('يرجى رفع صورة الهوية وصورة السيلفي');
}

function saveImage($file, $prefix, $dir) {
    if ($file['error'] !== UPLOAD_ERR_OK) { fail('فشل في رفع الملفات'); }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) fail('نوع الملف غير مدعوم، فقط الصور مسموحة');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) fail('تم رفض الملف لعدم مطابقته لصيغة صورة صحيحة');

    $safeName = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $target = rtrim($dir, '/') . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $target)) fail('تعذر حفظ الصورة على السيرفر');
    return $target;
}

$idPath = saveImage($_FILES['id_image'], 'id', __DIR__ . '/../uploads/kyc_ids');
$selfiePath = saveImage($_FILES['selfie_image'], 'selfie', __DIR__ . '/../uploads/kyc_selfies');

try {
    $pdo->beginTransaction();

    $phone = $draft['step2']['phone'];
    $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        throw new Exception('رقم الهاتف مسجل مسبقاً');
    }

    $stmt = $pdo->prepare('INSERT INTO users (phone, password_hash, pin_hash, full_name_ar, dob, whatsapp_phone, email, kyc_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $phone,
        password_hash($password, PASSWORD_DEFAULT),
        password_hash($pin, PASSWORD_DEFAULT),
        $draft['step1']['fullAr'],
        $draft['step1']['dob'],
        $draft['step2']['whatsapp'],
        $draft['step2']['email'],
        'pending'
    ]);

    $userId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO wallets (user_id, currency, balance) VALUES (?, 'ILS', 0)")->execute([$userId]);
    $pdo->prepare("INSERT INTO wallets (user_id, currency, balance) VALUES (?, 'USD', 0)")->execute([$userId]);
    $pdo->prepare("INSERT INTO wallets (user_id, currency, balance) VALUES (?, 'JOD', 0)")->execute([$userId]);

    $kycStmt = $pdo->prepare('INSERT INTO kyc_requests (user_id, full_name_en, id_number, usage_type, profession, address, id_image_path, selfie_image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $kycStmt->execute([
        $userId,
        $draft['step1']['fullEn'],
        $draft['step1']['idNumber'],
        $draft['step3']['usageType'],
        $draft['step3']['profession'],
        $draft['step3']['address'],
        str_replace(__DIR__ . '/../', '', $idPath),
        str_replace(__DIR__ . '/../', '', $selfiePath),
        'pending'
    ]);

    $admins = $pdo->query('SELECT id FROM users WHERE is_admin = 1')->fetchAll();
    foreach ($admins as $admin) {
        notifyUser((int)$admin['id'], 'طلب KYC جديد بانتظار المراجعة', 'warning');
    }

    $pdo->commit();
    $_SESSION['user_id'] = $userId;
    $_SESSION['is_admin'] = 0;
    unset($_SESSION['kyc_draft']);

    echo json_encode(['success' => true, 'redirect' => '../dashboard.php'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $pdo->rollBack();
    fail($e->getMessage());
}
