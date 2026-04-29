<?php
require_once 'includes/functions.php';
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <title>QPay | تسجيل متعدد المراحل</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="app-container" style="padding:20px; max-width:720px; margin:0 auto;">
    <div class="app-logo-fixed" style="position: static; margin-bottom: 1rem;"><span class="app-logo-text">QPAY</span></div>
    <div class="glass-card ios-inset-group">
        <h2 style="margin-bottom:12px;">التسجيل المتقدم (KYC)</h2>
        <div style="height:8px; background:#2c2c2e; border-radius:99px; overflow:hidden; margin-bottom:16px;"><div id="stepBar" style="height:100%; width:20%; background:linear-gradient(90deg,#007AFF,#5AC8FA);"></div></div>
        <p id="stepLabel" style="color:var(--ios-gray); margin-bottom:18px;">المرحلة 1 من 5</p>

        <form id="stepForm" enctype="multipart/form-data">
            <div class="step" data-step="1">
                <input name="full_name_ar" class="form-input" placeholder="الاسم الرباعي بالعربية" required>
                <input name="full_name_en" class="form-input" placeholder="الاسم الرباعي بالإنجليزية" required>
                <input name="id_number" class="form-input" placeholder="رقم الهوية" required>
                <input name="dob" type="date" class="form-input" required>
            </div>
            <div class="step" data-step="2" style="display:none;">
                <input name="phone" class="form-input" placeholder="059xxxxxxx أو 056xxxxxxx" required>
                <input name="whatsapp_phone" class="form-input" placeholder="رقم واتساب (اختياري)">
                <input name="email" type="email" class="form-input" placeholder="البريد الإلكتروني (اختياري)">
            </div>
            <div class="step" data-step="3" style="display:none;">
                <select name="usage_type" class="form-input" required>
                    <option value="">نوع الاستخدام</option>
                    <option value="personal">شخصي</option>
                    <option value="merchant">تاجر</option>
                    <option value="shop">محل</option>
                </select>
                <input name="profession" class="form-input" placeholder="المهنة" required>
                <input name="address" class="form-input" placeholder="العنوان" required>
            </div>
            <div class="step" data-step="4" style="display:none;">
                <label>صورة الهوية</label>
                <input type="file" name="id_image" class="form-input" accept="image/*" required>
                <label>سيلفي مع الهوية</label>
                <input type="file" name="selfie_image" class="form-input" accept="image/*" required>
                <input type="password" name="password" class="form-input" placeholder="كلمة المرور" autocomplete="new-password" required>
                <input type="password" name="pin" class="form-input" placeholder="PIN (4 أرقام)" maxlength="4" required>
                <label style="display:flex; gap:8px; align-items:center;"><input type="checkbox" name="agree_data" value="1" required> أتعهد بصحة البيانات</label>
            </div>
            <div class="step" data-step="5" style="display:none; text-align:center;">
                <h3>مراجعة نهائية</h3>
                <p style="color:var(--ios-gray)">اضغط إرسال لحفظ طلبك بحالة Pending وإرساله للأدمن.</p>
            </div>

            <div id="msg" style="margin:12px 0; font-weight:600;"></div>
            <div style="display:flex; gap:10px; margin-top:12px;">
                <button type="button" id="prevBtn" class="btn" style="background:#2c2c2e; display:none;">السابق</button>
                <button type="button" id="nextBtn" class="btn btn-primary">التالي</button>
            </div>
        </form>
    </div>
</div>
<script>
let step = 1;
const maxStep = 5;
const stepForm = document.getElementById('stepForm');
const msg = document.getElementById('msg');
function renderStep(){
  document.querySelectorAll('.step').forEach(el=>el.style.display = Number(el.dataset.step)===step?'block':'none');
  document.getElementById('stepBar').style.width = (step/maxStep*100)+'%';
  document.getElementById('stepLabel').innerText = `المرحلة ${step} من ${maxStep}`;
  document.getElementById('prevBtn').style.display = step===1?'none':'block';
  document.getElementById('nextBtn').innerText = step===5?'إرسال الطلب':'التالي';
  msg.innerText='';
}
async function validateStep(){
  if(step>=4) return true;
  const fd = new FormData(stepForm);
  const data = {};
  if(step===1){ ['full_name_ar','full_name_en','id_number','dob'].forEach(k=>data[k]=fd.get(k)); }
  if(step===2){ ['phone','whatsapp_phone','email'].forEach(k=>data[k]=fd.get(k)); }
  if(step===3){ ['usage_type','profession','address'].forEach(k=>data[k]=fd.get(k)); }
  const res = await fetch('api/register_step_val.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({step,data})});
  const out = await res.json();
  if(!out.success){ msg.style.color='var(--ios-red)'; msg.innerText=out.message; return false; }
  return true;
}
async function submitFinal(){
  const fd = new FormData(stepForm);
  const res = await fetch('api/register_submit.php',{method:'POST',body:fd});
  const out = await res.json();
  if(!out.success){ msg.style.color='var(--ios-red)'; msg.innerText=out.message; return; }
  window.location.href = out.redirect || 'dashboard.php';
}
document.getElementById('nextBtn').onclick = async ()=>{
  if(step<5){ const ok=await validateStep(); if(!ok) return; step++; renderStep(); return; }
  await submitFinal();
};
document.getElementById('prevBtn').onclick = ()=>{ if(step>1){ step--; renderStep(); }};
renderStep();
</script>
</body>
</html>
