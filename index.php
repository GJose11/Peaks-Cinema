<?php
date_default_timezone_set('Asia/Manila');
session_start();
include("peakscinemas_database.php");
require_once(__DIR__ . "/auth_otp_helpers.php");
ensureOtpTableSchema($conn);

// Already logged in — skip straight to home
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// ── Sign Up ──────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["customer_info"])) {
    function input_cleanup($d) { return htmlspecialchars(stripslashes(trim($d))); }
    $firstName = $lastName = $email = $password = $countryCode = $phoneNumber = "";
    if (!empty($_POST["lastName"]))  { $lastName  = input_cleanup($_POST['lastName']);  if (!preg_match("/^[a-zA-Z-' ]*$/", $lastName))  { $formError = "Invalid last name."; } }
    if (!empty($_POST["firstName"])) { $firstName = input_cleanup($_POST['firstName']); if (!preg_match("/^[a-zA-Z-' ]*$/", $firstName)) { $formError = "Invalid first name."; } }
    if (!empty($_POST["email"]))     { $email     = input_cleanup($_POST['email']);     if (!filter_var($email, FILTER_VALIDATE_EMAIL))  { $formError = "Invalid email format."; } }
    if (!empty($_POST["password"])) {
        $passwordPlain   = input_cleanup($_POST['password']);
        $confirmPassword = input_cleanup($_POST['confirmPassword']);
        if ($passwordPlain !== $confirmPassword) { $formError = "Passwords do not match."; }
        else { $password = password_hash($passwordPlain, PASSWORD_DEFAULT); }
    }
    $countryCode = input_cleanup($_POST['countryCode'] ?? '');
    $phoneNumber = input_cleanup($_POST['phoneNumber'] ?? '');
    if (!isset($formError) && $firstName && $lastName && $email && $password) {
        $check = mysqli_query($conn, "SELECT * FROM customer WHERE Email = '$email'");
        if (mysqli_num_rows($check) > 0) { $formError = "Email already exists. Please log in."; $activeForm = 'signup'; }
        else {
            $sql = "INSERT INTO customer (Name, Email, Password, CountryCode, PhoneNumber) VALUES ('$firstName $lastName', '$email', '$password', '$countryCode', '$phoneNumber')";
            if (mysqli_query($conn, $sql)) { $formSuccess = "Account created! You can now sign in."; $activeForm = 'login'; }
            else { $formError = "Database error. Please try again."; $activeForm = 'signup'; }
        }
    } else {
        if (!isset($formError)) $formError = "Please fill in all required fields.";
        $activeForm = 'signup';
    }
}

// ── Log In ───────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login_user"])) {
    $email    = trim($_POST["loginEmail"]    ?? '');
    $password = trim($_POST["loginPassword"] ?? '');
    $stmt = $conn->prepare("SELECT Customer_ID, Name, Password FROM customer WHERE Email = ?");
    $stmt->bind_param("s", $email); $stmt->execute();
    $result = $stmt->get_result();
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
                $mail->Body    = "
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
                            <p class='expiry'>⏱ This code expires in <strong>10 minutes</strong>. If you didn't request this, please ignore this email.</p>
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
                $activeForm = 'otp';
            } catch (Exception $e) {
                $formError = "Could not send OTP email. Please try again.";
                invalidateOtpForCustomer($conn, (int)$user['Customer_ID']);
                unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']);
                $activeForm = 'login';
            }
        } else { $formError = "Incorrect password."; $activeForm = 'login'; }
    } else { $formError = "No account found with that email."; $activeForm = 'login'; }
}

