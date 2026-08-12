<?php
include 'peakscinemas_database.php';

$rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
$theatersRes = $conn->query("SELECT Theater_ID FROM theater");
$theaterIds = [];
while($r = $theatersRes->fetch_assoc()) $theaterIds[] = $r['Theater_ID'];

foreach ($theaterIds as $tid) {
    foreach ($rows as $row) {
        for ($col = 0; $col < 20; $col++) {
            // Check if seat exists for this theater (template)
            $check = $conn->prepare("SELECT Seat_ID FROM seats WHERE Theater_ID = ? AND SeatRow = ? AND SeatColumn = ? AND TimeSlot_ID IS NULL");
            $check->bind_param("isi", $tid, $row, $col);
            $check->execute();
            if ($check->get_result()->num_rows == 0) {
                // Insert template seat
                $ins = $conn->prepare("INSERT INTO seats (SeatRow, SeatColumn, SeatType, SeatAvailability, SeatPrice, Theater_ID, TimeSlot_ID) VALUES (?, ?, 'Standard', 1, '350', ?, NULL)");
                $ins->bind_param("sii", $row, $col, $tid);
                $ins->execute();
            }
        }
    }
}

// Now do the same for all timeslots
$tsRes = $conn->query("SELECT TimeSlot_ID, Theater_ID FROM timeslot");
while($ts = $tsRes->fetch_assoc()) {
    $tsid = $ts['TimeSlot_ID'];
    $tid = $ts['Theater_ID'];
    foreach ($rows as $row) {
        for ($col = 0; $col < 20; $col++) {
            $check = $conn->prepare("SELECT Seat_ID FROM seats WHERE TimeSlot_ID = ? AND SeatRow = ? AND SeatColumn = ?");
            $check->bind_param("isi", $tsid, $row, $col);
            $check->execute();
            if ($check->get_result()->num_rows == 0) {
                // Insert timeslot seat
                $ins = $conn->prepare("INSERT INTO seats (SeatRow, SeatColumn, SeatType, SeatAvailability, SeatPrice, Theater_ID, TimeSlot_ID) VALUES (?, ?, 'Standard', 1, '350', ?, ?)");
                $ins->bind_param("siii", $row, $col, $tid, $tsid);
                $ins->execute();
            }
        }
    }
}

echo "Finished inserting missing seats." . PHP_EOL;
?>