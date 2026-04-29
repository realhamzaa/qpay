<?php
require_once 'db.php';

// --- وظيفة الوقت الذكي (Relative Time) ---
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'سنة',
        'm' => 'شهر',
        'w' => 'أسبوع',
        'd' => 'يوم',
        'h' => 'ساعة',
        'i' => 'دقيقة',
        's' => 'ثانية',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'منذ ' . implode(', ', $string) : 'الآن';
}

function calculateCommission($amount) {
    global $pdo;
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'commission_%'");
    $settings = [];
    while($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
    $type = $settings['commission_type'] ?? 'step';
    if ($type == 'step') {
        $step = (float)($settings['commission_step_amount'] ?? 50);
        $stepFee = (float)($settings['commission_step_fee'] ?? 0.5);
        return ($amount >= $step) ? ceil($amount / $step) * $stepFee : 0;
    } else {
        $percent = (float)($settings['commission_percentage_value'] ?? 1);
        return $amount * ($percent / 100);
    }
}

function notifyUser($userId, $message, $type = 'info') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $message, $type]);
}

function transferFunds($senderId, $receiverPhone, $amount, $currency, $pin) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$senderId]);
        $sender = $stmt->fetch();
        if (isset($sender['is_frozen']) && $sender['is_frozen']) { throw new Exception("❌ حسابك مجمد."); }
        if (!$sender['is_admin'] && isset($sender['kyc_status']) && $sender['kyc_status'] !== 'approved') { throw new Exception("❌ لا يمكن إجراء التحويل قبل اعتماد KYC."); }
        if (!password_verify($pin, $sender['pin_hash'])) { throw new Exception("❌ رمز PIN خطأ."); }
        if (!$sender['is_admin']) {
            $usage = (float)($sender['current_daily_usage'] ?? 0);
            $limit = (float)($sender['daily_limit'] ?? 1000);
            if ($usage + $amount > $limit) { throw new Exception("❌ تجاوزت الليمت اليومي."); }
        }
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$receiverPhone]);
        $receiver = $stmt->fetch();
        if (!$receiver) { throw new Exception("❌ المستلم غير مسجل."); }
        if ($receiver['id'] == $senderId) { throw new Exception("❌ لا يمكن التحويل لنفسك."); }
        if (!$sender['is_admin']) {
            $recvUsage = (float)($receiver['current_daily_receive'] ?? 0);
            $recvLimit = (float)($receiver['daily_receive_limit'] ?? 2000);
            if ($recvUsage + $amount > $recvLimit) { throw new Exception("❌ المستلم تجاوز سقف الاستقبال اليومي."); }
        }
        $fee = ($sender['is_admin']) ? 0 : calculateCommission($amount);
        $totalDeduction = $amount + $fee;
        $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? AND currency = ? FOR UPDATE");
        $stmt->execute([$senderId, $currency]);
        $senderWallet = $stmt->fetch();
        if (!$senderWallet || $senderWallet['balance'] < $totalDeduction) { throw new Exception("❌ رصيد غير كافٍ."); }
        $stmt = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ? AND currency = ? FOR UPDATE");
        $stmt->execute([$receiver['id'], $currency]);
        $receiverWallet = $stmt->fetch();
        if (!$receiverWallet) { throw new Exception("❌ المستلم لا يملك محفظة بهذه العملة."); }
        $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE id = ?")->execute([$totalDeduction, $senderWallet['id']]);
        $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?")->execute([$amount, $receiverWallet['id']]);
        $pdo->prepare("INSERT INTO transactions (sender_wallet_id, receiver_wallet_id, amount, fee_amount, type) VALUES (?, ?, ?, ?, 'transfer')")->execute([$senderWallet['id'], $receiverWallet['id'], $amount, $fee]);
        if (!$sender['is_admin']) {
            $pdo->prepare("UPDATE users SET current_daily_usage = current_daily_usage + ? WHERE id = ?")->execute([$amount, $senderId]);
            $pdo->prepare("UPDATE users SET current_daily_receive = current_daily_receive + ? WHERE id = ?")->execute([$amount, $receiver['id']]);
        }
        notifyUser($senderId, "تم تحويل $amount $currency إلى $receiverPhone", 'transaction');
        notifyUser($receiver['id'], "وصلتك حوالة بمبلغ $amount $currency من " . $sender['phone'], 'transaction');
        $pdo->commit(); return true;
    } catch (Exception $e) { $pdo->rollBack(); return $e->getMessage(); }
}
?>
