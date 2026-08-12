<?php
date_default_timezone_set('Asia/Manila');
session_start();
include("peakscinemas_database.php");

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    header("Location: personal_info_form.php?logged_out=1");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: personal_info_form.php");
    exit;
}

$uid = (int) $_SESSION['user_id'];

function fmt_dashboard_date(string $date, string $time): string {
    $stamp = strtotime(trim($date . ' ' . $time));
    return $stamp ? date('M d, Y · g:i A', $stamp) : 'Schedule unavailable';
}

function time_ago_label(?string $datetime): string {
    if (!$datetime) return 'Just now';
    $ts = strtotime($datetime);
    if (!$ts) return 'Just now';
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d', $ts);
}

$stmt = $conn->prepare("SELECT Name, Email, PhoneNumber, ProfilePhoto FROM customer WHERE Customer_ID = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: personal_info_form.php?error=user_not_found");
    exit;
}

$profile_photo = $user['ProfilePhoto'] ?? $_SESSION['profile_photo'] ?? null;
$user_name = $user['Name'] ?? 'User';
$nameParts = preg_split('/\s+/', trim($user_name));
$user_initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr(end($nameParts) ?: '', 0, 1));
if (strlen($user_initials) === 1) {
    $user_initials = strtoupper(substr($nameParts[0] ?? 'U', 0, 2));
}

$profile_completion = 0;
if (!empty(trim((string) ($user['Name'] ?? '')))) $profile_completion += 25;
if (!empty(trim((string) ($user['Email'] ?? '')))) $profile_completion += 25;
if (!empty(trim((string) ($user['PhoneNumber'] ?? '')))) $profile_completion += 25;
if (!empty($profile_photo)) $profile_completion += 25;

