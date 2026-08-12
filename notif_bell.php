<?php
/**
 * notif_bell.php — Reusable notification bell for any logged-in header.
 *
 * HOW TO USE:
 *   Include this file anywhere inside your <header> for logged-in users:
 *
 *     <?php if (isset($_SESSION['user_id'])) include("notif_bell.php"); ?>
 *
 * REQUIREMENTS:
 *   - session_start() and peakscinemas_database.php must already be included
 *     before this file is included.
 *   - The CSS block below must be added once to the page's <style> tag
 *     (copy the /* BELL CSS * / section into the page CSS).
 *   - notifications_api.php must be present for the live badge refresh.
 *
 * The bell:
 *   - Shows a red badge with unread count (fetched live via notifications_api.php)
 *   - Single click → opens a dropdown preview of the latest 5 notifications
 *   - Double-click or "See all" → goes to notifications.php full page
 */

// Quick unread count for initial server-side render (avoids flash on load)
$_notif_uid = (int)($_SESSION['user_id'] ?? 0);
$_notif_unread = 0;
if ($_notif_uid && isset($conn)) {
    $__r = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE Customer_ID=$_notif_uid AND IsRead=0");
    if ($__r) $_notif_unread = (int)$__r->fetch_assoc()['c'];
}
?>
<!-- ── Notification Bell ── -->
<div class="notif-bell-wrap" id="notifBellWrap">
    <button class="notif-bell-btn" id="notifBellBtn"
            onclick="notifBellToggle(event)"
            title="Notifications">
        🔔
        <span class="notif-bell-badge" id="notifBellBadge"
              style="<?= $_notif_unread > 0 ? '' : 'display:none;' ?>">
            <?= $_notif_unread > 0 ? ($_notif_unread > 9 ? '9+' : $_notif_unread) : '' ?>
        </span>
    </button>

    <!-- Dropdown -->
    <div class="notif-bell-drop" id="notifBellDrop">
        <div class="nbd-header">
            <span class="nbd-title">🔔 Notifications</span>
            <a href="notifications.php?mark_all=1" class="nbd-mark-all" id="notifMarkAll"
               style="display:none;" onclick="event.stopPropagation()">Mark all read</a>
        </div>
        <div class="nbd-list" id="notifBellList">
            <div class="nbd-loading">Loading…</div>
        </div>
        <a href="notifications.php" class="nbd-see-all" onclick="event.stopPropagation()">
            See all notifications →
        </a>
    </div>
</div>

<style>
/* ── BELL CSS — copy this into your page's <style> if not already present ── */
.notif-bell-wrap { position: relative; }

.notif-bell-btn {
    background: none; border: none; cursor: pointer;
    font-size: 1.15rem; padding: 6px 10px; border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    position: relative; transition: background 0.2s;
    line-height: 1;
}
.notif-bell-btn:hover { background: rgba(255,255,255,0.08); }

.notif-bell-badge {
    position: absolute; top: 2px; right: 2px;
    background: #ff4d4d; color: #fff;
    font-size: 0.52rem; font-weight: 800; font-family: 'Outfit', sans-serif;
    min-width: 16px; height: 16px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 3px; pointer-events: none;
    border: 1.5px solid #0f0f0f;
}

.notif-bell-drop {
    display: none;
    position: absolute; top: calc(100% + 10px); right: 0;
    width: 320px;
    background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px; overflow: hidden;
    box-shadow: 0 20px 50px rgba(0,0,0,0.7);
    z-index: 2000;
    animation: notifDropIn 0.18s ease;
}
.notif-bell-drop.open { display: flex; flex-direction: column; }

