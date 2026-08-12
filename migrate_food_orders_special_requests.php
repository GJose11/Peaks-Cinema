<?php
include "peakscinemas_database.php";

$sql = "ALTER TABLE food_orders
        ADD COLUMN SpecialRequests TEXT NULL AFTER UnitPrice";

if ($conn->query($sql)) {
    echo "Column 'SpecialRequests' added to 'food_orders' table successfully.\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}
?>