$activeStmt = $conn->prepare("
    SELECT
        COALESCE(tk.BookingRef, CONCAT('TK-', tk.Ticket_ID)) AS BookingRef,
        m.MovieName,
        m.MoviePoster,
        ml.MallName,
        th.TheaterName,
        ts.Date,
        ts.StartTime,
        ts.ScreeningType,
        tk.DateTime,
        tk.Price,
        s.SeatRow,
        s.SeatColumn,
        s.SeatType
    FROM ticket tk
    LEFT JOIN movie m ON m.Movie_ID = tk.Movie_ID
    LEFT JOIN timeslot ts ON ts.TimeSlot_ID = tk.TimeSlot_ID
    LEFT JOIN theater th ON th.Theater_ID = ts.Theater_ID
    LEFT JOIN mall ml ON ml.Mall_ID = th.Mall_ID
    LEFT JOIN seats s ON s.Seat_ID = tk.Seat_ID
    WHERE tk.Customer_ID = ? AND tk.Status = 1
    ORDER BY tk.DateTime DESC
");
$activeStmt->bind_param("i", $uid);
$activeStmt->execute();
$activeRows = $activeStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$now = time();
$activeBookings = [];
$completedBookings = [];
foreach ($activeRows as $row) {
    $ref = $row['BookingRef'];
    $screeningTs = strtotime(($row['Date'] ?? '') . ' ' . ($row['StartTime'] ?? ''));
    $seatLabel = '';
    if (!empty($row['SeatRow']) && isset($row['SeatColumn'])) {
        $seatLabel = $row['SeatRow'] . ((int) $row['SeatColumn'] + 1);
        if (!empty($row['SeatType'])) $seatLabel .= ' (' . $row['SeatType'] . ')';
    }
    if ($screeningTs && $screeningTs > $now) {
        $target =& $activeBookings;
    } else {
        $target =& $completedBookings;
    }
    if (!isset($target[$ref])) {
        $target[$ref] = [
            'ref' => $ref,
            'movie' => $row['MovieName'] ?? 'Movie',
            'poster' => $row['MoviePoster'] ?? '',
            'mall' => $row['MallName'] ?? 'Mall unavailable',
            'theater' => $row['TheaterName'] ?? 'Theater unavailable',
            'date' => $row['Date'] ?? '',
            'start_time' => $row['StartTime'] ?? '',
            'type' => $row['ScreeningType'] ?? 'Standard',
            'booked_at' => $row['DateTime'] ?? '',
            'total' => 0,
            'seats' => [],
            'status' => $screeningTs && $screeningTs > $now ? 'Upcoming' : 'Completed',
        ];
    }
    $target[$ref]['total'] += (float) ($row['Price'] ?? 0);
    if ($seatLabel !== '') $target[$ref]['seats'][] = $seatLabel;
}
unset($target);

$pastCols = $conn->query("SHOW COLUMNS FROM ticket LIKE 'CancelledAt'");
$cancelCol = ($pastCols && $pastCols->num_rows > 0) ? 'tk.CancelledAt' : 'NULL';
$pastStmt = $conn->prepare("
    SELECT
        COALESCE(tk.BookingRef, CONCAT('TK-', tk.Ticket_ID)) AS BookingRef,
        tk.Status,
        tk.DateTime,
        {$cancelCol} AS CancelledAt,
        tk.Price,
        m.MovieName,
        m.MoviePoster,
        ml.MallName,
        th.TheaterName,
        ts.Date,
        ts.StartTime,
        ts.ScreeningType,
        s.SeatRow,
        s.SeatColumn
    FROM ticket tk
    JOIN movie m ON m.Movie_ID = tk.Movie_ID
    JOIN timeslot ts ON ts.TimeSlot_ID = tk.TimeSlot_ID
    JOIN theater th ON th.Theater_ID = ts.Theater_ID
    JOIN mall ml ON ml.Mall_ID = th.Mall_ID
    LEFT JOIN seats s ON s.Seat_ID = tk.Seat_ID
    WHERE tk.Customer_ID = ? AND tk.Status IN (2,3)
    ORDER BY tk.DateTime DESC
    LIMIT 60
");
$pastStmt->bind_param("i", $uid);
$pastStmt->execute();
$pastRows = $pastStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pastBookings = [];
foreach ($pastRows as $row) {
    $ref = $row['BookingRef'];
    if (!isset($pastBookings[$ref])) {
        $pastBookings[$ref] = [
            'ref' => $ref,
            'movie' => $row['MovieName'] ?? 'Movie',
            'poster' => $row['MoviePoster'] ?? '',
            'mall' => $row['MallName'] ?? 'Mall unavailable',
            'theater' => $row['TheaterName'] ?? 'Theater unavailable',
            'date' => $row['Date'] ?? '',
            'start_time' => $row['StartTime'] ?? '',
            'type' => $row['ScreeningType'] ?? 'Standard',
            'booked_at' => $row['DateTime'] ?? '',
            'cancelled_at' => $row['CancelledAt'] ?? '',
            'status' => ((int) ($row['Status'] ?? 0) === 2) ? 'Cancelled' : 'Completed',
            'total' => 0,
            'seats' => [],
        ];
    }
    $pastBookings[$ref]['total'] += (float) ($row['Price'] ?? 0);
    if (!empty($row['SeatRow']) && isset($row['SeatColumn'])) {
        $pastBookings[$ref]['seats'][] = $row['SeatRow'] . ((int) $row['SeatColumn'] + 1);
    }
}

foreach ($completedBookings as $ref => $booking) {
    if (!isset($pastBookings[$ref])) $pastBookings[$ref] = $booking;
}

uasort($activeBookings, fn($a, $b) => strtotime($b['booked_at']) <=> strtotime($a['booked_at']));
uasort($pastBookings, fn($a, $b) => strtotime($b['booked_at']) <=> strtotime($a['booked_at']));

$notifStmt = $conn->prepare("
    SELECT Notif_ID, Title, Message, Type, IsRead, Created_At
    FROM notifications
    WHERE Customer_ID = ?
    ORDER BY Created_At DESC
    LIMIT 6
");
$notifStmt->bind_param("i", $uid);
$notifStmt->execute();
$notifications = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($notifications as &$notification) {
    $notification['time_ago'] = time_ago_label($notification['Created_At'] ?? null);
}
unset($notification);

$allUnreadStmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE Customer_ID = ? AND IsRead = 0");
$allUnreadStmt->bind_param("i", $uid);
$allUnreadStmt->execute();
$allUnread = $allUnreadStmt->get_result()->fetch_assoc();

$recentBookings = array_slice(array_values($activeBookings + $pastBookings), 0, 5);
$activeCount = count($activeBookings);
$pastCount = count($pastBookings);
$totalBookings = $activeCount + $pastCount;
$unreadNotifCount = (int) ($allUnread['unread_count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Profile Dashboard - Peak's Cinema</title>
<style>
*,
*::before,
*::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: #0f0f0f;
    color: #F9F9F9;
    min-height: 100vh;
}
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: url('movie-background-collage.jpg') center/cover no-repeat;
    opacity: 0.10;
    z-index: 0;
    pointer-events: none;
}
body::after {
    content: '';
    position: fixed;
    inset: 0;
    background: radial-gradient(ellipse at center, transparent 20%, rgba(15,15,15,0.6) 70%, #0f0f0f 100%);
    z-index: 1;
    pointer-events: none;
}

a { color: inherit; text-decoration: none; }

header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: #1C1C1C;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    transition: transform 0.3s ease;
}
header.hidden {
    transform: translateY(-100%);
}
.header-shell {
    width: 100%;
    margin: 0;
    min-height: 50px;
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 12px;
    padding: 0 20px;
}
.brand-logo-wrap {
    display: inline-flex;
    align-items: center;
    transition: transform 0.2s ease;
    flex-shrink: 0;
    justify-self: start;
}
.brand-logo-wrap:hover { transform: scale(1.03); }
.brand-logo { height: 34px; width: auto; filter: invert(1); display: block; }
.header-fill {
    width: 100%;
    justify-self: center;
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
    justify-self: end;
}
.nav-btn,
.bookings-btn,
.notif-btn,
.logout-link,
.primary-link,
.secondary-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 13px;
    border-radius: 9px;
    font-size: 0.76rem;
    font-weight: 600;
    transition: all 0.2s ease;
    min-height: 34px;
}
.nav-btn,
.bookings-btn,
.notif-btn {
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.06);
    color: rgba(249,249,249,0.65);
    font-family: 'Outfit', sans-serif;
    cursor: pointer;
}
.nav-btn:hover,
.bookings-btn:hover,
.notif-btn:hover,
.secondary-link:hover {
    background: rgba(255,255,255,0.12);
    color: #F9F9F9;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.nav-btn,
.bookings-btn { gap: 5px; }
.nav-btn::after {
    content: 'Home';
    font-size: 0.75rem;
    font-weight: 600;
}
.bookings-btn::after {
    content: 'My Bookings';
    font-size: 0.75rem;
    font-weight: 600;
}
.notif-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
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
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}
.logout-link {
    border: 1px solid rgba(255,77,77,0.25);
    background: rgba(255,77,77,0.08);
    color: #ff6b6b;
}
.logout-link:hover {
    background: rgba(255,77,77,0.18);
    color: #fff;
}
.primary-link {
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    color: #fff;
    box-shadow: 0 8px 18px rgba(255,77,77,0.22);
}
.primary-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(255,77,77,0.28);
}
.secondary-link {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    color: rgba(249,249,249,0.72);
}

