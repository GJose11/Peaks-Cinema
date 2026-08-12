<?php
session_start();
include_once("peakscinemas_database.php");

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
$uid = (int)$_SESSION['user_id'];

if (isset($_GET['id'])) {
    $nid = (int)$_GET['id'];
    $conn->query("UPDATE notifications SET IsRead = 1 WHERE Notif_ID = $nid AND Customer_ID = $uid");
}

$profile_photo = $_SESSION['profile_photo'] ?? null;
$stmt = $conn->prepare("SELECT Name, ProfilePhoto FROM customer WHERE Customer_ID = ?");
$stmt->bind_param("i", $uid); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$user_name = $row['Name'] ?? 'User';
if ($profile_photo === null) $profile_photo = $row['ProfilePhoto'] ?? null;
$np = explode(' ', trim($user_name));
$user_initials = strtoupper(substr($np[0]??'',0,1).substr(end($np)??'',0,1));
if (strlen($user_initials)===1) $user_initials = strtoupper(substr($np[0]??'',0,2));

$stmt = $conn->prepare("SELECT Notif_ID,Title,Message,Type,IsRead,Created_At FROM notifications WHERE Customer_ID=? ORDER BY Created_At DESC");
$stmt->bind_param("i",$uid); $stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($notifications as &$n) {
    $ts = strtotime($n['Created_At']); $diff = time()-$ts;
    if($diff<60) $n['time_ago']='Just now';
    elseif($diff<3600) $n['time_ago']=floor($diff/60).'m ago';
    elseif($diff<86400) $n['time_ago']=floor($diff/3600).'h ago';
    elseif($diff<604800) $n['time_ago']=floor($diff/86400).'d ago';
    else $n['time_ago']=date('M d, Y',$ts);
} unset($n);

