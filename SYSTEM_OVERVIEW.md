# QPAY - System Overview (PHP)

## الهدف
منصة محفظة رقمية محلية بثلاث عملات (ILS, USD, JOD) مع KYC متعدد المراحل ولوحة إدارة.

## الوحدات الأساسية
1. **Auth + Session**
2. **Advanced Multi-Step KYC**
3. **Wallets & Transfers**
4. **Financial History + PDF Export**
5. **Notifications**
6. **Admin Dashboard**

## تدفق KYC
- `kyc_register.php` يعرض Stepper من 5 مراحل.
- المراحل 1-3 تتحقق عبر `api/register_step_val.php` (AJAX + session draft).
- المرحلة 4 ترفع الملفات + كلمة مرور + PIN.
- المرحلة 5 ترسل الطلب عبر `api/register_submit.php`.
- الطلب يحفظ في `kyc_requests` بحالة `pending` مع إشعار للأدمن.

## قاعدة البيانات
- `users`
- `wallets`
- `transactions`
- `notifications`
- `settings`
- `kyc_requests`

## الأمان
- `password_hash` لكلمة المرور وPIN.
- فحص امتدادات + MIME للصور.
- أسماء ملفات عشوائية غير قابلة للتخمين.
- منع قبول التسجيل إذا العمر أقل من 18.
- التحقق من نمط الهاتف الفلسطيني (059/056).

## ملاحظات تطوير
- أي AI أو مطور جديد يبدأ من:
  - `includes/db.php` لبنية البيانات
  - `kyc_register.php` لتجربة التسجيل
  - `api/register_step_val.php`, `api/register_submit.php` لمنطق التحقق والحفظ
  - `dashboard.php` للعمليات المالية
  - `admin_dashboard.php` للتحكم الإداري


## مراجعة KYC (Admin)
- تظهر الطلبات المعلقة في `admin_dashboard.php`.
- الأدمن يستطيع قبول/رفض الطلب مع ملاحظة.
- عند القبول يتم تحديث `users.kyc_status = approved`.
- التحويلات المالية للمستخدم العادي لا تُقبل قبل اعتماد KYC.
