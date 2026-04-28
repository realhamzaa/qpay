/**
 * QPay Real-time Notifications & UI Updates
 */

async function refreshNotifications() {
    try {
        const response = await fetch('get_new_notifications.php');
        const data = await response.json();
        
        if (data.success) {
            // 1. تحديث عداد التنبيهات في الناف بار (إذا لزم الأمر)
            const badges = document.querySelectorAll('.notif-badge');
            if (data.unreadCount > 0) {
                badges.forEach(b => {
                    b.innerText = data.unreadCount;
                    b.style.display = 'block';
                });
            } else {
                badges.forEach(b => b.style.display = 'none');
            }
            
            // 2. تحديث قائمة التنبيهات إذا كان المستخدم في تبويب التنبيهات
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'notifications') {
                // ملاحظة: يمكن توسيع هذا لتحديث القائمة ديناميكياً بدون ريفريش
            }
            
            // 3. عرض تحذير إداري فوراً إذا ظهر جديد
            if (data.activeWarning) {
                // إظهار بانر التحذير إذا لم يكن موجوداً
                if (!document.querySelector('.warning-banner')) {
                    const banner = document.createElement('div');
                    banner.className = 'warning-banner fade-in';
                    banner.innerHTML = `<i class="fa fa-triangle-exclamation"></i> <span>تحذير: ${data.activeWarning}</span> <a href="?tab=notifications" style="color: #fff; margin-right: 15px; text-decoration: underline;">عرض</a>`;
                    document.querySelector('.app-container').prepend(banner);
                }
            }
        }
    } catch (error) {
        console.error('Error fetching notifications:', error);
    }
}

// التحديث كل 15 ثانية
setInterval(refreshNotifications, 15000);
document.addEventListener('DOMContentLoaded', refreshNotifications);
