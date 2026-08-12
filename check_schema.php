<?php
include("peakscinemas_database.php");
echo "food_orders:\n";
$result = $conn->query("DESCRIBE food_orders");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\nwalk_in_customers:\n";
$result = $conn->query("DESCRIBE walk_in_customers");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\nreminders_sent:\n";
$result = $conn->query("DESCRIBE reminders_sent");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Default'] . "\n";
}
?>
