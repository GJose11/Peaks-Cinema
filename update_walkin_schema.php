<?php
include "peakscinemas_database.php";

// Add FoodOrderDetails and SpecialRequests to walk_in_customers
// We store detailed food info as a JSON string for immediate display, 
// while maintaining the existing relationship with the food_orders table for reporting.
$sql = "ALTER TABLE walk_in_customers 
        ADD COLUMN FoodOrderDetails TEXT NULL AFTER BookingRef,
        ADD COLUMN SpecialRequests TEXT NULL AFTER FoodOrderDetails";

if ($conn->query($sql)) {
    echo "Table walk_in_customers updated successfully.\n";
} else {
    echo "Error updating table: " . $conn->error . "\n";
}
?>