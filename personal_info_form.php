<?php
session_start();
include("peakscinemas_database.php");
require_once(__DIR__ . "/auth_otp_helpers.php");
ensureOtpTableSchema($conn);

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (!isset($_SESSION['pending_user_id'])) {
        unset($_SESSION['show_form']);
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["customer_info"])) {
    function input_cleanup($data) {
        $data = trim($data); $data = stripslashes($data); $data = htmlspecialchars($data); return $data;
    }
    $firstName = $lastName = $email = $password = $confirmPassword = $countryCode = $phoneNumber = "";
    if (!empty($_POST["lastName"]))  { $lastName  = input_cleanup($_POST['lastName']);  if (!preg_match("/^[a-zA-Z-' ]*$/", $lastName))  { echo "<script>alert('Invalid last name.');</script>";  exit(); } }
    if (!empty($_POST["firstName"])) { $firstName = input_cleanup($_POST['firstName']); if (!preg_match("/^[a-zA-Z-' ]*$/", $firstName)) { echo "<script>alert('Invalid first name.');</script>"; exit(); } }
    if (!empty($_POST["email"]))     { $email     = input_cleanup($_POST['email']);     if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo "<script>alert('Invalid email format.');</script>"; exit(); } }
    if (!empty($_POST["password"]))  {
        $passwordPlain = input_cleanup($_POST['password']);
        $confirmPassword = input_cleanup($_POST['confirmPassword']);
        if ($passwordPlain !== $confirmPassword) { echo "<script>alert('Passwords do not match.');</script>"; exit(); }
        else { $password = password_hash($passwordPlain, PASSWORD_DEFAULT); }
    }
    $countryCode = input_cleanup($_POST['countryCode']);
    $phoneNumber = input_cleanup($_POST['phoneNumber']);
    if ($firstName && $lastName && $email && $password) {
        $check = mysqli_query($conn, "SELECT * FROM customer WHERE Email = '$email'");
        if (mysqli_num_rows($check) > 0) { echo "<script>alert('Email already exists. Please log in.');</script>"; }
        else {
            $sql = "INSERT INTO customer (Name, Email, Password, CountryCode, PhoneNumber) VALUES ('$firstName $lastName', '$email', '$password', '$countryCode', '$phoneNumber')";
            if (mysqli_query($conn, $sql)) { echo "<script>alert('Sign Up Successful! Please log in now.');</script>"; $_SESSION['show_form'] = 'login'; }
            else { echo "<script>alert('Database error: " . mysqli_error($conn) . "');</script>"; }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login_user"])) {
    $email = trim($_POST["loginEmail"]); $password = trim($_POST["loginPassword"]);
    $stmt = $conn->prepare("SELECT Customer_ID, Name, Password FROM customer WHERE Email = ?");
    $stmt->bind_param("s", $email); $stmt->execute(); $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['Password'])) {
            $otp = rand(100000, 999999);
            createOtpForCustomer($conn, (int)$user['Customer_ID'], $otp, 300, 60);
            $_SESSION['pending_user_id']   = $user['Customer_ID'];
            $_SESSION['pending_user_name'] = $user['Name'];
            $_SESSION['pending_email']     = $email;
            $_SESSION['show_form']         = 'otp';
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
                $mail->Username = 'peakscinema@gmail.com'; $mail->Password = 'pggs pvye frmk tmah';
                $mail->SMTPSecure = 'ssl'; $mail->Port = 465;
                $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema');
                $mail->addAddress($email); $mail->isHTML(true);
                $mail->Subject = "Your PeaksCinema Login Code";
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <style>
                        body { margin: 0; padding: 0; font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #0f0f0f; }
                        .container { max-width: 600px; margin: 40px auto; background: #1a1a1a; border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08); }
                        .header { background: linear-gradient(135deg, #ff4d4d 0%, #e03c3c 100%); padding: 40px 30px; text-align: center; }
                        .header-icon { font-size: 48px; margin-bottom: 12px; }
                        .header h1 { margin: 0; color: #ffffff; font-size: 28px; font-weight: 800; letter-spacing: -0.5px; }
                        .header p { margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 14px; font-weight: 500; }
                        .content { padding: 40px 30px; background: #1a1a1a; }
                        .greeting { font-size: 20px; font-weight: 700; color: #F9F9F9; margin: 0 0 16px; }
                        .message { font-size: 15px; color: rgba(249,249,249,0.6); line-height: 1.6; margin: 0 0 32px; }
                        .otp-box { background: #0f0f0f; border: 2px solid rgba(255,77,77,0.2); border-radius: 12px; padding: 40px 30px; text-align: center; margin: 0 0 32px; }
                        .otp-label { font-size: 11px; color: rgba(249,249,249,0.4); text-transform: uppercase; letter-spacing: 2px; margin: 0 0 16px; font-weight: 700; }
                        .otp-code { font-size: 48px; font-weight: 800; color: #ff4d4d; letter-spacing: 12px; margin: 0; font-family: 'Courier New', monospace; text-shadow: 0 0 20px rgba(255,77,77,0.3); }
                        .expiry { font-size: 13px; color: rgba(249,249,249,0.5); text-align: center; margin: 0 0 24px; padding: 16px; background: rgba(255,77,77,0.05); border-radius: 8px; border: 1px solid rgba(255,77,77,0.1); }
                        .expiry strong { color: #ff4d4d; font-weight: 700; }
                        .footer { background: #0f0f0f; padding: 24px 30px; text-align: center; border-top: 1px solid rgba(255,255,255,0.06); }
                        .footer p { margin: 0 0 6px; font-size: 12px; color: rgba(249,249,249,0.3); line-height: 1.6; }
                        .footer p:last-child { margin: 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <div class='header-icon'>🎬</div>
                            <h1>Peak's Cinema</h1>
                            <p>Login Verification</p>
                        </div>
                        <div class='content'>
                            <p class='greeting'>Welcome back! 👋</p>
                            <p class='message'>Your one-time login code is ready. Enter this code to access your account:</p>
                            <div class='otp-box'>
                                <p class='otp-label'>Verification Code</p>
                                <p class='otp-code'>$otp</p>
                            </div>
                            <p class='expiry'>⏱ This code expires in <strong>5 minutes</strong>. If you didn't request this, please ignore this email.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message from Peak's Cinema</p>
                            <p>Please do not reply to this email</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                $mail->send();
                echo "<script>alert('OTP sent to your email. Please enter it to continue.');</script>";
            } catch (Exception $e) {
                echo "<script>alert('Mailer Error: " . addslashes($mail->ErrorInfo) . "');</script>";
                invalidateOtpForCustomer($conn, (int)$user['Customer_ID']);
                unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']);
            }
        } else { echo "<script>alert('Invalid password.');</script>"; $_SESSION['show_form'] = 'login'; }
    } else { echo "<script>alert('Email not found.');</script>"; $_SESSION['show_form'] = 'login'; }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["verify_otp"])) {
    $userOtp = trim($_POST['otp']);
    if (empty($userOtp)) { echo "<script>alert('Please enter the OTP code.');</script>"; $_SESSION['show_form'] = 'otp'; }
    elseif (isset($_SESSION['pending_user_id'])) {
        $uid = $_SESSION['pending_user_id'];
        $otpRow = getLatestActiveOtp($conn, (int)$uid);
        if (!$otpRow) { echo "<script>alert('No OTP found. Please log in again.');</script>"; unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']); }
        elseif (strtotime($otpRow['otp_expiry']) < time()) { echo "<script>alert('OTP expired. Please log in again.');</script>"; markOtpUsed($conn, (int)$otpRow['otp_id']); unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']); }
        elseif ($userOtp == $otpRow['otp_code']) {
            $_SESSION['user_id']   = $_SESSION['pending_user_id'];
            $_SESSION['user_name'] = $_SESSION['pending_user_name'];
            if (!empty($_SESSION['pending_photo'])) $_SESSION['profile_photo'] = $_SESSION['pending_photo'];
            markOtpUsed($conn, (int)$otpRow['otp_id']);
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['pending_photo'], $_SESSION['show_form']);
            header("Location: home.php"); exit();
        } else { echo "<script>alert('Invalid OTP. Please try again.');</script>"; $_SESSION['show_form'] = 'otp'; }
    } else { echo "<script>alert('Session expired. Please log in again.');</script>"; unset($_SESSION['show_form']); }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["resend_otp"])) {
    if (!isset($_SESSION['pending_user_id'])) { echo "<script>alert('Session expired. Please log in again.');</script>"; unset($_SESSION['show_form']); }
    else {
        $uid = $_SESSION['pending_user_id'];
        $otpRow = getLatestActiveOtp($conn, (int)$uid);
        if ($otpRow && strtotime($otpRow['otp_resend_after']) > time()) {
            $wait = strtotime($otpRow['otp_resend_after']) - time();
            echo "<script>alert('Please wait $wait second(s) before requesting a new OTP.');</script>";
            $_SESSION['show_form'] = 'otp';
        } else {
            $otp = rand(100000, 999999);
            createOtpForCustomer($conn, (int)$uid, $otp, 300, 60);
            $_SESSION['show_form'] = 'otp'; $email = $_SESSION['pending_email'];
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
                $mail->Username = 'peakscinema@gmail.com'; $mail->Password = 'pggs pvye frmk tmah';
                $mail->SMTPSecure = 'ssl'; $mail->Port = 465;
                $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema');
                $mail->addAddress($email); $mail->isHTML(true);
                $mail->Subject = "Your New PeaksCinema Login Code";
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <style>
                        body { margin: 0; padding: 0; font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #0f0f0f; }
                        .container { max-width: 600px; margin: 40px auto; background: #1a1a1a; border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08); }
                        .header { background: linear-gradient(135deg, #ff4d4d 0%, #e03c3c 100%); padding: 40px 30px; text-align: center; }
                        .header-icon { font-size: 48px; margin-bottom: 12px; }
                        .header h1 { margin: 0; color: #ffffff; font-size: 28px; font-weight: 800; letter-spacing: -0.5px; }
                        .header p { margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 14px; font-weight: 500; }
                        .content { padding: 40px 30px; background: #1a1a1a; }
                        .greeting { font-size: 20px; font-weight: 700; color: #F9F9F9; margin: 0 0 16px; }
                        .message { font-size: 15px; color: rgba(249,249,249,0.6); line-height: 1.6; margin: 0 0 32px; }
                        .otp-box { background: #0f0f0f; border: 2px solid rgba(255,77,77,0.2); border-radius: 12px; padding: 40px 30px; text-align: center; margin: 0 0 32px; }
                        .otp-label { font-size: 11px; color: rgba(249,249,249,0.4); text-transform: uppercase; letter-spacing: 2px; margin: 0 0 16px; font-weight: 700; }
                        .otp-code { font-size: 48px; font-weight: 800; color: #ff4d4d; letter-spacing: 12px; margin: 0; font-family: 'Courier New', monospace; text-shadow: 0 0 20px rgba(255,77,77,0.3); }
                        .expiry { font-size: 13px; color: rgba(249,249,249,0.5); text-align: center; margin: 0 0 24px; padding: 16px; background: rgba(255,77,77,0.05); border-radius: 8px; border: 1px solid rgba(255,77,77,0.1); }
                        .expiry strong { color: #ff4d4d; font-weight: 700; }
                        .footer { background: #0f0f0f; padding: 24px 30px; text-align: center; border-top: 1px solid rgba(255,255,255,0.06); }
                        .footer p { margin: 0 0 6px; font-size: 12px; color: rgba(249,249,249,0.3); line-height: 1.6; }
                        .footer p:last-child { margin: 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <div class='header-icon'>🎬</div>
                            <h1>Peak's Cinema</h1>
                            <p>New Login Code Requested</p>
                        </div>
                        <div class='content'>
                            <p class='greeting'>Here's your new code! 🔄</p>
                            <p class='message'>Your new one-time login code is ready. Enter this code to access your account:</p>
                            <div class='otp-box'>
                                <p class='otp-label'>Verification Code</p>
                                <p class='otp-code'>$otp</p>
                            </div>
                            <p class='expiry'>⏱ This code expires in <strong>5 minutes</strong>. If you didn't request this, please ignore this email.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message from Peak's Cinema</p>
                            <p>Please do not reply to this email</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                $mail->send(); echo "<script>alert('A new OTP has been sent to your email.');</script>";
            } catch (Exception $e) { echo "<script>alert('Mailer Error: " . addslashes($mail->ErrorInfo) . "');</script>"; }
        }
    }
}

mysqli_close($conn);

if (isset($_SESSION['show_form']) && $_SESSION['show_form'] === 'otp') {
    $activeForm = (isset($_SESSION['pending_email']) && isset($_SESSION['pending_user_id'])) ? 'otp' : 'signup';
    if ($activeForm === 'signup') unset($_SESSION['show_form']);
} elseif (isset($_SESSION['show_form'])) {
    $activeForm = $_SESSION['show_form'];
} else {
    $activeForm = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'signup' : 'login';
}

define('GOOGLE_CLIENT_ID', '180356811024-djv9cq9s2975b22r89dndvb1cr9ico80.apps.googleusercontent.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<script src="https://accounts.google.com/gsi/client" async defer></script>
<title>Peak's Cinema — Sign In</title>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: #0f0f0f;
    color: #F9F9F9;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
body::before {
    content: '';
    position: fixed; inset: 0;
    background: url('movie-background-collage.jpg') center/cover no-repeat;
    opacity: 0.12; z-index: 0; pointer-events: none;
}
body::after {
    content: '';
    position: fixed; inset: 0;
    background: radial-gradient(ellipse at center, transparent 10%, rgba(15,15,15,0.55) 60%, #0f0f0f 100%);
    z-index: 1; pointer-events: none;
}

/* ── Header ── */
header {
    background: #1C1C1C;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 20px;
    position: fixed; top: 0; left: 0; width: 100%;
    height: 50px; z-index: 1000;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    transition: transform 0.3s ease, all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    overflow: visible;
}
.brand-logo-wrap {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    flex-shrink: 0;
}
.brand-logo-wrap:hover { transform: scale(1.05); }
.brand-logo {
    height: 34px;
    width: auto;
    filter: invert(1);
    display: block;
}
.profile-btn {
    background: #F9F9F9;
    border: none;
    border-radius: 50%;
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    overflow: hidden;
    padding: 0;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    flex-shrink: 0;
}
.profile-btn:hover {
    transform: scale(1.08);
    box-shadow: 0 4px 16px rgba(255,255,255,0.2);
}
.profile-initials {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    color: #fff;
}

/* ── Page layout ── */
.page-wrap {
    position: relative; z-index: 10;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 70px 20px 40px;
}

.auth-col {
    width: 100%;
    max-width: 380px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
}

/* Page heading above card */
.auth-heading {
    text-align: center;
    margin-bottom: 20px;
}
.auth-eyebrow {
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 3px; text-transform: uppercase;
    color: #ff4d4d; margin-bottom: 6px;
}
.auth-heading h1 {
    font-size: 1.5rem; font-weight: 800;
    color: #F9F9F9;
}

/* ── Auth card ── */
.auth-card {
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px;
    padding: 28px 24px 24px;
    width: 100%;
    animation: fadeUp 0.35s ease;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

.card-title {
    font-size: 1rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: rgba(249,249,249,0.45);
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

/* Fields */
.field { margin-bottom: 14px; }
.field label {
    display: block;
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    color: rgba(249,249,249,0.4);
    margin-bottom: 6px;
}
.field input {
    width: 100%; padding: 10px 13px;
    border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.1);
    background: #222;
    color: #F9F9F9;
    font-family: 'Outfit', sans-serif; font-size: 0.88rem;
    outline: none; transition: border-color 0.2s;
}
.field input:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
.field input::placeholder { color: rgba(249,249,249,0.2); }
.field input:-webkit-autofill,
.field input:-webkit-autofill:focus {
    -webkit-text-fill-color: #F9F9F9 !important;
    -webkit-box-shadow: 0 0 0 1000px #222 inset !important;
    caret-color: #F9F9F9;
}

.field-row { display: flex; gap: 10px; }
.field-row .field { flex: 1; margin-bottom: 0; }
.field-row .field:first-child { flex: 0 0 80px; }

/* Optional label */
.opt { opacity: 0.35; font-size: 0.6rem; text-transform: none; letter-spacing: 0; }

/* Submit button */
.btn-submit {
    display: block; width: 100%;
    padding: 12px;
    border-radius: 9px; border: none;
    background: #ff4d4d; color: #fff;
    font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    margin-top: 6px;
}
.btn-submit:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

/* Switch link */
.switch-link {
    background: none; border: none;
    color: rgba(249,249,249,0.35);
    font-family: 'Outfit', sans-serif; font-size: 0.78rem;
    cursor: pointer; margin-top: 14px;
    display: block; width: 100%; text-align: center;
    transition: color 0.2s; padding: 0;
}
.switch-link:hover { color: rgba(249,249,249,0.75); }
.switch-link span { color: #ff6b6b; font-weight: 600; }

/* Divider */
.divider {
    display: flex; align-items: center; gap: 10px;
    margin: 18px 0 14px;
    color: rgba(255,255,255,0.2);
    font-size: 0.7rem; letter-spacing: 1px; text-transform: uppercase;
}
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.08); }

/* Google button */
.google-btn-wrap { 
    display: flex; 
    justify-content: center; 
}
.google-btn-wrap > div { 
    border-radius: 9px !important; 
    overflow: hidden; 
}
/* When notice is added, remove centering */
.google-btn-wrap:has(.mobile-google-notice),
.google-btn-wrap:has(.pwa-notice) {
    display: block !important;
}
/* v3.0 - fix left spacing issue */

/* PWA notice */
.pwa-notice {
    background: rgba(255,152,0,0.08);
    border: 1px solid rgba(255,152,0,0.2);
    border-left: 3px solid #ff9800;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 0.78rem;
    color: rgba(249,249,249,0.7);
    line-height: 1.5;
    text-align: center;
}
.pwa-notice strong { color: #ffb74d; font-weight: 700; display: block; margin-bottom: 4px; }
.btn-open-browser {
    display: inline-block;
    margin-top: 10px;
    padding: 8px 16px;
    border-radius: 7px;
    border: 1px solid rgba(255,152,0,0.3);
    background: rgba(255,152,0,0.12);
    color: #ffb74d;
    font-family: 'Outfit', sans-serif;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-open-browser:hover {
    background: rgba(255,152,0,0.2);
    border-color: rgba(255,152,0,0.5);
    transform: translateY(-1px);
}

/* Mobile Google Sign-In notice */
.mobile-google-notice {
    background: rgba(66,133,244,0.08);
    border: 1px solid rgba(66,133,244,0.2);
    border-radius: 8px;
    padding: 14px 18px;
    font-size: 0.78rem;
    color: rgba(249,249,249,0.75);
    line-height: 1.65;
    text-align: left;
    word-spacing: 0.15em;
    letter-spacing: 0.01em;
    width: 100%;
    box-sizing: border-box;
}
.mobile-google-notice strong { 
    color: #4285f4; 
    font-weight: 700; 
    font-size: 0.82rem;
}
.mobile-google-notice br {
    display: block;
    content: "";
    margin: 5px 0;
}
/* v3.0 - fix left spacing issue */

/* OTP card extras */
.otp-hint {
    font-size: 0.82rem; color: rgba(249,249,249,0.4);
    text-align: center; line-height: 1.6; margin-bottom: 16px;
}
.otp-resend {
    text-align: center; margin-top: 14px;
    font-size: 0.78rem; color: rgba(249,249,249,0.35);
}
.otp-resend button {
    background: none; border: none;
    color: #ff6b6b; font-family: 'Outfit', sans-serif;
    font-size: 0.78rem; font-weight: 600;
    cursor: pointer; padding: 0; margin-left: 4px;
    transition: color 0.2s;
}
.otp-resend button:hover { color: #ff4d4d; }
.otp-small {
    display: block; text-align: center;
    font-size: 0.65rem; color: rgba(249,249,249,0.2);
    margin-top: 8px;
}

@media (max-width: 768px) {
    header {
        height: 60px;
        padding: 0 12px;
    }
    .brand-logo-wrap { flex-shrink: 0; }
    .brand-logo { height: 36px; }
    .profile-btn {
        width: 38px;
        height: 38px;
    }
    .profile-initials {
        font-size: 0.75rem;
    }
    .page-wrap { padding-top: 84px; }
}

@media (max-width: 480px) {
    header {
        padding: 0 10px;
    }
    .brand-logo { height: 32px; }
    .profile-btn {
        width: 36px;
        height: 36px;
    }
    .page-wrap { padding-top: 78px; }
}

@media (max-width: 600px) {
    .auth-heading h1 { font-size: 1.3rem; }
    .auth-card { padding: 24px 20px 20px; }
    .field input { padding: 12px 14px; font-size: 1rem; }
    .btn-submit { padding: 14px; font-size: 1rem; min-height: 48px; }
    .field-row { flex-direction: column; gap: 14px; }
    .field-row .field:first-child { flex: none; width: 100%; }
    .mobile-google-notice {
        padding: 16px 20px !important;
        font-size: 0.8rem !important;
        line-height: 1.7 !important;
    }
    .mobile-google-notice strong {
        font-size: 0.85rem !important;
        margin-bottom: 10px !important;
    }
}
</style>
</head>
<body>

<header>
    <a href="index.php" class="brand-logo-wrap">
        <img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo">
    </a>
    <button type="button" class="profile-btn" title="Guest Profile">
        <div class="profile-initials">?</div>
    </button>
</header>

<div class="page-wrap">
<div class="auth-col">

    <!-- SIGN UP -->
    <div id="signupSection" style="width:100%;<?= $activeForm !== 'signup' ? 'display:none;' : '' ?>">
        <div class="auth-heading">
            <p class="auth-eyebrow">Peak's Cinema</p>
            <h1>Create Account</h1>
        </div>
        <div class="auth-card">
            <p class="card-title">👤 Sign Up</p>
            <form method="POST">
                <div class="field-row" style="margin-bottom:14px;">
                    <div class="field">
                        <label>First Name</label>
                        <input type="text" name="firstName" placeholder="Juan">
                    </div>
                    <div class="field" style="flex:1;">
                        <label>Last Name</label>
                        <input type="text" name="lastName" placeholder="Dela Cruz">
                    </div>
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com">
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create a password">
                </div>
                <div class="field">
                    <label>Confirm Password</label>
                    <input type="password" name="confirmPassword" placeholder="Re-enter your password">
                </div>
                <div class="field">
                    <label>Phone Number <span class="opt">(optional)</span></label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="countryCode" placeholder="+63" style="width:68px;flex-shrink:0;">
                        <input type="tel" name="phoneNumber" placeholder="9XXXXXXXXX" style="flex:1;">
                    </div>
                </div>
                <button type="submit" name="customer_info" class="btn-submit">Create Account →</button>
            </form>
            <button class="switch-link" id="showLogin">Already have an account? <span>Sign in</span></button>

            <div class="divider">or</div>
            <div class="google-btn-wrap">
                <div id="g_id_onload"
                     data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                     data-login_uri="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                     data-auto_prompt="false"></div>
                <div class="g_id_signin"
                     data-type="standard" data-shape="rectangular"
                     data-theme="filled_black" data-text="continue_with"
                     data-size="large" data-logo_alignment="left" data-width="332"></div>
            </div>
        </div>
    </div>

    <!-- LOG IN -->
    <div id="loginSection" style="width:100%;<?= $activeForm !== 'login' ? 'display:none;' : '' ?>">
        <div class="auth-heading">
            <p class="auth-eyebrow">Peak's Cinema</p>
            <h1>Welcome Back</h1>
        </div>
        <div class="auth-card">
            <p class="card-title">🔑 Log In</p>
            <form method="POST">
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="loginEmail" placeholder="you@example.com">
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="loginPassword" placeholder="Your password">
                </div>
                <button type="submit" name="login_user" class="btn-submit">Sign In →</button>
            </form>
            <button class="switch-link" id="showSignup">Don't have an account? <span>Sign up</span></button>

            <div class="divider">or</div>
            <div class="google-btn-wrap">
                <div id="g_id_onload_login"
                     data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                     data-login_uri="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                     data-auto_prompt="false"></div>
                <div class="g_id_signin"
                     data-type="standard" data-shape="rectangular"
                     data-theme="filled_black" data-text="signin_with"
                     data-size="large" data-logo_alignment="left" data-width="332"></div>
            </div>
        </div>
    </div>

    <!-- OTP -->
    <div id="otpSection" style="width:100%;<?= $activeForm !== 'otp' ? 'display:none;' : '' ?>">
        <div class="auth-heading">
            <p class="auth-eyebrow">Peak's Cinema</p>
            <h1>Verify Email</h1>
        </div>
        <div class="auth-card">
            <p class="card-title">🔐 Enter OTP</p>
            <p class="otp-hint">We've sent a 6-digit code to your email.<br>Enter it below to continue.</p>
            <form method="POST" action="personal_info_form.php">
                <div class="field">
                    <label>OTP Code</label>
                    <input type="text" name="otp" maxlength="6" placeholder="000000"
                           style="text-align:center;font-size:1.4rem;font-weight:800;letter-spacing:8px;">
                </div>
                <button type="submit" name="verify_otp" class="btn-submit">Verify &amp; Continue →</button>
            </form>
            <form method="POST" action="personal_info_form.php">
                <p class="otp-resend">
                    Didn't receive the code?
                    <button type="submit" name="resend_otp">Resend</button>
                </p>
            </form>
            <small class="otp-small">Check your spam or promotions folder if you don't see it.</small>
        </div>
    </div>

</div>
</div>

<script>
    // Detect PWA mode
    const isPWA = window.matchMedia('(display-mode: standalone)').matches || 
                  window.navigator.standalone === true;

    // Wait for Google script to load and check if button renders
    window.addEventListener('load', () => {
        setTimeout(() => {
            // Check if Google button rendered (look for iframe)
            const hasGoogleBtn = document.querySelector('.google-btn-wrap iframe');
            
            if (!hasGoogleBtn) {
                // Google button failed to render - add helpful message below
                document.querySelectorAll('.google-btn-wrap').forEach(wrap => {
                    const notice = document.createElement('div');
                    notice.className = isPWA ? 'pwa-notice' : 'mobile-google-notice';
                    notice.style.marginTop = '12px';
                    
                    const isHTTP = window.location.protocol === 'http:';
                    
                    if (isPWA) {
                        notice.innerHTML = `
                            <strong>📱 PWA Mode Detected</strong>
                            Google Sign-In requires a browser. Please use email/password login, or open in your browser.
                            <br><a href="${window.location.href}" target="_blank" class="btn-open-browser">🌐 Open in Browser</a>
                        `;
                    } else if (isHTTP) {
                        notice.innerHTML = `
                            <strong>🔒 HTTPS Required</strong><br><br>
                            Google Sign-In needs HTTPS on mobile devices.<br><br>
                            Please use email/password login instead.
                        `;
                    } else {
                        notice.innerHTML = `
                            <strong>📱 Google Sign-In Unavailable</strong>
                            Please use email/password login above. Google Sign-In may be blocked by your browser settings.
                        `;
                    }
                    
                    wrap.appendChild(notice);
                });
            }
        }, 1500);
    });

    document.getElementById('showLogin')?.addEventListener('click', () => {
        document.getElementById('signupSection').style.display = 'none';
        document.getElementById('loginSection').style.display  = 'block';
    });
    document.getElementById('showSignup')?.addEventListener('click', () => {
        document.getElementById('loginSection').style.display  = 'none';
        document.getElementById('signupSection').style.display = 'block';
    });
</script>
</body>
</html>
