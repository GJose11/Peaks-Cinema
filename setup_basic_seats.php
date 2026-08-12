<?php
include("peakscinemas_database.php");

echo "<h2>🎬 Setting Up Basic Seats</h2>";

// Create basic theaters if they don't exist
$theaters = [
    ['Mall_ID' => 1, 'TheaterName' => 'Cinema 1', 'TheaterType' => 'Standard', 'TotalSeats' => 48],
    ['Mall_ID' => 1, 'TheaterName' => 'Cinema 2', 'TheaterType' => 'Premium', 'TotalSeats' => 32],
];

foreach ($theaters as $theater) {
    $check = $conn->prepare("SELECT Theater_ID FROM theater WHERE TheaterName = ? AND Mall_ID = ?");
    $check->bind_param("si", $theater['TheaterName'], $theater['Mall_ID']);
    $check->execute();
    
    if ($check->get_result()->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO theater(Mall_ID, TheaterName, TheaterType, TotalSeats) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $theater['Mall_ID'], $theater['TheaterName'], $theater['TheaterType'], $theater['TotalSeats']);
        $stmt->execute();
        $theater_id = $conn->insert_id;
        echo "<p>✅ Created theater: {$theater['TheaterName']} (ID: $theater_id)</p>";
        
        // Create seat layout
        createSeatLayout($theater_id, $theater['TheaterType']);
    } else {
        echo "<p>⚠️ Theater {$theater['TheaterName']} already exists</p>";
    }
}

function createSeatLayout($theater_id, $type) {
    global $conn;
    
    if ($type == 'Standard') {
        // 8 rows x 6 columns = 48 seats
        for ($row = 1; $row <= 8; $row++) {
            $rowLetter = chr(64 + $row); // A, B, C...
            for ($col = 1; $col <= 6; $col++) {
                $seatType = ($row <= 2) ? 'Premium' : 'Standard';
                $price = ($row <= 2) ? 12.50 : 10.00;
                
                $stmt = $conn->prepare("INSERT INTO seats(SeatRow, SeatColumn, SeatType, SeatPrice, SeatAvailability, Theater_ID) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisidi", $rowLetter, $col, $seatType, $price, 1, $theater_id);
                $stmt->execute();
            }
        }
    } else {
        // 4 rows x 8 columns = 32 seats (Premium)
        for ($row = 1; $row <= 4; $row++) {
            $rowLetter = chr(64 + $row);
            for ($col = 1; $col <= 8; $col++) {
                $seatType = 'Premium';
                $price = 15.00;
                
                $stmt = $conn->prepare("INSERT INTO seats(SeatRow, SeatColumn, SeatType, SeatPrice, SeatAvailability, Theater_ID) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisidi", $rowLetter, $col, $seatType, $price, 1, $theater_id);
                $stmt->execute();
            }
        }
    }
}

echo "<hr>";
echo "<h3>✅ Setup Complete!</h3>";
echo "<p><a href='seat_selection.php?movie_id=1&timeslot_id=1'>Test Seat Selection</a></p>";
echo "<p><a href='home.php'>← Back to Home</a></p>";
?>
