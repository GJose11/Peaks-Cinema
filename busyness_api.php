<?php
error_reporting(0);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');
ob_start();
include("peakscinemas_database.php");
ob_end_clean();
header('Content-Type: application/json');

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(array('malls' => array(), 'as_of' => date('g:i A')));
    exit;
}

$today    = date('Y-m-d');
$now      = date('H:i:s');
$plus4ts  = strtotime('+4 hours');
$tomorrow = date('Y-m-d', $plus4ts);
$cutoff   = date('H:i:s', $plus4ts);
$crosses  = ($tomorrow !== $today);

// Get all malls
$malls = array();
$mq = $conn->query("SELECT Mall_ID, MallName, Location FROM mall ORDER BY MallName ASC");
if ($mq) {
    while ($row = $mq->fetch_assoc()) {
        $malls[] = $row;
    }
}

if (empty($malls)) {
    echo json_encode(array('malls' => array(), 'as_of' => date('g:i A')));
    exit;
}

$out = array();

foreach ($malls as $mall) {
    $mid = (int)$mall['Mall_ID'];

    // Get theater IDs for this mall
    $tids = array();
    $tq = $conn->query("SELECT Theater_ID FROM theater WHERE Mall_ID = " . $mid);
    if ($tq) {
        while ($tr = $tq->fetch_assoc()) {
            $tids[] = (int)$tr['Theater_ID'];
        }
    }

    if (empty($tids)) {
        $out[] = makeCard($mall, 0, 0, 0, 0, null, $today);
        continue;
    }

    $tin = implode(',', $tids);

    // Time window — handles midnight crossover
    if ($crosses) {
        $tCond = "(ts.Date = '" . $today . "' AND ts.StartTime >= '" . $now . "')
                  OR (ts.Date = '" . $tomorrow . "' AND ts.StartTime <= '" . $cutoff . "')";
    } else {
        $tCond = "ts.Date = '" . $today . "'
                  AND ts.StartTime >= '" . $now . "'
                  AND ts.StartTime <= '" . $cutoff . "'";
    }

    // Overall mall busyness — ALL theaters, ALL screenings in next 4 hours
    $totalSeats  = 0;
    $bookedSeats = 0;
    $activeSlots = 0;
    $activeTh    = 0;

    $bq = $conn->query("
        SELECT
            COUNT(DISTINCT ts.TimeSlot_ID) AS slots,
            COUNT(DISTINCT ts.Theater_ID)  AS thcount,
            SUM(CASE WHEN s.SeatType != 'Empty' THEN 1 ELSE 0 END) AS tot,
            SUM(CASE WHEN s.SeatType != 'Empty' AND s.SeatAvailability = 'Taken' THEN 1 ELSE 0 END) AS bkd
        FROM timeslot ts
        LEFT JOIN seats s ON s.TimeSlot_ID = ts.TimeSlot_ID
        WHERE ts.Theater_ID IN (" . $tin . ")
          AND (" . $tCond . ")
    ");
    if ($bq) {
        $br = $bq->fetch_assoc();
        $totalSeats  = (int)$br['tot'];
        $bookedSeats = (int)$br['bkd'];
        $activeSlots = (int)$br['slots'];
        $activeTh    = (int)$br['thcount'];
    }

    // Best upcoming slot = theater with most open seats (no movie reference)
    $best = null;
    $bestQ = $conn->query("
        SELECT z.Date, z.StartTime, z.TheaterName,
               (z.tot - z.bkd) AS open_seats
        FROM (
            SELECT
                ts.Date,
                ts.StartTime,
                th.TheaterName,
                SUM(CASE WHEN s.SeatType != 'Empty' THEN 1 ELSE 0 END) AS tot,
                SUM(CASE WHEN s.SeatType != 'Empty' AND s.SeatAvailability = 'Taken' THEN 1 ELSE 0 END) AS bkd
            FROM timeslot ts
            JOIN theater th ON th.Theater_ID = ts.Theater_ID
            LEFT JOIN seats s ON s.TimeSlot_ID = ts.TimeSlot_ID
            WHERE ts.Theater_ID IN (" . $tin . ")
              AND (ts.Date > '" . $today . "'
                   OR (ts.Date = '" . $today . "' AND ts.StartTime >= '" . $now . "'))
            GROUP BY ts.TimeSlot_ID, ts.Date, ts.StartTime, th.TheaterName
        ) z
        WHERE (z.tot - z.bkd) > 0
        ORDER BY (z.tot - z.bkd) DESC
        LIMIT 1
    ");
    if ($bestQ && $bestQ->num_rows > 0) {
        $best = $bestQ->fetch_assoc();
    }

    $out[] = makeCard($mall, $totalSeats, $bookedSeats, $activeSlots, $activeTh, $best, $today);
}

echo json_encode(array('malls' => $out, 'as_of' => date('g:i A')));

function makeCard($mall, $total, $booked, $slots, $theaters, $best, $today) {
    $pct = ($total > 0) ? round(($booked / $total) * 100) : 0;

    if ($slots === 0) {
        $level = 'none';     $label = 'No screenings right now';
        $color = '#555555';  $dot   = "\xe2\x9a\xab"; $pct = 0;
    } elseif ($pct < 40) {
        $level = 'relaxed';  $label = 'Relaxed - Walk in anytime';
        $color = '#66BB6A';  $dot   = "\xf0\x9f\x9f\xa2";
    } elseif ($pct < 75) {
        $level = 'moderate'; $label = 'Moderate - Filling up';
        $color = '#FFD54F';  $dot   = "\xf0\x9f\x9f\xa1";
    } else {
        $level = 'busy';     $label = 'Very Busy - Book fast!';
        $color = '#FF4D4D';  $dot   = "\xf0\x9f\x94\xb4";
    }

    $bestLabel = null;
    $bestTheater = null;
    if ($best) {
        $parts = explode(':', $best['StartTime']);
        $h     = (int)$parts[0];
        $min   = str_pad((int)$parts[1], 2, '0', STR_PAD_LEFT);
        $ampm  = $h >= 12 ? 'PM' : 'AM';
        $h12   = $h % 12;
        if ($h12 === 0) $h12 = 12;
        $day   = ($best['Date'] === $today) ? 'Today' : date('M d', strtotime($best['Date']));
        $open  = (int)$best['open_seats'];
        $bestLabel   = $day . ' at ' . $h12 . ':' . $min . ' ' . $ampm . ' (' . $open . ' seats open)';
        $bestTheater = $best['TheaterName'];
    }

    return array(
        'mall_id'         => (int)$mall['Mall_ID'],
        'mall_name'       => $mall['MallName'],
        'location'        => $mall['Location'],
        'level'           => $level,
        'label'           => $label,
        'color'           => $color,
        'dot'             => $dot,
        'percent'         => $pct,
        'booked_seats'    => $booked,
        'total_seats'     => $total,
        'screenings_now'  => $slots,
        'theaters_active' => $theaters,
        'best_showtime'   => $bestLabel,
        'best_movie'      => null,
        'best_theater'    => $bestTheater,
        'updated_at'      => date('g:i A'),
    );
}
