<?php
session_start();
include("peakscinemas_database.php");
require_once(__DIR__ . "/auth_otp_helpers.php");
ensureOtpTableSchema($conn);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

if (!isset($_POST['credential'])) {
    header("Location: personal_info_form.php");
    exit;
}

// Decode the Google JWT token
$credential = $_POST['credential'];
$parts = explode('.', $credential);
if (count($parts) !== 3) {
    echo "<script>alert('Invalid Google token.'); window.location.href='index.php';</script>";
    exit;
}

$payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);

if (!$payload) {
    echo "<script>alert('Failed to decode Google token.'); window.location.href='index.php';</script>";
    exit;
}

$email  = $payload['email']   ?? '';
$name   = $payload['name']    ?? '';
$photo  = $payload['picture'] ?? '';

if (!$email) {
    echo "<script>alert('Could not get email from Google.'); window.location.href='index.php';</script>";
    exit;
}

// Check if user already exists
$stmt = $conn->prepare("SELECT Customer_ID, Name, ProfilePhoto FROM customer WHERE Email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // ── EXISTING USER: update photo then send OTP ──
    $user = $result->fetch_assoc();

    $stmt2 = $conn->prepare("UPDATE customer SET ProfilePhoto = ? WHERE Customer_ID = ?");
    $stmt2->bind_param("si", $photo, $user['Customer_ID']);
    $stmt2->execute();

    // Generate OTP
    $otp = rand(100000, 999999);
    createOtpForCustomer($conn, (int)$user['Customer_ID'], $otp, 300, 60);

    $_SESSION['pending_user_id']   = $user['Customer_ID'];
    $_SESSION['pending_user_name'] = $user['Name'];
    $_SESSION['pending_email']     = $email;
    $_SESSION['pending_photo']     = $photo;
    $_SESSION['show_form']         = 'otp';

    // Send OTP email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
        $mail->Username = 'peakscinema@gmail.com'; $mail->Password = 'pggs pvye frmk tmah';
        $mail->SMTPSecure = 'ssl'; $mail->Port = 465;
        $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema');
        $mail->addAddress($email); $mail->isHTML(true);
        $mail->Subject = "Your PeaksCinema Login Code";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:400px;margin:0 auto;'>
                <h2 style='color:#ff4d4d;'>Welcome back, {$user['Name']}! 🎬</h2>
                <p>Your one-time login code is:</p>
                <div style='background:#f4f4f4;padding:20px;text-align:center;border-radius:8px;margin:20px 0;'>
                    <h1 style='color:#ff4d4d;letter-spacing:8px;'>$otp</h1>
                </div>
                <p style='color:#888;font-size:12px;'>Expires in 5 minutes. If this wasn't you, ignore this email.</p>
            </div>
        ";
        $mail->send();
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        // Email failed — log in directly as fallback
        $_SESSION['user_id']       = $user['Customer_ID'];
        $_SESSION['user_name']     = $user['Name'];
        $_SESSION['profile_photo'] = $photo;
        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['pending_photo'], $_SESSION['show_form']);
        header("Location: home.php");
        exit;
    }

} else {
    // ── NEW USER: send OTP before activating account ──
    $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    // Insert the user first so we have a Customer_ID for OTP
    $stmt2 = $conn->prepare("INSERT INTO customer (Name, Email, Password, ProfilePhoto) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("ssss", $name, $email, $dummy_password, $photo);
    $stmt2->execute();
    $new_id = $conn->insert_id;

    // Generate OTP
    $otp = rand(100000, 999999);
    createOtpForCustomer($conn, (int)$new_id, $otp, 300, 60);

    // Store in session for OTP verification
    $_SESSION['pending_user_id']   = $new_id;
    $_SESSION['pending_user_name'] = $name;
    $_SESSION['pending_email']     = $email;
    $_SESSION['pending_photo']     = $photo;
    $_SESSION['show_form']         = 'otp';

    // Send OTP email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'peakscinema@gmail.com';
        $mail->Password   = 'pggs pvye frmk tmah';
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = 465;

        $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "Welcome to PeaksCinemas! Verify Your Email";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:400px;margin:0 auto;'>
                <h2 style='color:#ff4d4d;'>Welcome to PeaksCinemas, $name! 🎬</h2>
                <p>Thanks for signing up with Google. Please verify your email with the OTP below:</p>
                <div style='background:#f4f4f4;padding:20px;text-align:center;border-radius:8px;margin:20px 0;'>
                    <h1 style='color:#ff4d4d;letter-spacing:8px;'>$otp</h1>
                </div>
                <p style='color:#888;font-size:12px;'>This code expires in 5 minutes. If you did not sign up, ignore this email.</p>
            </div>
        ";
        $mail->send();

        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        // If email fails, clean up and abort
        $conn->query("DELETE FROM customer WHERE Customer_ID = $new_id");
        invalidateOtpForCustomer($conn, (int)$new_id);
        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['pending_photo'], $_SESSION['show_form']);
        echo "<script>alert('Could not send OTP email. Please try again.'); window.location.href='index.php';</script>";
        exit;
    }
}
?>
