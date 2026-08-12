<?php
$conn = mysqli_connect("localhost", "root", "", "peakscinemadb");
if (!$conn) {
    echo "Connection failed: " . mysqli_connect_error() . "\n";
    exit;
}
$q = mysqli_query($conn, "SHOW CREATE TABLE food_orders");
$row = mysqli_fetch_assoc($q);
echo $row['Create Table'] . "\n";
?>