$unread_notifs = array_values(array_filter($notifications, fn($n)=>!$n['IsRead']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Notifications – Peak's Cinema</title>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Outfit',sans-serif;background:#0f0f0f;color:#F9F9F9;min-height:100vh;padding-top:80px;padding-bottom:80px;}
body::before{content:'';position:fixed;inset:0;background:url('movie-background-collage.jpg') center/cover no-repeat;opacity:0.09;z-index:0;pointer-events:none;}
body::after{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at center,transparent 10%,rgba(15,15,15,0.6) 60%,#0f0f0f 100%);z-index:1;pointer-events:none;}

/* Header */
header{background:#161616;display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:fixed;top:0;left:0;width:100%;height:60px;z-index:1000;border-bottom:1px solid rgba(255,255,255,0.07);transition:transform 0.35s cubic-bezier(0.4,0,0.2,1);}
.logo,.brand-logo-wrap{display:inline-flex;align-items:center;text-decoration:none;transition:transform 0.2s cubic-bezier(0.4,0,0.2,1);cursor:pointer;flex-shrink:0;}
.brand-logo-wrap:hover{transform:scale(1.05);}
.brand-logo{height:34px;width:auto;filter:invert(1);display:block;}
.header-actions{display:flex;align-items:center;gap:10px;}
.bookings-btn{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:7px;padding:6px 9px;color:rgba(249,249,249,0.65);font-size:0.92rem;cursor:pointer;transition:all 0.25s;font-family:'Outfit',sans-serif;display:flex;align-items:center;height:32px;}
.bookings-btn:hover{background:rgba(255,255,255,0.12);color:#F9F9F9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
.bookings-btn.here,.bookings-btn.active{background:rgba(255,77,77,0.12);border-color:rgba(255,77,77,0.4);color:#ff4d4d;}
.bookings-btn::after{content:'My Bookings';font-size:0.75rem;font-weight:600;}
.notif-btn{position:relative;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:7px;padding:6px 9px;color:rgba(249,249,249,0.65);font-size:0.92rem;cursor:pointer;transition:all 0.25s;font-family:'Outfit',sans-serif;display:flex;align-items:center;height:32px;}
.notif-btn:hover{background:rgba(255,255,255,0.12);color:#F9F9F9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
.notif-btn.here,.notif-btn.active{background:rgba(255,77,77,0.12);border-color:rgba(255,77,77,0.4);color:#ff4d4d;}
.notif-wrap{position:relative;}
.notif-badge{position:absolute;top:-4px;right:-4px;background:#ff4d4d;color:#fff;font-size:0.55rem;font-weight:800;min-width:16px;height:16px;border-radius:8px;display:none;align-items:center;justify-content:center;padding:0 4px;}
.notif-dropdown{display:none;position:absolute;top:calc(100% + 8px);right:0;width:320px;background:#1a1a1a;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,0.6);z-index:2000;}
.notif-dropdown.open{display:block;}
.notif-header{padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between;}
.notif-header span{font-size:0.78rem;font-weight:700;color:rgba(249,249,249,0.5);letter-spacing:1px;text-transform:uppercase;}
.notif-mark-all{font-size:0.7rem;color:#ff6b6b;cursor:pointer;background:none;border:none;font-family:'Outfit',sans-serif;font-weight:600;}
.notif-list{max-height:280px;overflow-y:auto;}
.notif-item{padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.04);cursor:pointer;transition:background 0.15s;display:flex;gap:10px;align-items:flex-start;text-decoration:none;color:inherit;}
.notif-item:hover{background:rgba(255,255,255,0.04);}
.notif-item.unread{background:rgba(255,77,77,0.05);}
.notif-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px;background:#ff4d4d;box-shadow:0 0 8px rgba(255,77,77,0.5);}
.notif-dot.read{background:transparent;}
.notif-item-body{flex:1;min-width:0;}
.notif-item-title{font-size:0.8rem;font-weight:700;margin-bottom:2px;}
.notif-item-msg{font-size:0.72rem;color:rgba(249,249,249,0.4);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.notif-item-time{font-size:0.65rem;color:rgba(249,249,249,0.25);margin-top:4px;}
.notif-empty{text-align:center;padding:30px;font-size:0.82rem;color:rgba(249,249,249,0.2);}
.notif-footer-dd{padding:10px 16px;border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:space-between;}
.notif-footer-dd a{font-size:0.75rem;color:#ff6b6b;text-decoration:none;font-weight:600;}
.notif-footer-dd a.dim{color:rgba(249,249,249,0.4);}
.profile-btn{background:#F9F9F9;border:none;border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;padding:0;transition:transform 0.2s,box-shadow 0.2s;flex-shrink:0;}
.profile-btn img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.profile-btn:hover{transform:scale(1.08);box-shadow:0 0 14px rgba(255,255,255,0.25);}
.profile-initials{width:100%;height:100%;border-radius:50%;background:linear-gradient(135deg,#ff4d4d,#c0392b);display:flex;align-items:center;justify-content:center;font-size:0.78rem;font-weight:800;color:#fff;}

/* Page */
.page{position:relative;z-index:10;width:92%;max-width:920px;margin:0 auto;}
.page-eyebrow{font-size:0.65rem;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;color:#ff4d4d;margin-bottom:6px;}
.page-heading{font-size:1.75rem;font-weight:900;letter-spacing:-0.5px;margin-bottom:6px;}
.page-sub{font-size:0.82rem;color:rgba(249,249,249,0.38);margin-bottom:28px;}

/* Tabs */
.tabs-row{display:flex;gap:6px;margin-bottom:24px;background:#161616;border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:5px;width:fit-content;}
.tab{padding:8px 22px;border-radius:9px;border:none;font-family:'Outfit',sans-serif;font-size:0.82rem;font-weight:700;color:rgba(249,249,249,0.45);background:none;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:7px;white-space:nowrap;}
.tab.active{background:#ff4d4d;color:#fff;box-shadow:0 3px 12px rgba(255,77,77,0.3);}
.tab:hover:not(.active){background:rgba(255,255,255,0.06);color:#F9F9F9;}
.tab-count{background:rgba(255,255,255,0.18);color:#fff;font-size:0.62rem;font-weight:800;padding:1px 7px;border-radius:10px;line-height:1.6;}
.tab:not(.active) .tab-count{background:rgba(255,255,255,0.07);color:rgba(249,249,249,0.45);}

/* Actions bar */
.bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:12px;}
.bar-info{font-size:0.78rem;color:rgba(249,249,249,0.35);}
.btn-mark-all{padding:7px 18px;border-radius:8px;border:1px solid rgba(255,77,77,0.3);background:rgba(255,77,77,0.08);color:#ff6b6b;font-family:'Outfit',sans-serif;font-size:0.75rem;font-weight:700;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:5px;}
.btn-mark-all:hover{background:#ff4d4d;color:#fff;border-color:#ff4d4d;}

/* Notification card */
.notif-card{background:#161616;border:1px solid rgba(255,255,255,0.07);border-radius:16px;margin-bottom:10px;overflow:hidden;transition:border-color 0.2s,box-shadow 0.2s,background 0.2s;box-shadow:0 2px 12px rgba(0,0,0,0.3);position:relative;}
.notif-card:hover{border-color:rgba(255,255,255,0.14);box-shadow:0 8px 28px rgba(0,0,0,0.5);background:#1a1a1a;}
.notif-card.unread{border-color:rgba(255,77,77,0.2);background:rgba(255,77,77,0.03);}
.notif-card.unread:hover{border-color:rgba(255,77,77,0.35);}
.accent-bar{position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(to bottom,#ff4d4d,#c0392b);display:none;}
.notif-card.unread .accent-bar{display:block;}
.notif-inner{display:flex;align-items:flex-start;gap:14px;padding:18px 20px 18px 22px;}
.notif-status{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:5px;background:#ff4d4d;box-shadow:0 0 8px rgba(255,77,77,0.5);}
.notif-card.read .notif-status{background:rgba(255,255,255,0.12);box-shadow:none;}
.notif-icon-box{width:42px;height:42px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-top:-1px;}
.type-booking .notif-icon-box{background:rgba(102,187,106,0.1);border:1px solid rgba(102,187,106,0.2);}
.type-cancellation .notif-icon-box{background:rgba(255,77,77,0.1);border:1px solid rgba(255,77,77,0.2);}
.type-reminder .notif-icon-box{background:rgba(255,213,79,0.1);border:1px solid rgba(255,213,79,0.2);}
.type-default .notif-icon-box{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);}
.notif-body{flex:1;min-width:0;}
.notif-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:5px;}
.notif-title{font-size:0.95rem;font-weight:800;color:#F9F9F9;line-height:1.3;}
.notif-card.read .notif-title{color:rgba(249,249,249,0.6);font-weight:700;}
.notif-time{font-size:0.68rem;color:rgba(249,249,249,0.3);white-space:nowrap;margin-top:3px;flex-shrink:0;}
.notif-message{font-size:0.82rem;color:rgba(249,249,249,0.55);line-height:1.65;}
.notif-card.read .notif-message{color:rgba(249,249,249,0.38);}
.notif-footer-row{display:flex;align-items:center;gap:10px;margin-top:10px;}
.notif-type-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;}
.chip-booking{background:rgba(102,187,106,0.12);border:1px solid rgba(102,187,106,0.25);color:#81c784;}
.chip-cancellation{background:rgba(255,77,77,0.1);border:1px solid rgba(255,77,77,0.22);color:#ff6b6b;}
.chip-reminder{background:rgba(255,213,79,0.1);border:1px solid rgba(255,213,79,0.25);color:#FFD54F;}
.chip-default{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:rgba(249,249,249,0.4);}
.notif-read-label{font-size:0.65rem;color:rgba(249,249,249,0.25);font-weight:600;margin-left:auto;}
.notif-card.unread .notif-read-label{color:#ff6b6b;}

/* Empty */
.empty-state{text-align:center;padding:60px 20px;color:rgba(249,249,249,0.25);}
.empty-icon{font-size:3rem;margin-bottom:14px;opacity:0.7;}
.empty-title{font-size:1rem;font-weight:800;color:rgba(249,249,249,0.4);margin-bottom:6px;}
.empty-sub{font-size:0.8rem;margin-bottom:20px;}
.empty-link{display:inline-block;padding:9px 24px;border-radius:9px;background:#ff4d4d;color:#fff;font-weight:700;font-size:0.82rem;text-decoration:none;transition:all 0.2s;}
.empty-link:hover{background:#e03c3c;transform:translateY(-1px);}

@media(max-width:600px){
    .page{width:95%;}
    .page-heading{font-size:1.45rem;}
    .notif-icon-box{display:none;}
    .notif-inner{padding:14px 16px;gap:10px;}
    .tabs-row{width:100%;}
    .tab{flex:1;justify-content:center;padding:8px 10px;font-size:0.78rem;}
}
</style>
</head>
<body>

<header id="mainHeader">
    <a href="home.php" class="brand-logo-wrap" title="Peak's Cinema - Home">
        <img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo">
    </a>
    <div class="header-actions">
        <button type="button" class="bookings-btn" onclick="window.location.href='my_bookings.php'" title="My Bookings">
            🎟
        </button>
        <div class="notif-wrap">
            <button class="notif-btn here" id="notifBtn" onclick="toggleNotif(event)" title="Notifications">
                🔔<span class="notif-badge" id="notifBadge"></span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header"><span>Notifications</span><button class="notif-mark-all" onclick="markAllRead()">Mark all read</button></div>
                <div class="notif-list" id="notifList"><div class="notif-empty">Loading...</div></div>
                <div class="notif-footer-dd">
                    <a href="notifications_page.php">View All →</a>
                    <a href="my_bookings.php" class="dim">My Bookings</a>
                </div>
            </div>
        </div>
        <button class="profile-btn" onclick="window.location.href='profile_dashboard.php'" title="Profile Dashboard">
            <?php if(!empty($profile_photo)): ?><img src="<?=htmlspecialchars($profile_photo)?>" referrerpolicy="no-referrer" alt="Profile">
            <?php elseif(!empty($user_initials)): ?><div class="profile-initials"><?=htmlspecialchars($user_initials)?></div>
            <?php else: ?><div class="profile-initials">?</div><?php endif; ?>
        </button>
    </div>
</header>

<div class="page">
    <div class="page-eyebrow">My Account</div>
    <h1 class="page-heading">Notifications</h1>
    <p class="page-sub"><?=count($notifications)?> notification<?=count($notifications)!==1?'s':''?> · <?=count($unread_notifs)?> unread</p>

    <div class="tabs-row">
        <button class="tab active" id="tabUnread" onclick="switchTab('unread')">
            🔔 Unread <?php if(count($unread_notifs)): ?><span class="tab-count"><?=count($unread_notifs)?></span><?php endif; ?>
        </button>
        <button class="tab" id="tabAll" onclick="switchTab('all')">
            📋 All <span class="tab-count"><?=count($notifications)?></span>
        </button>
    </div>

    <!-- Unread -->
    <div id="sectionUnread">
        <?php if(!empty($unread_notifs)): ?>
        <div class="bar">
            <span class="bar-info"><?=count($unread_notifs)?> unread notification<?=count($unread_notifs)>1?'s':''?></span>
            <button class="btn-mark-all" onclick="markAllRead()">✓ Mark all as read</button>
        </div>
        <?php endif; ?>
        <?php if(empty($unread_notifs)): ?>
        <div class="empty-state">
            <div class="empty-icon">✓</div>
            <div class="empty-title">All caught up!</div>
            <p class="empty-sub">No unread notifications right now.</p>
            <a href="home.php" class="empty-link">Browse Movies →</a>
        </div>
        <?php else: foreach($unread_notifs as $n):
            $tk=strtolower($n['Type']??'default');
            $ic=['booking'=>'🎟','cancellation'=>'❌','reminder'=>'⏰'];
            $icon=$ic[$tk]??'🔔'; ?>
        <div class="notif-card unread type-<?=$tk?>">
            <div class="accent-bar"></div>
            <div class="notif-inner">
                <div class="notif-status"></div>
                <div class="notif-icon-box"><?=$icon?></div>
                <div class="notif-body">
                    <div class="notif-top">
                        <div class="notif-title"><?=htmlspecialchars($n['Title'])?></div>
                        <div class="notif-time"><?=htmlspecialchars($n['time_ago'])?></div>
                    </div>
                    <p class="notif-message"><?=htmlspecialchars($n['Message'])?></p>
                    <div class="notif-footer-row">
                        <?php if($n['Type']): ?><span class="notif-type-chip chip-<?=$tk?>"><?=$icon?> <?=ucfirst($tk)?></span><?php endif; ?>
                        <span class="notif-read-label">● Unread</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- All -->
    <div id="sectionAll" style="display:none;">
        <?php if(empty($notifications)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔔</div>
            <div class="empty-title">No notifications yet</div>
            <p class="empty-sub">Notifications about your bookings will appear here.</p>
            <a href="home.php" class="empty-link">Browse Movies →</a>
        </div>
        <?php else: foreach($notifications as $n):
            $tk=strtolower($n['Type']??'default');
            $ic=['booking'=>'🎟','cancellation'=>'❌','reminder'=>'⏰'];
            $icon=$ic[$tk]??'🔔';
            $isRead=(bool)$n['IsRead']; ?>
        <div class="notif-card <?=$isRead?'read':'unread'?> type-<?=$tk?>">
            <?php if(!$isRead): ?><div class="accent-bar"></div><?php endif; ?>
            <div class="notif-inner">
                <div class="notif-status"></div>
                <div class="notif-icon-box"><?=$icon?></div>
                <div class="notif-body">
                    <div class="notif-top">
                        <div class="notif-title"><?=htmlspecialchars($n['Title'])?></div>
                        <div class="notif-time"><?=htmlspecialchars($n['time_ago'])?></div>
                    </div>
                    <p class="notif-message"><?=htmlspecialchars($n['Message'])?></p>
                    <div class="notif-footer-row">
                        <?php if($n['Type']): ?><span class="notif-type-chip chip-<?=$tk?>"><?=$icon?> <?=ucfirst($tk)?></span><?php endif; ?>
                        <span class="notif-read-label"><?=$isRead?'✓ Read':'● Unread'?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
function switchTab(t){
    document.getElementById('sectionUnread').style.display=t==='unread'?'':'none';
    document.getElementById('sectionAll').style.display=t==='all'?'':'none';
    document.getElementById('tabUnread').classList.toggle('active',t==='unread');
    document.getElementById('tabAll').classList.toggle('active',t==='all');
}
function markAllRead(){
    fetch('notifications_api.php?action=mark_read').then(r=>r.json()).then(()=>location.reload()).catch(console.error);
}
(function(){
    const h=document.getElementById('mainHeader');let last=window.scrollY,tick=false;
    window.addEventListener('scroll',()=>{if(!tick){requestAnimationFrame(()=>{const cur=window.scrollY;h.style.transform=(cur>last&&cur>80)?'translateY(-100%)':'translateY(0)';last=cur;tick=false;});tick=true;}},{passive:true});
})();

// Notification dropdown functions
function toggleNotif(e){
    e.stopPropagation();
    const dd=document.getElementById('notifDropdown');
    if(dd){
        dd.classList.toggle('open');
        if(dd.classList.contains('open'))loadNotif();
    }
}

function loadNotif(){
    fetch('notifications_api.php')
        .then(r=>r.json())
        .then(data=>{
            const list=document.getElementById('notifList');
            const badge=document.getElementById('notifBadge');
            if(!list||!data||data.error)return;

            if(data.unread>0){
                badge.textContent=data.unread>9?'9+':data.unread;
                badge.style.display='flex';
            }else{
                badge.style.display='none';
            }

            if(!data.notifications?.length){
                list.innerHTML='<div class="notif-empty">No notifications yet.</div>';
                return;
            }

            list.innerHTML=data.notifications.map(n=>`
                <a class="notif-item ${n.IsRead==0?'unread':''}" href="notifications_page.php?id=${n.Notif_ID}">
                    <div class="notif-dot ${n.IsRead==1?'read':''}"></div>
                    <div class="notif-item-body">
                        <div class="notif-item-title">${n.Title}</div>
                        <div class="notif-item-msg">${n.Message}</div>
                        <div class="notif-item-time">${n.time_ago||'Just now'}</div>
                    </div>
                </a>
            `).join('');
        }).catch(err=>console.error('Notif error:',err));
}

document.addEventListener('click',()=>{
    document.getElementById('notifDropdown')?.classList.remove('open');
});

<?php if(isset($_SESSION['user_id'])): ?>
loadNotif();
setInterval(loadNotif,60000);
<?php endif; ?>
</script>
</body>
</html>
