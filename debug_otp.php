<?php
date_default_timezone_set('Asia/Manila');
include 'peakscinemas_database.php';
$phpNow = date('Y-m-d H:i:s');
var_dump($phpNow);
var_dump(date_default_timezone_get());
$q = $conn->query("SELECT NOW() as db_now");
$row = $q->fetch_assoc();
var_dump($row['db_now']);
?>
