<?php
include("peakscinemas_database.php");
session_start();
require_once(__DIR__ . "/seat_hold_helpers.php");

ensureSeatHoldSchema($conn);
releaseExpiredSeatHolds($conn);
$seatHoldToken = getSeatHoldToken();

$profile_link  = "personal_info_form.php";
$profile_photo = $_SESSION['profile_photo'] ?? null;
$user_initials = '';

if (isset($_SESSION['user_id'])) {
    $profile_link = "profile_dashboard.php";
    $stmt = $conn->prepare("SELECT Name, ProfilePhoto FROM customer WHERE Customer_ID = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!empty($row['ProfilePhoto'])) {
        $profile_photo = $row['ProfilePhoto'];
        $_SESSION['profile_photo'] = $profile_photo;
    }
    $nameParts = explode(' ', trim($row['Name'] ?? ''));
    $user_initials = strtoupper(substr($nameParts[0]??'',0,1).substr(end($nameParts)??'',0,1));
    if (strlen($user_initials)===1) $user_initials = strtoupper(substr($nameParts[0]??'',0,2));
}

$Movie_ID      = $_POST['movie_id']      ?? '';
$Mall_ID       = $_POST['mall_id']       ?? '';
$Date          = $_POST['date']          ?? '';
$TimeSlot_ID   = $_POST['timeslot_id']   ?? '';
$totalPrice    = (float)($_POST['priceTotal'] ?? 0);

// ── Seats: dedupe IDs and pair with labels from seat_selection (same order as first occurrence) ──
$rawSeatIds    = (array)($_POST['selectedSeats'] ?? []);
$rawSeatLabels = (array)($_POST['seatLabels']   ?? []);
$selectedSeats = [];
$labelBySeatId = [];
foreach ($rawSeatIds as $i => $sid) {
    $sidStr = trim((string)$sid);
    // Only real DB seat IDs (numeric). Placeholder IDs like "p_A_5" must not become (int)0.
    if ($sidStr === '' || !ctype_digit($sidStr)) {
        continue;
    }
    $sid = (int)$sid;
    if ($sid <= 0) {
        continue;
    }
    if (isset($labelBySeatId[$sid])) {
        continue;
    }
    $labelBySeatId[$sid] = isset($rawSeatLabels[$i]) ? trim((string)$rawSeatLabels[$i]) : '';
    $selectedSeats[]     = $sid;
}

// ── Parse food order ─────────────────────────────────────────────
$foodOrder = [];
$foodTotal = 0;
$specialRequests = isset($_POST['special_requests']) ? trim($_POST['special_requests']) : '';
if (!empty($_POST['foodOrder'])) {
    foreach ($_POST['foodOrder'] as $item) {
        $decoded = json_decode($item, true);
        if ($decoded) {
            $foodOrder[] = $decoded;
            $foodTotal  += ($decoded['price'] ?? 0) * ($decoded['qty'] ?? 1);
        }
    }
}
$seatTotal = $totalPrice - $foodTotal;

if (empty($selectedSeats) || $totalPrice <= 0) {
    header("Location: seat_selection.php?movie_id=$Movie_ID&mall_id=$Mall_ID&date=$Date&timeslot_id=$TimeSlot_ID");
    exit;
}

foreach ($selectedSeats as $seatId) {
    if (!holdSeatForToken($conn, (int)$seatId, (int)$TimeSlot_ID, $seatHoldToken, 10)) {
        header("Location: seat_selection.php?movie_id=$Movie_ID&mall_id=$Mall_ID&date=$Date&timeslot_id=$TimeSlot_ID");
        exit;
    }
}

$_SESSION['booking_data'] = [
    'movie_id'      => $Movie_ID,
    'mall_id'       => $Mall_ID,
    'date'          => $Date,
    'timeslot_id'   => $TimeSlot_ID,
    'selectedSeats' => $selectedSeats,
    'totalPrice'    => $totalPrice,
];

$movie_stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
$movie_stmt->bind_param("i", $Movie_ID); $movie_stmt->execute();
$movieDetails = $movie_stmt->get_result()->fetch_assoc();

$mall_stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$mall_stmt->bind_param("i", $Mall_ID); $mall_stmt->execute();
$mallDetails = $mall_stmt->get_result()->fetch_assoc();

$timeslot_stmt = $conn->prepare("SELECT * FROM timeslot INNER JOIN theater ON timeslot.Theater_ID = theater.Theater_ID WHERE TimeSlot_ID = ?");
$timeslot_stmt->bind_param("i", $TimeSlot_ID); $timeslot_stmt->execute();
$timeslotDetails = $timeslot_stmt->get_result()->fetch_assoc();