.page {
    position: relative;
    z-index: 10;
    width: min(1200px, calc(100% - 32px));
    margin: 74px auto 36px;
}
.hero,
.content-grid {
    display: grid;
    gap: 14px;
}
.hero { grid-template-columns: 1.45fr 1fr; margin-bottom: 14px; }
.content-grid { grid-template-columns: 1.35fr 1fr; }
.stack { display: grid; gap: 14px; }
.panel {
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px;
    box-shadow: 0 14px 32px rgba(0,0,0,0.24);
}
.hero-card,
.side-card,
.content-card { padding: 16px; }
.hero-card {
    position: relative;
    overflow: hidden;
}
.hero-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,77,77,0.12), rgba(192,57,43,0.02) 55%, transparent 100%);
    pointer-events: none;
}
.hero-main {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 14px;
    align-items: start;
}
.avatar-shell {
    width: 84px;
    height: 84px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.09);
    overflow: hidden;
    background: #141414;
    margin-top: 2px;
}
.avatar-shell img { width: 100%; height: 100%; object-fit: cover; display: block; }
.avatar-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    color: #fff;
    font-size: 1.6rem;
    font-weight: 900;
}
.eyebrow {
    font-size: 0.68rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #ff6b6b;
    font-weight: 800;
    margin-bottom: 8px;
}
.hero-title {
    font-size: clamp(1.7rem, 2.8vw, 2.4rem);
    line-height: 1;
    font-weight: 900;
    margin-bottom: 6px;
}
.hero-sub,
.side-card p,
.card-head p,
.booking-meta,
.booking-sub,
.notif-meta,
.empty-card,
.quick-link span {
    color: rgba(249,249,249,0.52);
    line-height: 1.5;
}
.identity-meta,
.hero-actions,
.stats-grid,
.booking-list,
.notif-list,
.quick-grid { display: grid; gap: 12px; }
.identity-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
}
.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
}
.identity-chip,
.quick-link {
    border-radius: 13px;
    padding: 11px 12px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    font-size: 0.76rem;
}
.identity-chip {
    flex: 1 1 170px;
    min-height: 54px;
    display: flex;
    align-items: center;
    line-height: 1.4;
}
.side-card { display: flex; flex-direction: column; justify-content: space-between; }
.side-card h2,
.card-head h3 { font-size: 0.96rem; font-weight: 800; margin-bottom: 4px; }
.completion-ring {
    margin-top: 14px;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 14px;
    align-items: center;
}
.ring {
    width: 82px;
    height: 82px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background:
        radial-gradient(circle at center, #171717 56%, transparent 57%),
        conic-gradient(#ff4d4d <?= $profile_completion ?>%, rgba(255,255,255,0.08) 0);
}
.ring span { font-size: 1.1rem; font-weight: 900; }
.completion-list { display: grid; gap: 7px; }
.completion-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-size: 0.76rem;
}
.completion-row strong { color: #F9F9F9; font-weight: 700; }
.completion-row em {
    font-style: normal;
    color: <?= $profile_completion === 100 ? '#81c784' : '#FFD54F' ?>;
    font-weight: 800;
}

.stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 14px; }
.stat-card {
    position: relative;
    overflow: hidden;
    padding: 15px 15px 14px;
    min-height: 116px;
}
.stat-card::before {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 0.95;
    pointer-events: none;
}
.stat-card.red::before    { background: linear-gradient(135deg, rgba(255,77,77,0.98), rgba(192,57,43,0.88)); }
.stat-card.orange::before { background: linear-gradient(135deg, rgba(255,140,66,0.96), rgba(255,77,77,0.82)); }
.stat-card.blue::before   { background: linear-gradient(135deg, rgba(66,120,255,0.94), rgba(100,180,255,0.82)); }
.stat-card.green::before  { background: linear-gradient(135deg, rgba(46,204,113,0.94), rgba(22,160,133,0.84)); }
.stat-body { position: relative; z-index: 1; display: flex; flex-direction: column; height: 100%; }
.stat-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: rgba(255,255,255,0.18);
    margin-bottom: 12px;
    font-size: 0.92rem;
}
.stat-label { font-size: 0.78rem; font-weight: 600; color: rgba(255,255,255,0.84); margin-bottom: 6px; }
.stat-value { font-size: clamp(1.45rem, 1.9vw, 1.95rem); font-weight: 900; line-height: 1; margin-bottom: 6px; }
.stat-note { margin-top: auto; font-size: 0.75rem; color: rgba(255,255,255,0.8); }