// ── Verify OTP ───────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["verify_otp"])) {
    $userOtp = trim($_POST['otp'] ?? '');
    if (empty($userOtp)) { $formError = "Please enter the OTP code."; $activeForm = 'otp'; }
    elseif (isset($_SESSION['pending_user_id'])) {
        $uid = $_SESSION['pending_user_id'];
        $otpRow = getLatestActiveOtp($conn, (int)$uid);
        if (!$otpRow) {
            $formError = "No OTP found. Please log in again.";
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']);
            $activeForm = 'login';
        } elseif ((int)$otpRow['is_db_expired'] === 1) {
            $formError = "OTP expired. Please log in again.";
            markOtpUsed($conn, (int)$otpRow['otp_id']);
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']);
            $activeForm = 'login';
        } elseif ($userOtp == $otpRow['otp_code']) {
            $_SESSION['user_id']   = $_SESSION['pending_user_id'];
            $_SESSION['user_name'] = $_SESSION['pending_user_name'];
            if (!empty($_SESSION['pending_photo'])) $_SESSION['profile_photo'] = $_SESSION['pending_photo'];
            markOtpUsed($conn, (int)$otpRow['otp_id']);
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['pending_photo'], $_SESSION['show_form']);
            header("Location: home.php"); exit();
        } else { $formError = "Invalid OTP. Please try again."; $activeForm = 'otp'; }
    } else { $formError = "Session expired. Please log in again."; $activeForm = 'login'; }
}

// ── Resend OTP ───────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["resend_otp"])) {
    if (!isset($_SESSION['pending_user_id'])) {
        $formError = "Session expired. Please log in again."; $activeForm = 'login';
    } else {
        $uid  = $_SESSION['pending_user_id'];
        $otpRow = getLatestActiveOtp($conn, (int)$uid);
        
        // Check resend wait using DB time if possible, or fallback to strtotime for the resend field
        // since we didn't add is_resend_allowed to getLatestActiveOtp yet.
        // Actually let's just use the same logic as expiry.
        if ($otpRow && strtotime($otpRow['otp_resend_after']) > time()) {
            $wait = strtotime($otpRow['otp_resend_after']) - time();
            $formError = "Please wait {$wait}s before requesting a new OTP.";
            $activeForm = 'otp';
        } else {
            $otp = rand(100000, 999999);
            createOtpForCustomer($conn, (int)$uid, $otp, 300, 60);
            $_SESSION['show_form'] = 'otp';
            $email = $_SESSION['pending_email'];
            $mail  = new PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
                $mail->Username = 'peakscinema@gmail.com'; $mail->Password = 'pggs pvye frmk tmah';
                $mail->SMTPSecure = 'ssl'; $mail->Port = 465;
                $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema');
                $mail->addAddress($email); $mail->isHTML(true);
                $mail->Subject = "Your New PeaksCinema Login Code";
                $mail->Body    = "
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
                            <p class='expiry'>⏱ This code expires in <strong>10 minutes</strong>. If you didn't request this, please ignore this email.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message from Peak's Cinema</p>
                            <p>Please do not reply to this email</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                $mail->send(); $formSuccess = "New OTP sent to your email."; $activeForm = 'otp';
            } catch (Exception $e) {
                $formError = "Could not send OTP. Please try again."; $activeForm = 'otp';
            }
        }
    }
}

// ── Determine active form ────────────────────────────────────
if (!isset($activeForm)) {
    if (isset($_SESSION['show_form']) && $_SESSION['show_form'] === 'otp' && isset($_SESSION['pending_user_id'])) {
        $activeForm = 'otp';
    } elseif (isset($_SESSION['show_form'])) {
        $activeForm = $_SESSION['show_form'];
    } elseif (isset($_GET['tab']) && $_GET['tab'] === 'register') {
        $activeForm = 'signup';
    } else {
        $activeForm = 'login';
    }
}

// Poster backdrop - randomized
$posters = [];
$res = $conn->query("SELECT MoviePoster, MovieName FROM movie WHERE MovieAvailability = 'Now Showing' ORDER BY RAND()");
while ($r = $res->fetch_assoc()) $posters[] = $r;

define('GOOGLE_CLIENT_ID', '180356811024-djv9cq9s2975b22r89dndvb1cr9ico80.apps.googleusercontent.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://accounts.google.com/gsi/client" async defer></script>
<title>Peak's Cinema — Sign In</title>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: #141414;
    color: #F9F9F9;
    min-height: 100vh;
    overflow-x: hidden;
    position: relative;
}

