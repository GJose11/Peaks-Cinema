<?php
include 'peakscinemas_database.php';
require_once __DIR__ . '/auth_otp_helpers.php';
ensureOtpTableSchema($conn);
$res = $conn->query("SHOW COLUMNS FROM otp");
while ($row = $res->fetch_assoc()) {
  echo $row['Field'] . '|' . $row['Type'] . '|' . $row['Null'] . '|' . $row['Key'] . '|' . $row['Extra'] . PHP_EOL;
}