.card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 14px;
}
.card-link { font-size: 0.76rem; color: #ff6b6b; font-weight: 700; white-space: nowrap; }
</style>
</head>
<body>

<header id="mainHeader">
    <div class="header-shell">
        <a href="home.php" class="brand-logo-wrap" title="Peak's Cinema - Home">
            <img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo">
        </a>
        <div class="header-fill" aria-hidden="true"></div>
        <div class="header-actions">
            <a href="home.php" class="nav-btn" title="Home">←</a>
            <a href="my_bookings.php" class="bookings-btn" title="My Bookings">🎟</a>
            <div class="notif-wrap">
                <a href="notifications_page.php" class="notif-btn" title="Notifications" aria-label="Notifications">🔔</a>
                <?php if ($unreadNotifCount > 0): ?>
                    <span class="notif-badge"><?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?></span>
                <?php endif; ?>
            </div>
            <a href="?logout=1" class="logout-link" onclick="return confirm('Log out of Peak\\'s Cinema?')">→ Log Out</a>
        </div>
    </div>
</header>

<main class="page">
    <section class="hero">
        <article class="panel hero-card">
            <div class="hero-main">
                <div class="avatar-shell">
                    <?php if (!empty($profile_photo)): ?>
                        <img src="<?= htmlspecialchars($profile_photo) ?>?v=<?= time() ?>" alt="Profile Photo" referrerpolicy="no-referrer">
                    <?php else: ?>
                        <div class="avatar-fallback"><?= htmlspecialchars($user_initials) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="eyebrow">Profile Dashboard</div>
                    <h1 class="hero-title"><?= htmlspecialchars($user_name) ?></h1>
                    <p class="hero-sub">This is your account command center for the final system. You can review bookings, check notifications, update your details, and jump back into the cinema flow from one place.</p>
                    <div class="identity-meta">
                        <div class="identity-chip">📧 <?= htmlspecialchars($user['Email'] ?? 'No email set') ?></div>
                        <div class="identity-chip">📱 <?= htmlspecialchars($user['PhoneNumber'] ?: 'No phone number yet') ?></div>
                        <div class="identity-chip">🎬 <?= $activeCount ?> upcoming booking<?= $activeCount === 1 ? '' : 's' ?></div>
                    </div>
                    <div class="hero-actions">
                        <a href="profile_edit.php" class="primary-link">✏ Edit Profile</a>
                        <a href="home.php#movies-section" class="secondary-link">🎟 Book Another Movie</a>
                        <a href="queue_tracker.php" class="secondary-link">⏱ Open Live Queue</a>
                    </div>
                </div>
            </div>
        </article>

        <aside class="panel side-card">
            <div>
                <div class="eyebrow">Account Health</div>
                <h2>Profile completion</h2>
                <p>A stronger profile makes the account feel finished for defense day and keeps your personal details easy to verify.</p>
            </div>
            <div class="completion-ring">
                <div class="ring"><span><?= $profile_completion ?>%</span></div>
                <div class="completion-list">
                    <div class="completion-row"><strong>Name</strong><em><?= !empty($user['Name']) ? 'Done' : 'Missing' ?></em></div>
                    <div class="completion-row"><strong>Email</strong><em><?= !empty($user['Email']) ? 'Done' : 'Missing' ?></em></div>
                    <div class="completion-row"><strong>Phone</strong><em><?= !empty($user['PhoneNumber']) ? 'Done' : 'Missing' ?></em></div>
                    <div class="completion-row"><strong>Photo</strong><em><?= !empty($profile_photo) ? 'Done' : 'Optional' ?></em></div>
                </div>
            </div>
        </aside>
    </section>

    <section class="stats-grid">
        <article class="panel stat-card red">
            <div class="stat-body">
                <div class="stat-icon">🎟</div>
                <div class="stat-label">Upcoming Bookings</div>
                <div class="stat-value"><?= $activeCount ?></div>
                <div class="stat-note">Ready for your next screening</div>
            </div>
        </article>
        <article class="panel stat-card orange">
            <div class="stat-body">
                <div class="stat-icon">🧾</div>
                <div class="stat-label">Booking History</div>
                <div class="stat-value"><?= $pastCount ?></div>
                <div class="stat-note">Completed and cancelled references</div>
            </div>
        </article>
        <article class="panel stat-card blue">
            <div class="stat-body">
                <div class="stat-icon">🔔</div>
                <div class="stat-label">Unread Notifications</div>
                <div class="stat-value"><?= $unreadNotifCount ?></div>
                <div class="stat-note">New updates waiting for you</div>
            </div>
        </article>
        <article class="panel stat-card green">
            <div class="stat-body">
                <div class="stat-icon">📊</div>
                <div class="stat-label">Total Booking Groups</div>
                <div class="stat-value"><?= $totalBookings ?></div>
                <div class="stat-note">Overall account activity</div>
            </div>
        </article>
    </section>

    <section class="content-grid">
        <div class="stack">
            <article class="panel content-card">
                <div class="card-head">
                    <div>
                        <h3>Recent Booking Activity</h3>
                        <p>Your latest reservation groups, with their schedule and current state.</p>
                    </div>
                    <a href="my_bookings.php" class="card-link">Open full history →</a>
                </div>

                <?php if (empty($recentBookings)): ?>
                    <div class="empty-card">No bookings yet. Once a ticket is reserved, your recent activity will show up here.</div>
                <?php else: ?>
                    <div class="booking-list">
                        <?php foreach ($recentBookings as $booking): ?>
                            <?php
                                $statusKey = strtolower($booking['status']);
                                $statusClass = $statusKey === 'cancelled' ? 'status-cancelled' : ($statusKey === 'completed' ? 'status-completed' : 'status-upcoming');
                                $posterStyle = !empty($booking['poster']) ? "background-image:url('" . htmlspecialchars($booking['poster'], ENT_QUOTES) . "');" : '';
                            ?>
                            <div class="booking-item">
                                <div class="booking-poster" style="<?= $posterStyle ?>"></div>
                                <div>
                                    <div class="booking-status <?= $statusClass ?>"><?= $statusKey === 'cancelled' ? '❌' : ($statusKey === 'completed' ? '✓' : '⏰') ?> <?= htmlspecialchars($booking['status']) ?></div>
                                    <div class="booking-title"><?= htmlspecialchars($booking['movie']) ?></div>
                                    <div class="booking-meta"><?= htmlspecialchars($booking['ref']) ?> · <?= htmlspecialchars($booking['mall']) ?> · <?= htmlspecialchars($booking['theater']) ?></div>
                                    <div class="booking-sub">
                                        <?= htmlspecialchars(fmt_dashboard_date($booking['date'], $booking['start_time'])) ?>
                                        <?php if (!empty($booking['seats'])): ?>
                                            · Seats: <?= htmlspecialchars(implode(', ', array_slice($booking['seats'], 0, 3))) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="booking-total">₱<?= number_format((float) $booking['total'], 2) ?><small><?= htmlspecialchars($booking['type'] ?: 'Standard') ?></small></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="panel content-card">
                <div class="card-head">
                    <div>
                        <h3>Quick Actions</h3>
                        <p>Fast shortcuts for the parts of the system you’ll likely use during the final run-through.</p>
                    </div>
                </div>
                <div class="quick-grid">
                    <a href="profile_edit.php" class="quick-link"><strong>✏ Update account details</strong><span>Change your name, email, phone number, password, and profile photo.</span></a>
                    <a href="my_bookings.php" class="quick-link"><strong>🎟 Review bookings</strong><span>Check active schedules, cancellation status, and your full booking history.</span></a>
                    <a href="notifications_page.php" class="quick-link"><strong>🔔 Open notifications</strong><span>See booking confirmations, reminders, and any account-related updates.</span></a>
                    <a href="home.php#movies-section" class="quick-link"><strong>🎬 Browse movies</strong><span>Jump back to the catalog and continue testing the main customer flow.</span></a>
                </div>
            </article>
        </div>

        <div class="stack">
            <article class="panel content-card">
                <div class="card-head">
                    <div>
                        <h3>Recent Notifications</h3>
                        <p>Your newest account alerts and booking updates.</p>
                    </div>
                    <a href="notifications_page.php" class="card-link">View all →</a>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="empty-card">No notifications yet. Booking updates and reminders will appear here.</div>
                <?php else: ?>
                    <div class="notif-list">
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                                $typeKey = strtolower((string) ($notification['Type'] ?? 'default'));
                                $pillClass = 'pill-default';
                                $icon = '🔔';
                                if ($typeKey === 'booking') { $pillClass = 'pill-booking'; $icon = '🎟'; }
                                elseif ($typeKey === 'cancellation') { $pillClass = 'pill-cancellation'; $icon = '❌'; }
                                elseif ($typeKey === 'reminder') { $pillClass = 'pill-reminder'; $icon = '⏰'; }
                            ?>
                            <div class="notif-item <?= (int) ($notification['IsRead'] ?? 0) === 0 ? 'unread' : '' ?>">
                                <div class="notif-icon"><?= $icon ?></div>
                                <div>
                                    <div class="notif-title"><?= htmlspecialchars($notification['Title'] ?? 'Notification') ?></div>
                                    <div class="notif-meta"><?= htmlspecialchars($notification['Message'] ?? '') ?></div>
                                    <span class="notif-pill <?= $pillClass ?>"><?= $icon ?> <?= htmlspecialchars(ucfirst($typeKey)) ?></span>
                                </div>
                                <div class="notif-side">
                                    <div><?= htmlspecialchars($notification['time_ago']) ?></div>
                                    <div style="margin-top:6px; color: <?= (int) ($notification['IsRead'] ?? 0) === 0 ? '#ff6b6b' : 'rgba(249,249,249,0.35)' ?>;"><?= (int) ($notification['IsRead'] ?? 0) === 0 ? 'Unread' : 'Read' ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="panel content-card">
                <div class="card-head">
                    <div>
                        <h3>Account Summary</h3>
                        <p>A quick look at the information currently stored for this customer account.</p>
                    </div>
                </div>
                <div class="quick-grid">
                    <div class="quick-link" style="cursor:default;"><strong>👤 Display Name</strong><span><?= htmlspecialchars($user_name) ?></span></div>
                    <div class="quick-link" style="cursor:default;"><strong>📧 Email Address</strong><span><?= htmlspecialchars($user['Email'] ?? 'Not set') ?></span></div>
                    <div class="quick-link" style="cursor:default;"><strong>📱 Mobile Number</strong><span><?= htmlspecialchars($user['PhoneNumber'] ?: 'No phone number yet') ?></span></div>
                    <div class="quick-link" style="cursor:default;"><strong>🖼 Profile Photo</strong><span><?= !empty($profile_photo) ? 'Uploaded and active' : 'Using initials avatar' ?></span></div>
                </div>
            </article>
        </div>
    </section>
</main>

<style>
.booking-item,
.notif-item {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 10px;
    align-items: center;
    padding: 10px;
    border-radius: 13px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
}
.booking-poster {
    width: 50px;
    height: 72px;
    border-radius: 10px;
    background: #111 center/cover no-repeat;
    border: 1px solid rgba(255,255,255,0.06);
}
.booking-title,
.notif-title { font-size: 0.84rem; font-weight: 800; margin-bottom: 3px; }
.booking-status,
.notif-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.4px;
    margin-bottom: 6px;
}
.status-upcoming  { background: rgba(100,180,255,0.12); border: 1px solid rgba(100,180,255,0.25); color: #64b5f6; }
.status-completed { background: rgba(102,187,106,0.12); border: 1px solid rgba(102,187,106,0.25); color: #81c784; }
.status-cancelled { background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.24); color: #ff6b6b; }
.booking-total {
    font-size: 0.88rem;
    font-weight: 900;
    color: #F9F9F9;
    text-align: right;
}
.booking-total small {
    display: block;
    font-size: 0.64rem;
    font-weight: 700;
    color: rgba(249,249,249,0.4);
    margin-top: 3px;
}
.notif-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.06);
    font-size: 0.92rem;
}
.notif-item.unread { background: rgba(255,77,77,0.05); border-color: rgba(255,77,77,0.14); }
.notif-side { text-align: right; font-size: 0.68rem; color: rgba(249,249,249,0.42); }
.pill-booking      { color: #81c784; background: rgba(102,187,106,0.12); border: 1px solid rgba(102,187,106,0.2); }
.pill-cancellation { color: #ff6b6b; background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.2); }
.pill-reminder     { color: #FFD54F; background: rgba(255,213,79,0.1); border: 1px solid rgba(255,213,79,0.2); }
.pill-default      { color: rgba(249,249,249,0.55); background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); }
.quick-link { transition: all 0.2s ease; }
.quick-link:hover {
    transform: translateY(-2px);
    border-color: rgba(255,77,77,0.24);
    background: rgba(255,77,77,0.05);
}
.quick-link strong { display: block; font-size: 0.8rem; margin-bottom: 6px; }
.empty-card {
    padding: 18px;
    border-radius: 13px;
    background: rgba(255,255,255,0.03);
    border: 1px dashed rgba(255,255,255,0.1);
    text-align: center;
    font-size: 0.78rem;
}
@media (max-width: 1040px) {
    .hero,
    .content-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 700px) {
    .page { width: min(100%, calc(100% - 20px)); }
    .header-shell {
        grid-template-columns: 1fr;
        min-height: auto;
        padding: 10px 12px;
        gap: 10px;
    }
    .brand-logo-wrap,
    .header-actions,
    .header-fill {
        justify-self: stretch;
    }
    .header-actions {
        justify-content: flex-start;
        gap: 6px;
    }
    .nav-btn::after,
    .bookings-btn::after {
        display: none;
    }
    .nav-btn,
    .bookings-btn,
    .notif-btn {
        width: 40px;
        min-width: 40px;
        padding: 0;
    }
    .hero-main { grid-template-columns: 1fr; }
    .identity-meta,
    .stats-grid,
    .quick-grid { grid-template-columns: 1fr; }
    .hero-actions { width: 100%; }
    .hero-actions a { flex: 1 1 100%; }
    .booking-item,
    .notif-item { grid-template-columns: 1fr; }
    .booking-total,
    .notif-side { text-align: left; }
    .page {
        margin-top: 94px;
    }
}
</style>

<script>
(function() {
    const h = document.getElementById('mainHeader');
    if (!h) return;

    let last = window.scrollY;
    let tick = false;

    window.addEventListener('scroll', () => {
        if (!tick) {
            requestAnimationFrame(() => {
                const cur = window.scrollY;
                cur > last && cur > 80 ? h.classList.add('hidden') : h.classList.remove('hidden');
                last = cur;
                tick = false;
            });
            tick = true;
        }
    }, { passive: true });
})();
</script>
</body>
</html>