/* Netflix-style backdrop - no tilt, bigger scale */
.backdrop {
    position: fixed;
    inset: -10%;
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    grid-template-rows: repeat(4, 1fr);
    gap: 8px;
    padding: 20px;
    z-index: 0;
    transform: scale(1.15);
    transform-origin: center center;
}
.backdrop-poster {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    border-radius: 4px;
    opacity: 0.5;
}

/* Netflix-style vignette overlay */
.vignette {
    position: fixed;
    inset: 0;
    z-index: 1;
    background: 
        radial-gradient(ellipse 70% 50% at 50% 50%, transparent 0%, rgba(20,20,20,0.5) 50%, rgba(20,20,20,0.95) 85%, #141414 100%),
        linear-gradient(to bottom, rgba(20,20,20,0.4) 0%, transparent 20%, transparent 60%, rgba(20,20,20,0.95) 100%);
    pointer-events: none;
}
.film-line {
    display: none;
}

/* Stage */
.stage {
    position: relative; z-index: 10;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Top bar */
.top-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 48px;
    flex-shrink: 0;
}
.logo-wrap img { height: 76px; filter: invert(1); cursor: pointer; transition: transform 0.2s; }
.logo-wrap img:hover { transform: scale(1.04); }

/* Main area */
.main-area {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 60px;
    padding: 20px 48px 48px;
}

/* Left: branding */
.brand-col {
    flex: 1;
    max-width: 400px;
    display: flex;
    flex-direction: column;
    gap: 0;
}
.brand-eyebrow {
    font-size: 0.7rem; font-weight: 700;
    letter-spacing: 4px; text-transform: uppercase;
    color: #ff4d4d; margin-bottom: 16px;
    opacity: 0; animation: fadeUp 0.6s 0.1s forwards;
}
.brand-tagline {
    font-size: clamp(1.5rem, 3vw, 2.2rem);
    font-weight: 800; line-height: 1.2;
    opacity: 0; animation: fadeUp 0.6s 0.4s forwards;
    margin-bottom: 12px;
}
.brand-tagline .accent { color: #ff4d4d; }
.brand-sub {
    font-size: 0.9rem; color: rgba(249,249,249,0.38);
    line-height: 1.7;
    opacity: 0; animation: fadeUp 0.6s 0.55s forwards;
    margin-bottom: 28px;
}

/* Guest CTA */
.guest-cta {
    opacity: 0; animation: fadeUp 0.6s 0.7s forwards;
}
.btn-browse-guest {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 26px; border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.04);
    color: rgba(249,249,249,0.6);
    font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 600;
    text-decoration: none; transition: all 0.2s;
}
.btn-browse-guest:hover {
    border-color: rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.08);
    color: #F9F9F9;
}
.guest-note {
    margin-top: 9px;
    font-size: 0.68rem; color: rgba(249,249,249,0.22);
    letter-spacing: 0.3px;
}

/* Now showing strip */
.now-strip {
    margin-top: 32px;
    opacity: 0; animation: fadeUp 0.6s 0.85s forwards;
}
.strip-label {
    font-size: 0.6rem; font-weight: 700;
    letter-spacing: 3px; text-transform: uppercase;
    color: rgba(249,249,249,0.25); margin-bottom: 10px;
}
.poster-strip { 
    display: flex; 
    gap: 7px; 
    flex-wrap: wrap;
}
.strip-poster {
    width: 48px; 
    aspect-ratio: 2/3; 
    border-radius: 5px;
    background-size: cover; 
    background-position: center;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s;
    cursor: pointer;
    opacity: 0.5;
}
.strip-poster:hover {
    opacity: 1;
    transform: scale(1.1) translateY(-3px);
    border-color: rgba(255,77,77,0.4);
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
}

/* Right: auth card */
.auth-col {
    width: 360px;
    flex-shrink: 0;
    opacity: 0; animation: fadeUp 0.6s 0.2s forwards;
}

