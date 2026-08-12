<?php
include 'peakscinemas_database.php';
$q = $conn->query('SHOW CREATE TABLE notifications');
$row = $q->fetch_assoc();
echo $row['Create Table'];
?>
