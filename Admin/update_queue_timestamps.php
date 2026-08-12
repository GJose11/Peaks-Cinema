<?php
/**
 * update_queue_timestamps.php
 * One-time script to update all queue timestamps to current Manila time
 * Run this once: http://localhost/PeaksCinema/Admin/update_queue_timestamps.php
 */
date_default_timezone_set('Asia/Manila');
include("../peakscinemas_database.php");

// Update all queue records to current timestamp
$now = date('Y-m-d H:i:s');
$result = $conn->query("UPDATE cinema_queue SET updated_at = '$now'");

if ($result) {
    $affected = $conn->affected_rows;
    echo "✓ Success! Updated $affected queue record(s) to current time: $now<br><br>";
    echo "<a href='queue_admin.php'>← Back to Queue Admin</a> | ";
    echo "<a href='../queue_tracker.php'>View Queue Tracker</a>";
} else {
    echo "✗ Error updating timestamps: " . $conn->error;
}
?>