/* Card */
.auth-card {
    background: rgba(26,26,26,0.95);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 28px 24px 22px;
    backdrop-filter: blur(12px);
}
.card-title {
    font-size: 0.78rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: rgba(249,249,249,0.4);
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

/* Feedback banners */
.msg-ok  { background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.2); color: #81c784; border-radius: 8px; padding: 10px 14px; font-size: 0.8rem; margin-bottom: 14px; }
.msg-err { background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.2); border-left: 3px solid #ff4d4d; color: #ff6b6b; border-radius: 8px; padding: 10px 14px; font-size: 0.8rem; margin-bottom: 14px; }

/* Fields */
.field { margin-bottom: 13px; }
.field label {
    display: block; font-size: 0.67rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    color: rgba(249,249,249,0.38); margin-bottom: 5px;
}
.field input {
    width: 100%; padding: 9px 13px;
    border-radius: 9px; border: 1px solid rgba(255,255,255,0.09);
    background: #1e1e1e; color: #F9F9F9;
    font-family: 'Outfit', sans-serif; font-size: 0.86rem;
    outline: none; transition: border-color 0.2s;
}
.field input:focus { border-color: rgba(255,77,77,0.45); background: #222; }
.field input::placeholder { color: rgba(249,249,249,0.18); }
.field input:-webkit-autofill,
.field input:-webkit-autofill:focus {
    -webkit-text-fill-color: #F9F9F9 !important;
    -webkit-box-shadow: 0 0 0 1000px #1e1e1e inset !important;
}
.field-row { display: flex; gap: 10px; }
.field-row .field { flex: 1; }
.opt { opacity: 0.3; font-size: 0.58rem; text-transform: none; letter-spacing: 0; }

/* Buttons */
.btn-submit {
    width: 100%; padding: 11px;
    border-radius: 9px; border: none;
    background: #ff4d4d; color: #fff;
    font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; margin-top: 4px;
}
.btn-submit:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(255,77,77,0.3); }

.switch-link {
    background: none; border: none;
    color: rgba(249,249,249,0.3);
    font-family: 'Outfit', sans-serif; font-size: 0.76rem;
    cursor: pointer; margin-top: 12px;
    display: block; width: 100%; text-align: center;
    transition: color 0.2s; padding: 0;
}
.switch-link:hover { color: rgba(249,249,249,0.7); }
.switch-link span { color: #ff6b6b; font-weight: 600; }

/* Divider */
.divider {
    display: flex; align-items: center; gap: 10px;
    margin: 16px 0 12px;
    color: rgba(255,255,255,0.18);
    font-size: 0.68rem; letter-spacing: 1px; text-transform: uppercase;
}
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.07); }

