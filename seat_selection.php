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

$Movie_ID    = filter_input(INPUT_GET, 'movie_id',    FILTER_VALIDATE_INT);
$Mall_ID     = filter_input(INPUT_GET, 'mall_id',     FILTER_VALIDATE_INT);
$Date        = filter_input(INPUT_GET, 'date');
$TimeSlot_ID = filter_input(INPUT_GET, 'timeslot_id', FILTER_VALIDATE_INT);

if (!$Movie_ID || !$Mall_ID || !$Date || !$TimeSlot_ID) { header("Location: home.php"); exit; }

$movie_stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
$movie_stmt->bind_param("i", $Movie_ID); $movie_stmt->execute();
$movieDetails = $movie_stmt->get_result()->fetch_assoc();

$mall_stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$mall_stmt->bind_param("i", $Mall_ID); $mall_stmt->execute();
$mallDetails = $mall_stmt->get_result()->fetch_assoc();

$timeslot_stmt = $conn->prepare("SELECT * FROM timeslot INNER JOIN theater ON timeslot.Theater_ID = theater.Theater_ID WHERE TimeSlot_ID = ?");
$timeslot_stmt->bind_param("i", $TimeSlot_ID); $timeslot_stmt->execute();
$timeslotDetails = $timeslot_stmt->get_result()->fetch_assoc();

if (!$movieDetails || !$mallDetails || !$timeslotDetails) { header("Location: home.php"); exit; }

