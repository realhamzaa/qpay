/**
 * QPay Real-time Notifications (cursor based)
 */
let notifCursor = 0;

async function markReadUpTo(uptoId) {
    try {
        await fetch('api/notifications_mark_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `upto_id=${encodeURIComponent(uptoId)}`
        });
    } catch (e) {}
}


function paintBadge(count) {
    const badges = document.querySelectorAll('.notif-badge');
    if (count > 0) {
        badges.forEach(b => { b.innerText = count; b.style.display = 'block'; });
    } else {
        badges.forEach(b => b.style.display = 'none');
    }
}

async function refreshNotifications() {
    try {
        const response = await fetch(`api/notifications_feed.php?since_id=${notifCursor}&limit=15`);
        const data = await response.json();
        if (!data.success) return;

        paintBadge(data.unread_count || 0);
        notifCursor = Math.max(notifCursor, Number(data.latest_id || 0));

        const isNotificationsTab = new URLSearchParams(window.location.search).get('tab') === 'notifications';
        if (isNotificationsTab && Number(data.latest_id || 0) > 0) {
            markReadUpTo(Number(data.latest_id));
            paintBadge(0);
        }

        if (Array.isArray(data.items) && data.items.length > 0 && !document.querySelector('.warning-banner')) {
            const warning = data.items.find(n => n.type === 'warning' && Number(n.is_read) === 0);
            if (warning) {
                const banner = document.createElement('div');
                banner.className = 'warning-banner fade-in';
                banner.innerHTML = `<i class="fa fa-triangle-exclamation"></i> <span>تحذير: ${warning.message}</span> <a href="?tab=notifications" style="color: #fff; margin-right: 15px; text-decoration: underline;">عرض</a>`;
                document.querySelector('.app-container')?.prepend(banner);
            }
        }
    } catch (error) {
        console.error('Error fetching notifications:', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    refreshNotifications();
    setInterval(refreshNotifications, 10000);
});
