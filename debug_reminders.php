<?php
date_default_timezone_set('Asia/Manila');
include 'peakscinemas_database.php';

echo "### Environment Check ###\n";
echo "PHP Time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Timezone: " . date_default_timezone_get() . "\n";

$q = $conn->query("SELECT NOW() as db_now, @@session.time_zone as tz");
$row = $q->fetch_assoc();
echo "DB Time: " . $row['db_now'] . "\n";
echo "DB Timezone: " . $row['tz'] . "\n";

echo "\n### Schema Check ###\n";
$q = $conn->query("SHOW CREATE TABLE reminders_sent");
$row = $q->fetch_assoc();
echo "Table Structure:\n" . $row['Create Table'] . "\n";

echo "\n### Booking Window Check ###\n";
echo "Checking for bookings starting between 25 and 35 minutes from now...\n";
$q = $conn->query("
    SELECT 
        tk.Ticket_ID, 
        ts.Date, 
        ts.StartTime, 
        STR_TO_DATE(CONCAT(ts.Date, ' ', ts.StartTime), '%Y-%m-%d %H:%i') as screening_dt,
        TIMESTAMPDIFF(MINUTE, NOW(), STR_TO_DATE(CONCAT(ts.Date, ' ', ts.StartTime), '%Y-%m-%d %H:%i')) as mins_diff
    FROM ticket tk
    JOIN timeslot ts ON ts.TimeSlot_ID = tk.TimeSlot_ID
    WHERE tk.Status = 1
    ORDER BY mins_diff ASC
    LIMIT 5
");

if ($q->num_rows === 0) {
    echo "No active bookings found in the 'ticket' table.\n";
} else {
    while($row = $q->fetch_assoc()) {
        echo "- Ticket ID: {$row['Ticket_ID']} | Screening: {$row['Date']} {$row['StartTime']} | Starts in: {$row['mins_diff']} mins\n";
    }
}

echo "\n### Recent Activity ###\n";
$q = $conn->query("SELECT * FROM reminders_sent ORDER BY SentAt DESC LIMIT 5");
if ($q->num_rows === 0) {
    echo "No reminders have been recorded in the 'reminders_sent' table yet.\n";
} else {
    while($row = $q->fetch_assoc()) {
        print_r($row);
    }
}
?>
