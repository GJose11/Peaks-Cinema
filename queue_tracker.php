<?php
/**
 * queue_tracker.php — Live Queue & Wait Time Tracker
 * Peak's Cinema — matches system design (Outfit, #0f0f0f, #ff4d4d)
 */
date_default_timezone_set('Asia/Manila');
include("peakscinemas_database.php");
session_start();

$profile_link  = "personal_info_form.php";
$profile_photo = $_SESSION['profile_photo'] ?? null;
$user_initials = '';
if (isset($_SESSION['user_id'])) {
    $profile_link = "profile_dashboard.php";
    $ps = $conn->prepare("SELECT Name, ProfilePhoto FROM customer WHERE Customer_ID = ?");
    $ps->bind_param("i", $_SESSION['user_id']); $ps->execute();
    $pr = $ps->get_result()->fetch_assoc();
    if (!empty($pr['ProfilePhoto'])) $profile_photo = $pr['ProfilePhoto'];
    $np = explode(' ', trim($pr['Name'] ?? ''));
    $user_initials = strtoupper(substr($np[0]??'',0,1).substr(end($np)??'',0,1));
    if (strlen($user_initials)===1) $user_initials = strtoupper(substr($np[0]??'',0,2));
}

// ── Fetch queue data ─────────────────────────────────────────────
$queueRes = $conn->query("
    SELECT q.*, m.MallName, m.Location
    FROM cinema_queue q
    JOIN mall m ON q.mall_id = m.Mall_ID
    ORDER BY m.MallName ASC, q.area ASC
");
$queueData = $queueRes ? $queueRes->fetch_all(MYSQLI_ASSOC) : [];

$byMall = [];
foreach ($queueData as $row) {
    if (!isset($byMall[$row['mall_id']])) {
        $byMall[$row['mall_id']] = ['name'=>$row['MallName'],'location'=>$row['Location'],'areas'=>[]];
    }
    $byMall[$row['mall_id']]['areas'][] = $row;
}

$mallsRes = $conn->query("SELECT Mall_ID, MallName FROM mall ORDER BY MallName ASC");
$malls    = $mallsRes ? $mallsRes->fetch_all(MYSQLI_ASSOC) : [];

$lastUpdated = !empty($queueData) ? max(array_column($queueData,'updated_at')) : null;
$currentTimeFormatted = null;
$lastUpdatedAgo = null;

// Always show current Manila time
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$currentTimeFormatted = $now->format('g:i A'); // e.g., "10:20 PM"

if ($lastUpdated) {
    // Calculate time ago from last database update
    $dt = new DateTime($lastUpdated, new DateTimeZone('Asia/Manila'));
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    
    // Only show "time ago" if data is less than 24 hours old
    if ($diff < 86400) { // 24 hours = 86400 seconds
        if ($diff < 60) {
            $lastUpdatedAgo = 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            $lastUpdatedAgo = $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        } else {
            $hours = floor($diff / 3600);
            $lastUpdatedAgo = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }
    } else {
        // If older than 24 hours, show days
        $days = floor($diff / 86400);
        $lastUpdatedAgo = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

$totalAreas = count($queueData);
$openAreas  = count(array_filter($queueData, fn($r)=>$r['status']==='open'));
$busyAreas  = count(array_filter($queueData, fn($r)=>$r['status']==='busy'));
$avgWait    = $totalAreas>0 ? round(array_sum(array_column($queueData,'current_wait_mins'))/$totalAreas) : 0;
$maxWait    = $totalAreas>0 ? max(array_column($queueData,'current_wait_mins')) : 0;
$allOpen    = array_filter($queueData, fn($r)=>$r['status']!=='closed');
$bestArea   = !empty($allOpen) ? array_reduce($allOpen, fn($c,$r)=>(!$c||$r['current_wait_mins']<$c['current_wait_mins'])?$r:$c) : null;

$areaLabels = [
    'entrance'   => 'Entrance Gate',     'ticketing'  => 'Ticketing Counter',
    'concession' => 'Concession Stand',  'cinema_1'   => 'Cinema Hall 1',
    'cinema_2'   => 'Cinema Hall 2',     'cinema_3'   => 'Cinema Hall 3',
    'restroom'   => 'Restrooms',         'parking'    => 'Parking Area',
];
$areaIcons = [
    'entrance'=>'🚪','ticketing'=>'🎟','concession'=>'🍿',
    'cinema_1'=>'🎬','cinema_2'=>'🎬','cinema_3'=>'🎬',
    'restroom'=>'🚻','parking'=>'🅿️',
];

function waitColor($m) { return $m<=5?'green':($m<=15?'yellow':'red'); }
function statusLabel($s) {
    return match($s){'open'=>['🟢','Open'],'busy'=>['🟡','Busy'],'closed'=>['🔴','Closed'],default=>['⚪','Unknown']};
}
function human_ago($ts) {
    $d=time()-$ts;
    if($d<60) return 'just now';
    if($d<3600) return round($d/60).'m ago';
    return round($d/3600).'h ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Live Queue Tracker – Peak's Cinema</title>
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Outfit',sans-serif; background:#0f0f0f; color:#F9F9F9; min-height:100vh; padding-top:60px; padding-bottom:60px; }
body::before { content:''; position:fixed; inset:0; background:url('movie-background-collage.jpg') center/cover no-repeat; opacity:0.12; z-index:0; pointer-events:none; }
body::after  { content:''; position:fixed; inset:0; background:radial-gradient(ellipse at center,transparent 10%,rgba(15,15,15,0.55) 60%,#0f0f0f 100%); z-index:1; pointer-events:none; }

/* ── Standardized Header ── */
header {
    background: #161616;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 28px;
    position: fixed; top: 0; left: 0; width: 100%;
    height: 60px; z-index: 1000;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
}
header.hidden {
    transform: translateY(-100%);
}
.brand-logo-wrap {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    flex-shrink: 0;
}
.brand-logo-wrap:hover { transform: scale(1.05); }
.brand-logo { height: 34px; width: auto; filter: invert(1); display: block; }

/* Right actions */
.header-actions { display: flex; align-items: center; gap: 6px; }

.bookings-btn, .notif-btn {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 7px; padding: 6px 9px;
    color: rgba(249,249,249,0.65); font-size: 0.92rem;
    cursor: pointer; transition: all 0.25s;
    font-family: 'Outfit', sans-serif;
    display: flex; align-items: center;
    height: 32px;
}
.bookings-btn { gap: 5px; }
.bookings-btn::after { content: 'My Bookings'; font-size: 0.75rem; font-weight: 600; }

.bookings-btn:hover, .notif-btn:hover { 
    background: rgba(255,255,255,0.12); color: #F9F9F9;
    transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.profile-btn {
    background: #F9F9F9; border: none; border-radius: 50%;
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; overflow: hidden; padding: 0;
    transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    flex-shrink: 0;
}
.profile-btn img { width: 100%; height: 100%; object-fit: cover; }
.profile-btn:hover { transform: scale(1.08); box-shadow: 0 4px 16px rgba(255,255,255,0.2); }
.profile-initials {
    width: 100%; height: 100%;
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; font-weight: 800; color: #fff;
}

@media (max-width: 768px) {
    header {
        padding: 0 20px;
        height: 60px;
    }
    .brand-logo { height: 34px; }
    .bookings-btn::after { display: none; }
    .bookings-btn, .notif-btn { width: 40px; justify-content: center; padding: 0; }
    .profile-btn { width: 38px; height: 38px; }
    .profile-initials { font-size: 0.75rem; }
}

@media (max-width: 480px) {
    header { 
        padding: 0 10px; 
        gap: 6px;
    }
    .brand-logo { height: 32px; }
    .bookings-btn, .notif-btn { 
        width: 36px; 
        height: 32px;
        font-size: 0.92rem;
    }
    .profile-btn { width: 36px; height: 36px; }
    .profile-initials { font-size: 0.72rem; }
}

/* Notification dropdown */
.notif-dropdown {
    display: none; position: absolute; top: calc(100% + 8px); right: 0;
    width: 320px; background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;
    overflow: hidden; box-shadow: 0 12px 40px rgba(0,0,0,0.6); z-index: 2000;
}
.notif-dropdown.open { display: block; }
.notif-header { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: space-between; }
.notif-header span { font-size: 0.78rem; font-weight: 700; color: rgba(249,249,249,0.5); letter-spacing: 1px; text-transform: uppercase; }
.notif-mark-all { font-size: 0.7rem; color: #ff6b6b; cursor: pointer; background: none; border: none; font-family: 'Outfit',sans-serif; font-weight: 600; }
.notif-list { max-height: 280px; overflow-y: auto; }
.notif-item { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.15s; display: flex; gap: 10px; align-items: flex-start; text-decoration: none; color: inherit; }
.notif-item:hover { background: rgba(255,255,255,0.04); }
.notif-item.unread { background: rgba(255,77,77,0.05); }
.notif-dot { width: 8px; height: 8px; border-radius: 50%; background: #ff4d4d; flex-shrink: 0; margin-top: 5px; }
.notif-dot.read { background: transparent; }
.notif-item-body { flex: 1; min-width: 0; }
.notif-item-title { font-size: 0.8rem; font-weight: 700; margin-bottom: 2px; }
.notif-item-msg { font-size: 0.72rem; color: rgba(249,249,249,0.4); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.notif-item-time { font-size: 0.65rem; color: rgba(249,249,249,0.25); margin-top: 4px; }
.notif-empty { text-align: center; padding: 30px; font-size: 0.82rem; color: rgba(249,249,249,0.2); }
.notif-footer { padding: 10px 16px; border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; }
.notif-footer a { font-size: 0.75rem; color: #ff6b6b; text-decoration: none; font-weight: 600; }

.notif-wrap { position: relative; }
.notif-badge { 
    position: absolute; 
    top: -4px; 
    right: -4px; 
    background: #ff4d4d; 
    color: #fff; 
    font-size: 0.55rem; 
    font-weight: 800; 
    min-width: 16px; 
    height: 16px; 
    border-radius: 8px; 
    display: none; 
    align-items: center; 
    justify-content: center; 
    padding: 0 4px; 
}

.live-badge { display:inline-flex; align-items:center; gap:4px; font-size:0.58rem; font-weight:700; letter-spacing:0.5px; color:rgba(249,249,249,0.6); white-space:nowrap; }
.pulse-dot { width:6px; height:6px; border-radius:50%; background:#22c55e; animation:pulse 2s ease-in-out infinite; flex-shrink:0; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1);box-shadow:0 0 0 0 rgba(34,197,94,0.4);} 50%{opacity:0.8;transform:scale(1.2);box-shadow:0 0 0 6px rgba(34,197,94,0);} }

.hnav-link { color:rgba(249,249,249,0.45); text-decoration:none; font-size:0.65rem; font-weight:500; padding:4px 8px; border-radius:5px; border:1px solid rgba(255,255,255,0.1); transition:all 0.2s; white-space:nowrap; }
.hnav-link:hover { background:rgba(255,255,255,0.08); color:#F9F9F9; }

.notif-list { max-height:320px; overflow-y:auto; }
.notif-item { padding:12px 16px; border-bottom:1px solid rgba(255,255,255,0.04); cursor:pointer; transition:background 0.15s; display:flex; gap:10px; align-items:flex-start; text-decoration:none; color:inherit; }
.notif-item:hover { background:rgba(255,255,255,0.04); }
.notif-item.unread { background:rgba(255,77,77,0.05); }
.notif-dot { width:8px; height:8px; border-radius:50%; background:#ff4d4d; flex-shrink:0; margin-top:5px; }
.notif-dot.read { background:transparent; }
.notif-item-body { flex:1; min-width:0; }
.notif-item-title { font-size:0.8rem; font-weight:700; margin-bottom:2px; color:#F9F9F9; }
.notif-item-msg { font-size:0.72rem; color:rgba(249,249,249,0.4); line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.notif-item-time { font-size:0.65rem; color:rgba(249,249,249,0.25); margin-top:4px; }
.notif-empty { text-align:center; padding:30px; font-size:0.82rem; color:rgba(249,249,249,0.2); }
.notif-footer { padding:10px 16px; border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:space-between; align-items:center; }
.notif-footer a { font-size:0.75rem; color:#ff6b6b; text-decoration:none; font-weight:600; }
.notif-footer a.dim { color:rgba(249,249,249,0.3); }

/* ── Layout ── */
.outer { position:relative; z-index:10; width:95%; max-width:1280px; margin:28px auto; }
.page-label { font-size:0.72rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#ff4d4d; margin-bottom:5px; }
.page-title  { font-size:1.7rem; font-weight:800; margin-bottom:6px; }
.page-sub    { font-size:0.88rem; color:rgba(249,249,249,0.4); margin-bottom:24px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

/* ── Summary strip ── */
.summary-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-bottom:24px; }
.summary-card { background:#1a1a1a; border:1px solid rgba(255,255,255,0.07); border-radius:14px; padding:18px; text-align:center; transition:transform 0.2s,border-color 0.2s; }
.summary-card:hover { transform:translateY(-2px); border-color:rgba(255,255,255,0.12); }
.summary-num { font-size:2rem; font-weight:800; font-variant-numeric:tabular-nums; }
.summary-num.green  { color:#22c55e; } .summary-num.yellow { color:#f59e0b; } .summary-num.red { color:#ff4d4d; } .summary-num.blue { color:#64b5f6; }
.summary-label { font-size:0.68rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:rgba(249,249,249,0.3); margin-top:4px; }

/* ── Filter bar ── */
.filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.filter-btn { padding:7px 18px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.04); color:rgba(249,249,249,0.5); font-family:'Outfit',sans-serif; font-size:0.78rem; font-weight:600; cursor:pointer; transition:all 0.2s; }
.filter-btn:hover { border-color:rgba(255,255,255,0.2); color:#F9F9F9; }
.filter-btn.active { background:rgba(255,77,77,0.12); border-color:rgba(255,77,77,0.35); color:#ff4d4d; }

/* ── Tip banner ── */
.tip-banner { background:rgba(255,77,77,0.06); border:1px solid rgba(255,77,77,0.15); border-radius:12px; padding:14px 18px; margin-bottom:24px; display:flex; align-items:flex-start; gap:12px; }
.tip-banner .icon { font-size:1.3rem; flex-shrink:0; margin-top:1px; }
.tip-title  { font-weight:700; font-size:0.85rem; margin-bottom:3px; }
.tip-body   { font-size:0.78rem; color:rgba(249,249,249,0.5); line-height:1.6; }

/* ── Mall section ── */
.mall-section { margin-bottom:32px; }
.mall-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:8px; }
.mall-name { font-size:1.2rem; font-weight:800; }
.mall-loc  { font-size:0.72rem; color:rgba(249,249,249,0.35); margin-top:2px; }
.mall-traffic { font-size:0.68rem; font-weight:700; padding:4px 12px; border-radius:20px; white-space:nowrap; }
.mall-traffic.green  { background:rgba(34,197,94,0.1);  border:1px solid rgba(34,197,94,0.25);  color:#22c55e; }
.mall-traffic.yellow { background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.25); color:#f59e0b; }
.mall-traffic.red    { background:rgba(255,77,77,0.1);  border:1px solid rgba(255,77,77,0.25);  color:#ff4d4d; }

/* ── Queue grid ── */
.queue-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:12px; }
.queue-card { background:#1a1a1a; border:1px solid rgba(255,255,255,0.07); border-radius:14px; overflow:hidden; position:relative; transition:transform 0.2s,border-color 0.2s,box-shadow 0.2s; animation:fadeUp 0.35s ease both; }
@keyframes fadeUp { from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);} }
.queue-card:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(0,0,0,0.5); }
.queue-card.closed { opacity:0.45; filter:grayscale(0.5); }
.queue-card::after { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:14px 14px 0 0; }
.queue-card.green::after  { background:#22c55e; } .queue-card.yellow::after { background:#f59e0b; } .queue-card.red::after { background:#ff4d4d; } .queue-card.closed::after { background:rgba(255,255,255,0.1); }
.qc-body { padding:16px; }
.qc-top  { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:10px; }
.qc-icon { font-size:1.6rem; }
.qc-status-badge { font-size:0.62rem; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; padding:3px 9px; border-radius:20px; }
.qc-status-badge.green  { background:rgba(34,197,94,0.12);  color:#22c55e; border:1px solid rgba(34,197,94,0.25); }
.qc-status-badge.yellow { background:rgba(245,158,11,0.12); color:#f59e0b; border:1px solid rgba(245,158,11,0.25); }
.qc-status-badge.red    { background:rgba(255,77,77,0.12);  color:#ff4d4d; border:1px solid rgba(255,77,77,0.25); }
.qc-status-badge.closed { background:rgba(255,255,255,0.05); color:rgba(249,249,249,0.3); border:1px solid rgba(255,255,255,0.08); }
.qc-area    { font-weight:700; font-size:0.88rem; margin-bottom:3px; }
.qc-updated { font-size:0.62rem; color:rgba(249,249,249,0.3); margin-bottom:12px; }
.qc-wait-row { display:flex; align-items:flex-end; gap:4px; margin-bottom:10px; }
.qc-wait-num { font-size:2.4rem; font-weight:800; line-height:1; }
.qc-wait-num.green { color:#22c55e; } .qc-wait-num.yellow { color:#f59e0b; } .qc-wait-num.red { color:#ff4d4d; }
.qc-wait-unit { font-size:0.72rem; color:rgba(249,249,249,0.4); margin-bottom:6px; }
.qc-bar-bg   { background:rgba(255,255,255,0.07); border-radius:100px; height:5px; overflow:hidden; margin-bottom:10px; }
.qc-bar-fill { height:100%; border-radius:100px; transition:width 0.7s ease; }
.qc-bar-fill.green { background:#22c55e; } .qc-bar-fill.yellow { background:#f59e0b; } .qc-bar-fill.red { background:#ff4d4d; }
.qc-people { font-size:0.7rem; color:rgba(249,249,249,0.4); }
.qc-people strong { color:#F9F9F9; }
.qc-closed-msg { font-size:0.78rem; color:rgba(249,249,249,0.3); margin-top:8px; }

/* ── Empty state ── */
.empty-state { text-align:center; padding:60px 20px; color:rgba(249,249,249,0.2); }
.empty-state .icon { font-size:3rem; margin-bottom:14px; }
.empty-state p { font-size:0.88rem; line-height:1.7; max-width:420px; margin:0 auto 16px; }
.empty-state a { color:#ff4d4d; text-decoration:none; font-weight:700; }

/* ── Refresh bar ── */
.refresh-bar { display:inline-flex; align-items:center; gap:10px; background:#1a1a1a; border:1px solid rgba(255,255,255,0.07); border-radius:20px; padding:7px 16px; font-size:0.72rem; color:rgba(249,249,249,0.4); margin-bottom:24px; }
.refresh-bar strong { color:#F9F9F9; }
#countdown { color:#22c55e; font-weight:700; }

@media (max-width: 768px) {
    .outer { width: calc(100% - 24px); margin: 20px auto; }
    .page-title { font-size: 1.4rem; }
    .page-sub { font-size: 0.8rem; }
    .summary-strip { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .summary-card { padding: 14px; }
    .summary-num { font-size: 1.6rem; }
    .filter-bar { gap: 6px; }
    .filter-btn { padding: 6px 14px; font-size: 0.72rem; }
    .mall-header { flex-direction: column; align-items: flex-start; }
    .mall-name { font-size: 1.05rem; }
    .queue-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
    .qc-body { padding: 12px; }
    .qc-icon { font-size: 1.3rem; }
    .qc-area { font-size: 0.8rem; }
    .qc-wait-num { font-size: 2rem; }
}

@media (max-width: 480px) {
    .outer { width: calc(100% - 16px); margin: 14px auto; }
    .page-label { font-size: 0.65rem; }
    .page-title { font-size: 1.2rem; }
    .page-sub { font-size: 0.75rem; gap: 6px; }
    .summary-strip { grid-template-columns: 1fr 1fr; gap: 8px; }
    .summary-card { padding: 12px; }
    .summary-num { font-size: 1.4rem; }
    .summary-label { font-size: 0.62rem; }
    .filter-bar { gap: 5px; }
    .filter-btn { padding: 5px 12px; font-size: 0.68rem; }
    .tip-banner { padding: 12px 14px; gap: 10px; }
    .tip-banner .icon { font-size: 1.1rem; }
    .tip-title { font-size: 0.78rem; }
    .tip-body { font-size: 0.72rem; }
    .mall-name { font-size: 0.95rem; }
    .mall-loc { font-size: 0.68rem; }
    .mall-traffic { font-size: 0.62rem; padding: 3px 10px; }
    .queue-grid { grid-template-columns: 1fr; gap: 8px; }
    .qc-body { padding: 10px; }
    .qc-icon { font-size: 1.2rem; }
    .qc-status-badge { font-size: 0.58rem; padding: 2px 8px; }
    .qc-area { font-size: 0.75rem; }
    .qc-updated { font-size: 0.58rem; }
    .qc-wait-num { font-size: 1.8rem; }
    .qc-wait-unit { font-size: 0.68rem; }
    .qc-people { font-size: 0.65rem; }
    .refresh-bar { padding: 6px 12px; font-size: 0.68rem; }
}
</style>
</head>
<body>

<!-- ── Header ── -->
<header id="mainHeader">
    <a href="home.php" class="brand-logo-wrap" title="Peak's Cinema - Home">
        <img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo">
    </a>

    <!-- Right actions -->
    <div class="header-actions">
        <div class="live-badge"><span class="pulse-dot"></span> Live Queue</div>
        <a href="home.php" class="hnav-link">← Home</a>
        
        <?php if(isset($_SESSION['user_id'])): ?>
        <!-- My Bookings button -->
        <button type="button" class="bookings-btn" onclick="window.location.href='my_bookings.php'" title="My Bookings">
            🎟
        </button>

        <!-- Notification bell -->
        <div class="notif-wrap">
            <button class="notif-btn" id="notifBtn" onclick="toggleNotif(event)" title="Notifications">
                🔔<span class="notif-badge" id="notifBadge"></span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span>Notifications</span>
                    <button class="notif-mark-all" onclick="markAllRead()">Mark all read</button>
                </div>
                <div class="notif-list" id="notifList">
                    <div class="notif-empty">Loading...</div>
                </div>
                <div class="notif-footer">
                    <a href="notifications_page.php">View All →</a>
                    <a href="my_bookings.php" class="dim">My Bookings</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile button -->
        <button class="profile-btn" onclick="window.location.href='<?=$profile_link?>'" title="Profile">
            <?php if(!empty($profile_photo)): ?>
                <img src="<?=htmlspecialchars($profile_photo)?>?v=<?=time()?>" alt="Profile" referrerpolicy="no-referrer">
            <?php elseif(!empty($user_initials)): ?>
                <div class="profile-initials"><?=htmlspecialchars($user_initials)?></div>
            <?php else: ?>
                <div class="profile-initials">?</div>
            <?php endif; ?>
        </button>
    </div>
</header>

<div class="outer">
    <p class="page-label">Live Updates</p>
    <h1 class="page-title">🚶 Queue Tracker</h1>
    <p class="page-sub">
        Real-time wait times across all Peak's Cinema locations.
        <span class="refresh-bar">🔄 Refreshing in <strong id="countdown">30</strong>s
            &nbsp;·&nbsp; Current time: <?= $currentTimeFormatted ?>
        </span>
    </p>

    <!-- Summary strip -->
    <div class="summary-strip">
        <div class="summary-card"><div class="summary-num green"><?=$openAreas?></div><div class="summary-label">Open Areas</div></div>
        <div class="summary-card"><div class="summary-num yellow"><?=$busyAreas?></div><div class="summary-label">Busy Areas</div></div>
        <div class="summary-card"><div class="summary-num <?=waitColor($avgWait)?>"><?=$avgWait?>m</div><div class="summary-label">Avg Wait</div></div>
        <div class="summary-card"><div class="summary-num red"><?=$maxWait?>m</div><div class="summary-label">Longest Wait</div></div>
        <div class="summary-card"><div class="summary-num blue"><?=count($malls)?></div><div class="summary-label">Locations</div></div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
        <button class="filter-btn active" onclick="filterMall(this,'all')">🏬 All Locations</button>
        <?php foreach($malls as $m): ?>
        <button class="filter-btn" onclick="filterMall(this,'mall-<?=$m['Mall_ID']?>')"><?=htmlspecialchars($m['MallName'])?></button>
        <?php endforeach; ?>
    </div>

    <!-- Best area tip -->
    <?php if($bestArea): ?>
    <div class="tip-banner">
        <div class="icon">💡</div>
        <div>
            <div class="tip-title">Shortest wait right now</div>
            <div class="tip-body">
                Head to <strong><?=htmlspecialchars($areaLabels[$bestArea['area']] ?? ucfirst($bestArea['area']))?></strong>
                at <strong><?=htmlspecialchars($bestArea['MallName'])?></strong> —
                only <strong><?=$bestArea['current_wait_mins']?> min</strong> wait
                with <?=$bestArea['queue_length']?> <?=$bestArea['queue_length']==1?'person':'people'?> in line.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Queue data -->
    <?php if(empty($byMall)): ?>
    <div class="empty-state">
        <div class="icon">📭</div>
        <p>No queue data yet. Staff can start adding live data via the <a href="Admin/queue_admin.php">Queue Admin Panel</a>.</p>
        <p style="font-size:0.75rem;color:rgba(249,249,249,0.2);">Make sure you've run <strong>queue_tracker_setup.sql</strong> in phpMyAdmin first.</p>
    </div>
    <?php else: ?>
    <?php foreach($byMall as $mallId => $mall):
        $waits = array_column($mall['areas'],'current_wait_mins');
        $avgM  = round(array_sum($waits)/count($waits));
        $oc    = waitColor($avgM);
        $ot    = $oc==='green'?'🟢 Light traffic':($oc==='yellow'?'🟡 Moderate':'🔴 Heavy traffic');
    ?>
    <div class="mall-section" data-mall="mall-<?=$mallId?>">
        <div class="mall-header">
            <div>
                <div class="mall-name"><?=htmlspecialchars($mall['name'])?></div>
                <div class="mall-loc">📍 <?=htmlspecialchars($mall['location']??'')?></div>
            </div>
            <div class="mall-traffic <?=$oc?>"><?=$ot?> · avg <?=$avgM?>min</div>
        </div>
        <div class="queue-grid">
            <?php foreach($mall['areas'] as $area):
                $color  = $area['status']==='closed' ? 'closed' : waitColor($area['current_wait_mins']);
                $barPct = min(100, round($area['current_wait_mins']/40*100));
                [$sEmoji,$sText] = statusLabel($area['status']);
                $icon   = $areaIcons[$area['area']] ?? '📍';
                $label  = $areaLabels[$area['area']] ?? ucwords(str_replace('_',' ',$area['area']));
                $updAgo = $area['updated_at'] ? human_ago(strtotime($area['updated_at'])) : '—';
                $peeps  = min($area['queue_length'], 8);
            ?>
            <div class="queue-card <?=$color?>">
                <div class="qc-body">
                    <div class="qc-top">
                        <div class="qc-icon"><?=$icon?></div>
                        <div class="qc-status-badge <?=$color==='closed'?'closed':$color?>"><?=$sEmoji?> <?=$sText?></div>
                    </div>
                    <div class="qc-area"><?=htmlspecialchars($label)?></div>
                    <div class="qc-updated">Updated <?=$updAgo?></div>
                    <?php if($area['status']!=='closed'): ?>
                    <div class="qc-wait-row">
                        <div class="qc-wait-num <?=$color?>"><?=$area['current_wait_mins']?></div>
                        <div class="qc-wait-unit">min wait</div>
                    </div>
                    <div class="qc-bar-bg">
                        <div class="qc-bar-fill <?=$color?>" style="width:<?=$barPct?>%"></div>
                    </div>
                    <div class="qc-people">
                        <?=str_repeat('👤',$peeps).($area['queue_length']>8?'…':'')?>
                        <strong><?=$area['queue_length']?></strong> in line
                    </div>
                    <?php else: ?>
                    <div class="qc-closed-msg">Currently closed</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// ── Countdown + auto-refresh ──────────────────────────────────
let secs = 30;
const cd = document.getElementById('countdown');
setInterval(() => { secs--; if(cd) cd.textContent=secs; if(secs<=0) location.reload(); }, 1000);

// ── Filter by mall ────────────────────────────────────────────
function filterMall(btn, filter) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.mall-section').forEach(s => {
        s.style.display = (filter==='all' || s.dataset.mall===filter) ? '' : 'none';
    });
}

// ── Header Scroll Behavior ──
(function() {
    const h = document.getElementById('mainHeader');
    let last = window.scrollY, tick = false;
    window.addEventListener('scroll', () => {
        if (!tick) {
            requestAnimationFrame(() => {
                const cur = window.scrollY;
                if (cur > last && cur > 80) {
                    h.style.transform = 'translateY(-100%)';
                } else {
                    h.style.transform = 'translateY(0)';
                }
                last = cur; tick = false;
            });
            tick = true;
        }
    }, { passive: true });
})();

// ── Search Logic ──
const searchInput = document.getElementById('searchInput');
const searchDrop  = document.getElementById('searchDrop');
let searchTimer   = null;
let selectedIndex = -1;

function updateSearchHighlight() {
    const items = searchDrop.querySelectorAll('.search-result-item');
    items.forEach((item, index) => {
        if (index === selectedIndex) {
            item.classList.add('selected');
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('selected');
        }
    });
}

if (searchInput) {
    searchInput.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(searchTimer);
        selectedIndex = -1;
        if (!q) {
            searchDrop.style.display = 'none';
            searchDrop.innerHTML = '';
            return;
        }
        searchTimer = setTimeout(() => {
            fetch('search_movies.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.length) {
                        searchDrop.innerHTML = '<div style="padding:15px; text-align:center; color:rgba(255,255,255,0.3); font-size:0.8rem;">No movies found</div>';
                        searchDrop.style.display = 'block';
                        return;
                    }
                    searchDrop.innerHTML = data.map(m => `
                        <a href="movie.php?movie_id=${m.id}" class="search-result-item">
                            <img src="${m.poster}" onerror="this.src='movie-background-collage.jpg'">
                            <div class="search-result-info">
                                <span class="search-result-title">${m.name}</span>
                                <span class="search-result-meta">${m.genre || ''} ${m.rating ? '• ' + m.rating : ''}</span>
                            </div>
                            <div class="search-result-btn">Buy Tickets</div>
                        </a>
                    `).join('');
                    searchDrop.style.display = 'block';
                }).catch(() => { searchDrop.style.display = 'none'; });
        }, 200);
    });

    searchInput.addEventListener('keydown', function(e) {
        const items = searchDrop.querySelectorAll('.search-result-item');
        if (searchDrop.style.display === 'none' || !items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = (selectedIndex + 1) % items.length;
            updateSearchHighlight();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = (selectedIndex - 1 + items.length) % items.length;
            updateSearchHighlight();
        } else if (e.key === 'Enter') {
            if (selectedIndex >= 0) { e.preventDefault(); items[selectedIndex].click(); }
        } else if (e.key === 'Escape') {
            searchDrop.style.display = 'none';
        }
    });
}

document.addEventListener('click', e => {
    if (searchDrop && !searchDrop.contains(e.target) && e.target !== searchInput)
        searchDrop.style.display = 'none';
});

// ── Notifications Logic ──
function toggleNotif(e) {
    e.stopPropagation();
    const dd = document.getElementById('notifDropdown');
    if (dd) {
        dd.classList.toggle('open');
        if (dd.classList.contains('open')) loadNotif();
    }
}

function loadNotif() {
    fetch('notifications_api.php')
        .then(r => r.json())
        .then(data => {
            const list  = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');
            if (!list || !data || data.error) return;

            if (data.unread > 0) {
                badge.textContent = data.unread > 9 ? '9+' : data.unread;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }

            if (!data.notifications?.length) {
                list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
                return;
            }

            list.innerHTML = data.notifications.map(n => `
                <a class="notif-item ${n.IsRead == 0 ? 'unread' : ''}" href="notifications_page.php?id=${n.Notif_ID}">
                    <div class="notif-dot ${n.IsRead == 1 ? 'read' : ''}"></div>
                    <div class="notif-item-body">
                        <div class="notif-item-title">${n.Title}</div>
                        <div class="notif-item-msg">${n.Message}</div>
                        <div class="notif-item-time">${n.time_ago || 'Just now'}</div>
                    </div>
                </a>
            `).join('');
        }).catch(err => console.error('Notif error:', err));
}

function markAllRead() {
    fetch('notifications_api.php?action=mark_read')
        .then(r => r.json())
        .then(() => loadNotif())
        .catch(err => console.error('Mark read error:', err));
}

document.addEventListener('click', () => {
    document.getElementById('notifDropdown')?.classList.remove('open');
});

<?php if (isset($_SESSION['user_id'])): ?>
loadNotif();
setInterval(loadNotif, 60000);
<?php endif; ?>
</script>
</body>
</html>
