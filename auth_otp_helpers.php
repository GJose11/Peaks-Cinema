<?php

function ensureOtpTableSchema(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $res = $conn->query("SHOW COLUMNS FROM otp");
    if (!$res) {
        return;
    }

    $columns = [];
    $hasPrimary = false;
    while ($row = $res->fetch_assoc()) {
        $field = $row['Field'] ?? '';
        if ($field === '') {
            continue;
        }
        $columns[$field] = $row;
        if (($row['Key'] ?? '') === 'PRI') {
            $hasPrimary = true;
        }
    }

    if (!isset($columns['otp_id'])) {
        $conn->query("ALTER TABLE otp ADD COLUMN otp_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        $hasPrimary = true;
    } elseif (!$hasPrimary) {
        // Existing otp_id values are not usable as unique keys, so rebuild the column cleanly.
        $conn->query("ALTER TABLE otp DROP COLUMN otp_id");
        $conn->query("ALTER TABLE otp ADD COLUMN otp_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        $hasPrimary = true;
    }

    if (!isset($columns['is_used'])) {
        $conn->query("ALTER TABLE otp ADD COLUMN is_used TINYINT(1) NOT NULL DEFAULT 0 AFTER otp_resend_after");
    }

    if (!isset($columns['used_at'])) {
        $conn->query("ALTER TABLE otp ADD COLUMN used_at DATETIME NULL DEFAULT NULL AFTER is_used");
    }
}

function invalidateOtpForCustomer(mysqli $conn, int $customerId): void
{
    ensureOtpTableSchema($conn);
    $stmt = $conn->prepare("
        UPDATE otp
        SET is_used = 1, used_at = NOW()
        WHERE customer_id = ? AND (is_used = 0 OR is_used IS NULL)
    ");
    if ($stmt) {
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
    }
}

function createOtpForCustomer(mysqli $conn, int $customerId, int $otp, int $expirySeconds = 300, int $resendSeconds = 60): bool
{
    ensureOtpTableSchema($conn);
    invalidateOtpForCustomer($conn, $customerId);

    // Calculate expiry and resend times using database NOW() to ensure consistency
    $stmt = $conn->prepare("
        INSERT INTO otp (customer_id, otp_code, otp_expiry, otp_resend_after, is_used)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), DATE_ADD(NOW(), INTERVAL ? SECOND), 0)
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("iiii", $customerId, $otp, $expirySeconds, $resendSeconds);
    return $stmt->execute();
}

function getLatestActiveOtp(mysqli $conn, int $customerId): ?array
{
    ensureOtpTableSchema($conn);
    // Include a check if it's already expired in the query using DB time
    $stmt = $conn->prepare("
        SELECT *, (otp_expiry < NOW()) as is_db_expired
        FROM otp
        WHERE customer_id = ? AND (is_used = 0 OR is_used IS NULL)
        ORDER BY created_at DESC, otp_id DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function markOtpUsed(mysqli $conn, int $otpId): void
{
    ensureOtpTableSchema($conn);
    $stmt = $conn->prepare("UPDATE otp SET is_used = 1, used_at = NOW() WHERE otp_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $otpId);
        $stmt->execute();
    }
}

