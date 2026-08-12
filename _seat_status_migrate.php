<?php
session_start();
include __DIR__ . '/peakscinemas_database.php';
require __DIR__ . '/seat_hold_helpers.php';

ensureSeatHoldSchema($conn);
releaseExpiredSeatHolds($conn);

$r = $conn->query("SHOW COLUMNS FROM seats LIKE 'SeatAvailability'");
$c = $r ? $r->fetch_assoc() : null;
echo "type=" . ($c['Type'] ?? 'n/a') . PHP_EOL;

$q = $conn->query("SELECT SeatAvailability, COUNT(*) AS cnt FROM seats GROUP BY SeatAvailability ORDER BY SeatAvailability");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        echo $row['SeatAvailability'] . ':' . $row['cnt'] . PHP_EOL;
    }
}
