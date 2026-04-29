<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$step = (int)($input['step'] ?? 0);
$data = $input['data'] ?? [];

if (!isset($_SESSION['kyc_draft'])) {
    $_SESSION['kyc_draft'] = [];
}

function fail($msg) {
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($step === 1) {
    $fullAr = trim($data['full_name_ar'] ?? '');
    $fullEn = trim($data['full_name_en'] ?? '');
    $idNumber = trim($data['id_number'] ?? '');
    $dob = trim($data['dob'] ?? '');

    if ($fullAr === '' || $fullEn === '' || $idNumber === '' || $dob === '') fail('يرجى تعبئة جميع الحقول الأساسية');
    $dobTs = strtotime($dob);
    if (!$dobTs) fail('تاريخ الميلاد غير صالح');
    $age = (int)floor((time() - $dobTs) / (365.25 * 24 * 3600));
    if ($age < 18) fail('يجب أن تكون بعمر 18 عاماً على الأقل');

    $_SESSION['kyc_draft']['step1'] = compact('fullAr', 'fullEn', 'idNumber', 'dob');
}

if ($step === 2) {
    $phone = trim($data['phone'] ?? '');
    $whatsapp = trim($data['whatsapp_phone'] ?? '');
    $email = trim($data['email'] ?? '');

    if (!preg_match('/^(059|056)\d{7}$/', $phone)) fail('رقم الجوال يجب أن يبدأ بـ 059 أو 056');
    if ($whatsapp !== '' && !preg_match('/^(059|056)\d{7}$/', $whatsapp)) fail('رقم الواتساب غير صالح');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('البريد الإلكتروني غير صالح');

    $_SESSION['kyc_draft']['step2'] = compact('phone', 'whatsapp', 'email');
}

if ($step === 3) {
    $usageType = trim($data['usage_type'] ?? '');
    $profession = trim($data['profession'] ?? '');
    $address = trim($data['address'] ?? '');

    if (!in_array($usageType, ['personal', 'merchant', 'shop'], true)) fail('نوع الاستخدام غير صالح');
    if ($profession === '' || $address === '') fail('يرجى إدخال المهنة والعنوان');

    $_SESSION['kyc_draft']['step3'] = compact('usageType', 'profession', 'address');
}

echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
