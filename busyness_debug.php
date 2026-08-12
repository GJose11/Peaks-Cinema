<?php
date_default_timezone_set('Asia/Manila');
include("peakscinemas_database.php");
header('Content-Type: application/json');

$today      = date('Y-m-d');
$nowTime    = date('H:i:s');
$cutoffTime = date('H:i:s', strtotime('+4 hours'));

$debug = [
    'today'       => $today,
    'nowTime'     => $nowTime,
    'cutoffTime'  => $cutoffTime,
    'steps'       => []
];

// Step 1: malls
$r = $conn->query("SELECT Mall_ID, MallName FROM mall");
if (!$r) { $debug['steps'][] = 'MALLS QUERY FAILED: ' . $conn->error; }
else {
    $malls = $r->fetch_all(MYSQLI_ASSOC);
    $debug['steps'][] = 'Malls found: ' . count($malls);
    $debug['malls'] = $malls;
}

// Step 2: theaters
$r2 = $conn->query("SELECT Theater_ID, TheaterName, Mall_ID FROM theater");
$debug['theaters'] = $r2 ? $r2->fetch_all(MYSQLI_ASSOC) : 'FAILED: ' . $conn->error;

// Step 3: timeslots today or future
$r3 = $conn->query("SELECT TimeSlot_ID, Date, StartTime, Theater_ID, Movie_ID FROM timeslot WHERE Date >= '$today' LIMIT 10");
$debug['timeslots_sample'] = $r3 ? $r3->fetch_all(MYSQLI_ASSOC) : 'FAILED: ' . $conn->error;

// Step 4: seats sample
$r4 = $conn->query("SELECT Seat_ID, SeatType, SeatAvailability, TimeSlot_ID FROM seats WHERE TimeSlot_ID IS NOT NULL LIMIT 5");
$debug['seats_sample'] = $r4 ? $r4->fetch_all(MYSQLI_ASSOC) : 'FAILED: ' . $conn->error;

// Step 5: try the busy query
$busy_stmt = $conn->prepare("
    SELECT
        ml.Mall_ID,
        COUNT(DISTINCT ts.TimeSlot_ID)                                                  AS active_screenings,
        COUNT(DISTINCT ts.Theater_ID)                                                   AS active_theaters,
        COUNT(CASE WHEN s.SeatType != 'Empty' THEN 1 END)                              AS total_seats,
COUNT(CASE WHEN s.SeatType != 'Empty' AND s.SeatAvailability = 'Taken' THEN 1 END)   AS booked_seats
    FROM mall ml
    JOIN theater th  ON th.Mall_ID    = ml.Mall_ID
    JOIN timeslot ts ON ts.Theater_ID = th.Theater_ID
    LEFT JOIN seats s ON s.TimeSlot_ID = ts.TimeSlot_ID
    WHERE ts.Date = ?
      AND ts.StartTime >= ?
      AND ts.StartTime <= ?
    GROUP BY ml.Mall_ID
");
if (!$busy_stmt) {
    $debug['busy_query'] = 'PREPARE FAILED: ' . $conn->error;
} else {
    $busy_stmt->bind_param("sss", $today, $nowTime, $cutoffTime);
    $busy_stmt->execute();
    $debug['busy_query'] = 'OK';
    $debug['busy_result'] = $busy_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

echo json_encode($debug, JSON_PRETTY_PRINT);
