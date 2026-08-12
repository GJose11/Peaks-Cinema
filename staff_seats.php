﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿<?php
date_default_timezone_set('Asia/Manila');
require_once(__DIR__ . "/staff_guard.php");
include(__DIR__ . "/peakscinemas_database.php");
require_once(__DIR__ . "/seat_hold_helpers.php");

$movie_id    = filter_input(INPUT_GET, 'movie_id',    FILTER_VALIDATE_INT);
$timeslot_id = filter_input(INPUT_GET, 'timeslot_id', FILTER_VALIDATE_INT);
if (!$movie_id || !$timeslot_id) { header("Location: staff_dashboard.php"); exit; }

$stmt = $conn->prepare("
    SELECT t.*, ml.MallName, th.TheaterName,
           m.MovieName, m.MoviePoster, m.Runtime, m.Genre, m.Price
    FROM timeslot t
    JOIN theater th ON th.Theater_ID = t.Theater_ID
    JOIN mall    ml ON ml.Mall_ID    = th.Mall_ID
    JOIN movie   m  ON m.Movie_ID   = t.Movie_ID
    WHERE t.TimeSlot_ID = ? AND t.Movie_ID = ?
");
$stmt->bind_param("ii", $timeslot_id, $movie_id);
$stmt->execute();
$screeningResult = $stmt->get_result();
$screening = $screeningResult->fetch_assoc();
$screeningResult->free();
$stmt->close();
if (!$screening) { header("Location: staff_dashboard.php"); exit; }

$seats_stmt = $conn->prepare("SELECT * FROM seats WHERE TimeSlot_ID = ? ORDER BY SeatRow ASC, CAST(SeatColumn AS UNSIGNED) ASC");
$seats_stmt->bind_param("i", $timeslot_id);
$seats_stmt->execute();
$seatResult = $seats_stmt->get_result();
$layout = [];
while ($s = $seatResult->fetch_assoc()) $layout[$s['SeatRow']][] = $s;
$seatResult->free();
$seats_stmt->close();

if (empty($layout)) {
    $theaterId = (int)($screening['Theater_ID'] ?? 0);
    $ts2 = $conn->prepare("SELECT * FROM seats WHERE Theater_ID = ? GROUP BY SeatRow, SeatColumn ORDER BY SeatRow ASC, CAST(SeatColumn AS UNSIGNED) ASC");
    $ts2->bind_param("i", $theaterId);
    $ts2->execute();
    $r2 = $ts2->get_result();
    while ($s = $r2->fetch_assoc()) $layout[$s['SeatRow']][] = $s;
    $r2->free();
    $ts2->close();
}
uksort($layout, 'strnatcasecmp');

$basePrice = floatval($screening['Price'] ?? 350);
$staffName = $_SESSION['staff_name'] ?? 'Staff';
$staffId   = $_SESSION['staff_id']   ?? '-';
$loginTime = $_SESSION['staff_login_time'] ?? date('g:i A');

// Mirror the same food-and-drinks catalog used by the customer seat-selection flow.
$foodMenu = [
    11 => ['id' => 11, 'name' => 'Regular Popcorn', 'description' => 'Salted or Buttered', 'price' => 120, 'icon' => '🍿'],
    12 => ['id' => 12, 'name' => 'Large Popcorn',   'description' => 'Salted or Buttered', 'price' => 160, 'icon' => '🍿'],
    15 => ['id' => 15, 'name' => 'Regular Drink',   'description' => 'Coke, Sprite, or Royal', 'price' => 80,  'icon' => '🥤'],
    19 => ['id' => 19, 'name' => 'Combo Meal',      'description' => 'Regular Popcorn + Drink', 'price' => 180, 'icon' => '🍿🥤'],
    20 => ['id' => 20, 'name' => 'Large Combo',     'description' => 'Large Popcorn + Drink',   'price' => 230, 'icon' => '🍿🥤'],
    14 => ['id' => 14, 'name' => 'Hot Dog',         'description' => 'Classic jumbo frank',     'price' => 110, 'icon' => '🌭'],
];

// Prefer real database data when the matching food_items records exist.
$foodTableCheck = $conn->query("SHOW TABLES LIKE 'food_items'");
if ($foodTableCheck && $foodTableCheck->num_rows > 0) {
    $foodIds = implode(',', array_map('intval', array_keys($foodMenu)));
    $foodQuery = $conn->query("
        SELECT Item_ID, ItemName, Description, Price, Category
        FROM food_items
        WHERE IsAvailable = 1 AND Item_ID IN ($foodIds)
        ORDER BY FIELD(Item_ID, $foodIds)
    ");
    if ($foodQuery) {
        while ($item = $foodQuery->fetch_assoc()) {
            $itemId = (int)$item['Item_ID'];
            if (!isset($foodMenu[$itemId])) {
                continue;
            }
            $foodMenu[$itemId]['name'] = $item['ItemName'] ?: $foodMenu[$itemId]['name'];
            $foodMenu[$itemId]['description'] = $item['Description'] ?: $foodMenu[$itemId]['description'];
            $foodMenu[$itemId]['price'] = (float)$item['Price'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Seat Selection - Staff | Peak's Cinema</title>
<style>
/* â”€â”€ Reset â”€â”€ */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

/* â”€â”€ Body â”€â”€ */
body {
    font-family: 'Outfit', sans-serif;
    background: #0f0f0f;
    color: #F9F9F9;
    min-height: 100vh;
    padding-top: 70px;
    padding-bottom: 60px;
}
body::before {
    content: '';
    position: fixed; inset: 0;
    background: url('movie-background-collage.jpg') center/cover no-repeat;
    opacity: 0.04; z-index: 0; pointer-events: none;
}
body::after {
    content: '';
    position: fixed; inset: 0;
    background: radial-gradient(ellipse at center, transparent 20%, #0f0f0f 75%);
    z-index: 1; pointer-events: none;
}

/* â”€â”€ Header â”€â”€ */
header {
    background: #1C1C1C;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 30px;
    position: fixed; top: 0; left: 0; width: 100%;
    height: 60px; z-index: 1000;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.logo {
    display: flex; align-items: center;
}
.logo img { height: 42px; width: auto; filter: invert(1); display: block; }
nav { display: flex; gap: 4px; }
nav a {
    color: rgba(249,249,249,0.5); text-decoration: none;
    font-size: 0.8rem; font-weight: 500;
    padding: 6px 14px; border-radius: 6px; transition: all 0.2s;
}
nav a:hover  { background: rgba(255,255,255,0.08); color: #F9F9F9; }
nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }

/* â”€â”€ Page wrapper â€” two columns â”€â”€ */
.outer {
    position: relative; z-index: 10;
    width: 95%; max-width: 1280px;
    margin: 28px auto;
}

.page-label {
    font-size: 0.72rem; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase;
    color: #ff4d4d; margin-bottom: 5px;
}
.page-title { font-size: 1.7rem; font-weight: 800; margin-bottom: 20px; }

.stepper { display: flex; align-items: stretch; gap: 0; margin-bottom: 24px; background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; overflow: hidden; }
.step-item { flex: 1; display: flex; align-items: center; gap: 12px; padding: 14px 20px; cursor: default; transition: background 0.2s; position: relative; }
.step-item:not(:last-child)::after { content: '›'; position: absolute; right: -1px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.15); font-size: 1.2rem; z-index: 1; }
.step-item.active   { background: rgba(255,77,77,0.08); }
.step-item.done     { background: rgba(102,187,106,0.06); cursor: pointer; }
.step-item.inactive { opacity: 0.4; }
.step-num { width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; font-weight: 800; }
.step-item.active   .step-num { background: #ff4d4d; color: #fff; }
.step-item.done     .step-num { background: #66BB6A; color: #fff; }
.step-item.inactive .step-num { background: rgba(255,255,255,0.1); color: rgba(249,249,249,0.4); }
.step-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.35); }
.step-title { font-size: 0.82rem; font-weight: 700; color: #F9F9F9; margin-top: 1px; }
.step-item.inactive .step-title { color: rgba(249,249,249,0.4); }

.two-col {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}
@media (max-width: 900px) {
    .two-col { grid-template-columns: 1fr; }
    .sidebar { position: static !important; }
    .stepper { margin-bottom: 18px; }
    .step-item { padding: 12px 16px; }
    .step-num { width: 26px; height: 26px; font-size: 0.75rem; }
    .step-label { font-size: 0.6rem; }
    .step-title { font-size: 0.8rem; }
    .food-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 560px) {
    .stepper { margin-bottom: 14px; border-radius: 10px; }
    .step-item { padding: 10px 12px; gap: 8px; }
    .step-num { width: 24px; height: 24px; font-size: 0.7rem; }
    .step-label { display: none; }
    .step-title { font-size: 0.75rem; }
    .food-grid { grid-template-columns: 1fr; }
}

/* â”€â”€ Panels â”€â”€ */
.panel {
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px; overflow: hidden;
    margin-bottom: 16px;
}
.panel:last-child { margin-bottom: 0; }
.panel-header {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; justify-content: space-between;
}
.panel-header h2 {
    font-size: 0.78rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: rgba(249,249,249,0.45);
}
.panel-body { padding: 20px; }

/* â”€â”€ Sticky sidebar â”€â”€ */
.sidebar { position: sticky; top: 80px; }

/* â”€â”€ Movie info card â”€â”€ */
.movie-card {
    display: flex; gap: 14px; padding: 16px;
    align-items: flex-start;
}
.movie-thumb {
    width: 52px; height: 78px; flex-shrink: 0;
    border-radius: 7px; background: #111;
    background-size: cover; background-position: center;
    border: 1px solid rgba(255,255,255,0.08);
}
.movie-card h3 { font-size: 0.88rem; font-weight: 800; margin-bottom: 6px; line-height: 1.3; }
.movie-card-meta {
    font-size: 0.7rem; color: rgba(249,249,249,0.4);
    display: flex; flex-direction: column; gap: 3px;
}
.type-pill {
    display: inline-block; margin-top: 5px;
    font-size: 0.62rem; font-weight: 700; letter-spacing: 1px;
    background: rgba(255,77,77,0.12); border: 1px solid rgba(255,77,77,0.25);
    color: #ff6b6b; padding: 2px 8px; border-radius: 10px;
}

.food-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.food-card {
    background: #1e1e1e;
    border: 2px solid rgba(255,255,255,0.07);
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}
.food-card:hover {
    border-color: rgba(255,77,77,0.35);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.4);
}
.food-card.selected {
    border-color: #ff4d4d;
    background: rgba(255,77,77,0.06);
}
.food-card-img {
    width: 100%;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.6rem;
    background: #161616;
}
.food-card-body { padding: 10px 12px; }
.food-card-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: #F9F9F9;
    margin-bottom: 2px;
}
.food-card-desc {
    font-size: 0.65rem;
    color: rgba(249,249,249,0.35);
    margin-bottom: 6px;
}
.food-card-price {
    font-size: 0.78rem;
    font-weight: 800;
    color: #ff4d4d;
}
.food-card-qty {
    display: none;
    align-items: center;
    justify-content: space-between;
    margin-top: 8px;
    gap: 6px;
}
.food-card.selected .food-card-qty { display: flex; }
.qty-btn {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: rgba(255,77,77,0.15);
    border: 1px solid rgba(255,77,77,0.3);
    color: #ff4d4d;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    flex-shrink: 0;
    font-family: 'Outfit', sans-serif;
}
.qty-btn:hover {
    background: #ff4d4d;
    color: #fff;
}
.qty-val {
    font-size: 0.88rem;
    font-weight: 700;
    color: #F9F9F9;
    min-width: 20px;
    text-align: center;
}
.food-check-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #ff4d4d;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 800;
    display: none;
    align-items: center;
    justify-content: center;
}
.food-card.selected .food-check-badge { display: flex; }
.food-order-list {
    margin: 10px 0 12px;
    min-height: 18px;
}
.food-summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: rgba(249,249,249,0.45);
    margin-bottom: 4px;
}
.food-subtotal {
    background: rgba(255,77,77,0.06);
    border: 1px solid rgba(255,77,77,0.15);
    border-radius: 10px;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    font-size: 0.82rem;
}
.food-subtotal .lbl { color: rgba(249,249,249,0.5); }
.food-subtotal .val {
    font-weight: 800;
    color: #ff4d4d;
    font-size: 0.95rem;
}
.food-help {
    font-size: 0.78rem;
    color: rgba(249,249,249,0.35);
    line-height: 1.5;
    margin-bottom: 16px;
}
.food-skip-note {
    text-align: center;
    font-size: 0.72rem;
    color: rgba(249,249,249,0.25);
    margin-bottom: 14px;
}
.food-actions { display: flex; gap: 10px; }
.btn-back-seats { flex: 1; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: rgba(249,249,249,0.5); font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-back-seats:hover { background: rgba(255,255,255,0.1); color: #F9F9F9; }
.btn-pay-now { flex: 2; padding: 12px; background: #ff4d4d; border: none; border-radius: 10px; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.92rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.btn-pay-now:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

/* Special Requests */
.quick-requests { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
.request-btn { padding: 7px 16px; background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 7px; color: rgba(249,249,249,0.7); font-family: 'Outfit', sans-serif; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.15s; }
.request-btn:hover { border-color: rgba(255,77,77,0.4); color: #F9F9F9; background: #2a2a2a; }
.request-btn.active { background: rgba(255,77,77,0.1); border-color: #ff4d4d; color: #ff4d4d; }

#foodStep { display: none; }
#foodStep.active { display: block; }

/* â”€â”€ Screen bar â”€â”€ */
.screen-wrap {
    text-align: center;
    margin-bottom: 20px;
}
.screen-bar {
    display: inline-block;
    padding: 7px 60px;
    background: linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0.03));
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 2px 2px 28% 28% / 2px 2px 10px 10px;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 4px;
    color: rgba(255,255,255,0.4);
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   SEAT GRID â€” built entirely with inline-block
   so each row's label + seats are treated as
   one natural text flow with no flex/grid math
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
.seat-map {
    /* Shrink to fit, then centre horizontally */
    display: table;
    margin: 0 auto;
    width: auto;
}

.srow {
    display: table-row;
}

.rlbl {
    display: table-cell;
    width: 18px;
    min-width: 18px;
    vertical-align: middle;
    text-align: center;
    font-size: 0.65rem;
    font-weight: 700;
    color: rgba(249,249,249,0.3);
    padding: 0;
    white-space: nowrap;
}
/* Right label â€” identical to left */
.rlbl-r { padding: 0; }

.seat, .seat-empty {
    display: table-cell;
    width: 36px;
    height: 36px;
    vertical-align: middle;
    text-align: center;
}

/* Spacer between seats */
.seat-gap {
    display: table-cell;
    width: 4px;
}
.aisle-gap {
    display: table-cell;
    width: 18px;
}
.section-gap {
    display: table-cell;
    width: 8px; /* smaller gap between groups of 5 */
}
/* Spacer between rows */
.row-gap {
    display: table-row;
    height: 0;
}

.seat {
    border-radius: 7px 7px 3px 3px;
    font-size: 0.62rem;
    font-weight: 700;
    line-height: 36px;
    cursor: pointer;
    user-select: none;
    transition: transform 0.1s, filter 0.1s;
    border: none;
    outline: none;
}

.seat.standard { background: #2e2e2e; border-bottom: 3px solid #505050; color: rgba(249,249,249,0.5); }
.seat.vip      { background: #3d1a1a; border-bottom: 3px solid #ff4d4d; color: #ff7070; }
.seat.imax     { background: #332b00; border-bottom: 3px solid #ffc107; color: #ffd54f; }
.seat.taken    { background: #1c1c1c; border-bottom: 3px solid #282828 !important; color: #333; cursor: not-allowed; }
.seat.empty-seat { background: #2e2e2e; border-bottom: 3px solid #505050; color: rgba(249,249,249,0.5); cursor: pointer; }

.seat:hover:not(.taken):not(.empty-seat):not(.selected) {
    transform: scale(1.18);
    filter: brightness(1.45);
    position: relative; z-index: 2;
}
.seat.selected {
    background: rgba(255,77,77,0.28) !important;
    border-bottom: 3px solid #ff4d4d !important;
    color: #fff !important;
    transform: scale(1.12);
    position: relative; z-index: 2;
    box-shadow: 0 0 10px rgba(255,77,77,0.35);
}

/* â”€â”€ Legend â”€â”€ */
.legend {
    display: flex; flex-wrap: wrap; gap: 14px;
    justify-content: center;
    margin-top: 22px; padding-top: 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.68rem; color: rgba(249,249,249,0.4); }
.legend-dot { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; }

/* â”€â”€ Summary panel â”€â”€ */
.divider { height: 1px; background: rgba(255,255,255,0.06); margin: 14px 0; }
.no-seats { font-size: 0.78rem; color: rgba(249,249,249,0.22); }

.seat-tag {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.25);
    border-radius: 6px; padding: 4px 10px;
    font-size: 0.78rem; font-weight: 700; color: #ff6b6b;
    margin: 3px;
}
.seat-tag button { background: none; border: none; color: #ff6b6b; cursor: pointer; font-size: 0.75rem; padding: 0; }

.price-row { display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 8px; }
.price-row .lbl { color: rgba(249,249,249,0.45); }
.price-row .val { font-weight: 700; }
.price-row.total .lbl { font-size: 0.88rem; color: #F9F9F9; font-weight: 700; }
.price-row.total .val { font-size: 1.1rem; color: #ff4d4d; font-weight: 800; }

.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.form-group label { font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); }
.form-group input {
    background: #222; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 9px; color: #F9F9F9;
    font-family: 'Outfit', sans-serif; font-size: 0.88rem;
    padding: 10px 13px; outline: none; width: 100%;
    transition: border-color 0.2s;
}
.form-group input:focus { border-color: rgba(255,77,77,0.5); background: #252525; }

.btn-proceed {
    width: 100%; padding: 13px;
    background: #2a2a2a; border: 1px solid rgba(255,255,255,0.08);
    border-radius: 9px; color: rgba(249,249,249,0.3);
    font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 700;
    cursor: not-allowed; pointer-events: none;
    transition: all 0.25s; margin-top: 6px;
}
.btn-proceed.active { background: #ff4d4d; color: #fff; border-color: #ff4d4d; cursor: pointer; pointer-events: all; }
.btn-proceed.active:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

.error-banner {
    background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.25);
    border-left: 3px solid #ff4d4d; border-radius: 8px;
    padding: 11px 16px; font-size: 0.82rem; color: #ff6b6b;
    margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
}
</style>
</head>
<body>

<!-- Header -->
<header>
    <div class="logo"><img src="peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
    <nav>
        <a href="staff_dashboard.php">Dashboard</a>
        <a href="staff_seats.php" class="active">Seat Selection</a>
        <a href="staff_payment.php">Payment</a>
        <a href="staff_receipt.php">Receipt</a>
    </nav>
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="text-align:right;line-height:1.4;">
            <div style="font-size:0.78rem;font-weight:600;color:rgba(249,249,249,0.6);"><?= htmlspecialchars($staffName) ?></div>
            <div style="font-size:0.62rem;color:rgba(249,249,249,0.25);">Staff #<?= htmlspecialchars($staffId) ?> - Since <?= htmlspecialchars($loginTime) ?></div>
        </div>
        <a href="home.php"
           style="color:rgba(249,249,249,0.45);text-decoration:none;font-size:0.78rem;font-weight:500;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);transition:all 0.2s;"
           onmouseover="this.style.background='rgba(255,255,255,0.06)';this.style.color='#F9F9F9'"
           onmouseout="this.style.background='';this.style.color='rgba(249,249,249,0.45)'">&#8592; Customer Site</a>
        <a href="staff_logout.php"
           style="color:#ff4d4d;text-decoration:none;font-size:0.78rem;font-weight:600;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,77,77,0.3);background:rgba(255,77,77,0.08);transition:all 0.2s;"
           onmouseover="this.style.background='rgba(255,77,77,0.18)'"
           onmouseout="this.style.background='rgba(255,77,77,0.08)'"
           onclick="return confirm('Log out?')">&#x2192; Log Out</a>
    </div>
</header>

<div class="outer">
    <p class="page-label">Book Tickets</p>
    <h1 class="page-title" id="pageTitle">Select Your Seats</h1>

    <div class="stepper">
        <div class="step-item active" id="step1Tab" onclick="goToStep(1)">
            <div class="step-num">1</div>
            <div><div class="step-label">Step 1</div><div class="step-title">Choose Seats</div></div>
        </div>
        <div class="step-item inactive" id="step2Tab">
            <div class="step-num">2</div>
            <div><div class="step-label">Step 2</div><div class="step-title">Food &amp; Drinks</div></div>
        </div>
    </div>

    <div id="seatStep">
        <div class="two-col">

            <div>
                <?php if (isset($_GET['error']) && $_GET['error'] === 'no_seats'): ?>
                <div class="error-banner">No seats were selected. Please select at least one seat before proceeding.</div>
                <?php endif; ?>

                <div class="panel">
                    <div class="panel-header"><h2>Theater Layout</h2></div>
                    <div class="panel-body">

                        <?php if (empty($layout)): ?>
                        <div style="text-align:center;padding:40px;color:rgba(249,249,249,0.2);font-size:0.82rem;">
                            No seats found for this screening.
                        </div>
                        <?php else: ?>

                        <div class="screen-wrap">
                            <div class="screen-bar">SCREEN</div>
                        </div>

                        <div style="overflow-x:auto; padding-bottom:8px;">
                        <div class="seat-map">

                            <?php
                            $firstRow = true;
                            define('CINEMA_HALF', 10);
                            define('CINEMA_QTR',  5);
                            ?>
                            <?php foreach ($layout as $rowLabel => $cols): ?>
                            <?php
                            $slotCount = 0;
                            foreach ($cols as $s) {
                                $c = (int)($s['SeatColumn'] ?? 0);
                                if ($c > $slotCount) {
                                    $slotCount = $c;
                                }
                            }
                            $slotCount = max($slotCount, 20);

                            $paddedCols = array_fill(0, $slotCount, null);
                            foreach ($cols as $s) {
                                $c = (int)($s['SeatColumn'] ?? 0);
                                if ($c > 0 && $c <= $slotCount) {
                                    $paddedCols[$c - 1] = $s;
                                }
                            }

                            $defaultRef = ['SeatType' => 'Standard', 'SeatPrice' => $basePrice];
                            for ($i = 0; $i < $slotCount; $i++) {
                                if ($paddedCols[$i] === null) {
                                    $ref = $cols[0] ?? $defaultRef;
                                    $paddedCols[$i] = [
                                        'Seat_ID'          => 'p_' . $rowLabel . '_' . ($i + 1),
                                        'SeatRow'          => $rowLabel,
                                        'SeatColumn'       => $i + 1,
                                        'SeatType'         => $ref['SeatType'] ?? 'Standard',
                                            'SeatAvailability' => 'Available',
                                        'SeatPrice'        => $ref['SeatPrice'] ?? $basePrice,
                                    ];
                                }
                            }
                            ?>

                            <?php if (!$firstRow): ?>
                            <div class="row-gap"></div>
                            <?php endif; $firstRow = false; ?>

                            <div class="srow">
                                <div class="rlbl"><?= htmlspecialchars($rowLabel) ?></div>
                                <div class="seat-gap"></div>

                                <?php foreach ($paddedCols as $seatPos => $seat):
                                    $type  = strtolower(trim($seat['SeatType'] ?? 'standard'));
                                    $avail = normalizeSeatAvailability($seat['SeatAvailability'] ?? 'Available');
                                    $css   = str_contains($type,'vip') ? 'vip' : (str_contains($type,'imax') ? 'imax' : 'standard');
                                    $taken = ($avail !== 'Available');
                                    $price = floatval($seat['SeatPrice'] ?? $basePrice);
                                    if (str_contains($type,'vip'))  $price *= 1.3;
                                    if (str_contains($type,'imax')) $price *= 1.5;

                                    $isPad = is_string($seat['Seat_ID'] ?? null) && strncmp((string)$seat['Seat_ID'], 'p_', 2) === 0;
                                    $displayNum = $seatPos + 1;
                                    $lbl = $seat['SeatRow'] . $displayNum;
                                ?>

                                <?php if ($seatPos > 0): ?>
                                <div class="seat-gap"></div>
                                <?php endif; ?>
                                <?php if ($seatPos === CINEMA_HALF): ?>
                                <div class="aisle-gap"></div>
                                <?php elseif ($seatPos === CINEMA_QTR || $seatPos === CINEMA_HALF + CINEMA_QTR): ?>
                                <div class="section-gap"></div>
                                <?php endif; ?>

                                    <div class="seat <?= $css ?><?= ($taken || $isPad) ? ' taken' : '' ?>"
                                         style="<?= $isPad ? 'opacity:0.2;cursor:default;' : '' ?>"
                                         data-id="<?= htmlspecialchars($seat['Seat_ID']) ?>"
                                         data-label="<?= htmlspecialchars($lbl) ?>"
                                         data-price="<?= round($price) ?>"
                                         data-type="<?= htmlspecialchars($type) ?>"
                                         onclick="<?= ($taken || $isPad) ? '' : 'toggleSeat(this)' ?>"
                                         title="<?= $isPad ? 'Not a bookable seat' : ($lbl . ' - ' . ($taken ? 'Taken' : 'Available - ₱'.number_format(round($price)))) ?>">
                                         <?= $isPad ? $displayNum : ($taken ? 'X' : $displayNum) ?>
                                    </div>

                                <?php endforeach; ?>

                                <div class="seat-gap"></div>
                                <div class="rlbl rlbl-r"><?= htmlspecialchars($rowLabel) ?></div>
                            </div>

                            <?php endforeach; ?>

                        </div>
                        </div>

                        <div class="legend">
                            <div class="legend-item"><div class="legend-dot" style="background:#2e2e2e;border-bottom:3px solid #505050;"></div>Standard</div>
                            <div class="legend-item"><div class="legend-dot" style="background:#3d1a1a;border-bottom:3px solid #ff4d4d;"></div>VIP</div>
                            <div class="legend-item"><div class="legend-dot" style="background:#332b00;border-bottom:3px solid #ffc107;"></div>IMAX</div>
                            <div class="legend-item"><div class="legend-dot" style="background:rgba(255,77,77,0.28);border-bottom:3px solid #ff4d4d;"></div>Selected</div>
                            <div class="legend-item"><div class="legend-dot" style="background:#1c1c1c;border-bottom:3px solid #282828;"></div>Taken</div>
                        </div>

                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sidebar">
                <div class="panel">
                    <div class="panel-header"><h2>Now Booking</h2></div>
                    <div class="movie-card">
                        <div class="movie-thumb" style="background-image:url('<?= htmlspecialchars($screening['MoviePoster']) ?>');"></div>
                        <div>
                            <h3><?= htmlspecialchars($screening['MovieName']) ?></h3>
                            <div class="movie-card-meta">
                                <span><?= htmlspecialchars($screening['MallName']) ?></span>
                                <span><?= htmlspecialchars($screening['TheaterName']) ?></span>
                                <span><?= date('F d, Y', strtotime($screening['Date'])) ?></span>
                                <span><?= date('g:i A', strtotime($screening['StartTime'])) ?></span>
                            </div>
                            <span class="type-pill"><?= htmlspecialchars($screening['ScreeningType']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><h2>Booking Summary</h2></div>
                    <div class="panel-body">
                        <div class="price-row">
                            <span class="lbl">Customer</span>
                            <span class="val">Walk-in Customer</span>
                        </div>
                        <div class="divider"></div>
                        <div id="seatTagList" style="margin-bottom:14px;min-height:40px;">
                            <span class="no-seats">No seats selected yet.</span>
                        </div>
                        <div class="divider"></div>
                        <div class="price-row">
                            <span class="lbl">Seats Selected</span>
                            <span class="val" id="seatCount">0</span>
                        </div>
                        <div class="price-row total">
                            <span class="lbl">Total</span>
                            <span class="val">₱ <span id="seatPriceTotal">0.00</span></span>
                        </div>
                        <button type="button" class="btn-proceed" id="confirmBtn" onclick="goToStep(2)" disabled>
                            Select seats to continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="foodStep">
        <div class="two-col">
            <div>
                <div class="panel">
                    <div class="panel-header"><h2>🍿 Add Food &amp; Drinks</h2></div>
                    <div class="panel-body">
                        <p class="food-help">
                            Pre-order snacks for this screening — skip the queue! All items will be ready at the counter when the customer arrives.
                        </p>
                        <div class="food-grid">
                            <?php foreach ($foodMenu as $food): ?>
                            <div class="food-card" id="fc-<?= (int)$food['id'] ?>" onclick="toggleFood(<?= (int)$food['id'] ?>)">
                                <div class="food-check-badge">✓</div>
                                <div class="food-card-img"><?= htmlspecialchars($food['icon']) ?></div>
                                <div class="food-card-body">
                                    <div class="food-card-name"><?= htmlspecialchars($food['name']) ?></div>
                                    <div class="food-card-desc"><?= htmlspecialchars($food['description']) ?></div>
                                    <div class="food-card-price">₱<?= number_format((float)$food['price']) ?></div>
                                    <div class="food-card-qty">
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(<?= (int)$food['id'] ?>,-1)">−</button>
                                        <span class="qty-val" id="qty-<?= (int)$food['id'] ?>">1</span>
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(<?= (int)$food['id'] ?>,1)">+</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="panel" style="margin-top: 20px;">
                            <div class="panel-header"><h2>Special Requests</h2></div>
                            <div class="panel-body">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); margin-bottom: 6px;">Quick Instructions</label>
                                    <div class="quick-requests" style="margin-bottom: 12px;">
                                        <button type="button" class="request-btn" onclick="addRequest('No ice', this)">No ice</button>
                                        <button type="button" class="request-btn" onclick="addRequest('Extra ice', this)">Extra ice</button>
                                        <button type="button" class="request-btn" onclick="addRequest('Extra butter', this)">Extra butter</button>
                                        <button type="button" class="request-btn" onclick="addRequest('Less salt', this)">Less salt</button>
                                        <button type="button" class="request-btn" onclick="addRequest('Separated', this)">Separated</button>
                                    </div>
                                    <label style="display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); margin-bottom: 6px;">Food & Drink Instructions</label>
                                    <textarea name="special_requests" id="specialRequests" rows="3" placeholder="e.g., No ice, extra butter on popcorn..." 
                                        style="width: 100%; padding: 12px; border-radius: 9px; border: 1px solid rgba(255,255,255,0.1); background: #222; color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.88rem; outline: none; transition: border-color 0.2s; resize: none;" oninput="syncRequestButtons()"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sidebar">
                <div class="panel">
                    <div class="panel-header"><h2>Now Booking</h2></div>
                    <div class="movie-card">
                        <div class="movie-thumb" style="background-image:url('<?= htmlspecialchars($screening['MoviePoster']) ?>');"></div>
                        <div>
                            <h3><?= htmlspecialchars($screening['MovieName']) ?></h3>
                            <div class="movie-card-meta">
                                <span><?= htmlspecialchars($screening['MallName']) ?></span>
                                <span><?= htmlspecialchars($screening['TheaterName']) ?></span>
                                <span><?= date('F d, Y', strtotime($screening['Date'])) ?></span>
                                <span><?= date('g:i A', strtotime($screening['StartTime'])) ?></span>
                            </div>
                            <span class="type-pill"><?= htmlspecialchars($screening['ScreeningType']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><h2>Order Summary</h2></div>
                    <div class="panel-body">
                        <div class="price-row" style="margin-bottom:8px;">
                            <span class="lbl">Seats (<span id="foodSeatCount">0 seats</span>)</span>
                            <span class="val">₱ <span id="foodSeatTotal">0.00</span></span>
                        </div>
                        <div id="foodOrderList" class="food-order-list">
                            <div style="font-size:0.72rem;color:rgba(249,249,249,0.2);margin-bottom:4px;">No food added</div>
                        </div>
                        <div class="divider"></div>
                        <div class="food-subtotal">
                            <span class="lbl">🍿 Food &amp; Drinks</span>
                            <span class="val">+ ₱<span id="foodSubtotal">0.00</span></span>
                        </div>
                        <div class="price-row total" style="margin-bottom:16px;">
                            <span class="lbl">Grand Total</span>
                            <span class="val">₱ <span id="grandTotal">0.00</span></span>
                        </div>
                        <p class="food-skip-note">🍿 Food is optional — you can skip this step</p>
                        <div class="food-actions">
                            <button type="button" class="btn-back-seats" onclick="goToStep(1)">← Back</button>
                            <button type="button" class="btn-pay-now" onclick="proceedToPayment()">Proceed to Payment →</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- /outer -->

<script>
const selectedSeats = {};
const foodItems = {};
const MOVIE_ID = <?= $movie_id ?>;
const TIMESLOT_ID = <?= $timeslot_id ?>;

// CRITICAL FIX: Dynamic seat map scaling to prevent overflow
(function() {
    function scaleSeatMap() {
        const container = document.querySelector('.panel-body');
        const seatMap = document.getElementById('seatMap');
        
        if (!container || !seatMap) {
            console.warn('Seat map or container not found');
            return;
        }
        
        // Reset transform to get natural size
        seatMap.style.transform = 'scale(1)';
        seatMap.style.marginBottom = '0';
        
        // Get natural dimensions
        const containerWidth = container.offsetWidth;
        const seatMapWidth = seatMap.scrollWidth;
        const seatMapHeight = seatMap.scrollHeight;
        
        if (seatMapWidth === 0) {
            console.warn('Seat map width is 0, skipping scale');
            return;
        }
        
        // Calculate scale to fit container
        const isMobile = window.innerWidth <= 768;
        const padding = isMobile ? 5 : 8; 
        const maxWidth = containerWidth - padding;
        let scale = 1;
        
        if (seatMapWidth > maxWidth) {
            scale = maxWidth / seatMapWidth;
        }
        
        // On mobile, ensure minimum scale for visibility
        if (isMobile && scale < 0.5) {
            scale = 0.5;
        }
        
        // Apply scale
        seatMap.style.transform = `scale(${scale})`;
        seatMap.style.transformOrigin = 'top center';
        
        // Calculate and apply negative margin to compensate for scaled height
        const scaledHeight = seatMapHeight * scale;
        const heightDiff = seatMapHeight - scaledHeight;
        seatMap.style.marginBottom = `-${heightDiff}px`;
        
        // Set container min-height to prevent collapse
        container.style.minHeight = `${scaledHeight}px`;
        
        // Ensure map is visible
        seatMap.style.visibility = 'visible';
        seatMap.style.opacity = '1';
    }
    
    // Run on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scaleSeatMap);
    } else {
        scaleSeatMap();
    }
    
    // Run on resize with debounce
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(scaleSeatMap, 100);
    });
    
    // Run after a short delay to ensure everything is rendered
    setTimeout(scaleSeatMap, 300);
})();

const FOOD_MENU = <?= json_encode(array_map(static function ($food) {
    return [
        'name' => $food['name'],
        'price' => (float)$food['price'],
    ];
}, $foodMenu), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function goToStep(step) {
    const seatStep = document.getElementById('seatStep');
    const foodStep = document.getElementById('foodStep');
    const step1Tab = document.getElementById('step1Tab');
    const step2Tab = document.getElementById('step2Tab');
    const pageTitle = document.getElementById('pageTitle');

    if (step === 1) {
        seatStep.style.display = '';
        foodStep.classList.remove('active');
        step1Tab.className = 'step-item active';
        step2Tab.className = 'step-item inactive';
        pageTitle.textContent = 'Select Your Seats';
    } else {
        if (Object.keys(selectedSeats).length === 0) return;
        seatStep.style.display = 'none';
        foodStep.classList.add('active');
        step1Tab.className = 'step-item done';
        step2Tab.className = 'step-item active';
        pageTitle.textContent = 'Food & Drinks';
        updateFoodSummary();
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function toggleSeat(el) {
    const { id, label, price, type } = el.dataset;
    if (selectedSeats[id]) {
        delete selectedSeats[id];
        el.classList.remove('selected');
    } else {
        selectedSeats[id] = { label, price: parseFloat(price) || 0, type };
        el.classList.add('selected');
    }
    updateSummary();
}

function removeSeat(id) {
    delete selectedSeats[id];
    document.querySelector(`.seat[data-id="${id}"]`)?.classList.remove('selected');
    updateSummary();
}

function updateSummary() {
    const keys = Object.keys(selectedSeats);
    const total = keys.reduce((sum, key) => sum + selectedSeats[key].price, 0);

    document.getElementById('seatCount').textContent = keys.length;
    document.getElementById('seatPriceTotal').textContent = total.toLocaleString('en-PH', { minimumFractionDigits: 2 });

    const list = document.getElementById('seatTagList');
    list.innerHTML = keys.length === 0
        ? '<span class="no-seats">No seats selected yet.</span>'
        : keys.map(id =>
            `<span class="seat-tag">
                ${selectedSeats[id].label}
                <span style="font-size:0.62rem;opacity:0.55;">₱${selectedSeats[id].price.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
                <button onclick="removeSeat('${id}')">✕</button>
            </span>`
          ).join('');

    const btn = document.getElementById('confirmBtn');
    if (keys.length > 0) {
        btn.classList.add('active');
        btn.removeAttribute('disabled');
        btn.textContent = `Next: Food & Drinks (${keys.length} seat${keys.length > 1 ? 's' : ''}) →`;
    } else {
        btn.classList.remove('active');
        btn.setAttribute('disabled', true);
        btn.textContent = 'Select seats to continue';
    }
}

function toggleFood(id) {
    const card = document.getElementById('fc-' + id);
    if (foodItems[id]) {
        delete foodItems[id];
        card.classList.remove('selected');
    } else {
        foodItems[id] = {
            id: parseInt(id, 10),
            name: FOOD_MENU[id].name,
            price: FOOD_MENU[id].price,
            qty: 1
        };
        card.classList.add('selected');
        document.getElementById('qty-' + id).textContent = '1';
    }
    updateFoodSummary();
}

function changeQty(id, delta) {
    if (!foodItems[id]) return;
    foodItems[id].qty = Math.max(1, foodItems[id].qty + delta);
    document.getElementById('qty-' + id).textContent = foodItems[id].qty;
    updateFoodSummary();
}

function formatSeatCount(n) {
    return n + (n === 1 ? ' seat' : ' seats');
}

function updateFoodSummary() {
    const seatKeys = Object.keys(selectedSeats);
    const seatTotal = seatKeys.reduce((sum, key) => sum + selectedSeats[key].price, 0);
    const foodTotal = Object.values(foodItems).reduce((sum, item) => sum + (item.price * item.qty), 0);
    const grandTotal = seatTotal + foodTotal;

    document.getElementById('foodSeatCount').textContent = formatSeatCount(seatKeys.length);
    document.getElementById('foodSeatTotal').textContent = seatTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    document.getElementById('foodSubtotal').textContent = foodTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    document.getElementById('grandTotal').textContent = grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 });

    const foodList = document.getElementById('foodOrderList');
    const selectedFood = Object.values(foodItems);
    foodList.innerHTML = selectedFood.length === 0
        ? '<div style="font-size:0.72rem;color:rgba(249,249,249,0.2);margin-bottom:4px;">No food added</div>'
        : selectedFood.map(item =>
            `<div class="food-summary-row">
                <span>🍿 ${item.name} x${item.qty}</span>
                <span>₱${(item.price * item.qty).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
            </div>`
          ).join('');
}

function addRequest(text, btn) {
    const ta = document.getElementById('specialRequests');
    let current = ta.value.trim();
    let requests = current ? current.split(',').map(r => r.trim()).filter(r => r !== "") : [];
    
    // Check if the request is already in the list
    const index = requests.findIndex(r => r.toLowerCase() === text.toLowerCase());
    
    if (index !== -1) {
        // Remove it if it exists (toggle behavior)
        requests.splice(index, 1);
        if (btn) btn.classList.remove('active');
    } else {
        // Add it if it doesn't exist
        requests.push(text);
        if (btn) btn.classList.add('active');
    }
    
    ta.value = requests.join(', ');
}

function syncRequestButtons() {
    const ta = document.getElementById('specialRequests');
    const current = ta.value.toLowerCase();
    const btns = document.querySelectorAll('.request-btn');
    
    btns.forEach(btn => {
        const text = btn.textContent.trim().toLowerCase();
        // Check if the exact request exists in the comma-separated list
        const requests = current.split(',').map(r => r.trim());
        if (requests.includes(text)) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

function proceedToPayment() {
    const keys = Object.keys(selectedSeats);
    if (!keys.length) { alert('Please select at least one seat.'); return; }

    const total = keys.reduce((sum, key) => sum + selectedSeats[key].price, 0)
        + Object.values(foodItems).reduce((sum, item) => sum + (item.price * item.qty), 0);
    if (total <= 0) { alert('Invalid pricing. Please go back.'); return; }

    const special_requests = document.getElementById('specialRequests').value.trim();
    const seats = encodeURIComponent(JSON.stringify(Object.entries(selectedSeats).map(([id, data]) => ({ id, ...data }))));
    const food = encodeURIComponent(JSON.stringify(Object.entries(foodItems).map(([id, item]) => ({ id, ...item }))));
    const requests = encodeURIComponent(special_requests);
    
    window.location.href = `staff_payment.php?movie_id=${MOVIE_ID}&timeslot_id=${TIMESLOT_ID}&seats=${seats}&food=${food}&special_requests=${requests}`;
}
</script>
</body>
</html>
