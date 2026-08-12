<?php

function ensureCancellationRequestSchema(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS booking_cancellation_requests (
            Request_ID INT AUTO_INCREMENT PRIMARY KEY,
            BookingRef VARCHAR(100) NOT NULL,
            Customer_ID INT NOT NULL,
            TimeSlot_ID INT NOT NULL,
            Reason TEXT NOT NULL,
            Status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            RequestedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ReviewedAt DATETIME NULL DEFAULT NULL,
            AdminNote VARCHAR(255) NULL DEFAULT NULL,
            INDEX idx_bcr_customer_requested (Customer_ID, RequestedAt),
            INDEX idx_bcr_status_requested (Status, RequestedAt),
            INDEX idx_bcr_booking_status (BookingRef, Status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!schemaHasColumn($conn, 'booking_cancellation_requests', 'AdminNote')) {
        $conn->query("ALTER TABLE booking_cancellation_requests ADD COLUMN AdminNote VARCHAR(255) NULL DEFAULT NULL AFTER ReviewedAt");
    }

    if (!schemaHasColumn($conn, 'ticket', 'CancelledAt')) {
        $conn->query("ALTER TABLE ticket ADD COLUMN CancelledAt DATETIME NULL DEFAULT NULL");
    }
}

function schemaHasColumn(mysqli $conn, string $table, string $column): bool
{
    $tableEscaped = $conn->real_escape_string($table);
    $columnEscaped = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'");
    return (bool) ($result && $result->num_rows > 0);
}

function addCustomerNotification(mysqli $conn, int $customerId, string $title, string $message, string $type = 'general'): void
{
    $stmt = $conn->prepare("INSERT INTO notifications(Customer_ID, Title, Message, Type) VALUES(?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $customerId, $title, $message, $type);
        $stmt->execute();
    }
}

function getRecentCancellationRequestCount(mysqli $conn, int $customerId, int $days = 7): int
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM booking_cancellation_requests
         WHERE Customer_ID = ?
           AND RequestedAt >= DATE_SUB(NOW(), INTERVAL ? DAY)"
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("ii", $customerId, $days);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}

function getPendingCancellationRequestsByBooking(mysqli $conn, int $customerId): array
{
    $stmt = $conn->prepare(
        "SELECT Request_ID, BookingRef, Reason, RequestedAt
         FROM booking_cancellation_requests
         WHERE Customer_ID = ?
           AND Status = 'pending'
         ORDER BY RequestedAt DESC"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $map[$row['BookingRef']] = $row;
    }

    return $map;
}
