<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/peakscinemas_database.php';
require_once __DIR__ . '/seat_hold_helpers.php';

ensureSeatHoldSchema($conn);
releaseExpiredSeatHolds($conn);
$token = getSeatHoldToken();

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$action = strtolower(trim((string)($payload['action'] ?? '')));
$seatId = (int)($payload['seat_id'] ?? 0);
$timeSlotId = (int)($payload['timeslot_id'] ?? 0);

if (!in_array($action, ['hold', 'release'], true) || $seatId <= 0 || $timeSlotId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing seat hold parameters.']);
    exit;
}

if ($action === 'hold') {
    $ok = holdSeatForToken($conn, $seatId, $timeSlotId, $token, 10);
} else {
    $ok = releaseSeatForToken($conn, $seatId, $timeSlotId, $token);
}

if (!$ok) {
    $stmt = $conn->prepare("SELECT SeatAvailability, HoldToken, HoldExpiresAt FROM seats WHERE Seat_ID = ? AND TimeSlot_ID = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $seatId, $timeSlotId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $status = normalizeSeatAvailability($row['SeatAvailability'] ?? 'Available');
            if ($status === 'Taken') {
                echo json_encode(['success' => false, 'error' => 'Seat is already booked.']);
                exit;
            }
            if ($status === 'Taken' && ($row['HoldToken'] ?? '') !== $token) {
                echo json_encode(['success' => false, 'error' => 'Seat is temporarily held by another user.']);
                exit;
            }
        }
    }

    echo json_encode(['success' => false, 'error' => 'Unable to update seat hold.']);
    exit;
}

echo json_encode(['success' => true]);
