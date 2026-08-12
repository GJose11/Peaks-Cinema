<?php
date_default_timezone_set('Asia/Manila');
include __DIR__ . '/../peakscinemas_database.php';
require_once __DIR__ . '/../auth_otp_helpers.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    } else {
        echo "[OK] $msg\n";
    }
}

// 1. Create a dummy customer if not exists
$conn->query("INSERT IGNORE INTO customer (Customer_ID, Name, Email, Password) VALUES (9999, 'Test User', 'test_otp@example.com', 'pass')");
$customerId = 9999;

// 2. Test OTP creation with future expiry
$otpCode = 123456;
$ok = createOtpForCustomer($conn, $customerId, $otpCode, 300, 60);
assert_true($ok, "OTP created successfully");

// 3. Verify it is NOT expired
$otpRow = getLatestActiveOtp($conn, $customerId);
assert_true($otpRow !== null, "OTP row fetched");
assert_true($otpRow['otp_code'] == $otpCode, "OTP code matches");
assert_true((int)$otpRow['is_db_expired'] === 0, "OTP is NOT expired initially");

// 4. Test OTP creation with past expiry (simulate expiration)
$expiredOtpCode = 654321;
// We'll manually insert an expired one since createOtpForCustomer uses DATE_ADD(NOW(), ...)
$conn->query("UPDATE otp SET is_used = 1 WHERE customer_id = $customerId"); // invalidate previous
$conn->query("INSERT INTO otp (customer_id, otp_code, otp_expiry, otp_resend_after, is_used) VALUES ($customerId, '$expiredOtpCode', DATE_SUB(NOW(), INTERVAL 10 SECOND), DATE_SUB(NOW(), INTERVAL 10 SECOND), 0)");

$otpRow = getLatestActiveOtp($conn, $customerId);
assert_true($otpRow !== null, "Expired OTP row fetched");
assert_true($otpRow['otp_code'] == $expiredOtpCode, "Expired OTP code matches");
assert_true((int)$otpRow['is_db_expired'] === 1, "OTP is correctly marked as EXPIRED by DB");

// Cleanup
$conn->query("DELETE FROM otp WHERE customer_id = $customerId");
$conn->query("DELETE FROM customer WHERE Customer_ID = $customerId");

echo "OTP tests passed.\n";
?>
