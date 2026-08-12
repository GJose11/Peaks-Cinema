<?php
include 'peakscinemas_database.php';
$q = $conn->query('DESCRIBE reminders_sent');
while($r = $q->fetch_assoc()){ echo $r['Field'].' '.$r['Type']."\n"; }
?>