$seatPositions = [];
if (!empty($selectedSeats)) {
    $placeholders = implode(',', array_fill(0, count($selectedSeats), '?'));
    $seat_stmt = $conn->prepare("SELECT Seat_ID, SeatRow, SeatColumn FROM seats WHERE Seat_ID IN ($placeholders)");
    $types = str_repeat('i', count($selectedSeats));
    $seat_stmt->bind_param($types, ...$selectedSeats);
    $seat_stmt->execute();
    $seatResult = $seat_stmt->get_result();
    $byId = [];
    while ($s = $seatResult->fetch_assoc()) {
        $byId[(int)$s['Seat_ID']] = $s;
    }
    foreach ($selectedSeats as $sid) {
        $lbl = $labelBySeatId[$sid] ?? '';
        if ($lbl !== '') {
            $seatPositions[] = $lbl;
            continue;
        }
        $s = $byId[(int)$sid] ?? null;
        if (!$s) {
            continue;
        }
        $col = (int)($s['SeatColumn'] ?? 0);
        $seatPositions[] = $s['SeatRow'] . ($col > 0 ? $col : '?');
    }
    sort($seatPositions);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Payment – <?= htmlspecialchars($movieDetails['MovieName'] ?? 'Peak\'s Cinema') ?></title>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: #0f0f0f; color: #F9F9F9;
    min-height: 100vh; padding-top: 70px; padding-bottom: 60px;
}
body::before { content: ''; position: fixed; inset: 0; background: url('movie-background-collage.jpg') center/cover no-repeat; opacity: 0.12; z-index: 0; pointer-events: none; }
body::after  { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse at center, transparent 10%, rgba(15,15,15,0.55) 60%, #0f0f0f 100%); z-index: 1; pointer-events: none; }

/* ── Standardized Header ── */
header {
    background: #1C1C1C;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 20px;
    position: fixed; top: 0; left: 0; width: 100%;
    height: 50px; z-index: 1000;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    transition: transform 0.3s ease, all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
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
.header-actions { display: flex; align-items: center; gap: 8px; }

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
.bookings-btn:hover, .notif-btn:hover { 
    background: rgba(255,255,255,0.12); color: #F9F9F9;
    transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.bookings-btn { gap: 5px; }
.bookings-btn::after { content: 'My Bookings'; font-size: 0.75rem; font-weight: 600; }

.notif-wrap { position: relative; }
.notif-badge {
    position: absolute; top: -4px; right: -4px;
    background: #ff4d4d; color: #fff;
    font-size: 0.55rem; font-weight: 800;
    min-width: 16px; height: 16px; border-radius: 8px;
    display: none; align-items: center; justify-content: center;
    padding: 0 4px;
}

.profile-btn {
    background: #F9F9F9; border: none; border-radius: 50%;
    width: 34px; height: 34px;
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
        flex-wrap: nowrap;
        height: 50px;
        padding: 0 12px;
        gap: 8px;
    }
    .brand-logo-wrap { flex-shrink: 0; }
    .brand-logo { height: 32px; }
    .bookings-btn::after { display: none; }
    .bookings-btn, .notif-btn { width: 36px; justify-content: center; padding: 0; }
}

@media (max-width: 480px) {
    header { padding: 0 10px; gap: 6px; }
    .brand-logo { height: 30px; }
    .header-actions { gap: 5px; }
    .bookings-btn { padding: 6px 9px; font-size: 0.95rem; }
    .notif-btn { padding: 6px 9px; font-size: 0.95rem; }
    .profile-btn { width: 36px; height: 36px; }
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
.notif-item { padding: 12px 16px 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.15s; display: flex; gap: 6px; align-items: flex-start; text-decoration: none; color: inherit; }
.notif-item:hover { background: rgba(255,255,255,0.04); }
.notif-item.unread { background: rgba(255,77,77,0.05); }
.notif-dot { width: 6px; height: 6px; border-radius: 50%; background: #ff4d4d; flex-shrink: 0; margin-top: 6px; }
.notif-dot.read { background: transparent; }
.notif-item-body { flex: 1; min-width: 0; }
.notif-item-title { font-size: 0.8rem; font-weight: 700; margin-bottom: 2px; }
.notif-item-msg { font-size: 0.72rem; color: rgba(249,249,249,0.4); line-height: 1.5; }
.notif-item-time { font-size: 0.65rem; color: rgba(249,249,249,0.25); margin-top: 4px; }
.notif-empty { text-align: center; padding: 30px; font-size: 0.82rem; color: rgba(249,249,249,0.2); }
.notif-footer { padding: 10px 16px; border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; }
.notif-footer a { font-size: 0.75rem; color: #ff6b6b; text-decoration: none; font-weight: 600; }

.outer { position: relative; z-index: 10; width: 95%; max-width: 1100px; margin: 28px auto; }
.page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
.page-title  { font-size: 1.7rem; font-weight: 800; margin-bottom: 20px; }

.two-col { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }
@media (max-width: 860px) { .two-col { grid-template-columns: 1fr; } .sidebar { position: static !important; } }

@media (max-width: 600px) {
    body { padding-top: 100px; }
    .sandbox-banner { font-size: 0.7rem; padding: 10px 10px; top: 60px; }
    .outer { margin: 15px auto; width: 92%; }
    .page-title { font-size: 1.4rem; margin-bottom: 15px; }
    .panel-header { padding: 12px 15px; }
    .panel-body { padding: 15px; }
    .method-grid { grid-template-columns: 1fr; }
    .form-row { flex-direction: column; gap: 0; }
    .discount-type-row { flex-direction: column; }
    .disc-btn { padding: 12px; min-height: 44px; }
    .total-row { padding: 15px; }
    .total-val { font-size: 1.1rem; }
    .btn-submit { min-height: 48px; }
    .method-card { min-height: 52px; padding: 12px 16px; }
}

.panel { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; margin-bottom: 16px; }
.panel:last-child { margin-bottom: 0; }
.panel-header { padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; gap: 8px; }
.panel-header h2 { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
.panel-body { padding: 20px; }

.sidebar { position: sticky; top: 80px; }

.movie-card { display: flex; gap: 14px; padding: 16px; align-items: flex-start; }
.movie-thumb { width: 52px; height: 78px; flex-shrink: 0; border-radius: 7px; background: #111; background-size: cover; background-position: center; border: 1px solid rgba(255,255,255,0.08); }
.movie-card h3 { font-size: 0.88rem; font-weight: 800; margin-bottom: 6px; line-height: 1.3; }
.movie-card-meta { font-size: 0.7rem; color: rgba(249,249,249,0.4); display: flex; flex-direction: column; gap: 3px; }
.type-pill { display: inline-block; margin-top: 5px; font-size: 0.62rem; font-weight: 700; letter-spacing: 1px; background: rgba(255,77,77,0.12); border: 1px solid rgba(255,77,77,0.25); color: #ff6b6b; padding: 2px 8px; border-radius: 10px; }

.order-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.04); }
.order-row:last-child { border-bottom: none; }
.order-row .lbl { color: rgba(249,249,249,0.45); }
.order-row .val { font-weight: 600; text-align: right; max-width: 60%; }
.seats-wrap { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; margin-top: 4px; }
.seat-chip { padding: 2px 8px; border-radius: 5px; background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.2); font-size: 0.7rem; font-weight: 700; color: #ff6b6b; }
.total-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; background: rgba(255,77,77,0.06); border-top: 1px solid rgba(255,77,77,0.12); }
.total-lbl { font-size: 0.82rem; color: rgba(249,249,249,0.5); }
.total-val { font-size: 1.2rem; font-weight: 800; color: #ff4d4d; }

/* ── Sandbox Mode Banner ── */
.sandbox-banner {
    background: linear-gradient(90deg, #ff9800, #f57c00);
    color: #000;
    padding: 10px 20px;
    text-align: center;
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    position: sticky;
    top: 60px;
    z-index: 999;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.method-card { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #222; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; cursor: pointer; transition: all 0.2s; }
.method-card:hover { border-color: rgba(255,255,255,0.2); background: #272727; }
.method-card.selected { border-color: #ff4d4d; background: rgba(255,77,77,0.08); }
.method-card input[type="radio"] { display: none; }
.method-logo { width: 38px; height: 24px; object-fit: contain; background: #fff; border-radius: 4px; padding: 2px 4px; flex-shrink: 0; }
.method-name { font-size: 0.8rem; font-weight: 600; color: #F9F9F9; }

.fields-wrap { margin-top: 20px; display: none; }
.fields-wrap.active { display: block; }
.form-row { display: flex; gap: 12px; }
.form-group { flex: 1; margin-bottom: 14px; }
.form-group label { display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); margin-bottom: 6px; }
.form-group input { width: 100%; padding: 10px 13px; border-radius: 9px; border: 1px solid rgba(255,255,255,0.1); background: #222; color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.88rem; outline: none; transition: border-color 0.2s; }
.form-group input::placeholder { color: rgba(249,249,249,0.2); }
.form-group input:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
.form-group input.valid   { border-color: rgba(76,175,80,0.6); }
.form-group input.invalid { border-color: rgba(255,77,77,0.5); background: rgba(255,77,77,0.04); }
.err { font-size: 0.68rem; color: #ff6b6b; margin-top: 4px; display: none; }
.form-hint { font-size: 0.75rem; color: rgba(249,249,249,0.3); margin-top: 6px; }

.status-box { border-radius: 8px; padding: 11px 14px; font-size: 0.8rem; margin-top: 4px; display: none; }
.status-box.ok  { background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.25); color: #81c784; }
.status-box.err { background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.2); color: #ff6b6b; }

.btn-submit { width: 100%; padding: 14px; background: #2a2a2a; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; color: rgba(249,249,249,0.3); font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: not-allowed; pointer-events: none; transition: all 0.25s; margin-top: 6px; letter-spacing: 0.3px; }
.btn-submit.active { background: #ff4d4d; color: #fff; border-color: #ff4d4d; cursor: pointer; pointer-events: all; }
.btn-submit.active:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

input:-webkit-autofill, input:-webkit-autofill:focus { -webkit-text-fill-color: #F9F9F9 !important; -webkit-box-shadow: 0 0 0 1000px #222 inset !important; caret-color: #F9F9F9; }

/* ── PWD / Senior Discount ── */
.discount-panel { margin-bottom: 16px; }
.discount-type-row { display: flex; gap: 8px; margin-bottom: 14px; }
.disc-btn {
    flex: 1; padding: 10px 12px; border-radius: 9px; text-align: center;
    border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04);
    color: rgba(249,249,249,0.5); font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; font-family: 'Outfit', sans-serif;
}
.disc-btn:hover { border-color: rgba(255,77,77,0.3); color: #F9F9F9; }
.disc-btn.active { background: rgba(255,77,77,0.12); border-color: rgba(255,77,77,0.4); color: #ff4d4d; }
.disc-id-wrap { display: none; margin-bottom: 4px; }
.disc-preview { display: none; padding: 10px 14px; background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.2); border-radius: 9px; font-size: 0.8rem; color: #81c784; margin-top: 10px; }
.disc-law { font-size: 0.7rem; color: rgba(249,249,249,0.3); line-height: 1.6; margin-bottom: 14px; }

/* ── ID Photo Upload ── */
.id-upload-wrap {
    display: none;
    margin-top: 14px;
}
.id-upload-label {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 8px; padding: 20px;
    border: 2px dashed rgba(255,255,255,0.15); border-radius: 10px;
    cursor: pointer; transition: all 0.2s;
    background: rgba(255,255,255,0.03);
    text-align: center;
}
.id-upload-label:hover { border-color: rgba(255,77,77,0.4); background: rgba(255,77,77,0.04); }
.id-upload-label.has-file { border-color: rgba(76,175,80,0.4); border-style: solid; background: rgba(76,175,80,0.04); }
.id-upload-icon { font-size: 1.8rem; }
.id-upload-text { font-size: 0.78rem; color: rgba(249,249,249,0.45); line-height: 1.5; }
.id-upload-text strong { color: #ff6b6b; font-weight: 700; }
.id-upload-input { display: none; }
.id-preview-wrap {
    display: none; margin-top: 10px; position: relative;
    border-radius: 10px; overflow: hidden;
    border: 1px solid rgba(76,175,80,0.3);
}
.id-preview-wrap img {
    width: 100%; max-height: 200px; object-fit: cover;
    display: block;
}
.id-preview-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity 0.2s;
}
.id-preview-wrap:hover .id-preview-overlay { opacity: 1; }
.id-preview-change {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
    color: #fff; padding: 7px 16px; border-radius: 7px;
    font-family: 'Outfit', sans-serif; font-size: 0.78rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.id-preview-change:hover { background: rgba(255,255,255,0.25); }
.id-verified-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(76,175,80,0.1); border: 1px solid rgba(76,175,80,0.25);
    color: #81c784; font-size: 0.72rem; font-weight: 700;
    padding: 4px 10px; border-radius: 10px; margin-top: 8px;
}

/* ── Header actions ── */
.header-actions { display: flex; align-items: center; gap: 10px; }
.hnav-link { color: rgba(249,249,249,0.45); text-decoration: none; font-size: 0.78rem; font-weight: 500; padding: 6px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); transition: all 0.2s; white-space: nowrap; position: relative; }
.hnav-link:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
.hnav-badge { position: absolute; top: -4px; right: -4px; background: #ff4d4d; color: #fff; font-size: 0.55rem; font-weight: 800; min-width: 16px; height: 16px; border-radius: 8px; padding: 0 4px; display: none; align-items: center; justify-content: center; }
.notif-list { max-height: 300px; overflow-y: auto; }
.notif-item { padding: 12px 16px 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); cursor: pointer; transition: background 0.15s; display: flex; gap: 6px; align-items: flex-start; }
.notif-item:hover { background: rgba(255,255,255,0.04); }
.notif-dot { width: 6px; height: 6px; border-radius: 50%; background: #ff4d4d; flex-shrink: 0; margin-top: 6px; }
.notif-item-body { flex: 1; min-width: 0; }
.notif-item-title { font-size: 0.8rem; font-weight: 700; color: #F9F9F9; margin-bottom: 2px; }
.notif-item-msg { font-size: 0.72rem; color: rgba(249,249,249,0.45); line-height: 1.5; }
.notif-item-time  { font-size: 0.65rem; color: rgba(249,249,249,0.25); margin-top: 3px; }
.notif-empty { padding: 24px; text-align: center; font-size: 0.8rem; color: rgba(249,249,249,0.3); }
.notif-footer { padding: 10px 16px; border-top: 1px solid rgba(255,255,255,0.07); display: flex; justify-content: space-between; }
.notif-footer a { font-size: 0.72rem; color: rgba(249,249,249,0.4); text-decoration: none; }
.notif-footer a:hover { color: #ff4d4d; }
</style>
</head>
<body>

<header id="mainHeader">
    <a href="home.php" class="brand-logo-wrap" title="Peak's Cinema - Home">
        <img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo">
    </a>

    <!-- Right actions -->
    <div class="header-actions">
        <?php if (isset($_SESSION['user_id'])): ?>
        <button type="button" class="bookings-btn" onclick="window.location.href='my_bookings.php'" title="My Bookings">
            🎟
        </button>
        <div class="notif-wrap">
            <button class="notif-btn" id="notifBtn" onclick="toggleNotif(event)" title="Notifications">
                🔔<span class="notif-badge" id="notifBadge"></span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span>Notifications</span>
                    <button type="button" class="notif-mark-all" onclick="markAllRead()">Mark all read</button>
                </div>
                <div class="notif-list" id="notifList"><div class="notif-empty">Loading...</div></div>
                <div class="notif-footer">
                    <a href="notifications_page.php">View All →</a>
                    <a href="my_bookings.php" style="color:rgba(249,249,249,0.4);">My Bookings</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <button class="profile-btn" onclick="window.location.href='<?= $profile_link ?>'" title="Profile">
        <?php if (!empty($profile_photo)): ?>
            <img src="<?= htmlspecialchars($profile_photo) ?>?v=<?= time() ?>" alt="Profile" referrerpolicy="no-referrer">
        <?php elseif (!empty($user_initials)): ?>
            <div class="profile-initials"><?= htmlspecialchars($user_initials) ?></div>
        <?php else: ?>
            <div class="profile-initials">?</div>
        <?php endif; ?>
        </button>
    </div>
</header>

<div class="sandbox-banner">
    <span>🚧</span>
    <span>SANDBOX MODE: TEST TRANSACTIONS ONLY — NO REAL CHARGES WILL BE MADE</span>
</div>

<div class="outer">
    <p class="page-label">Book Tickets</p>
    <h1 class="page-title">Payment</h1>

    <div class="two-col">

        <!-- LEFT: Payment form -->
        <div>
            <form id="paymentForm" action="receipt.php" method="POST" novalidate>
                <input type="hidden" name="movie_id"    value="<?= htmlspecialchars($Movie_ID) ?>">
                <input type="hidden" name="mall_id"     value="<?= htmlspecialchars($Mall_ID) ?>">
                <input type="hidden" name="date"        value="<?= htmlspecialchars($Date) ?>">
                <input type="hidden" name="timeslot_id" value="<?= htmlspecialchars($TimeSlot_ID) ?>">
                <input type="hidden" name="totalPrice"  id="totalPriceHidden" value="<?= htmlspecialchars($totalPrice) ?>">
                <input type="hidden" name="discountType"   id="discountTypeHidden"   value="">
                <input type="hidden" name="discountId"     id="discountIdHidden"     value="">
                <input type="hidden" name="discountAmount" id="discountAmountHidden" value="0">
                <input type="hidden" name="discountPhoto"  id="discountPhotoHidden"  value="">
                <?php foreach ($selectedSeats as $seat): ?>
                <input type="hidden" name="selectedSeats[]" value="<?= htmlspecialchars((string)$seat) ?>">
                <?php
                $sl = $labelBySeatId[(int)$seat] ?? '';
                ?>
                <input type="hidden" name="seatLabels[]" value="<?= htmlspecialchars($sl) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="foodTotal" value="<?= htmlspecialchars((string)$foodTotal) ?>">
                <?php foreach ($foodOrder as $fi): ?>
                <input type="hidden" name="foodOrder[]" value="<?= htmlspecialchars(json_encode($fi), ENT_QUOTES, 'UTF-8') ?>">
                <?php endforeach; ?>
                <input type="hidden" name="special_requests" value="<?= htmlspecialchars($specialRequests) ?>">

                <!-- ── PWD / Senior Discount ── -->
                <div class="panel discount-panel">
                    <div class="panel-header"><h2>🪪 PWD / Senior Citizen Discount</h2></div>
                    <div class="panel-body">
                        <p class="disc-law">
                            Qualified PWD and Senior Citizens are entitled to <strong style="color:#ff4d4d;">20% off</strong> on ticket prices under RA 9994 &amp; RA 7277.
                            Please present your valid ID at the cinema entrance for verification.
                        </p>
                        <div class="discount-type-row">
                            <button type="button" class="disc-btn active" id="dBtn-none"   onclick="setDiscount('none')">No Discount</button>
                            <button type="button" class="disc-btn"        id="dBtn-pwd"    onclick="setDiscount('pwd')">♿ PWD</button>
                            <button type="button" class="disc-btn"        id="dBtn-senior" onclick="setDiscount('senior')">👴 Senior Citizen</button>
                        </div>
                        <div class="disc-id-wrap" id="discIdWrap">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>ID Number <span style="color:#ff4d4d;">*</span></label>
                                <input type="text" id="discountIdInput" placeholder="Enter your PWD / Senior Citizen ID number"
                                       oninput="onDiscIdChange(this.value)">
                            </div>

                            <!-- Photo upload -->
                            <div class="id-upload-wrap" id="idPhotoWrap">
                                <label style="font-size:0.68rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(249,249,249,0.4);display:block;margin-bottom:6px;">
                                    ID Photo <span style="color:#ff4d4d;">*</span>
                                    <span style="color:rgba(249,249,249,0.25);font-size:0.6rem;text-transform:none;letter-spacing:0;font-weight:500;margin-left:4px;">(JPG, PNG — max 5MB)</span>
                                </label>

                                <!-- Drop zone -->
                                <label class="id-upload-label" id="idUploadLabel" for="idPhotoInput">
                                    <div class="id-upload-icon">📷</div>
                                    <div class="id-upload-text">
                                        <strong>Click to upload</strong> or drag &amp; drop your ID photo<br>
                                        Front of PWD/Senior Citizen ID card
                                    </div>
                                </label>
                                <input type="file" class="id-upload-input" id="idPhotoInput"
                                       accept="image/jpeg,image/png,image/webp"
                                       onchange="handleIdPhoto(this)">

                                <!-- Preview -->
                                <div class="id-preview-wrap" id="idPreviewWrap">
                                    <img id="idPreviewImg" src="" alt="ID Preview">
                                    <div class="id-preview-overlay">
                                        <button type="button" class="id-preview-change"
                                                onclick="document.getElementById('idPhotoInput').click()">
                                            🔄 Change Photo
                                        </button>
                                    </div>
                                </div>

                                <div id="idVerifiedBadge" style="display:none;"></div>

                                <div id="idBlurWarning" style="display:none;margin-top:10px;padding:12px 14px;background:rgba(255,152,0,0.08);border:1px solid rgba(255,152,0,0.25);border-radius:9px;font-size:0.8rem;"></div>

                                <p style="font-size:0.68rem;color:rgba(249,249,249,0.25);margin-top:8px;line-height:1.5;">
                                    Your ID photo is used for verification at the cinema. It will not be stored permanently.
                                </p>
                            </div>
                        </div>
                        <div class="disc-preview" id="discPreview"></div>
                        <div id="verifyNotice" style="display:none;margin-top:10px;padding:11px 14px;background:rgba(255,152,0,0.07);border:1px solid rgba(255,152,0,0.2);border-radius:9px;font-size:0.78rem;color:#ffb74d;line-height:1.6;">
                            🔍 <strong>Staff Verification Required</strong><br>
                            Your discount will be reviewed by our staff. If your ID is valid, the discount will be confirmed. If rejected, you will be notified and the full amount will apply.
                        </div>
                    </div>
                </div>

                <!-- Payment method -->
                <div class="panel">
                    <div class="panel-header"><h2>💳 Choose Payment Method</h2></div>
                    <div class="panel-body">
                        <div class="method-grid">
                            <div class="method-card" onclick="selectMethod('credit')">
                                <input type="radio" name="paymentMethod" value="credit" id="credit">
                                <img src="visa.png" alt="Card" class="method-logo">
                                <span class="method-name">Credit / Debit</span>
                            </div>
                            <div class="method-card" onclick="selectMethod('paypal')">
                                <input type="radio" name="paymentMethod" value="paypal" id="paypal">
                                <img src="paypal.png" alt="PayPal" class="method-logo">
                                <span class="method-name">PayPal</span>
                            </div>
                            <div class="method-card" onclick="selectMethod('gcash')">
                                <input type="radio" name="paymentMethod" value="gcash" id="gcash">
                                <img src="gcash.png" alt="GCash" class="method-logo">
                                <span class="method-name">GCash</span>
                            </div>
                            <div class="method-card" onclick="selectMethod('paymaya')">
                                <input type="radio" name="paymentMethod" value="paymaya" id="paymaya">
                                <img src="paymaya.png" alt="PayMaya" class="method-logo">
                                <span class="method-name">PayMaya</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment details -->
                <div class="panel">
                    <div class="panel-header"><h2>📝 Payment Details</h2></div>
                    <div class="panel-body">

                        <!-- Credit / Debit -->
                        <div id="creditFields" class="fields-wrap">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" id="cardFirstName" name="cardFirstName" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="cardFirstNameErr">Letters only</div>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" id="cardLastName" name="cardLastName" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="cardLastNameErr">Letters only</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Card Number</label>
                                <input type="text" id="cardNumber" name="cardNumber" placeholder="1234 5678 9012 3456" data-required maxlength="19">
                                <div class="err" id="cardNumberErr">Enter a valid card number (13–16 digits)</div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Expiry Date</label>
                                    <input type="text" id="expiryDate" name="expiryDate" placeholder="MM/YY" data-required maxlength="5">
                                    <div class="err" id="expiryDateErr">Format: MM/YY</div>
                                </div>
                                <div class="form-group">
                                    <label>CVV</label>
                                    <input type="text" id="cvv" name="cvv" placeholder="123" data-required data-pattern="[0-9]{3,4}" maxlength="4">
                                    <div class="err" id="cvvErr">3–4 digits</div>
                                </div>
                            </div>
                        </div>

                        <!-- PayPal -->
                        <div id="paypalFields" class="fields-wrap">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" id="paypalFirstName" name="paypalFirstName" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="paypalFirstNameErr">Letters only</div>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" id="paypalLastName" name="paypalLastName" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="paypalLastNameErr">Letters only</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" id="paypalPhone" name="paypalPhone" placeholder="09XXXXXXXXX" data-required data-pattern="09[0-9]{9}" maxlength="11">
                                    <div class="err" id="paypalPhoneErr">Format: 09XXXXXXXXX</div>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" id="paypalEmail" name="paypalEmail" placeholder="you@example.com" data-required>
                                    <div class="err" id="paypalEmailErr">Enter a valid email</div>
                                </div>
                            </div>
                            <p class="form-hint">You will be redirected to PayPal to complete payment.</p>
                        </div>

                        <!-- GCash -->
                        <div id="gcashFields" class="fields-wrap">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" id="gcashFirstName" name="gcashFirstName" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="gcashFirstNameErr">Letters only</div>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" id="gcashLastName" name="gcashLastName" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="gcashLastNameErr">Letters only</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>GCash Mobile Number</label>
                                <input type="text" id="gcashNumber" name="gcashNumber" placeholder="09XXXXXXXXX" data-required data-pattern="09[0-9]{9}" maxlength="11">
                                <div class="err" id="gcashNumberErr">Format: 09XXXXXXXXX</div>
                            </div>
                            <p class="form-hint">You will receive a payment request in your GCash app.</p>
                        </div>

                        <!-- PayMaya -->
                        <div id="paymayaFields" class="fields-wrap">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" id="paymayaFirstName" name="paymayaFirstName" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="paymayaFirstNameErr">Letters only</div>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" id="paymayaLastName" name="paymayaLastName" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="paymayaLastNameErr">Letters only</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>PayMaya Mobile Number</label>
                                <input type="text" id="paymayaNumber" name="paymayaNumber" placeholder="09XXXXXXXXX" data-required data-pattern="09[0-9]{9}" maxlength="11">
                                <div class="err" id="paymayaNumberErr">Format: 09XXXXXXXXX</div>
                            </div>
                            <p class="form-hint">You will receive a payment request in your PayMaya app.</p>
                        </div>

                        <div id="noMethodMsg" style="text-align:center;padding:30px 0;color:rgba(249,249,249,0.2);font-size:0.85rem;">
                            ← Select a payment method to continue
                        </div>

                        <div id="statusBox" class="status-box"></div>
                        <button type="submit" class="btn-submit" id="submitBtn" disabled>Select a payment method</button>
                    </div>
                </div>

            </form>
        </div>

        <!-- RIGHT: Order summary -->
        <div class="sidebar">
            <div class="panel">
                <div class="panel-header"><h2>🎬 Your Booking</h2></div>
                <div class="movie-card">
                    <?php if ($movieDetails): ?>
                    <div class="movie-thumb" style="background-image:url('<?= htmlspecialchars($movieDetails['MoviePoster']) ?>');"></div>
                    <?php endif; ?>
                    <div>
                        <h3><?= htmlspecialchars($movieDetails['MovieName'] ?? '—') ?></h3>
                        <div class="movie-card-meta">
                            <?php if ($mallDetails): ?><span>📍 <?= htmlspecialchars($mallDetails['MallName']) ?></span><?php endif; ?>
                            <?php if ($timeslotDetails): ?>
                            <span>🏛 <?= htmlspecialchars($timeslotDetails['TheaterName']) ?></span>
                            <span>📅 <?= date('F d, Y', strtotime($Date)) ?></span>
                            <span>🕐 <?= date('g:i A', strtotime($timeslotDetails['StartTime'] ?? '00:00')) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($timeslotDetails): ?>
                        <span class="type-pill"><?= htmlspecialchars($timeslotDetails['ScreeningType']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><h2>🎟 Order Summary</h2></div>
                <div class="panel-body">
                    <div class="order-row">
                        <span class="lbl">Seats</span>
                        <span class="val">
                            <div class="seats-wrap">
                                <?php foreach ($seatPositions as $sp): ?>
                                <span class="seat-chip"><?= htmlspecialchars($sp) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </span>
                    </div>
                    <div class="order-row">
                        <span class="lbl">Quantity</span>
                        <span class="val"><?= count($selectedSeats) ?> seat<?= count($selectedSeats) > 1 ? 's' : '' ?></span>
                    </div>
                    <?php if (!empty($foodOrder)): ?>
                    <div class="order-row" style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,0.06);">
                        <span class="lbl" style="font-size:0.72rem;">Seats Subtotal</span>
                        <span class="val" style="font-size:0.82rem;">₱<?= number_format($seatTotal, 2) ?></span>
                    </div>
                    <?php foreach ($foodOrder as $fi): ?>
                    <div class="order-row">
                        <span class="lbl" style="display:flex;align-items:center;gap:6px;">
                            <span>🍿</span>
                            <span style="font-size:0.78rem;color:rgba(249,249,249,0.7);"><?= htmlspecialchars($fi['name']) ?> <span style="color:rgba(249,249,249,0.35);">×<?= (int)$fi['qty'] ?></span></span>
                        </span>
                        <span class="val" style="font-size:0.82rem;color:#ff6b6b;">₱<?= number_format($fi['price'] * $fi['qty'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="order-row">
                        <span class="lbl" style="font-size:0.72rem;">Food &amp; Drinks</span>
                        <span class="val" style="font-size:0.82rem;color:#ff6b6b;">+₱<?= number_format($foodTotal, 2) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Discount row — shown by JS -->
                    <div class="order-row" id="discountRow" style="display:none;">
                        <span class="lbl" id="discountRowLabel" style="color:#81c784;">🪪 Discount (20%)</span>
                        <span class="val" id="discountRowVal" style="color:#81c784;">−₱0.00</span>
                    </div>
                </div>
                <div class="total-row">
                    <span class="total-lbl">Total Amount</span>
                    <span class="total-val" id="grandTotalDisplay">₱<?= number_format($totalPrice, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ── Prices ────────────────────────────────────────────────────
const BASE_TOTAL  = <?= $totalPrice ?>;   // seats + food (before discount)
const SEAT_TOTAL  = <?= $seatTotal ?>;    // seats only (discount applies to this)
let   currentDiscount = 'none';
let   discountAmount  = 0;

// ── PWD / Senior Discount ─────────────────────────────────────
function setDiscount(type) {
    currentDiscount = type;
    ['none','pwd','senior'].forEach(t => {
        document.getElementById('dBtn-' + t).classList.toggle('active', t === type);
    });

    const idWrap    = document.getElementById('discIdWrap');
    const photoWrap = document.getElementById('idPhotoWrap');
    const preview   = document.getElementById('discPreview');
    const discRow   = document.getElementById('discountRow');
    const typeHid   = document.getElementById('discountTypeHidden');
    const amtHid    = document.getElementById('discountAmountHidden');

    if (type === 'none') {
        idWrap.style.display    = 'none';
        photoWrap.style.display = 'none';
        preview.style.display   = 'none';
        discRow.style.display   = 'none';
        discountAmount = 0;
        typeHid.value  = '';
        amtHid.value   = '0';
        document.getElementById('discountPhotoHidden').value = '';
        document.getElementById('verifyNotice').style.display = 'none';
        document.getElementById('idBlurWarning').style.display = 'none';
    } else {
        idWrap.style.display    = 'block';
        photoWrap.style.display = 'block';
        typeHid.value = type;
        document.getElementById('verifyNotice').style.display = 'block';
        onDiscIdChange(document.getElementById('discountIdInput').value);
    }
    updateGrandTotal();
    revalidate();
}

function onDiscIdChange(val) {
    const preview   = document.getElementById('discPreview');
    const discRow   = document.getElementById('discountRow');
    const idHid     = document.getElementById('discountIdHidden');
    const amtHid    = document.getElementById('discountAmountHidden');
    const lbl       = currentDiscount === 'pwd' ? 'PWD' : 'Senior Citizen';

    idHid.value = val.trim();

    if (val.trim() && currentDiscount !== 'none') {
        discountAmount = parseFloat((SEAT_TOTAL * 0.20).toFixed(2));
        const newTotal = (BASE_TOTAL - discountAmount).toFixed(2);
        preview.style.display   = 'block';
        preview.innerHTML        = `✓ ${lbl} discount applied: <strong>−₱${discountAmount.toFixed(2)}</strong> off tickets. New total: <strong>₱${newTotal}</strong>`;
        discRow.style.display    = '';
        document.getElementById('discountRowLabel').textContent = `🪪 ${lbl} Discount (20%)`;
        document.getElementById('discountRowVal').textContent   = `−₱${discountAmount.toFixed(2)}`;
        amtHid.value = discountAmount.toFixed(2);
    } else {
        discountAmount = 0;
        preview.style.display  = 'none';
        discRow.style.display  = 'none';
        amtHid.value = '0';
    }
    updateGrandTotal();
}

function updateGrandTotal() {
    const grand = (BASE_TOTAL - discountAmount).toFixed(2);
    document.getElementById('grandTotalDisplay').textContent = '₱' + parseFloat(grand).toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('totalPriceHidden').value = grand;
}

// ── Payment method ────────────────────────────────────────────
let currentMethod = '';

function selectMethod(method) {
    currentMethod = method;
    document.getElementById(method).checked = true;
    document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
    document.querySelector(`.method-card input[value="${method}"]`).closest('.method-card').classList.add('selected');
    document.querySelectorAll('.fields-wrap').forEach(f => f.classList.remove('active'));
    document.getElementById(method + 'Fields').classList.add('active');
    document.getElementById('noMethodMsg').style.display = 'none';
    revalidate();
}

function validateField(el, showErr) {
    const val     = el.value.trim();
    const req     = el.hasAttribute('data-required');
    const pattern = el.getAttribute('data-pattern');
    const errEl   = document.getElementById(el.id + 'Err');
    el.classList.remove('valid', 'invalid');
    if (!req && !val) { if (errEl) errEl.style.display = 'none'; return true; }
    if (req && !val) {
        if (showErr) { el.classList.add('invalid'); if (errEl) errEl.style.display = 'block'; }
        return false;
    }
    let ok = true;
    if (el.id === 'cardNumber')  ok = el.value.replace(/\s/g,'').length >= 13;
    else if (el.id === 'expiryDate') ok = /^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(el.value);
    else if (el.type === 'email') ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    else if (pattern) ok = new RegExp('^' + pattern + '$').test(val);
    el.classList.add(ok ? 'valid' : 'invalid');
    if (errEl) errEl.style.display = ok ? 'none' : 'block';
    return ok;
}

// ── Blur detection via canvas ────────────────────────────────
function checkImageBlur(imgEl, callback) {
    const canvas  = document.createElement('canvas');
    const ctx     = canvas.getContext('2d');
    canvas.width  = imgEl.naturalWidth  || imgEl.width;
    canvas.height = imgEl.naturalHeight || imgEl.height;
    ctx.drawImage(imgEl, 0, 0);

    // Sample a center region for sharpness
    const cx = Math.floor(canvas.width / 4);
    const cy = Math.floor(canvas.height / 4);
    const sw = Math.floor(canvas.width / 2);
    const sh = Math.floor(canvas.height / 2);
    const data = ctx.getImageData(cx, cy, sw, sh).data;

    // Laplacian variance — measures edge sharpness
    let sum = 0, sumSq = 0, n = 0;
    for (let i = 0; i < data.length; i += 4) {
        const gray = 0.299*data[i] + 0.587*data[i+1] + 0.114*data[i+2];
        sum   += gray;
        sumSq += gray * gray;
        n++;
    }
    const mean     = sum / n;
    const variance = (sumSq / n) - (mean * mean);
    // Threshold — variance < 180 = likely blurry for a document scan
    const isBlurry = variance < 180;
    callback(isBlurry, variance);
}

// ── ID Photo handler ────────────────────────────────────────
function handleIdPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    // Size check — max 5MB
    if (file.size > 5 * 1024 * 1024) {
        alert('Photo too large. Please upload an image under 5MB.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;

        const previewWrap = document.getElementById('idPreviewWrap');
        const previewImg  = document.getElementById('idPreviewImg');
        const uploadLabel = document.getElementById('idUploadLabel');
        const badge       = document.getElementById('idVerifiedBadge');

        previewImg.src            = base64;
        previewWrap.style.display = 'block';
        uploadLabel.style.display = 'none';
        badge.style.display       = 'block';
        badge.innerHTML           = '<span class="id-verified-badge">⏳ Checking photo...</span>';

        // Run blur check once image loads
        previewImg.onload = function() {
            checkImageBlur(previewImg, function(isBlurry, variance) {
                const blurWarn = document.getElementById('idBlurWarning');
                if (isBlurry) {
                    // Show warning but still allow — staff will do final check
                    badge.innerHTML = '<span class="id-verified-badge" style="background:rgba(255,152,0,0.1);border-color:rgba(255,152,0,0.3);color:#ffb74d;">⚠ Photo may be blurry</span>';
                    if (blurWarn) { blurWarn.style.display = 'block'; }
                    // Clear stored photo — force re-upload
                    document.getElementById('discountPhotoHidden').value = '';
                    // Give option: retry or proceed anyway
                    if (blurWarn) {
                        blurWarn.innerHTML = `
                            <div style="display:flex;align-items:flex-start;gap:10px;">
                                <span style="font-size:1.2rem;flex-shrink:0;">⚠️</span>
                                <div>
                                    <strong style="color:#ffb74d;display:block;margin-bottom:3px;">Photo appears blurry</strong>
                                    <span style="font-size:0.72rem;color:rgba(249,249,249,0.55);">Make sure the ID text is clearly readable. A blurry photo may cause your discount to be rejected during staff verification.</span>
                                    <div style="margin-top:8px;display:flex;gap:8px;">
                                        <button type="button" onclick="document.getElementById('idPhotoInput').click()"
                                                style="padding:6px 14px;border-radius:7px;border:none;background:#ffb74d;color:#000;font-family:'Outfit',sans-serif;font-size:0.75rem;font-weight:700;cursor:pointer;">
                                            📷 Retake Photo
                                        </button>
                                        <button type="button" onclick="acceptBlurryPhoto('${base64}')"
                                                style="padding:6px 14px;border-radius:7px;border:1px solid rgba(255,255,255,0.15);background:transparent;color:rgba(249,249,249,0.5);font-family:'Outfit',sans-serif;font-size:0.75rem;font-weight:600;cursor:pointer;">
                                            Use anyway
                                        </button>
                                    </div>
                                </div>
                            </div>`;
                    }
                } else {
                    badge.innerHTML = '<span class="id-verified-badge">✓ ID Photo Uploaded — Pending Staff Verification</span>';
                    if (blurWarn) blurWarn.style.display = 'none';
                    document.getElementById('discountPhotoHidden').value = base64;
                }
                revalidate();
            });
        };
    };
    reader.readAsDataURL(file);
}

function acceptBlurryPhoto(base64) {
    document.getElementById('discountPhotoHidden').value = base64;
    const blurWarn = document.getElementById('idBlurWarning');
    if (blurWarn) blurWarn.style.display = 'none';
    const badge = document.getElementById('idVerifiedBadge');
    if (badge) badge.innerHTML = '<span class="id-verified-badge" style="background:rgba(255,152,0,0.08);border-color:rgba(255,152,0,0.25);color:#ffb74d;">⚠ Blurry photo — may affect verification</span>';
    revalidate();
}

// Drag & drop support
document.addEventListener('DOMContentLoaded', () => {
    const label = document.getElementById('idUploadLabel');
    if (!label) return;
    label.addEventListener('dragover', e => { e.preventDefault(); label.style.borderColor = '#ff4d4d'; });
    label.addEventListener('dragleave', () => { label.style.borderColor = ''; });
    label.addEventListener('drop', e => {
        e.preventDefault(); label.style.borderColor = '';
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            const inp = document.getElementById('idPhotoInput');
            const dt  = new DataTransfer();
            dt.items.add(file);
            inp.files = dt.files;
            handleIdPhoto(inp);
        }
    });
});

function revalidate() {
    const statusEl = document.getElementById('statusBox');
    const btn      = document.getElementById('submitBtn');

    // Check: if discount selected, ID number required
    if (currentDiscount !== 'none' && !document.getElementById('discountIdInput').value.trim()) {
        statusEl.className = 'status-box err'; statusEl.textContent = 'Please enter your PWD/Senior ID number.'; statusEl.style.display = 'block';
        btn.disabled = true; btn.className = 'btn-submit'; btn.textContent = 'Enter your ID to continue';
        return;
    }
    // Check: if discount selected, ID photo required
    if (currentDiscount !== 'none' && !document.getElementById('discountPhotoHidden').value) {
        statusEl.className = 'status-box err'; statusEl.textContent = 'Please upload a photo of your PWD/Senior ID.'; statusEl.style.display = 'block';
        btn.disabled = true; btn.className = 'btn-submit'; btn.textContent = 'Upload ID photo to continue';
        return;
    }

    if (!currentMethod) { statusEl.style.display = 'none'; btn.disabled = true; btn.className = 'btn-submit'; btn.textContent = 'Select a payment method'; return; }

    const fields = document.querySelectorAll(`#${currentMethod}Fields input[data-required]`);
    let allOk = true, anyFilled = false;
    fields.forEach(f => { if (f.value.trim()) anyFilled = true; if (!validateField(f, false)) allOk = false; });

    if (!anyFilled) { statusEl.style.display = 'none'; }
    else if (allOk) { statusEl.className = 'status-box ok'; statusEl.textContent = '✓ All fields valid. Ready to pay.'; statusEl.style.display = 'block'; }
    else { statusEl.className = 'status-box err'; statusEl.textContent = 'Please fill in all required fields correctly.'; statusEl.style.display = 'block'; }

    btn.disabled = !allOk;
    btn.className = allOk ? 'btn-submit active' : 'btn-submit';
    btn.textContent = allOk ? 'Complete Payment →' : 'Complete all fields to continue';
}

document.getElementById('cardNumber')?.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim(); revalidate();
});
document.getElementById('expiryDate')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'');
    if (v.length >= 2) v = v.slice(0,2) + '/' + v.slice(2,4);
    this.value = v; revalidate();
});
document.querySelectorAll('input[data-required]').forEach(input => {
    input.addEventListener('input', revalidate);
    input.addEventListener('blur', function() { validateField(this, true); revalidate(); });
});

document.getElementById('paymentForm').addEventListener('submit', function(e) {
    if (!currentMethod) { e.preventDefault(); alert('Please select a payment method.'); return; }
    if (currentDiscount !== 'none' && !document.getElementById('discountIdInput').value.trim()) {
        e.preventDefault(); alert('Please enter your PWD/Senior Citizen ID number.'); return;
    }
    if (currentDiscount !== 'none' && !document.getElementById('discountPhotoHidden').value) {
        e.preventDefault(); alert('Please upload a photo of your PWD/Senior ID.'); return;
    }
    const fields = document.querySelectorAll(`#${currentMethod}Fields input[data-required]`);
    let ok = true;
    fields.forEach(f => { if (!validateField(f, true)) ok = false; });
    if (!ok) { e.preventDefault(); alert('Please fill in all required fields correctly.'); }
});

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
