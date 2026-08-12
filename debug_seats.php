<?php
include 'peakscinemas_database.php';
$res = $conn->query('SELECT TimeSlot_ID FROM timeslot LIMIT 1');
$r = $res->fetch_assoc();
$tsid = $r['TimeSlot_ID'];

$res = $conn->query("SELECT SeatRow, SeatColumn, Seat_ID FROM seats WHERE TimeSlot_ID = $tsid AND SeatRow = 'A' ORDER BY CAST(SeatColumn AS UNSIGNED)");
while($r = $res->fetch_assoc()) {
    echo "Col: " . $r['SeatColumn'] . " ID: " . $r['Seat_ID'] . PHP_EOL;
}
?>