@keyframes notifDropIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.nbd-header {
    padding: 13px 16px 10px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; justify-content: space-between;
}
.nbd-title { font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
.nbd-mark-all { font-size: 0.7rem; color: #ff6b6b; font-weight: 600; text-decoration: none; }
.nbd-mark-all:hover { color: #ff4d4d; }

.nbd-list { max-height: 280px; overflow-y: auto; }
.nbd-list::-webkit-scrollbar { width: 3px; }
.nbd-list::-webkit-scrollbar-thumb { background: rgba(255,77,77,0.3); border-radius: 2px; }

.nbd-loading { padding: 24px; text-align: center; font-size: 0.78rem; color: rgba(249,249,249,0.25); }

.nbd-item {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 11px 14px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    text-decoration: none; color: inherit;
    transition: background 0.15s; cursor: pointer;
}
.nbd-item:last-child { border-bottom: none; }
.nbd-item:hover { background: rgba(255,255,255,0.04); }
.nbd-item.unread { background: rgba(255,77,77,0.05); }

.nbd-icon {
    font-size: 1rem; width: 30px; height: 30px; flex-shrink: 0;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.07);
}
.nbd-icon.booking      { background: rgba(76,175,80,0.12); }
.nbd-icon.cancellation { background: rgba(255,77,77,0.12); }
.nbd-icon.refund       { background: rgba(255,193,7,0.12); }

.nbd-item-title { font-size: 0.78rem; font-weight: 700; color: rgba(249,249,249,0.8); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
.nbd-item-msg   { font-size: 0.7rem;  color: rgba(249,249,249,0.35); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
.nbd-item-time  { font-size: 0.62rem; color: rgba(249,249,249,0.2); margin-top: 3px; }
.nbd-dot { width: 7px; height: 7px; border-radius: 50%; background: #ff4d4d; flex-shrink: 0; margin-top: 6px; }

.nbd-empty { padding: 30px 16px; text-align: center; font-size: 0.78rem; color: rgba(249,249,249,0.2); }

.nbd-see-all {
    display: block; padding: 11px 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
    font-size: 0.75rem; font-weight: 600; color: #ff6b6b;
    text-decoration: none; text-align: center;
    transition: background 0.15s;
}
.nbd-see-all:hover { background: rgba(255,77,77,0.08); color: #ff4d4d; }
</style>

<script>
(function() {
    const ICONS = { booking: '🎬', cancellation: '❌', refund: '💰', general: '🔔' };

    function timeAgo(str) {
        const diff = Math.floor((Date.now() - new Date(str)) / 1000);
        if (diff < 60)       return 'Just now';
        if (diff < 3600)     return Math.floor(diff/60) + 'm ago';
        if (diff < 86400)    return Math.floor(diff/3600) + 'h ago';
        if (diff < 604800)   return Math.floor(diff/86400) + 'd ago';
        return new Date(str).toLocaleDateString('en-PH', {month:'short',day:'numeric'});
    }

    function renderDrop(data) {
        const list   = document.getElementById('notifBellList');
        const badge  = document.getElementById('notifBellBadge');
        const markAll = document.getElementById('notifMarkAll');
        if (!list) return;

        // Update badge
        if (data.unread > 0) {
            badge.textContent = data.unread > 9 ? '9+' : data.unread;
            badge.style.display = '';
            if (markAll) markAll.style.display = '';
        } else {
            badge.style.display = 'none';
            if (markAll) markAll.style.display = 'none';
        }

        if (!data.notifications || data.notifications.length === 0) {
            list.innerHTML = '<div class="nbd-empty">No notifications yet 🔔</div>';
            return;
        }

        list.innerHTML = data.notifications.slice(0,5).map(n => `
            <a class="nbd-item ${!n.IsRead ? 'unread' : ''}"
               href="notifications.php?id=${n.Notif_ID}"
               onclick="notifBellClose()">
                <div class="nbd-icon ${n.Type}">${ICONS[n.Type] || '🔔'}</div>
                <div style="flex:1;min-width:0;">
                    <div class="nbd-item-title">${n.Title}</div>
                    <div class="nbd-item-msg">${n.Message}</div>
                    <div class="nbd-item-time">${timeAgo(n.Created_At)}</div>
                </div>
                ${!n.IsRead ? '<div class="nbd-dot"></div>' : ''}
            </a>
        `).join('');
    }

    function loadNotifs() {
        fetch('notifications_api.php?action=fetch')
            .then(r => r.json())
            .then(renderDrop)
            .catch(() => {
                const list = document.getElementById('notifBellList');
                if (list) list.innerHTML = '<div class="nbd-empty">Could not load notifications.</div>';
            });
    }

    window.notifBellToggle = function(e) {
        e.stopPropagation();
        const drop = document.getElementById('notifBellDrop');
        const isOpen = drop.classList.contains('open');
        if (!isOpen) {
            drop.classList.add('open');
            loadNotifs();
        } else {
            drop.classList.remove('open');
        }
    };

    window.notifBellClose = function() {
        const drop = document.getElementById('notifBellDrop');
        if (drop) drop.classList.remove('open');
    };

    // Close on outside click
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('notifBellWrap');
        if (wrap && !wrap.contains(e.target)) notifBellClose();
    });

    // Refresh badge every 60 seconds while page is open
    setInterval(function() {
        fetch('notifications_api.php?action=fetch')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('notifBellBadge');
                if (!badge) return;
                if (data.unread > 0) {
                    badge.textContent = data.unread > 9 ? '9+' : data.unread;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(() => {});
    }, 60000);
})();
</script>
