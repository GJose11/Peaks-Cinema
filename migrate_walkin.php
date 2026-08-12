<?php
include "peakscinemas_database.php";

$sql = "CREATE TABLE IF NOT EXISTS walk_in_customers (
    WalkIn_ID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    ContactInfo VARCHAR(255),
    WalkInDate DATE NOT NULL,
    BookingTime TIME NOT NULL,
    Status ENUM('Active', 'Completed', 'Cancelled') DEFAULT 'Active',
    Staff_ID INT,
    BookingRef VARCHAR(20) UNIQUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Staff_ID) REFERENCES staff(Staff_ID)
)";

if ($conn->query($sql)) {
    echo "Table walk_in_customers created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>