$seats_stmt = $conn->prepare("
    SELECT * FROM seats WHERE TimeSlot_ID = ?
    ORDER BY SeatRow ASC,
             (CASE WHEN CAST(SeatColumn AS UNSIGNED) = 0 THEN 999 ELSE CAST(SeatColumn AS UNSIGNED) END) ASC
");
$seats_stmt->bind_param("i", $TimeSlot_ID); $seats_stmt->execute();
$seatLayout = $seats_stmt->get_result();
$layoutProper = [];
if ($seatLayout) {
    while ($seat = $seatLayout->fetch_assoc()) {
        $layoutProper[$seat['SeatRow']][] = [
            'Seat_ID'          => $seat['Seat_ID'],
            'SeatType'         => $seat['SeatType'],
            'SeatPrice'        => $seat['SeatPrice'],
            'SeatAvailability' => $seat['SeatAvailability'],
            'SeatColumn'       => $seat['SeatColumn'],
            'HoldToken'        => $seat['HoldToken'] ?? null,
        ];
    }
}

// Fallback: If no seats for timeslot, try loading default layout for this theater
if (empty($layoutProper)) {
    $theater_id = (int)($timeslotDetails['Theater_ID'] ?? 0);
    if ($theater_id > 0) {
        $default_stmt = $conn->prepare("
            SELECT * FROM seats WHERE Theater_ID = ?
            ORDER BY SeatRow ASC, (CASE WHEN CAST(SeatColumn AS UNSIGNED) = 0 THEN 999 ELSE CAST(SeatColumn AS UNSIGNED) END) ASC
        ");
        $default_stmt->bind_param("i", $theater_id);
        $default_stmt->execute();
        $defaultRes = $default_stmt->get_result();
        while ($seat = $defaultRes->fetch_assoc()) {
            $layoutProper[$seat['SeatRow']][] = [
                'Seat_ID'          => $seat['Seat_ID'],
                'SeatType'         => $seat['SeatType'],
                'SeatPrice'        => $seat['SeatPrice'],
                'SeatAvailability' => $seat['SeatAvailability'],
                'SeatColumn'       => $seat['SeatColumn'],
                'HoldToken'        => $seat['HoldToken'] ?? null,
            ];
        }
    }
}
uksort($layoutProper, 'strnatcasecmp');

$basePrice = floatval($movieDetails['Price'] ?? 350);

/**
  * Build visual slots from the real positive SeatColumn values.
  * Column 0 is a stored aisle/gap marker and should not shift the visible numbering.
  */
function peaks_build_visual_row(array $cols, string $rowLabel, float $basePrice): array {
    $defaultRef = ['SeatType' => 'Standard', 'SeatPrice' => $basePrice];
    $slotCount = 0;

    foreach ($cols as $s) {
        $c = (int)($s['SeatColumn'] ?? 0);
        if ($c > $slotCount) {
            $slotCount = $c;
        }
    }
    $slotCount = max($slotCount, 20);

    $row = array_fill(0, $slotCount, null);

    // Place real seats into their visual slots. Column 0 is an aisle marker, not a numbered seat.
    foreach ($cols as $s) {
        $c = (int)($s['SeatColumn'] ?? 0);
        if ($c > 0 && $c <= $slotCount) {
            $row[$c - 1] = $s;
        }
    }

    // Fill any missing positive columns with non-bookable placeholders.
    for ($i = 0; $i < $slotCount; $i++) {
        if ($row[$i] === null) {
            $side = ($i < 10) ? 'L' : 'R';
            $ref = $cols[0] ?? $defaultRef;
            $row[$i] = [
                'Seat_ID'          => 'p_' . $rowLabel . '_' . $side . ($i + 1),
                'SeatType'         => $ref['SeatType'] ?? 'Standard',
                'SeatPrice'        => $ref['SeatPrice'] ?? $basePrice,
                'SeatAvailability' => 'Available',
                'SeatColumn'       => $i + 1,
            ];
        }
    }
    return $row;
}

/** Human label (e.g. B3) for a seat based on its 1-20 sequential position */
function peaks_visual_label_for_seat(array $padded20, string $rowLabel, $seatId): ?string {
    foreach ($padded20 as $seatPos => $seat) {
        if ((string)$seat['Seat_ID'] === (string)$seatId) {
            return $rowLabel . ($seatPos + 1);
        }
    }
    return null;
}

function peaks_build_row_rank_map(array $layoutProper): array {
    $rank = [];
    $i = 0;
    foreach (array_keys($layoutProper) as $rowLabel) {
        $rank[(string)$rowLabel] = $i++;
    }
    return $rank;
}

function peaks_row_distance(string $currentRow, ?string $targetRow, array $rowRank): int {
    if ($targetRow === null || $targetRow === '') {
        return 0;
    }
    if (isset($rowRank[$currentRow], $rowRank[$targetRow])) {
        return abs($rowRank[$currentRow] - $rowRank[$targetRow]);
    }
    return abs(
        ord(strtoupper(substr($currentRow, 0, 1))) -
        ord(strtoupper(substr((string)$targetRow, 0, 1)))
    );
}

function peaks_collect_available_seats(array $layoutProper, float $basePrice, string $seatHoldToken): array {
    $availableSeats = [];

    foreach ($layoutProper as $rowLabel => $cols) {
        $paddedCols = peaks_build_visual_row($cols, (string)$rowLabel, $basePrice);
        foreach ($paddedCols as $seatPos => $seat) {
            $type = strtolower(trim((string)($seat['SeatType'] ?? 'standard')));
            $avail = normalizeSeatAvailability($seat['SeatAvailability'] ?? 'Available');
            $heldBySelf = ($avail === 'Taken' && (($seat['HoldToken'] ?? '') === $seatHoldToken));
            $taken = ($avail === 'Taken') && !$heldBySelf;
            $isEmpty = ($type === 'empty');
            $isPad = is_string($seat['Seat_ID'] ?? null)
                && strncmp((string)$seat['Seat_ID'], 'p_', 2) === 0;

            if ($isPad || $isEmpty || $taken) {
                continue;
            }

            $availableSeats[] = [
                'seat_id'     => (int)$seat['Seat_ID'],
                'row'         => (string)$rowLabel,
                'visual_col'  => $seatPos + 1,
                'seat_column' => (int)($seat['SeatColumn'] ?? 0),
                'type'        => (string)($seat['SeatType'] ?? 'Standard'),
                'price'       => (float)($seat['SeatPrice'] ?? $basePrice),
            ];
        }
    }

    return $availableSeats;
}

function peaks_pick_recommended_seat(array $availableSeats, ?array $recentSeat, ?string $prefType, array $rowRank): ?array {
    if (empty($availableSeats)) {
        return null;
    }

    usort($availableSeats, static function (array $a, array $b) use ($recentSeat, $prefType, $rowRank): int {
        if (!empty($recentSeat)) {
            $targetRow = (string)($recentSeat['row'] ?? '');
            $targetCol = (int)($recentSeat['visual_col'] ?? 10);
            $targetType = trim((string)($recentSeat['type'] ?? $prefType ?? ''));

            $aRowDist = peaks_row_distance($a['row'], $targetRow, $rowRank);
            $bRowDist = peaks_row_distance($b['row'], $targetRow, $rowRank);
            if ($aRowDist !== $bRowDist) {
                return $aRowDist <=> $bRowDist;
            }

            $aColDist = abs((int)$a['visual_col'] - $targetCol);
            $bColDist = abs((int)$b['visual_col'] - $targetCol);
            if ($aColDist !== $bColDist) {
                return $aColDist <=> $bColDist;
            }

            $aTypePenalty = ($targetType !== '' && strcasecmp($a['type'], $targetType) === 0) ? 0 : 1;
            $bTypePenalty = ($targetType !== '' && strcasecmp($b['type'], $targetType) === 0) ? 0 : 1;
            if ($aTypePenalty !== $bTypePenalty) {
                return $aTypePenalty <=> $bTypePenalty;
            }
        } else {
            $targetType = trim((string)($prefType ?? ''));
            $aTypePenalty = ($targetType !== '' && strcasecmp($a['type'], $targetType) === 0) ? 0 : 1;
            $bTypePenalty = ($targetType !== '' && strcasecmp($b['type'], $targetType) === 0) ? 0 : 1;
            if ($aTypePenalty !== $bTypePenalty) {
                return $aTypePenalty <=> $bTypePenalty;
            }
        }

        $aCenterDist = abs((int)$a['visual_col'] - 10);
        $bCenterDist = abs((int)$b['visual_col'] - 10);
        if ($aCenterDist !== $bCenterDist) {
            return $aCenterDist <=> $bCenterDist;
        }

        return [$a['row'], (int)$a['visual_col']] <=> [$b['row'], (int)$b['visual_col']];
    });

    return $availableSeats[0];
}

// ── AI Seat Recommendation ────────────────────────────────────────
$aiRec = null;
$debug_ai = []; // Debug array
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $debug_ai['user_id'] = $uid;

    $recentSeat = null;
    $prefType = null;
    $theaterId = (int)($timeslotDetails['Theater_ID'] ?? 0);
    $rowRank = peaks_build_row_rank_map($layoutProper);
    $availableSeats = peaks_collect_available_seats($layoutProper, $basePrice, $seatHoldToken);

    $recent_stmt = $conn->prepare("
        SELECT s.SeatRow, s.SeatColumn, s.SeatType, tk.DateTime
        FROM ticket tk
        JOIN timeslot ts ON ts.TimeSlot_ID = tk.TimeSlot_ID
        JOIN seats s ON s.Seat_ID = tk.Seat_ID
        WHERE tk.Customer_ID = ?
          AND tk.Status != 2
          AND ts.Theater_ID = ?
          AND s.SeatType NOT IN ('Empty')
          AND CAST(s.SeatColumn AS UNSIGNED) > 0
        ORDER BY tk.DateTime DESC, tk.Ticket_ID DESC
        LIMIT 1
    ");
    if ($recent_stmt) {
        $recent_stmt->bind_param("ii", $uid, $theaterId);
        $recent_stmt->execute();
        $recentRow = $recent_stmt->get_result()->fetch_assoc();
        if ($recentRow) {
            $recentSeat = [
                'row'        => (string)$recentRow['SeatRow'],
                'visual_col' => (int)$recentRow['SeatColumn'],
                'type'       => (string)$recentRow['SeatType'],
                'label'      => (string)$recentRow['SeatRow'] . (int)$recentRow['SeatColumn'],
            ];
        }
    }

    $hist_stmt = $conn->prepare("
        SELECT s.SeatType, COUNT(*) AS cnt
        FROM ticket tk
        JOIN seats s ON s.Seat_ID = tk.Seat_ID
        JOIN timeslot ts ON ts.TimeSlot_ID = tk.TimeSlot_ID
        WHERE tk.Customer_ID = ?
          AND tk.Status != 2
          AND ts.Theater_ID = ?
          AND s.SeatType NOT IN ('Empty')
        GROUP BY s.SeatType
        ORDER BY cnt DESC, s.SeatType ASC
        LIMIT 1
    ");
    if ($hist_stmt) {
        $hist_stmt->bind_param("ii", $uid, $theaterId);
        $hist_stmt->execute();
        $prefRow = $hist_stmt->get_result()->fetch_assoc();
        if ($prefRow) {
            $prefType = (string)$prefRow['SeatType'];
        }
    }

    $debug_ai['pref_type'] = $prefType;
    $debug_ai['recent_seat'] = $recentSeat;
    $debug_ai['has_history'] = !empty($recentSeat) || !empty($prefType);
    $debug_ai['total_available_seats'] = count($availableSeats);
    $debug_ai['available_seat_types'] = array_values(array_unique(array_map(
        static fn(array $seat): string => (string)$seat['type'],
        $availableSeats
    )));

    $recSeat = peaks_pick_recommended_seat($availableSeats, $recentSeat, $prefType, $rowRank);
    $debug_ai['rec_seat_found'] = !empty($recSeat);

    if ($recSeat) {
        $title = 'Seat Recommendation';
        $reason = 'Here\'s a great center seat for your first booking!';

        if (!empty($recentSeat)) {
            $title = 'Based On Your Last Seat';
            $reason = 'Based on your last booked seat (' . $recentSeat['label'] . '), we found the closest available match.';
        } elseif (!empty($prefType)) {
            $title = 'Your Previous Seats';
            $reason = 'Based on your booking history, we recommend a ' . $prefType . ' seat.';
        }

        $aiRec = [
            'seat_id'     => $recSeat['seat_id'],
            'row'         => $recSeat['row'],
            'label'       => '',
            'type'        => $recSeat['type'],
            'price'       => $recSeat['price'],
            'title'       => $title,
            'reason'      => $reason,
            'has_history' => !empty($recentSeat) || !empty($prefType),
        ];
        $debug_ai['ai_rec_created'] = true;
    }
} else {
    $debug_ai['error'] = 'User not logged in';
}

// Same visual label as the seat map (position in padded row, not SeatColumn arithmetic)
if (!empty($aiRec) && !empty($aiRec['row']) && isset($layoutProper[$aiRec['row']])) {
    $paddedAi = peaks_build_visual_row($layoutProper[$aiRec['row']], $aiRec['row'], $basePrice);
    $lblAi    = peaks_visual_label_for_seat($paddedAi, $aiRec['row'], $aiRec['seat_id']);
    if ($lblAi !== null) {
        $aiRec['label'] = $lblAi;
    } else {
        $aiRec['label'] = $aiRec['row'] . ' (see map)';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Select Seats – <?= htmlspecialchars($movieDetails['MovieName']) ?></title>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: #0f0f0f;
    color: #F9F9F9;
    min-height: 100vh;
    padding-top: 60px;
    padding-bottom: 60px;
    overflow-x: hidden;
    width: 100%;
    max-width: 100vw;
}
body::before {
    content: '';
    position: fixed; inset: 0;
    background: url('movie-background-collage.jpg') center/cover no-repeat;
    opacity: 0.12; z-index: 0; pointer-events: none;
}
body::after {
    content: '';
    position: fixed; inset: 0;
    background: radial-gradient(ellipse at center, transparent 10%, rgba(15,15,15,0.55) 60%, #0f0f0f 100%);
    z-index: 1; pointer-events: none;
}

/* ── Standardized Header ── */
header {
    background: #1C1C1C;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 50px;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    transition: transform 0.3s ease, all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
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
    .brand-logo { height: 36px; }
    .bookings-btn::after { display: none; }
    .bookings-btn, .notif-btn { width: 40px; justify-content: center; padding: 0; }
}

@media (max-width: 480px) {
    header { padding: 0 10px; gap: 8px; }
    .brand-logo { height: 32px; }
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

.outer { 
    position: relative; 
    z-index: 10; 
    width: calc(100% - 40px);
    max-width: 1280px; 
    margin: 28px auto; 
    padding: 0;
    overflow: visible;
    box-sizing: border-box;
}

@media (max-width: 1024px) {
    .outer {
        width: calc(100% - 32px);
        margin: 20px auto;
    }
}

@media (max-width: 480px) {
    .outer {
        width: calc(100% - 16px);
        margin: 14px auto;
    }
}
.page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
.page-title { font-size: 1.7rem; font-weight: 800; margin-bottom: 20px; }

.two-col { 
    display: flex;
    flex-direction: row;
    gap: 24px; 
    align-items: flex-start; 
    width: 100%; 
    box-sizing: border-box;
}

.two-col > div:first-child {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

@media (max-width: 1024px) {
    .two-col { 
        flex-direction: column;
        gap: 20px; 
    }
    
    .two-col > div:first-child {
        width: 100%;
    }
}
/* ══════════════════════════════════════════════════════
   MOBILE RESPONSIVENESS
══════════════════════════════════════════════════════ */

/* ── Tablet and below: full responsive ── */
@media (max-width: 1024px) {
    .outer { 
        width: calc(100% - 32px);
        margin: 20px auto;
        padding: 0;
    }
    
    /* Stepper adjustments */
    .stepper { margin-bottom: 18px; }
    .step-item { padding: 12px 16px; }
    .step-num { width: 26px; height: 26px; font-size: 0.75rem; }
    .step-label { font-size: 0.6rem; }
    .step-title { font-size: 0.8rem; }
    
    /* Panel adjustments */
    .panel { 
        width: 100%; 
        max-width: 100%; 
        box-sizing: border-box;
        overflow: hidden;
    }
    .panel-header { padding: 12px 16px; }
    .panel-body { padding: 16px; overflow-x: hidden; }
    
    /* Movie card */
    .movie-card { padding: 14px; gap: 12px; }
    .movie-thumb { width: 48px; height: 72px; }
    .movie-card h3 { font-size: 0.85rem; }
    .movie-card-meta { font-size: 0.68rem; }
    
    /* Food grid */
    .food-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
}

/* ── Mobile phones ── */
@media (max-width: 480px) {
    body { padding-top: 60px; padding-bottom: 40px; }
    
    .outer { 
        width: calc(100% - 16px);
        margin: 14px auto;
        padding: 0;
    }
    .page-label { font-size: 0.65rem; }
    .page-title { font-size: 1.3rem; margin-bottom: 14px; }
    
    /* Stepper - more compact */
    .stepper { margin-bottom: 14px; border-radius: 10px; }
    .step-item { padding: 10px 12px; gap: 8px; }
    .step-num { width: 24px; height: 24px; font-size: 0.7rem; }
    .step-label { display: none; } /* Hide "Step 1" label on small screens */
    .step-title { font-size: 0.75rem; }
    
    /* Panels - ensure they don't overflow */
    .panel { border-radius: 11px; margin-bottom: 12px; max-width: 100%; overflow: hidden; }
    .panel-header { padding: 10px 14px; }
    .panel-body { padding: 14px; overflow-x: hidden; }
    
    /* Movie card - make it more compact */
    .movie-card { padding: 12px; gap: 10px; flex-wrap: nowrap; }
    .movie-thumb { width: 44px; height: 66px; flex-shrink: 0; }
    .movie-card h3 { font-size: 0.8rem; line-height: 1.2; }
    .movie-card-meta { font-size: 0.65rem; gap: 2px; }
    .movie-card-meta span { 
        display: block; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        white-space: nowrap; 
    }
    .movie-card-meta span:nth-child(n+4) { display: none; } /* Hide extra meta on tiny screens */
    .type-pill { font-size: 0.58rem; padding: 2px 7px; margin-top: 4px; }
    
    /* Screen bar */
    .screen-bar { padding: 4px 15px; font-size: 0.5rem; letter-spacing: 1.5px; }
    
    /* Legend */
    .legend { gap: 6px; padding-top: 10px; flex-wrap: wrap; }
    .legend-item { font-size: 0.58rem; }
    .legend-dot { width: 10px; height: 10px; }
    
    /* AI recommendation banner - compact for mobile */
    .ai-rec-banner { 
        padding: 4px 6px !important; 
        gap: 4px !important; 
        border-radius: 6px !important; 
        margin-bottom: 8px !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
    }
    .ai-rec-left { gap: 4px !important; flex: 1 !important; min-width: 0 !important; }
    .ai-rec-icon { width: 20px !important; height: 20px !important; font-size: 0.7rem !important; border-radius: 4px !important; }
    .ai-rec-text { flex: 1 !important; min-width: 0 !important; }
    .ai-rec-text h4 { font-size: 0.52rem !important; margin-bottom: 1px !important; }
    .ai-rec-text p { font-size: 0.48rem !important; line-height: 1.2 !important; }
    .ai-rec-seat { font-size: 0.56rem !important; }
    .ai-rec-btn { 
        padding: 4px 6px !important; 
        font-size: 0.52rem !important; 
        white-space: nowrap !important;
        flex-shrink: 0 !important;
    }
    
    /* Seat map - reduce actual sizes to fit viewport */
    .seat-map-container {
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 5px 0;
        width: 100%;
    }
    .seat-map {
        transform: none;
        gap: 0 !important;
        margin: 0 auto;
    }
    .srow { gap: 0 !important; }
    .seat-btn { width: 13px !important; height: 13px !important; line-height: 13px !important; font-size: 0.38rem !important; border-radius: 2px 2px 1px 1px !important; }
    .seat-cell-wrap, .seat-empty-wrap { width: 13px !important; height: 13px !important; }
    .rlbl { width: 7px !important; min-width: 7px !important; font-size: 0.42rem !important; }
    .seat-gap { width: 2px !important; }
    .aisle-gap { width: 5px !important; }
    .section-gap { width: 2px !important; }
    
    /* Booking summary - prevent overflow */
    #seatTagList { 
        overflow-x: auto; 
        white-space: nowrap; 
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .seat-tag { 
        font-size: 0.72rem; 
        padding: 3px 8px; 
        display: inline-flex;
        white-space: nowrap;
    }
    .price-row { font-size: 0.78rem; margin-bottom: 6px; }
    .price-row.total .lbl { font-size: 0.85rem; }
    .price-row.total .val { font-size: 1rem; }
    .btn-proceed { padding: 13px; font-size: 0.88rem; width: 100%; }
    
    /* Food step */
    .food-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .food-card { overflow: hidden; }
    .food-card-img { height: 80px; font-size: 2rem; }
    .food-card-body { padding: 9px; }
    .food-card-name { font-size: 0.75rem; }
    .food-card-desc { font-size: 0.62rem; }
    .food-card-price { font-size: 0.72rem; }
    .qty-btn { width: 24px; height: 24px; font-size: 0.9rem; }
    .qty-val { font-size: 0.82rem; }
    .food-check-badge { width: 18px; height: 18px; font-size: 0.58rem; }
    .food-subtotal { padding: 10px 12px; font-size: 0.78rem; }
    .food-skip-note { font-size: 0.68rem; }
    .btn-back-seats { padding: 11px; font-size: 0.82rem; }
    .btn-pay-now { padding: 11px; font-size: 0.86rem; }
}

/* ── Very small phones ── */
@media (max-width: 400px) {
    .page-title { font-size: 1.15rem; }
    
    /* AI recommendation - even more compact */
    .ai-rec-banner { padding: 3px 5px !important; gap: 3px !important; }
    .ai-rec-icon { width: 18px !important; height: 18px !important; font-size: 0.65rem !important; }
    .ai-rec-text h4 { font-size: 0.5rem !important; }
    .ai-rec-text p { font-size: 0.46rem !important; }
    .ai-rec-seat { font-size: 0.54rem !important; }
    .ai-rec-btn { padding: 3px 5px !important; font-size: 0.5rem !important; }
    
    /* Seat map - reduce sizes even more */
    .seat-map { gap: 0 !important; }
    .srow { gap: 0 !important; }
    .seat-btn { width: 12px !important; height: 12px !important; line-height: 12px !important; font-size: 0.36rem !important; border-radius: 2px 2px 1px 1px !important; }
    .seat-cell-wrap, .seat-empty-wrap { width: 12px !important; height: 12px !important; }
    .rlbl { width: 6px !important; min-width: 6px !important; font-size: 0.4rem !important; }
    .seat-gap { width: 1px !important; }
    .aisle-gap { width: 4px !important; }
    .section-gap { width: 2px !important; }
    .screen-bar { padding: 3px 12px !important; font-size: 0.46rem !important; letter-spacing: 1px !important; }
    
    /* Food grid - single column on very small screens */
    .food-grid { grid-template-columns: 1fr; }
}

.panel { 
    background: #1a1a1a; 
    border: 1px solid rgba(255,255,255,0.07); 
    border-radius: 14px; 
    overflow: hidden; 
    margin-bottom: 16px; 
    width: 100%; 
    min-width: 0;
    box-sizing: border-box; 
}
.panel:last-child { margin-bottom: 0; }
.panel-header { padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: space-between; }
.panel-header h2 { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
.panel-body { padding: 20px; overflow: hidden; }
.sidebar { 
    position: sticky; 
    top: 80px; 
    width: 340px;
    flex-shrink: 0;
    box-sizing: border-box;
    overflow: hidden;
}

@media (max-width: 1024px) {
    .sidebar {
        position: static;
        width: 100%;
        max-width: 100%;
    }
}

.movie-card { display: flex; gap: 14px; padding: 16px; align-items: flex-start; }
.movie-thumb { width: 52px; height: 78px; flex-shrink: 0; border-radius: 7px; background: #111; background-size: cover; background-position: center; border: 1px solid rgba(255,255,255,0.08); }
.movie-card h3 { font-size: 0.88rem; font-weight: 800; margin-bottom: 6px; line-height: 1.3; }
.movie-card-meta { font-size: 0.7rem; color: rgba(249,249,249,0.4); display: flex; flex-direction: column; gap: 3px; }
.type-pill { display: inline-block; margin-top: 5px; font-size: 0.62rem; font-weight: 700; letter-spacing: 1px; background: rgba(255,77,77,0.12); border: 1px solid rgba(255,77,77,0.25); color: #ff6b6b; padding: 2px 8px; border-radius: 10px; }

.screen-wrap { text-align: center; margin-bottom: 20px; }
.screen-bar { display: inline-block; padding: 7px 60px; background: linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0.03)); border: 1px solid rgba(255,255,255,0.12); border-radius: 2px 2px 28% 28% / 2px 2px 10px 10px; font-size: 0.65rem; font-weight: 700; letter-spacing: 4px; color: rgba(255,255,255,0.4); }

.seat-map-container {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    box-sizing: border-box;
    position: relative;
    padding: 0 2px 2px;
}

.seat-map { 
    display: flex;
    flex-direction: column;
    gap: 0;
    margin: 0 auto;
    width: fit-content;
    transform-origin: top center;
    transition: none;
}
.srow { 
    display: flex;
    align-items: center;
    gap: 0;
    flex-wrap: nowrap;
}
.rlbl { 
    width: 18px; 
    min-width: 18px;
    text-align: center; 
    font-size: 0.65rem; 
    font-weight: 700; 
    color: rgba(249,249,249,0.3);
    flex-shrink: 0;
}
.rlbl-r { 
    flex-shrink: 0;
}
.seat-cell-wrap, .seat-empty-wrap { 
    width: 36px; 
    height: 36px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.seat-gap { 
    width: 4px;
    flex-shrink: 0;
}
.aisle-gap { 
    width: 18px;
    flex-shrink: 0;
}
.section-gap { 
    width: 8px;
    flex-shrink: 0;
}
.row-gap { 
    display: none;
}

.seat-btn { width: 36px; height: 36px; border-radius: 7px 7px 3px 3px; font-size: 0.62rem; font-weight: 700; line-height: 36px; text-align: center; cursor: pointer; user-select: none; display: block; transition: transform 0.1s, filter 0.1s; border: none; outline: none; }
.seat-checkbox { display: none; }
.seat-btn.standard { background: #2e2e2e; border-bottom: 3px solid #505050; color: rgba(249,249,249,0.5); }
.seat-btn.vip      { background: #3d1a1a; border-bottom: 3px solid #ff4d4d; color: #ff7070; }
.seat-btn.imax     { background: #332b00; border-bottom: 3px solid #ffc107; color: #ffd54f; }
.seat-btn.taken    { background: #1c1c1c; border-bottom: 3px solid #282828 !important; color: #333; cursor: not-allowed; pointer-events: none; }
.seat-gap-slot {
    width: 36px;
    height: 36px;
    border-radius: 7px 7px 3px 3px;
    background: rgba(255,255,255,0.02);
    border: 1px dashed rgba(255,255,255,0.08);
    opacity: 0.35;
    cursor: default;
}
.seat-btn:not(.taken):hover { transform: scale(1.18); filter: brightness(1.45); position: relative; z-index: 2; }
.seat-btn.selected { background: rgba(255,77,77,0.28) !important; border-bottom: 3px solid #ff4d4d !important; color: #fff !important; transform: scale(1.12); position: relative; z-index: 2; box-shadow: 0 0 10px rgba(255,77,77,0.35); }

.legend { display: flex; flex-wrap: wrap; gap: 14px; justify-content: center; margin-top: 22px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.06); }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.68rem; color: rgba(249,249,249,0.4); }
.legend-dot  { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; }

.divider { height: 1px; background: rgba(255,255,255,0.06); margin: 14px 0; }
.no-seats { font-size: 0.78rem; color: rgba(249,249,249,0.22); }
.seat-tag { display: inline-flex; align-items: center; gap: 5px; background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.25); border-radius: 6px; padding: 4px 10px; font-size: 0.78rem; font-weight: 700; color: #ff6b6b; margin: 3px; }

.price-row { display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 8px; }
.price-row .lbl { color: rgba(249,249,249,0.45); }
.price-row .val { font-weight: 700; }
.price-row.total .lbl { font-size: 0.88rem; color: #F9F9F9; font-weight: 700; }
.price-row.total .val { font-size: 1.1rem; color: #ff4d4d; font-weight: 800; }

.btn-proceed { width: 100%; padding: 14px; background: #2a2a2a; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; color: rgba(249,249,249,0.3); font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: not-allowed; pointer-events: none; transition: all 0.25s; margin-top: 6px; letter-spacing: 0.3px; }
.btn-proceed.active { background: #ff4d4d; color: #fff; border-color: #ff4d4d; cursor: pointer; pointer-events: all; }
.btn-proceed.active:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

/* ── Stepper ── */
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

/* ── Food step ── */
#foodStep { display: none; }
#foodStep.active { display: block; }
.food-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); gap: 12px; margin-bottom: 20px; }
.food-card { background: #1e1e1e; border: 2px solid rgba(255,255,255,0.07); border-radius: 12px; overflow: hidden; cursor: pointer; transition: all 0.2s; position: relative; }
.food-card:hover { border-color: rgba(255,77,77,0.35); transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.4); }
.food-card.selected { border-color: #ff4d4d; background: rgba(255,77,77,0.06); }
.food-card-img { width: 100%; height: 100px; display: flex; align-items: center; justify-content: center; font-size: 2.6rem; background: #161616; }
.food-card-body { padding: 10px 12px; }
.food-card-name { font-size: 0.82rem; font-weight: 700; color: #F9F9F9; margin-bottom: 2px; }
.food-card-desc { font-size: 0.65rem; color: rgba(249,249,249,0.35); margin-bottom: 6px; }
.food-card-price { font-size: 0.78rem; font-weight: 800; color: #ff4d4d; }
.food-card-qty { display: none; align-items: center; justify-content: space-between; margin-top: 8px; gap: 6px; }
.food-card.selected .food-card-qty { display: flex; }
.qty-btn { width: 26px; height: 26px; border-radius: 6px; background: rgba(255,77,77,0.15); border: 1px solid rgba(255,77,77,0.3); color: #ff4d4d; font-size: 1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0; font-family: 'Outfit', sans-serif; }
.qty-btn:hover { background: #ff4d4d; color: #fff; }
.qty-val { font-size: 0.88rem; font-weight: 700; color: #F9F9F9; min-width: 20px; text-align: center; }
.food-check-badge { position: absolute; top: 8px; right: 8px; width: 22px; height: 22px; border-radius: 50%; background: #ff4d4d; color: #fff; font-size: 0.65rem; font-weight: 800; display: none; align-items: center; justify-content: center; }
.food-card.selected .food-check-badge { display: flex; }
.food-subtotal { background: rgba(255,77,77,0.06); border: 1px solid rgba(255,77,77,0.15); border-radius: 10px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; font-size: 0.82rem; }
.food-subtotal .lbl { color: rgba(249,249,249,0.5); }
.food-subtotal .val { font-weight: 800; color: #ff4d4d; font-size: 0.95rem; }
.food-skip-note { text-align: center; font-size: 0.72rem; color: rgba(249,249,249,0.25); margin-bottom: 14px; }
.food-actions { display: flex; gap: 10px; }
.btn-back-seats { flex: 1; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: rgba(249,249,249,0.5); font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-back-seats:hover { background: rgba(255,255,255,0.1); color: #F9F9F9; }
.btn-pay-now { flex: 2; padding: 12px; background: #ff4d4d; border: none; border-radius: 10px; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.92rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.btn-pay-now:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

/* Quick Instructions Buttons */
.quick-requests { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
.request-btn { padding: 7px 16px; background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 7px; color: rgba(249,249,249,0.7); font-family: 'Outfit', sans-serif; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.15s; }
.request-btn:hover { border-color: rgba(255,77,77,0.4); color: #F9F9F9; background: #2a2a2a; }
.request-btn.active { background: rgba(255,77,77,0.1); border-color: #ff4d4d; color: #ff4d4d; }

/* ── Header nav ── */
.header-actions { display: flex; align-items: center; gap: 10px; }
.hnav-link { color: rgba(249,249,249,0.45); text-decoration: none; font-size: 0.78rem; font-weight: 500; padding: 6px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); transition: all 0.2s; white-space: nowrap; position: relative; }
.hnav-link:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
.hnav-badge { position: absolute; top: -4px; right: -4px; background: #ff4d4d; color: #fff; font-size: 0.55rem; font-weight: 800; min-width: 16px; height: 16px; border-radius: 8px; padding: 0 4px; display: none; align-items: center; justify-content: center; }

/* ══ AI SEAT RECOMMENDATION ════════════════════════════════════ */

/* ── 1. Banner ── */
.ai-rec-banner {
    background: linear-gradient(135deg, rgba(255,77,77,0.12), rgba(30,10,10,0.95));
    border: 1px solid rgba(255,77,77,0.4);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 16px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 10px;
    flex-wrap: wrap;
    box-shadow: 0 4px 24px rgba(255,77,77,0.15);
    position: relative; overflow: hidden;
}
/* Animated shimmer on banner */
.ai-rec-banner::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(105deg, transparent 30%, rgba(255,77,77,0.08) 50%, transparent 70%);
    animation: bannerShimmer 3s infinite;
    pointer-events: none;
}
@keyframes bannerShimmer {
    0%   { transform: translateX(-100%); }
    100% { transform: translateX(200%); }
}

.ai-rec-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.ai-rec-icon {
    width: 32px; height: 32px; flex-shrink: 0;
    background: rgba(255,77,77,0.2);
    border: 1px solid rgba(255,77,77,0.4);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
}
.ai-rec-text { flex: 1; min-width: 0; }
.ai-rec-text h4 {
    font-size: 0.72rem; font-weight: 800;
    color: #ff6b6b; margin-bottom: 3px;
    letter-spacing: 0.3px;
}
.ai-rec-text p { 
    font-size: 0.68rem; 
    color: rgba(249,249,249,0.55); 
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ai-rec-seat {
    font-weight: 800; color: #ff4d4d;
    font-size: 0.85rem;
    text-shadow: 0 0 10px rgba(255,77,77,0.5);
}

/* ── 2. Button — more visible, not a submit ── */
.ai-rec-btn {
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-family: 'Outfit', sans-serif;
    font-size: 0.72rem; font-weight: 800;
    cursor: pointer; transition: all 0.2s;
    white-space: nowrap; flex-shrink: 0;
    box-shadow: 0 4px 16px rgba(255,77,77,0.4);
    letter-spacing: 0.3px;
}
.ai-rec-btn:hover {
    background: linear-gradient(135deg, #ff3333, #a93226);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255,77,77,0.55);
}
.ai-rec-btn.applied {
    background: linear-gradient(135deg, #66BB6A, #388E3C);
    box-shadow: 0 4px 16px rgba(102,187,106,0.35);
    cursor: default;
}
.ai-rec-btn.applied:hover { transform: none; box-shadow: 0 4px 16px rgba(102,187,106,0.35); }

/* ── 3. Seat highlight animations ── */

/* Persistent glow ring BEFORE click */
.seat-btn.ai-glow {
    outline: 3px solid rgba(255,77,77,0.7);
    outline-offset: 3px;
    box-shadow: 0 0 0 4px rgba(255,77,77,0.25), 0 0 20px rgba(255,77,77,0.4);
    animation: aiGlowPulse 1.6s ease-in-out infinite;
    position: relative; z-index: 5;
}
@keyframes aiGlowPulse {
    0%,100% { box-shadow: 0 0 0 3px rgba(255,77,77,0.3), 0 0 12px rgba(255,77,77,0.3); }
    50%      { box-shadow: 0 0 0 7px rgba(255,77,77,0.15), 0 0 28px rgba(255,77,77,0.6); }
}
/* Badge on top of recommended seat */
.seat-btn.ai-glow::after {
    content: '✦ AI';
    position: absolute;
    top: -20px; left: 50%;
    transform: translateX(-50%);
    background: #ff4d4d;
    color: #fff;
    font-size: 0.45rem;
    font-weight: 900;
    padding: 2px 5px;
    border-radius: 4px;
    white-space: nowrap;
    letter-spacing: 0.5px;
    pointer-events: none;
    z-index: 20;
}

/* Big pop animation on button click */
@keyframes aiClickPop {
    0%   { transform: scale(1);   box-shadow: 0 0 0 0px rgba(255,77,77,1); }
    15%  { transform: scale(1.6); box-shadow: 0 0 0 12px rgba(255,77,77,0.6); }
    35%  { transform: scale(1.3); box-shadow: 0 0 0 20px rgba(255,77,77,0.25); }
    55%  { transform: scale(1.5); box-shadow: 0 0 0 8px rgba(255,77,77,0.5); }
    75%  { transform: scale(1.25); box-shadow: 0 0 0 16px rgba(255,77,77,0.2); }
    100% { transform: scale(1.15); box-shadow: 0 0 0 4px #ff4d4d, 0 0 24px rgba(255,77,77,0.6); }
}
.seat-btn.ai-pop {
    animation: aiClickPop 0.9s cubic-bezier(0.36, 0.07, 0.19, 0.97) forwards;
    z-index: 10; position: relative;
}
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

<form id="seatForm" action="payment.php" method="POST">
    <input type="hidden" name="movie_id"    value="<?= htmlspecialchars($Movie_ID) ?>">
    <input type="hidden" name="mall_id"     value="<?= htmlspecialchars($Mall_ID) ?>">
    <input type="hidden" name="date"        value="<?= htmlspecialchars($Date) ?>">
    <input type="hidden" name="timeslot_id" value="<?= htmlspecialchars($TimeSlot_ID) ?>">
    <input type="hidden" name="priceTotal"  id="priceTotalHidden" value="0">

<div class="outer">
    <p class="page-label">Book Tickets</p>
    <h1 class="page-title" id="pageTitle">Select Your Seats</h1>

    <!-- Stepper -->
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

    <!-- Step 1: Seat selection -->
    <div id="seatStep">
    <div class="two-col">

        <div>
            <div class="panel">
                <div class="panel-header"><h2>🪑 Theater Layout</h2></div>
                <div class="panel-body">

                    <?php if (empty($layoutProper)): ?>
                    <div style="text-align:center;padding:40px;color:rgba(249,249,249,0.2);font-size:0.82rem;">No seats found for this screening.</div>
                    <?php else: ?>

                    <?php if ($aiRec): ?>
                    <div class="ai-rec-banner" id="aiRecBanner">
                        <div class="ai-rec-left">
                            <div class="ai-rec-icon">�</div>
                            <div class="ai-rec-text">
                                <h4>✦ Your Previous Seats</h4>
                                <p>
                                    <?= htmlspecialchars($aiRec['reason']) ?><br>
                                    Suggested: <span class="ai-rec-seat"><?= htmlspecialchars($aiRec['label']) ?></span>
                                    &nbsp;·&nbsp; <?= htmlspecialchars($aiRec['type']) ?>
                                    &nbsp;·&nbsp; ₱<?= number_format($aiRec['price'], 0) ?>
                                </p>
                            </div>
                        </div>
                        <!-- ✅ FIX: type="button" prevents form submission -->
                        <button type="button" class="ai-rec-btn" id="aiRecBtn"
                                onclick="applyAiRec(<?= (int)$aiRec['seat_id'] ?>)">
                            🎯 Highlight Seat
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="screen-wrap">
                        <div class="screen-bar">── SCREEN ──</div>
                    </div>

                    <div class="seat-map-container" id="seatMapContainer">
                    <div class="seat-map" id="seatMap">

                        <?php
                        $firstRow = true;
                        if (!defined('CINEMA_HALF')) define('CINEMA_HALF', 10);
                        if (!defined('CINEMA_QTR'))  define('CINEMA_QTR',  5);
                        ?>

                        <?php foreach ($layoutProper as $rowLabel => $cols): ?>
                        <?php 
                            $paddedCols = peaks_build_visual_row($cols, $rowLabel, $basePrice); 
                        ?>

                        <?php if (!$firstRow): ?><div class="row-gap"></div><?php endif; $firstRow = false; ?>

                        <div class="srow">
                            <div class="rlbl"><?= htmlspecialchars($rowLabel) ?></div>
                            <div class="seat-gap"></div>

                            <?php foreach ($paddedCols as $seatPos => $seat):
                                $type    = strtolower(trim($seat['SeatType'] ?? 'standard'));
                                $avail   = normalizeSeatAvailability($seat['SeatAvailability'] ?? 'Available');
                                $css     = str_contains($type,'vip') ? 'vip' : (str_contains($type,'imax') ? 'imax' : 'standard');
                                $heldBySelf = ($avail === 'Taken' && (($seat['HoldToken'] ?? '') === $seatHoldToken));
                                $taken   = ($avail === 'Taken') && !$heldBySelf;
                                $isEmpty = ($type === 'empty');
                                $price   = floatval($seat['SeatPrice'] ?? $basePrice);
                                
                                // Padded placeholder seats — not in DB; must not be bookable
                                $isPad   = is_string($seat['Seat_ID'] ?? null)
                                    && strncmp((string)$seat['Seat_ID'], 'p_', 2) === 0;

                                // Display sequential numbers (1–20) based on slot position
                                $dispNum = $seatPos + 1;
                                $lbl     = $rowLabel . $dispNum;
                                $isRec   = ($aiRec && (string)$seat['Seat_ID'] === (string)$aiRec['seat_id']);
                            ?>

                            <?php if ($seatPos > 0): ?><div class="seat-gap"></div><?php endif; ?>
                            <?php if ($seatPos === CINEMA_HALF): ?>
                            <div class="aisle-gap"></div>
                            <?php elseif ($seatPos === CINEMA_QTR || $seatPos === CINEMA_HALF + CINEMA_QTR): ?>
                            <div class="section-gap"></div>
                            <?php endif; ?>

                            <div class="seat-cell-wrap">
                                <?php if ($isPad || $isEmpty): ?>
                                <div class="seat-gap-slot" title="<?= $isEmpty ? 'Aisle / no seat' : 'Not a bookable seat' ?>"></div>
                                <?php elseif (!$taken): ?>
                                <label style="display:block;cursor:pointer;">
                                    <input class="seat-checkbox" type="checkbox"
                                           name="selectedSeats[]"
                                           value="<?= $seat['Seat_ID'] ?>"
                                           data-price="<?= $price ?>"
                                           data-label="<?= htmlspecialchars($lbl) ?>"
                                           <?= $heldBySelf ? 'checked' : '' ?>
                                           onchange="onSeatChange(this)">
                                    <div class="seat-btn <?= $css ?><?= $isRec ? ' ai-glow' : '' ?><?= $heldBySelf ? ' selected' : '' ?>"
                                         id="seat-<?= $seat['Seat_ID'] ?>"><?= $dispNum ?></div>
                                </label>
                                <?php else: ?>
                                <div class="seat-btn taken">✕</div>
                                <?php endif; ?>
                            </div>

                            <?php endforeach; ?>

                            <div class="seat-gap"></div>
                            <div class="rlbl rlbl-r"><?= htmlspecialchars($rowLabel) ?></div>
                        </div>

                        <?php endforeach; ?>

                    </div>
                    </div><!-- /seat-map-container -->

                    <div class="legend">
                        <div class="legend-item"><div class="legend-dot" style="background:#2e2e2e;border-bottom:3px solid #505050;"></div>Standard</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#3d1a1a;border-bottom:3px solid #ff4d4d;"></div>VIP</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#332b00;border-bottom:3px solid #ffc107;"></div>IMAX</div>
                        <div class="legend-item"><div class="legend-dot" style="background:rgba(255,77,77,0.28);border-bottom:3px solid #ff4d4d;"></div>Selected</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#1c1c1c;border-bottom:3px solid #282828;"></div>Taken</div>
                        <?php if ($aiRec): ?>
                        <div class="legend-item"><div class="legend-dot" style="outline:2px solid #ff4d4d;outline-offset:2px;"></div>AI Pick</div>
                        <?php endif; ?>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="panel">
                <div class="panel-header"><h2>🎬 Now Booking</h2></div>
                <div class="movie-card">
                    <div class="movie-thumb" style="background-image:url('<?= htmlspecialchars($movieDetails['MoviePoster']) ?>');"></div>
                    <div>
                        <h3><?= htmlspecialchars($movieDetails['MovieName']) ?></h3>
                        <div class="movie-card-meta">
                            <span>📍 <?= htmlspecialchars($mallDetails['MallName']) ?></span>
                            <span>🏛 <?= htmlspecialchars($timeslotDetails['TheaterName']) ?></span>
                            <span>📅 <?= date('F d, Y', strtotime($Date)) ?></span>
                            <span>🕐 <?= date('g:i A', strtotime($timeslotDetails['StartTime'] ?? $timeslotDetails['STARTTIME'] ?? '')) ?></span>
                        </div>
                        <span class="type-pill"><?= htmlspecialchars($timeslotDetails['ScreeningType']) ?></span>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><h2>🎟 Booking Summary</h2></div>
                <div class="panel-body">
                    <div id="seatTagList" style="margin-bottom:14px;min-height:40px;">
                        <span class="no-seats">No seats selected yet.</span>
                    </div>
                    <div class="divider"></div>
                    <div class="price-row">
                        <span class="lbl">Seats Selected</span>
                        <span class="val" id="seatCount">0 seats</span>
                    </div>
                    <div class="price-row total">
                        <span class="lbl">Total</span>
                        <span class="val">₱ <span id="seatPriceTotal">0.00</span></span>
                    </div>
                    <button type="button" class="btn-proceed" id="confirmBtn"
                            onclick="goToStep(2)" disabled>
                        Select seats to continue
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /seatStep -->

    <!-- Step 2: Food & Drinks -->
    <div id="foodStep">
        <div class="two-col">
            <div>
                <div class="panel">
                    <div class="panel-header"><h2>🍿 Add Food &amp; Drinks</h2></div>
                    <div class="panel-body">
                        <p style="font-size:0.78rem;color:rgba(249,249,249,0.35);margin-bottom:16px;">
                            Pre-order snacks for your screening — skip the queue! All items will be ready at the counter when you arrive.
                        </p>
                        <div class="food-grid" id="foodGrid">
                            <div class="food-card" id="fc-11" onclick="toggleFood(11,120)">
                                <div class="food-check-badge">✓</div>
                                <div class="food-card-img">🍿</div>
                                <div class="food-card-body">
                                    <div class="food-card-name">Regular Popcorn</div>
                                    <div class="food-card-desc">Salted or Buttered</div>
                                    <div class="food-card-price">₱120</div>
                                    <div class="food-card-qty">
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(11,-1)">−</button>
                                        <span class="qty-val" id="qty-11">1</span>
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(11,1)">+</button>
                                    </div>
                                </div>
                            </div>
                            <div class="food-card" id="fc-12" onclick="toggleFood(12,160)">
                                <div class="food-check-badge">✓</div>
                                <div class="food-card-img">🍿</div>
                                <div class="food-card-body">
                                    <div class="food-card-name">Large Popcorn</div>
                                    <div class="food-card-desc">Salted or Buttered</div>
                                    <div class="food-card-price">₱160</div>
                                    <div class="food-card-qty">
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(12,-1)">−</button>
                                        <span class="qty-val" id="qty-12">1</span>
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(12,1)">+</button>
                                    </div>
                                </div>
                            </div>
                            <div class="food-card" id="fc-15" onclick="toggleFood(15,80)">
                                <div class="food-check-badge">✓</div>
                                <div class="food-card-img">🥤</div>
                                <div class="food-card-body">
                                    <div class="food-card-name">Regular Drink</div>
                                    <div class="food-card-desc">Coke, Sprite, or Royal</div>
                                    <div class="food-card-price">₱80</div>
                                    <div class="food-card-qty">
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(15,-1)">−</button>
                                        <span class="qty-val" id="qty-15">1</span>
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(15,1)">+</button>
                                    </div>
                                </div>
                            </div>
                            <div class="food-card" id="fc-19" onclick="toggleFood(19,180)">
                                <div class="food-check-badge">✓</div>
                                <div class="food-card-img">🍿🥤</div>
                                <div class="food-card-body">
                                    <div class="food-card-name">Combo Meal</div>
                                    <div class="food-card-desc">Regular Popcorn + Drink</div>
                                    <div class="food-card-price">₱180</div>
                                    <div class="food-card-qty">
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(19,-1)">−</button>
                                        <span class="qty-val" id="qty-19">1</span>
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(19,1)">+</button>
                                    </div>
                                </div>
                            </div>
                            <div class="food-card" id="fc-20" onclick="toggleFood(20,230)">
                                <div class="food-check-badge">✓</div>
                                <div class="food-card-img">🍿🥤</div>
                                <div class="food-card-body">
                                    <div class="food-card-name">Large Combo</div>
                                    <div class="food-card-desc">Large Popcorn + Drink</div>
                                    <div class="food-card-price">₱230</div>
                                    <div class="food-card-qty">
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(20,-1)">−</button>
                                        <span class="qty-val" id="qty-20">1</span>
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(20,1)">+</button>
                                    </div>
                                </div>
                            </div>
                            <div class="food-card" id="fc-14" onclick="toggleFood(14,110)">
                                <div class="food-check-badge">✓</div>
                                <div class="food-card-img">🌭</div>
                                <div class="food-card-body">
                                    <div class="food-card-name">Hot Dog</div>
                                    <div class="food-card-desc">Classic jumbo frank</div>
                                    <div class="food-card-price">₱110</div>
                                    <div class="food-card-qty">
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(14,-1)">−</button>
                                        <span class="qty-val" id="qty-14">1</span>
                                        <button type="button" class="qty-btn" onclick="event.stopPropagation();changeQty(14,1)">+</button>
                                    </div>
                                </div>
                            </div>
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
                    <div class="panel-header"><h2>🎬 Now Booking</h2></div>
                    <div class="movie-card">
                        <div class="movie-thumb" style="background-image:url('<?= htmlspecialchars($movieDetails['MoviePoster']) ?>');"></div>
                        <div>
                            <h3><?= htmlspecialchars($movieDetails['MovieName']) ?></h3>
                            <div class="movie-card-meta">
                                <span>📍 <?= htmlspecialchars($mallDetails['MallName']) ?></span>
                                <span>🏛 <?= htmlspecialchars($timeslotDetails['TheaterName']) ?></span>
                                <span>📅 <?= date('F d, Y', strtotime($Date)) ?></span>
                                <span>🕐 <?= date('g:i A', strtotime($timeslotDetails['StartTime'] ?? $timeslotDetails['STARTTIME'] ?? '')) ?></span>
                            </div>
                            <span class="type-pill"><?= htmlspecialchars($timeslotDetails['ScreeningType']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><h2>🧾 Order Summary</h2></div>
                    <div class="panel-body">
                        <div class="price-row" style="margin-bottom:8px;">
                            <span class="lbl">Seats (<span id="foodSeatCount">0 seats</span>)</span>
                            <span class="val">₱ <span id="foodSeatTotal">0.00</span></span>
                        </div>
                        <div id="foodOrderList" style="margin-bottom:10px;min-height:20px;"></div>
                        <div class="divider"></div>
                        <div class="food-subtotal">
                            <span class="lbl">🍿 Food & Drinks</span>
                            <span class="val">+ ₱ <span id="foodSubtotal">0.00</span></span>
                        </div>
                        <div class="price-row total" style="margin-bottom:16px;">
                            <span class="lbl">Grand Total</span>
                            <span class="val">₱ <span id="grandTotal">0.00</span></span>
                        </div>
                        <p class="food-skip-note">🍿 Food is optional — you can skip this step</p>
                        <div class="food-actions">
                            <button type="button" class="btn-back-seats" onclick="goToStep(1)">← Back</button>
                            <button type="button" class="btn-pay-now"    onclick="submitOrder()">Pay Now →</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /foodStep -->

</div>
</form>

<script>
// CRITICAL FIX: Dynamic seat map scaling to prevent overflow
(function() {
    function scaleSeatMap() {
        const container = document.getElementById('seatMapContainer');
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
        // Use less padding on mobile for bigger seats
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

const TIME_SLOT_ID = <?= json_encode((int)$TimeSlot_ID) ?>;
const selectedSeats = {};
const foodItems     = {};

const FOOD_MENU = {
    11: { name: 'Regular Popcorn', price: 120 },
    12: { name: 'Large Popcorn',   price: 160 },
    15: { name: 'Regular Drink',   price: 80  },
    19: { name: 'Combo Meal',      price: 180 },
    20: { name: 'Large Combo',     price: 230 },
    14: { name: 'Hot Dog',         price: 110 },
};

// ── Step navigation ───────────────────────────────────────────
function goToStep(step) {
    const seatStep  = document.getElementById('seatStep');
    const foodStep  = document.getElementById('foodStep');
    const step1Tab  = document.getElementById('step1Tab');
    const step2Tab  = document.getElementById('step2Tab');
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

// ── AI Recommendation ─────────────────────────────────────────
async function applyAiRec(seatId) {
    const recBtn   = document.getElementById('aiRecBtn');
    const sid      = String(seatId);
    const checkbox = document.querySelector('input.seat-checkbox[value="' + sid.replace(/"/g, '\\"') + '"]');
    if (!checkbox) return;

    const actualLabel = checkbox.dataset.label || String(seatId);
    const price       = parseFloat(checkbox.dataset.price) || 0;
    const seatEl      = document.getElementById('seat-' + sid);

    // 1. Auto-select the seat if not already selected
    if (!checkbox.checked) {
        checkbox.checked = true;
        await onSeatChange(checkbox);
        if (!checkbox.checked) {
            return;
        }
    }

    // 2. Remove glow, add big pop animation
    if (seatEl) {
        seatEl.classList.remove('ai-glow');
        seatEl.classList.remove('ai-pop');
        void seatEl.offsetWidth; // reflow to restart animation
        seatEl.classList.add('ai-pop');

        // Scroll to seat smoothly
        seatEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // After pop, keep a permanent glow ring
        setTimeout(() => {
            seatEl.classList.remove('ai-pop');
            seatEl.style.boxShadow = '0 0 0 3px #ff4d4d, 0 0 20px rgba(255,77,77,0.5)';
        }, 950);
    }

    // 3. Update button to confirmed state
    if (recBtn) {
        recBtn.textContent = '✓ Seat ' + actualLabel + ' Selected';
        recBtn.classList.add('applied');
    }
}

// ── Seat selection ────────────────────────────────────────────
async function updateSeatHold(action, seatId) {
    const response = await fetch('seat_hold.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action,
            seat_id: Number(seatId),
            timeslot_id: TIME_SLOT_ID
        })
    });
    return response.json();
}

async function onSeatChange(checkbox) {
    const id    = checkbox.value;
    const price = parseFloat(checkbox.dataset.price) || 0;
    const label = checkbox.dataset.label;
    const btn   = document.getElementById('seat-' + id);

    checkbox.disabled = true;
    try {
        const action = checkbox.checked ? 'hold' : 'release';
        const result = await updateSeatHold(action, id);
        if (!result.success) {
            checkbox.checked = !checkbox.checked;
            if (checkbox.checked) {
                selectedSeats[id] = { label, price };
                btn?.classList.add('selected');
            } else {
                delete selectedSeats[id];
                btn?.classList.remove('selected');
            }
            alert(result.error || 'Unable to update seat hold.');
            return;
        }

        if (checkbox.checked) {
            selectedSeats[id] = { label, price };
            btn?.classList.add('selected');
        } else {
            delete selectedSeats[id];
            btn?.classList.remove('selected');
        }
    } catch (error) {
        checkbox.checked = !checkbox.checked;
        alert('Unable to sync seat selection right now. Please try again.');
    } finally {
        checkbox.disabled = false;
    }
    updateSummary();
}

function updateSummary() {
    const keys  = Object.keys(selectedSeats);
    const total = keys.reduce((s, k) => s + selectedSeats[k].price, 0);

    document.getElementById('seatCount').textContent      = keys.length;
    document.getElementById('seatPriceTotal').textContent = total.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('priceTotalHidden').value     = total.toFixed(2);

    const list = document.getElementById('seatTagList');
    list.innerHTML = keys.length === 0
        ? '<span class="no-seats">No seats selected yet.</span>'
        : keys.map(id =>
            `<span class="seat-tag">
                ${selectedSeats[id].label}
                <span style="font-size:0.62rem;opacity:0.55;">₱${selectedSeats[id].price.toLocaleString()}</span>
            </span>`).join('');

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

// ── Food ──────────────────────────────────────────────────────
function toggleFood(id, price) {
    const card = document.getElementById('fc-' + id);
    if (foodItems[id]) {
        delete foodItems[id];
        card.classList.remove('selected');
    } else {
        foodItems[id] = { name: FOOD_MENU[id].name, price: FOOD_MENU[id].price, qty: 1 };
        card.classList.add('selected');
        document.getElementById('qty-' + id).textContent = 1;
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
    const seatKeys  = Object.keys(selectedSeats);
    const seatTotal = seatKeys.reduce((s, k) => s + selectedSeats[k].price, 0);
    const foodTotal = Object.values(foodItems).reduce((s, f) => s + f.price * f.qty, 0);
    const grand     = seatTotal + foodTotal;

    document.getElementById('foodSeatCount').textContent = formatSeatCount(seatKeys.length);
    document.getElementById('foodSeatTotal').textContent = seatTotal.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('foodSubtotal').textContent  = foodTotal.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('grandTotal').textContent    = grand.toLocaleString('en-PH', {minimumFractionDigits:2});

    const ol = document.getElementById('foodOrderList');
    const items = Object.values(foodItems);
    ol.innerHTML = items.length === 0
        ? '<div style="font-size:0.72rem;color:rgba(249,249,249,0.2);margin-bottom:4px;">No food added</div>'
        : items.map(f =>
            `<div style="display:flex;justify-content:space-between;font-size:0.75rem;color:rgba(249,249,249,0.45);margin-bottom:4px;">
                <span>🍿 ${f.name} x${f.qty}</span>
                <span>₱${(f.price*f.qty).toLocaleString()}</span>
            </div>`).join('');

    document.getElementById('priceTotalHidden').value = grand.toFixed(2);
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

// ── Submit ────────────────────────────────────────────────────
function submitOrder() {
    const form = document.getElementById('seatForm');
    if (!form) return;

    // Sync from DOM so we never submit empty seats if JS state drifted from checkboxes
    document.querySelectorAll('.seat-checkbox:checked').forEach(cb => {
        const id = cb.value;
        const price = parseFloat(cb.dataset.price) || 0;
        const label = cb.dataset.label || '';
        if (!selectedSeats[id]) {
            selectedSeats[id] = { label, price };
        }
    });

    const seatKeys = Object.keys(selectedSeats);
    if (seatKeys.length === 0) {
        alert('Please select at least one seat.');
        goToStep(1);
        return;
    }

    const seatTotal = seatKeys.reduce((s, k) => s + selectedSeats[k].price, 0);
    const foodSum   = Object.values(foodItems).reduce((s, f) => s + f.price * f.qty, 0);
    document.getElementById('priceTotalHidden').value = (seatTotal + foodSum).toFixed(2);

    document.querySelectorAll('.seat-hidden-input, .food-hidden-input').forEach(e => e.remove());

    // Drop checkbox names so only our hidden selectedSeats[] post (avoids duplicate IDs + broken disabled submit)
    document.querySelectorAll('.seat-checkbox').forEach(cb => { cb.removeAttribute('name'); });

    seatKeys.forEach(seatId => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'selectedSeats[]';
        inp.value = seatId;
        inp.className = 'seat-hidden-input';
        form.appendChild(inp);
        const lab = document.createElement('input');
        lab.type = 'hidden';
        lab.name = 'seatLabels[]';
        lab.value = (selectedSeats[seatId] && selectedSeats[seatId].label) || '';
        lab.className = 'seat-hidden-input';
        form.appendChild(lab);
    });

    Object.entries(foodItems).forEach(([id, item]) => {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'foodOrder[]';
        inp.value = JSON.stringify({ id, name: item.name, price: item.price, qty: item.qty });
        inp.className = 'food-hidden-input';
        form.appendChild(inp);
    });

    const specialRequestsInput = document.createElement('input');
    specialRequestsInput.type = 'hidden';
    specialRequestsInput.name = 'special_requests';
    specialRequestsInput.value = document.getElementById('specialRequests').value.trim();
    form.appendChild(specialRequestsInput);

    form.submit();
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

document.querySelectorAll('.seat-checkbox:checked').forEach(cb => {
    selectedSeats[cb.value] = {
        label: cb.dataset.label || '',
        price: parseFloat(cb.dataset.price) || 0
    };
});

updateSummary();

<?php if (isset($_SESSION['user_id'])): ?>
loadNotif();
setInterval(loadNotif, 60000);
<?php endif; ?>
</script>
</body>
</html>
