<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data.']);
    exit;
}

$movie_id    = $data['movie_id'];
$mall_id     = $data['mall_id'];
$date        = $data['date'];
$timeslot_id = $data['timeslot_id'];
$seat_ids    = $data['seat_ids'];

if (empty($seat_ids)) {
    echo json_encode(['success' => false, 'error' => 'No seats selected.']);
    exit;
}

// Check if any of the selected seats are already taken in the ticket table
$placeholders = implode(',', array_fill(0, count($seat_ids), '?'));
$query = "SELECT Seat_ID FROM ticket 
          WHERE Movie_ID = ? 
          AND Mall_ID = ? 
          AND Date = ? 
          AND Timeslot_ID = ? 
          AND Seat_ID IN ($placeholders)
          AND Status = 1";

$stmt = $conn->prepare($query);
$params = array_merge([$movie_id, $mall_id, $date, $timeslot_id], $seat_ids);
$types = 'iiss' . str_repeat('s', count($seat_ids));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $taken = [];
    while ($row = $result->fetch_assoc()) {
        $taken[] = $row['Seat_ID'];
    }
    echo json_encode([
        'success' => false, 
        'error' => 'Some seats are already taken: ' . implode(', ', $taken),
        'taken_seats' => $taken
    ]);
} else {
    echo json_encode(['success' => true]);
}