/* Google button */
.google-btn-wrap { 
    display: flex; 
    justify-content: center; 
    width: 100%; 
}
.google-btn-wrap > div { 
    border-radius: 9px !important; 
    overflow: hidden; 
    max-width: 100%; 
}
.google-btn-wrap iframe { 
    max-width: 100% !important; 
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
.pwa-notice strong { color: #ffb74d; font-weight: 700; }
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
    padding: 16px 20px;
    font-size: 0.8rem;
    color: rgba(249,249,249,0.75);
    line-height: 1.7;
    text-align: justify;
    word-spacing: 0.2em;
    letter-spacing: 0.02em;
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

/* OTP */
.otp-hint { font-size: 0.8rem; color: rgba(249,249,249,0.38); text-align: center; line-height: 1.6; margin-bottom: 14px; }
.otp-resend { text-align: center; margin-top: 12px; font-size: 0.76rem; color: rgba(249,249,249,0.3); }
.otp-resend button { background: none; border: none; color: #ff6b6b; font-family: 'Outfit', sans-serif; font-size: 0.76rem; font-weight: 600; cursor: pointer; margin-left: 4px; }
.otp-small { display: block; text-align: center; font-size: 0.63rem; color: rgba(249,249,249,0.18); margin-top: 8px; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Responsive */
@media (max-width: 860px) {
    .main-area { flex-direction: column; gap: 32px; padding: 16px 20px 40px; align-items: center; }
    .brand-col  { max-width: 100%; align-items: center; text-align: center; }
    .poster-strip { justify-content: center; }
    .auth-col   { width: 100%; max-width: 380px; }
    .top-bar    { padding: 16px 20px; }
}

@media (max-width: 480px) {
    .auth-col { max-width: 100%; padding: 0 10px; }
    .auth-card { padding: 24px 18px; }
    .google-btn-wrap { 
        width: 100%; 
        min-height: 44px;
    }
    .google-btn-wrap > div { 
        width: 100% !important; 
        max-width: 100% !important; 
    }
    .google-btn-wrap iframe {
        width: 100% !important;
        min-width: 100% !important;
    }
    .mobile-google-notice {
        padding: 18px 22px !important;
        font-size: 0.82rem !important;
        line-height: 1.75 !important;
        text-align: justify !important;
        word-spacing: 0.25em !important;
    }
    .mobile-google-notice strong {
        font-size: 0.85rem !important;
        margin-bottom: 10px !important;
    }
}
</style>
</head>
<body>

<div class="backdrop">
    <?php 
    // Fill 8x4 grid = 32 posters, repeat if needed
    for ($i = 0; $i < 32; $i++) {
        if (!empty($posters) && isset($posters[$i % count($posters)])) {
            $p = $posters[$i % count($posters)];
            echo '<div class="backdrop-poster" style="background-image:url(\'' . htmlspecialchars($p['MoviePoster']) . '\');"></div>';
        } else {
            echo '<div class="backdrop-poster" style="background:#1a1a1a;"></div>';
        }
    }
    ?>
</div>
<div class="vignette"></div>

<div class="stage">

    <div class="top-bar">
        <div class="logo-wrap">
            <img src="peakscinematransparent.png" alt="Peak's Cinema">
        </div>
    </div>

    <div class="main-area">

        <!-- LEFT: Branding -->
        <div class="brand-col">
            <p class="brand-eyebrow">Now Open &nbsp;·&nbsp; Metro Manila</p>
            <h1 class="brand-tagline">Welcome to<br><span class="accent">Peak's Cinema</span></h1>
            <p class="brand-sub">
                Premium cinema experiences across Metro Manila.
                Book your seats, pick your moment, live the story.
            </p>
            <div class="guest-cta">
                <a href="home.php" class="btn-browse-guest">🎬 Browse Movies as Guest</a>
                <p class="guest-note">No account needed — sign in later to book tickets.</p>
            </div>
            <?php if (!empty($posters)): ?>
            <div class="now-strip">
                <p class="strip-label">Now Showing</p>
                <div class="poster-strip">
                    <?php foreach ($posters as $p): ?>
                    <div class="strip-poster"
                         style="background-image:url('<?= htmlspecialchars($p['MoviePoster']) ?>');"
                         title="<?= htmlspecialchars($p['MovieName']) ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Auth forms -->
        <div class="auth-col">

            <!-- SIGN UP -->
            <div id="signupSection" style="<?= $activeForm !== 'signup' ? 'display:none;' : '' ?>">
                <div class="auth-card">
                    <p class="card-title">👤 Create Account</p>
                    <?php if (!empty($formError) && $activeForm === 'signup'): ?>
                    <div class="msg-err">⚠ <?= htmlspecialchars($formError) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($formSuccess) && $activeForm === 'signup'): ?>
                    <div class="msg-ok">✓ <?= htmlspecialchars($formSuccess) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="field-row">
                            <div class="field">
                                <label>First Name</label>
                                <input type="text" name="firstName" placeholder="Juan" required>
                            </div>
                            <div class="field">
                                <label>Last Name</label>
                                <input type="text" name="lastName" placeholder="Dela Cruz" required>
                            </div>
                        </div>
                        <div class="field">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="you@example.com" required>
                        </div>
                        <div class="field">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Create a password" required>
                        </div>
                        <div class="field">
                            <label>Confirm Password</label>
                            <input type="password" name="confirmPassword" placeholder="Re-enter password" required>
                        </div>
                        <div class="field">
                            <label>Phone <span class="opt">(optional)</span></label>
                            <div style="display:flex;gap:8px;">
                                <input type="text" name="countryCode" placeholder="+63" style="width:62px;flex-shrink:0;">
                                <input type="tel" name="phoneNumber" placeholder="9XXXXXXXXX" style="flex:1;">
                            </div>
                        </div>
                        <button type="submit" name="customer_info" class="btn-submit">Create Account →</button>
                    </form>
                    <button class="switch-link" id="showLogin">Already have an account? <span>Sign in</span></button>
                    <div class="divider">or</div>
                    <div class="google-btn-wrap" id="googleSignupWrap">
                        <div id="g_id_onload"
                             data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                             data-login_uri="<?= (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                             data-auto_prompt="false"></div>
                        <div class="g_id_signin"
                             data-type="standard" data-shape="rectangular"
                             data-theme="filled_black" data-text="continue_with"
                             data-size="large" data-logo_alignment="left"></div>
                    </div>
                </div>
            </div>

            <!-- LOG IN -->
            <div id="loginSection" style="<?= $activeForm !== 'login' ? 'display:none;' : '' ?>">
                <div class="auth-card">
                    <p class="card-title">🔑 Sign In</p>
                    <?php if (!empty($formError) && $activeForm === 'login'): ?>
                    <div class="msg-err">⚠ <?= htmlspecialchars($formError) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($formSuccess) && $activeForm === 'login'): ?>
                    <div class="msg-ok">✓ <?= htmlspecialchars($formSuccess) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="field">
                            <label>Email</label>
                            <input type="email" name="loginEmail" placeholder="you@example.com" required>
                        </div>
                        <div class="field">
                            <label>Password</label>
                            <input type="password" name="loginPassword" placeholder="Your password" required>
                        </div>
                        <button type="submit" name="login_user" class="btn-submit">Sign In →</button>
                    </form>
                    <button class="switch-link" id="showSignup">Don't have an account? <span>Sign up</span></button>
                    <div class="divider">or</div>
                    <div class="google-btn-wrap" id="googleLoginWrap">
                        <div id="g_id_onload_login"
                             data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                             data-login_uri="<?= (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                             data-auto_prompt="false"></div>
                        <div class="g_id_signin"
                             data-type="standard" data-shape="rectangular"
                             data-theme="filled_black" data-text="signin_with"
                             data-size="large" data-logo_alignment="left"></div>
                    </div>
                </div>
            </div>

            <!-- OTP -->
            <div id="otpSection" style="<?= $activeForm !== 'otp' ? 'display:none;' : '' ?>">
                <div class="auth-card">
                    <p class="card-title">🔐 Verify Email</p>
                    <?php if (!empty($formError) && $activeForm === 'otp'): ?>
                    <div class="msg-err">⚠ <?= htmlspecialchars($formError) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($formSuccess) && $activeForm === 'otp'): ?>
                    <div class="msg-ok">✓ <?= htmlspecialchars($formSuccess) ?></div>
                    <?php endif; ?>
                    <p class="otp-hint">We've sent a 6-digit code to your email.<br>Enter it below to continue.</p>
                    <form method="POST">
                        <div class="field">
                            <label>OTP Code</label>
                            <input type="text" name="otp" maxlength="6" placeholder="000000"
                                   style="text-align:center;font-size:1.4rem;font-weight:800;letter-spacing:8px;" required>
                        </div>
                        <button type="submit" name="verify_otp" class="btn-submit">Verify &amp; Continue →</button>
                    </form>
                    <form method="POST">
                        <p class="otp-resend">
                            Didn't receive the code?
                            <button type="submit" name="resend_otp">Resend</button>
                        </p>
                    </form>
                    <small class="otp-small">Check your spam or promotions folder.</small>
                </div>
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
    document.getElementById('loginSection').style.display  = '';
});
document.getElementById('showSignup')?.addEventListener('click', () => {
    document.getElementById('loginSection').style.display  = 'none';
    document.getElementById('signupSection').style.display = '';
});
</script>
</body>
</html>
