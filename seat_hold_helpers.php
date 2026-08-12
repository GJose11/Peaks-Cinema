<?php

function normalizeSeatAvailability($value): string
{
    $raw = strtolower(trim((string)$value));
    return match ($raw) {
        '0', 'taken', '2', 'held' => 'Taken',
        default => 'Available',
    };
}

function ensureSeatHoldSchema(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $res = $conn->query("SHOW COLUMNS FROM seats");
    if (!$res) {
        return;
    }

    $columns = [];
    while ($row = $res->fetch_assoc()) {
        $field = $row['Field'] ?? '';
        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    if (!isset($columns['HoldToken'])) {
        $conn->query("ALTER TABLE seats ADD COLUMN HoldToken VARCHAR(64) NULL DEFAULT NULL AFTER TimeSlot_ID");
    }

    if (!isset($columns['HoldExpiresAt'])) {
        $conn->query("ALTER TABLE seats ADD COLUMN HoldExpiresAt DATETIME NULL DEFAULT NULL AFTER HoldToken");
    }

    $seatAvailabilityInfo = $conn->query("SHOW COLUMNS FROM seats LIKE 'SeatAvailability'");
    $seatAvailabilityRow = $seatAvailabilityInfo ? $seatAvailabilityInfo->fetch_assoc() : null;
    $seatAvailabilityDbType = strtolower((string)($seatAvailabilityRow['Type'] ?? ''));

    if ($seatAvailabilityDbType !== '' && !str_contains($seatAvailabilityDbType, 'char') && !str_contains($seatAvailabilityDbType, 'text') && !str_contains($seatAvailabilityDbType, 'enum')) {
        $conn->query("ALTER TABLE seats MODIFY SeatAvailability VARCHAR(20) NOT NULL DEFAULT 'Available'");
    }

    $conn->query("
        UPDATE seats
        SET SeatAvailability = CASE
            WHEN LOWER(TRIM(CAST(SeatAvailability AS CHAR))) IN ('0', 'taken', '2', 'held') THEN 'Taken'
            ELSE 'Available'
        END
    ");
}

function getSeatHoldToken(): string
{
    if (empty($_SESSION['seat_hold_token'])) {
        $_SESSION['seat_hold_token'] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['seat_hold_token'];
}

function releaseExpiredSeatHolds(mysqli $conn): void
{
    ensureSeatHoldSchema($conn);
    $conn->query("
        UPDATE seats
        SET SeatAvailability = 'Available',
            HoldToken = NULL,
            HoldExpiresAt = NULL
        WHERE SeatAvailability = 'Taken'
          AND HoldExpiresAt IS NOT NULL
          AND HoldExpiresAt < NOW()
    ");
}

function holdSeatForToken(mysqli $conn, int $seatId, int $timeSlotId, string $token, int $minutes = 10): bool
{
    ensureSeatHoldSchema($conn);
    releaseExpiredSeatHolds($conn);

    $stmt = $conn->prepare("
        UPDATE seats
        SET SeatAvailability = 'Taken',
            HoldToken = ?,
            HoldExpiresAt = DATE_ADD(NOW(), INTERVAL ? MINUTE)
        WHERE Seat_ID = ?
          AND TimeSlot_ID = ?
          AND SeatType <> 'Empty'
          AND (
                SeatAvailability = 'Available'
                OR (SeatAvailability = 'Taken' AND HoldToken = ?)
              )
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("siiis", $token, $minutes, $seatId, $timeSlotId, $token);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

function releaseSeatForToken(mysqli $conn, int $seatId, int $timeSlotId, string $token): bool
{
    ensureSeatHoldSchema($conn);
    $stmt = $conn->prepare("
        UPDATE seats
        SET SeatAvailability = 'Available',
            HoldToken = NULL,
            HoldExpiresAt = NULL
        WHERE Seat_ID = ?
          AND TimeSlot_ID = ?
          AND SeatAvailability = 'Taken'
          AND HoldToken = ?
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("iis", $seatId, $timeSlotId, $token);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

function touchSeatHolds(mysqli $conn, int $timeSlotId, array $seatIds, string $token, int $minutes = 10): void
{
    ensureSeatHoldSchema($conn);
    releaseExpiredSeatHolds($conn);

    if (empty($seatIds)) {
        return;
    }

    $seatIds = array_values(array_filter(array_map('intval', $seatIds), fn($id) => $id > 0));
    if (empty($seatIds)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($seatIds), '?'));

    $stmt = $conn->prepare("
        UPDATE seats
        SET HoldExpiresAt = DATE_ADD(NOW(), INTERVAL ? MINUTE),
            HoldToken = ?,
            SeatAvailability = 'Taken'
        WHERE TimeSlot_ID = ?
          AND Seat_ID IN ($placeholders)
          AND SeatAvailability = 'Taken'
          AND HoldToken = ?
    ");
    if (!$stmt) {
        return;
    }

    $types = "isi" . str_repeat('i', count($seatIds)) . "s";
    $params = array_merge([$minutes, $token, $timeSlotId], $seatIds, [$token]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
}
