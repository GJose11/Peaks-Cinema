<?php
date_default_timezone_set('Asia/Manila');
session_start();
include("peakscinemas_database.php");
require_once(__DIR__ . "/seat_hold_helpers.php");

ensureSeatHoldSchema($conn);
releaseExpiredSeatHolds($conn);
$seatHoldToken = getSeatHoldToken();

require_once 'PHPMailer-master/src/Exception.php';
require_once 'PHPMailer-master/src/PHPMailer.php';
require_once 'PHPMailer-master/src/SMTP.php';

$Movie_ID       = isset($_POST['movie_id'])       ? $_POST['movie_id']       : '';
$Mall_ID        = isset($_POST['mall_id'])        ? $_POST['mall_id']        : '';
$Date           = isset($_POST['date'])           ? $_POST['date']           : '';
$TimeSlot_ID    = isset($_POST['timeslot_id'])    ? $_POST['timeslot_id']    : '';
$totalPrice     = isset($_POST['totalPrice'])     ? $_POST['totalPrice']     : 0;
$paymentMethod  = isset($_POST['paymentMethod'])  ? $_POST['paymentMethod']  : '';
$discountType   = trim($_POST['discount_type']    ?? '');
$discountIdNo   = trim($_POST['discount_id']      ?? '');
$discountAmount = (float)($_POST['discountAmount'] ?? 0);
$discountPhoto  = $_POST['discountPhoto']           ?? '';
$foodTotal      = (float)($_POST['foodTotal']       ?? 0);
$cashGiven      = isset($_POST['cashGiven']) ? (float)$_POST['cashGiven'] : null;

// Seats: dedupe and pair with labels (payment.php sends parallel arrays)
$rawSeatIds    = (array)($_POST['selectedSeats'] ?? []);
$rawSeatLabels = (array)($_POST['seatLabels']   ?? []);
$selectedSeats = [];
$labelBySeatId = [];
foreach ($rawSeatIds as $i => $sid) {
    $sidStr = trim((string)$sid);
    if ($sidStr === '' || !ctype_digit($sidStr)) {
        continue;
    }
    $sid = (int)$sid;
    if ($sid <= 0 || isset($labelBySeatId[$sid])) {
        continue;
    }
    $labelBySeatId[$sid] = isset($rawSeatLabels[$i]) ? trim((string)$rawSeatLabels[$i]) : '';
    $selectedSeats[]     = $sid;
}

// Handle food data from payment.php (sent as foodOrder[] JSON objects)
$foodOrderRaw = $_POST['foodOrder'] ?? [];
$foodSummary = [];
$specialRequests = $_POST['special_requests'] ?? null;
foreach ($foodOrderRaw as $fJson) {
    $f = json_decode($fJson, true);
    if ($f && isset($f['id'], $f['qty']) && array_key_exists('price', $f)) {
        $fid = (int)$f['id'];
        $qty = max(1, (int)$f['qty']);
        $price = (float)$f['price'];
        $name = trim((string)($f['name'] ?? ''));
        if ($fid > 0 && $price >= 0) {
            $foodSummary[$fid] = ['qty' => $qty, 'price' => $price, 'name' => $name];
        }
    }
}
if ($foodTotal <= 0 && !empty($foodSummary)) {
    $foodTotal = 0;
    foreach ($foodSummary as $fd) {
        $foodTotal += ($fd['price'] ?? 0) * ($fd['qty'] ?? 1);
    }
}

$seatSubtotal = max(0, round(((float)$totalPrice - $foodTotal) + $discountAmount, 2));

if (isset($_SESSION['user_id'])) {
    $Customer_ID = $_SESSION['user_id'];
} elseif (isset($_SESSION['Customer_ID'])) {
    $Customer_ID = $_SESSION['Customer_ID'];
} else {
    header("Location: personal_info_form.php");
    exit;
}

$profile_link  = "profile_dashboard.php";
$profile_photo = $_SESSION['profile_photo'] ?? null;

$customerName = '';
if ($paymentMethod === 'credit') {
    $customerName = trim(($_POST['cardFirstName'] ?? '') . ' ' . ($_POST['cardLastName'] ?? ''));
} elseif ($paymentMethod === 'paypal') {
    $customerName = trim(($_POST['paypalFirstName'] ?? '') . ' ' . ($_POST['paypalLastName'] ?? ''));
} elseif ($paymentMethod === 'gcash') {
    $customerName = trim(($_POST['gcashFirstName'] ?? '') . ' ' . ($_POST['gcashLastName'] ?? ''));
} elseif ($paymentMethod === 'paymaya') {
    $customerName = trim(($_POST['paymayaFirstName'] ?? '') . ' ' . ($_POST['paymayaLastName'] ?? ''));
}

if (empty($paymentMethod)) {
    header("Location: payment.php");
    exit;
}

// Fetch profile photo from DB
$stmt = $conn->prepare("SELECT Name, Email, ProfilePhoto FROM customer WHERE Customer_ID = ?");
$stmt->bind_param("i", $Customer_ID);
$stmt->execute();
$customerRow = $stmt->get_result()->fetch_assoc();
$customerEmail = $customerRow['Email'] ?? '';
if (!empty($customerRow['ProfilePhoto'])) {
    $profile_photo = $customerRow['ProfilePhoto'];
    $_SESSION['profile_photo'] = $profile_photo;
}
$user_initials = '';
$nameParts = explode(' ', trim($customerRow['Name'] ?? ''));
$user_initials = strtoupper(substr($nameParts[0]??'',0,1).substr(end($nameParts)??'',0,1));
if (strlen($user_initials)===1) $user_initials = strtoupper(substr($nameParts[0]??'',0,2));

$movie_stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
$movie_stmt->bind_param("i", $Movie_ID); $movie_stmt->execute();
$movieDetails = ($movie_stmt->get_result())->fetch_assoc();

$mall_stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$mall_stmt->bind_param("i", $Mall_ID); $mall_stmt->execute();
$mallDetails = ($mall_stmt->get_result())->fetch_assoc();

$timeslot_stmt = $conn->prepare("SELECT * FROM timeslot WHERE TimeSlot_ID = ?");
$timeslot_stmt->bind_param("i", $TimeSlot_ID); $timeslot_stmt->execute();
$timeslotDetails = ($timeslot_stmt->get_result())->fetch_assoc();

$theater_stmt = $conn->prepare("SELECT TheaterName FROM theater WHERE Theater_ID = ?");
$theater_stmt->bind_param("i", $timeslotDetails['Theater_ID']); $theater_stmt->execute();
$theaterDetails = ($theater_stmt->get_result())->fetch_assoc();

$seatPositions = [];
if (!empty($selectedSeats)) {
    $placeholders = str_repeat('?,', count($selectedSeats) - 1) . '?';
    $seat_stmt = $conn->prepare("SELECT Seat_ID, SeatRow, SeatColumn FROM seats WHERE Seat_ID IN ($placeholders)");
    $types = str_repeat('i', count($selectedSeats));
    $seat_stmt->bind_param($types, ...$selectedSeats);
    $seat_stmt->execute();
    $seatResult = $seat_stmt->get_result();
    $byId = [];
    while ($seat = $seatResult->fetch_assoc()) {
        $byId[(int)$seat['Seat_ID']] = $seat;
    }
    foreach ($selectedSeats as $sid) {
        $lbl = $labelBySeatId[$sid] ?? '';
        if ($lbl !== '') {
            $seatPositions[] = $lbl;
            continue;
        }
        $seat = $byId[(int)$sid] ?? null;
        if (!$seat) {
            continue;
        }
        $col = (int)$seat['SeatColumn'];
        if ($col <= 0) {
            $seatPositions[] = $seat['SeatRow'] . '?';
        } else {
            $seatPositions[] = $seat['SeatRow'] . $col;
        }
    }
    sort($seatPositions);
}

$bookingRef = 'PC-' . date('Ymd') . '-' . rand(1000, 9999);

$paymentLabel = match($paymentMethod) {
    'credit'  => 'Credit / Debit Card',
    'paypal'  => 'PayPal',
    'gcash'   => 'GCash',
    'paymaya' => 'PayMaya',
    default   => '-'
};

// Pure string time formatter — no timezone dependency
function fmtTime($t) {
    $p    = explode(':', trim($t));
    $h    = (int)($p[0] ?? 0);
    $m    = str_pad((int)($p[1] ?? 0), 2, '0', STR_PAD_LEFT);
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12  = $h % 12 ?: 12;
    return "{$h12}:{$m} {$ampm}";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        $seatUpdate_stmt = $conn->prepare("
            UPDATE seats
            SET SeatAvailability = 'Taken',
                HoldToken = NULL,
                HoldExpiresAt = NULL
            WHERE Seat_ID = ?
              AND TimeSlot_ID = ?
              AND (
                    SeatAvailability = 'Available'
                    OR (SeatAvailability = 'Taken' AND HoldToken = ?)
                  )
        ");
        foreach ($selectedSeats as $Seat_ID) {
            $seatUpdate_stmt->bind_param("iis", $Seat_ID, $TimeSlot_ID, $seatHoldToken);
            $seatUpdate_stmt->execute();
            if ($seatUpdate_stmt->affected_rows < 1) {
                throw new Exception("One or more selected seats are no longer available.");
            }
        }

        $ticketIDs = [];
        $Status   = 1;
        $paymentStatus = 'Paid';
        $receiptStatus = 'Issued';
        $dateTime = date('Y-m-d H:i:s');
        $price    = $totalPrice / count($selectedSeats);

        // Check if BookingRef column exists — safe for both old and new DB schemas
        $colCheck = $conn->query("SHOW COLUMNS FROM ticket LIKE 'BookingRef'");
        $hasBookingRef = ($colCheck && $colCheck->num_rows > 0);

        if ($hasBookingRef) {
            $ticket_stmt = $conn->prepare("INSERT INTO ticket(Seat_ID, Customer_ID, Movie_ID, TimeSlot_ID, Price, Status, DateTime, BookingRef) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        } else {
            $ticket_stmt = $conn->prepare("INSERT INTO ticket(Seat_ID, Customer_ID, Movie_ID, TimeSlot_ID, Price, Status, DateTime) VALUES (?, ?, ?, ?, ?, ?, ?)");
        }

        foreach ($selectedSeats as $Seat_ID) {
            if ($hasBookingRef) {
                $ticket_stmt->bind_param("iiiidiss", $Seat_ID, $Customer_ID, $Movie_ID, $TimeSlot_ID, $price, $Status, $dateTime, $bookingRef);
            } else {
                $ticket_stmt->bind_param("iiiidis", $Seat_ID, $Customer_ID, $Movie_ID, $TimeSlot_ID, $price, $Status, $dateTime);
            }
            $ticket_stmt->execute();
            $ticketIDs[] = $conn->insert_id;
        }

        $payment_stmt = $conn->prepare("INSERT INTO payment(Ticket_ID, PaymentMethod, AmountPaid, PaymentDate, PaymentStatus) VALUES (?, ?, ?, ?, ?)");
        $receipt_stmt = $conn->prepare("INSERT INTO `e-receipt`(PaymentID, DateIssued, SentToEmail, ReceiptStatus) VALUES (?, ?, ?, ?)");

        $primaryTicketId = $ticketIDs[0] ?? 0;
        $paymentDateOnly = date('Y-m-d', strtotime($dateTime));
        $payment_stmt->bind_param("isdss", $primaryTicketId, $paymentMethod, $totalPrice, $paymentDateOnly, $paymentStatus);
        $payment_stmt->execute();
        $Payment_ID  = $conn->insert_id;
        $dateIssued  = $paymentDateOnly;
        $receipt_stmt->bind_param("isss", $Payment_ID, $dateIssued, $customerEmail, $receiptStatus);
        $receipt_stmt->execute();

        $conn->commit();

        // ── Save discount verification record ─────────────────
        if (!empty($discountType) && !empty($discountIdNo) && $discountAmount > 0) {
            $hasVerifyTable = $conn->query("SHOW TABLES LIKE 'discount_verifications'")->num_rows > 0;
            if ($hasVerifyTable) {
                // Save one record per booking (use first ticket ID)
                $firstTid = $ticketIDs[0] ?? 0;
                $vs = $conn->prepare("INSERT INTO discount_verifications
                    (Ticket_ID, Customer_ID, BookingRef, DiscountType, DiscountIdNo, DiscountPhoto, DiscountAmount, Status)
                    VALUES (?,?,?,?,?,?,?,'pending')");
                $vs->bind_param("iissssd",
                    $firstTid, $Customer_ID, $bookingRef,
                    $discountType, $discountIdNo, $discountPhoto, $discountAmount);
                $vs->execute();

                // Notify customer that verification is pending
                $vTitle = "Discount Verification Pending 🔍";
                $vMsg   = "Your " . ($discountType === 'pwd' ? 'PWD' : 'Senior Citizen') .
                          " discount for booking $bookingRef is pending staff verification. " .
                          "You will be notified once reviewed.";
                $vn = $conn->prepare("INSERT INTO notifications (Customer_ID, Title, Message, Type) VALUES (?,?,?,'general')");
                $vn->bind_param("iss", $Customer_ID, $vTitle, $vMsg); $vn->execute();
            }
        }

        // ── Save food orders ──────────────────────────────────
        if (!empty($foodSummary)) {
            $hasFoodOrders = $conn->query("SHOW TABLES LIKE 'food_orders'")->num_rows > 0;
            if ($hasFoodOrders) {
                $fo_stmt = $conn->prepare("INSERT INTO food_orders (Customer_ID, TimeSlot_ID, Item_ID, Quantity, UnitPrice, SpecialRequests, BookingRef) VALUES (?,?,?,?,?,?,?)");
                foreach ($foodSummary as $fid => $fd) {
                    $lineTotal = (float)$fd['price'] * (int)$fd['qty'];
                    $fo_stmt->bind_param("iiiidss", $Customer_ID, $TimeSlot_ID, $fid, $fd['qty'], $lineTotal, $specialRequests, $bookingRef);
                    $fo_stmt->execute();
                }
            }
        }

        // ── Create notification for customer ──
        if ($Customer_ID) {
            $notifTitle = "Booking Confirmed! 🎬";
            $notifMsg   = "Your booking for " . ($movieDetails['MovieName'] ?? 'your movie') .
                          " on " . date('F d, Y', strtotime($Date)) .
                          " at " . fmtTime($timeslotDetails['StartTime'] ?? $timeslotDetails['STARTTIME'] ?? '') .
                          " has been confirmed. Ref: $bookingRef";
            $notifType  = 'booking';
            $notif_stmt = $conn->prepare("INSERT INTO notifications (Customer_ID, Title, Message, Type) VALUES (?, ?, ?, ?)");
            if ($notif_stmt) {
                $notif_stmt->bind_param("isss", $Customer_ID, $notifTitle, $notifMsg, $notifType);
                $notif_stmt->execute();
            }
        }

        // ── Send confirmation email ───────────────────────────
        if (!empty($customerEmail)) {
            try {
                $emailBody = '
                <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;background:#1a1a1a;border-radius:12px;overflow:hidden;">
                  <div style="background:#ff4d4d;padding:24px 28px;">
                    <h1 style="color:#fff;font-size:22px;margin:0;">Booking Confirmed! 🎬</h1>
                    <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:6px 0 0;">Peak\'s Cinema</p>
                  </div>
                  <div style="padding:24px 28px;">
                    <p style="color:#ccc;font-size:14px;margin:0 0 20px;">Here are your booking details:</p>
                    <table style="width:100%;border-collapse:collapse;">
                      <tr><td style="padding:8px 0;color:#888;font-size:13px;">Movie</td><td style="padding:8px 0;font-size:13px;text-align:right;color:#F9F9F9;">' . htmlspecialchars($movieDetails['MovieName'] ?? '') . '</td></tr>
                      <tr><td style="padding:8px 0;color:#888;font-size:13px;">Cinema</td><td style="padding:8px 0;font-size:13px;text-align:right;color:#F9F9F9;">' . htmlspecialchars(($mallDetails['MallName'] ?? '') . ' — ' . ($theaterDetails['TheaterName'] ?? '')) . '</td></tr>
                      <tr><td style="padding:8px 0;color:#888;font-size:13px;">Date</td><td style="padding:8px 0;font-size:13px;text-align:right;color:#F9F9F9;">' . htmlspecialchars(date('F d, Y', strtotime($Date))) . '</td></tr>
                      <tr><td style="padding:8px 0;color:#888;font-size:13px;">Screening Time</td><td style="padding:8px 0;font-size:13px;text-align:right;color:#F9F9F9;">' . htmlspecialchars(fmtTime($timeslotDetails['StartTime'] ?? '') . ' · ' . ($timeslotDetails['ScreeningType'] ?? '')) . '</td></tr>
                      <tr><td style="padding:8px 0;color:#888;font-size:13px;">Seats</td><td style="padding:8px 0;font-size:13px;text-align:right;color:#F9F9F9;">' . htmlspecialchars(implode(', ', $seatPositions)) . '</td></tr>
                      <tr><td style="padding:8px 0;color:#888;font-size:13px;">Tickets</td><td style="padding:8px 0;font-size:13px;text-align:right;color:#F9F9F9;">' . count($selectedSeats) . ' ticket' . (count($selectedSeats) > 1 ? 's' : '') . '</td></tr>
                      <tr><td style="padding:8px 0;color:#888;font-size:13px;">Payment</td><td style="padding:8px 0;font-size:13px;text-align:right;color:#F9F9F9;">' . htmlspecialchars($paymentLabel) . '</td></tr>';

                if (!empty($foodSummary)) {
                    $emailBody .= '<tr><td colspan="2" style="padding:8px 0;border-top:1px solid #333;font-size:12px;color:#888;text-transform:uppercase;letter-spacing:1px;">Food Order</td></tr>';
                    $fidListEmail = implode(',', array_map('intval', array_keys($foodSummary)));
                    $fnRowsEmail = $conn->query("SELECT Item_ID, ItemName FROM food_items WHERE Item_ID IN ($fidListEmail)");
                    $fnMapEmail = [];
                    if ($fnRowsEmail) while ($fn = $fnRowsEmail->fetch_assoc()) $fnMapEmail[$fn['Item_ID']] = $fn['ItemName'];
                    foreach ($foodSummary as $fid => $fd) {
                        $fname = $fnMapEmail[$fid] ?? ($fd['name'] ?? 'Food Item');
                        $emailBody .= '<tr><td style="padding:4px 0;font-size:13px;color:#F9F9F9;">' . htmlspecialchars($fname) . ' x' . $fd['qty'] . '</td><td style="padding:4px 0;font-size:13px;text-align:right;color:#F9F9F9;">₱' . number_format($fd['price'] * $fd['qty'], 2) . '</td></tr>';
                    }
                    if (!empty($specialRequests)) {
                        $emailBody .= '<tr><td colspan="2" style="padding:8px;background:rgba(255,255,255,0.05);border-radius:6px;font-size:12px;color:#aaa;font-style:italic;">Special Requests: "' . htmlspecialchars($specialRequests) . '"</td></tr>';
                    }
                    $emailBody .= '<tr><td style="padding:8px 0;font-weight:700;font-size:13px;color:#F9F9F9;">Food Subtotal</td><td style="padding:8px 0;font-weight:700;font-size:13px;text-align:right;color:#ff4d4d;">₱' . number_format($foodTotal, 2) . '</td></tr>';
                }

                $emailBody .= '
                      <tr><td colspan="2" style="padding:8px 0;border-top:1px solid #333;"></td></tr>
                      <tr><td style="padding:8px 0;font-weight:700;font-size:14px;color:#F9F9F9;">Total Paid</td><td style="padding:8px 0;font-weight:700;font-size:14px;text-align:right;color:#ff4d4d;">₱' . number_format($totalPrice, 2) . '</td></tr>
                    </table>
                    <div style="margin-top:20px;padding:14px 18px;background:rgba(255,77,77,0.08);border:1px solid rgba(255,77,77,0.25);border-radius:8px;text-align:center;">
                      <p style="font-size:11px;color:#888;letter-spacing:2px;text-transform:uppercase;margin:0 0 4px;">Booking Reference</p>
                      <p style="font-size:18px;font-weight:700;color:#ff4d4d;letter-spacing:3px;margin:0;">' . htmlspecialchars($bookingRef) . '</p>
                    </div>
                    <p style="color:#666;font-size:12px;margin-top:20px;text-align:center;">Present this reference at the cinema entrance. Enjoy the show!</p>
                  </div>
                </div>';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'peakscinema@gmail.com';
                $mail->Password   = 'pggs pvye frmk tmah';
                $mail->SMTPSecure = 'ssl';
                $mail->Port       = 465;
                $mail->setFrom('peakscinema@gmail.com', "Peak's Cinema");
                $mail->addAddress($customerEmail, $customerRow['Name'] ?? '');
                $mail->isHTML(true);
                $mail->Subject = "Booking Confirmed – " . ($movieDetails['MovieName'] ?? 'Your Movie') . " | Ref: $bookingRef";
                $mail->Body    = $emailBody;
                $mail->send();
            } catch (\Exception $emailEx) {
                // Email failure is non-critical — booking is already saved
                error_log("Receipt email failed: " . $emailEx->getMessage());
            }
        }

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>PeaksCinemas – Booking Confirmation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: #0d0d0d;
            color: #F9F9F9;
            min-height: 100vh;
            padding-top: 80px;
            padding-bottom: 60px;
        }
        body::before {
            content: '';
            position: fixed; inset: 0;
            background: url("movie-background-collage.jpg") no-repeat center center fixed;
            background-size: cover;
            opacity: 0.12;
            z-index: 0;
            pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed; inset: 0;
            background: radial-gradient(ellipse at center, transparent 10%, rgba(13,13,13,0.5) 60%, #0d0d0d 100%);
            z-index: 1;
            pointer-events: none;
        }

        /* ── Standardized Header ── */
        header {
            background: #1C1C1C;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 20px;
            position: fixed; top: 0; left: 0; width: 100%;
            height: 50px; z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            transition: transform 0.3s ease, all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        header.hidden { transform: translateY(-100%); }
        .brand-logo-wrap {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            flex-shrink: 0;
        }
        .brand-logo-wrap:hover { transform: scale(1.05); }
        .brand-logo { height: 34px; width: auto; filter: invert(1); display: block; }

        .header-actions { display: flex; align-items: center; gap: 8px; }
        .bookings-btn, .notif-btn {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 7px; padding: 6px 9px;
            color: rgba(249,249,249,0.65); font-size: 0.92rem;
            cursor: pointer; transition: all 0.25s;
            font-family: 'Outfit', sans-serif;
            display: flex; align-items: center;
            height: 32px;
        }
        .bookings-btn:hover, .notif-btn:hover { 
            background: rgba(255,255,255,0.12); color: #F9F9F9;
            transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .bookings-btn { gap: 5px; }
        .bookings-btn::after { content: 'My Bookings'; font-size: 0.75rem; font-weight: 600; }

        .notif-wrap { position: relative; }
        .notif-badge {
            position: absolute; top: -4px; right: -4px;
            background: #ff4d4d; color: #fff;
            font-size: 0.55rem; font-weight: 800;
            min-width: 16px; height: 16px; border-radius: 8px;
            display: none; align-items: center; justify-content: center;
            padding: 0 4px;
        }

        .profile-btn {
            background: #F9F9F9; border: none; border-radius: 50%;
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; overflow: hidden; padding: 0;
            transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            flex-shrink: 0;
        }
        .profile-btn img { width: 100%; height: 100%; object-fit: cover; }
        .profile-btn:hover { transform: scale(1.08); box-shadow: 0 4px 16px rgba(255,255,255,0.2); }
        .profile-initials {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, #ff4d4d, #c0392b);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.78rem; font-weight: 800; color: #fff;
        }

        /* ── Main layout ── */
        main {
            width: 95%;
            max-width: 560px;
            margin: 28px auto;
            display: flex;
            flex-direction: column;
            gap: 18px;
            position: relative;
            z-index: 10;
        }
        .page-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin: 0 0 4px;
        }
        .page-title {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 0;
        }

        .page-intro {
            margin-bottom: 0;
        }

        /* ── Success banner ── */
        .success-banner {
            text-align: center;
            padding: 26px;
            background: linear-gradient(135deg, rgba(76,175,80,0.12), rgba(76,175,80,0.04));
            border: 1px solid rgba(76,175,80,0.2);
            border-radius: 14px;
            margin-bottom: 0;
        }
        .success-icon { font-size: 2.5rem; margin: 0 0 10px; }
        .success-title { font-size: 1rem; font-weight: 800; color: #66bb6a; margin: 0 0 4px; }
        .success-sub { font-size: 0.78rem; color: rgba(249,249,249,0.35) }

        /* ── Receipt card ── */
        .panel {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 14px;
            overflow: hidden;
        }
        .receipt-top {
            background: linear-gradient(135deg, #1a0808, #1a1a1a);
            padding: 22px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-align: center;
        }
        .receipt-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 8px;
        }
        .receipt-logo img {
            height: 34px;
            width: auto;
            filter: invert(1);
            display: block;
        }
        .receipt-movie { font-size: 1.05rem; font-weight: 800; margin-top: 10px; }
        .receipt-type {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            border-radius: 20px;
            background: rgba(255,77,77,0.1);
            border: 1px solid rgba(255,77,77,0.25);
            font-size: 0.68rem;
            font-weight: 700;
            color: #ff6b6b;
            letter-spacing: 1px;
        }

        .tear-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 24px;
            border-top: 2px dashed rgba(255,255,255,0.07);
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.1);
        }

        .panel-body { padding: 20px; }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 0.82rem;
        }
        .receipt-row:last-child { border-bottom: none; }

        .r-lbl { color: rgba(249,249,249,0.4); flex-shrink: 0; margin-right: 12px; }
        .r-val { font-weight: 600; text-align: right; }

        .seats-wrap { display: flex; flex-wrap: wrap; gap: 5px; justify-content: flex-end; }
        .seat-chip {
            padding: 3px 9px;
            border-radius: 5px;
            background: rgba(255,77,77,0.1);
            border: 1px solid rgba(255,77,77,0.2);
            font-size: 0.72rem;
            font-weight: 700;
            color: #ff6b6b;
        }

        .pay-section {
            padding: 16px 24px;
            background: rgba(255,255,255,0.02);
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .pay-row { display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 7px; }
        .pay-row .pl { color: rgba(249,249,249,0.4); }
        .pay-row .pv { font-weight: 600; }
        .pay-row.total {
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.07);
            margin-top: 4px;
        }
        .pay-row.total .pl { font-size: 0.9rem; color: #F9F9F9; font-weight: 700; }
        .pay-row.total .pv { font-size: 1rem; color: #ff4d4d; font-weight: 800; }

        .receipt-footer { padding: 16px 24px; text-align: center; border-top: 1px solid rgba(255,255,255,0.04); }
        .receipt-ref-text { font-size: 0.68rem; color: rgba(249,249,249,0.2); letter-spacing: 1px; margin-bottom: 4px; }
        .receipt-ts { font-size: 0.68rem; color: rgba(249,249,249,0.15); }

        /* Notification dropdown */
        .notif-dropdown {
            display: none; position: absolute; top: calc(100% + 8px); right: 0;
            width: 320px; background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;
            overflow: hidden; box-shadow: 0 12px 40px rgba(0,0,0,0.6); z-index: 2000;
        }
        .notif-dropdown.open { display: block; }
        .notif-header { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: space-between; }
        .notif-header span { font-size: 0.78rem; font-weight: 700; color: rgba(249,249,249,0.5); letter-spacing: 1px; text-transform: uppercase; }
        .notif-mark-all { font-size: 0.7rem; color: #ff6b6b; cursor: pointer; background: none; border: none; font-family: 'Outfit',sans-serif; font-weight: 600; }
        .notif-list { max-height: 280px; overflow-y: auto; }
        .notif-item { padding: 12px 16px 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.15s; display: flex; gap: 6px; align-items: flex-start; text-decoration: none; color: inherit; }
        .notif-item:hover { background: rgba(255,255,255,0.04); }
        .notif-item.unread { background: rgba(255,77,77,0.05); }
        .notif-dot { width: 6px; height: 6px; border-radius: 50%; background: #ff4d4d; flex-shrink: 0; margin-top: 6px; }
        .notif-dot.read { background: transparent; }
        .notif-item-body { flex: 1; min-width: 0; }
        .notif-item-title { font-size: 0.8rem; font-weight: 700; margin-bottom: 2px; color: #F9F9F9; }
        .notif-item-msg { font-size: 0.72rem; color: rgba(249,249,249,0.45); line-height: 1.5; }
        .notif-item-time { font-size: 0.65rem; color: rgba(249,249,249,0.25); margin-top: 4px; }
        .notif-empty { text-align: center; padding: 30px; font-size: 0.82rem; color: rgba(249,249,249,0.2); }
        .notif-footer { padding: 10px 16px; border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; }
        .notif-footer a { font-size: 0.75rem; color: #ff6b6b; text-decoration: none; font-weight: 600; }

        /* ── Action buttons ── */
        .actions { display: flex; gap: 10px; margin-top: 0; }
        .actions button, .actions a {
            flex: 1; padding: 13px; text-align: center; border-radius: 10px;
            font-family: 'Outfit', sans-serif; font-size: 0.85rem; font-weight: 700;
            cursor: pointer; text-decoration: none; transition: all 0.2s; border: none;
        }
        .btn-print { background: #ff4d4d; color: #fff; }
        .btn-print:hover { background: #e03c3c; transform: translateY(-1px); }
        .btn-new {
            background: rgba(255,255,255,0.05);
            color: rgba(249,249,249,0.7);
            border: 1px solid rgba(255,255,255,0.1) !important;
        }
        .btn-new:hover { background: rgba(255,255,255,0.09); color: #F9F9F9; }

        /* ── QR section ── */
        .qr-section {
            backdrop-filter: blur(2px);
            background: rgba(0,0,0,0.4);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.6);
            padding: 16px 20px;
            text-align: center;
            margin-top: 0;
        }
        .qr-section p {
            font-size: 0.72rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.35);
            margin: 0 0 10px;
        }
        .qr-section img {
            width: 130px; height: 130px;
            border-radius: 10px;
            border: 2px solid rgba(249,249,249,0.1);
            background: #fff;
            padding: 6px;
        }

        /* ── Print styles ── */
        @media print {
            body { background: #fff; color: #000; padding: 0; }
            header, .actions, .success-banner, .qr-section p { display: none !important; }
            main { max-width: 100%; margin: 0; padding: 0; }
            .panel { border: 1px solid #ddd; background: #fff !important; color: #000 !important; }
            .receipt-top { background: #f9f9f9 !important; border-bottom: 1px solid #ddd; }
            .r-lbl, .pay-row .pl, .receipt-ref-text, .receipt-ts { color: #666 !important; }
            .r-val, .pay-row .pv, .receipt-movie { color: #000 !important; }
            .pay-row.total .pv { color: #c0392b !important; }
            .seat-chip { background: #f0f0f0 !important; border-color: #ddd !important; color: #c0392b !important; }
            .qr-section { background: #fff !important; box-shadow: none; }
        }
    </style>
</head>
<body>

<header id="mainHeader">
    <a href="home.php" class="brand-logo-wrap" title="Peak's Cinema - Home">
        <img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo">
    </a>

    <!-- Right actions -->
    <div class="header-actions">
        <?php if (isset($_SESSION['user_id'])): ?>
        <button type="button" class="bookings-btn" onclick="window.location.href='my_bookings.php'" title="My Bookings">
            🎟
        </button>
        <div class="notif-wrap">
            <button class="notif-btn" id="notifBtn" onclick="toggleNotif(event)" title="Notifications">
                🔔<span class="notif-badge" id="notifBadge"></span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span>Notifications</span>
                    <button type="button" class="notif-mark-all" onclick="markAllRead()">Mark all read</button>
                </div>
                <div class="notif-list" id="notifList"><div class="notif-empty">Loading...</div></div>
                <div class="notif-footer">
                    <a href="notifications_page.php">View All →</a>
                    <a href="my_bookings.php" style="color:rgba(249,249,249,0.4);">My Bookings</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <button class="profile-btn" onclick="window.location.href='<?= $profile_link ?>'" title="Profile">
        <?php if (!empty($profile_photo)): ?>
            <img src="<?= htmlspecialchars($profile_photo) ?>?v=<?= time() ?>" alt="Profile" referrerpolicy="no-referrer">
        <?php elseif (!empty($user_initials)): ?>
            <div class="profile-initials"><?= htmlspecialchars($user_initials) ?></div>
        <?php else: ?>
            <div class="profile-initials">?</div>
        <?php endif; ?>
        </button>
    </div>
</header>

<main>
    <div class="page-intro">
        <p class="page-label">Booking Complete</p>
        <h1 class="page-title">Receipt</h1>
    </div>

    <!-- Success banner -->
    <div class="success-banner">
        <div class="success-icon">✅</div>
        <div class="success-title">Booking Confirmed!</div>
        <div class="success-sub">Tickets reserved. Present this receipt at the entrance.</div>
    </div>

    <!-- Receipt details -->
    <div class="panel" id="receiptCard">
        <div class="receipt-top">
            <div class="receipt-logo">
                <img src="peakscinematransparent.png" alt="Peak's Cinema Logo">
            </div>
            <div class="receipt-movie"><?= htmlspecialchars($movieDetails['MovieName']) ?></div>
            <span class="receipt-type"><?= htmlspecialchars($timeslotDetails['ScreeningType'] ?? 'Standard') ?></span>
        </div>

        <div class="tear-line"><span>✂</span><span>Official Receipt</span><span>✂</span></div>

        <div class="panel-body">
            <div class="receipt-row">
                <span class="r-lbl">Customer</span>
                <span class="r-val"><?= !empty($customerName) ? htmlspecialchars($customerName) : 'Valued Customer' ?></span>
            </div>
            <div class="receipt-row">
                <span class="r-lbl">Date</span>
                <span class="r-val"><?= date('F d, Y', strtotime($Date)) ?></span>
            </div>
            <div class="receipt-row">
                <span class="r-lbl">Show Time</span>
                <span class="r-val"><?= isset($timeslotDetails['StartTime']) ? fmtTime($timeslotDetails['StartTime']) : 'N/A' ?></span>
            </div>
            <div class="receipt-row">
                <span class="r-lbl">Venue</span>
                <span class="r-val"><?= htmlspecialchars($mallDetails['MallName']) ?> — <?= htmlspecialchars($theaterDetails['TheaterName']) ?></span>
            </div>
            <div class="receipt-row">
                <span class="r-lbl">Seats</span>
                <span class="r-val">
                    <div class="seats-wrap">
                        <?php foreach ($seatPositions as $sp): ?>
                            <span class="seat-chip"><?= htmlspecialchars($sp) ?></span>
                        <?php endforeach; ?>
                    </div>
                </span>
            </div>
            <div class="receipt-row">
                <span class="r-lbl">Booking Time</span>
                <span class="r-val"><?= isset($dateTime) ? date('F d, Y g:i A', strtotime($dateTime)) : 'N/A' ?></span>
            </div>
        </div>

        <div class="tear-line"><span style="opacity:0.3;letter-spacing:3px;">· · · · · · · · · · · · · · · · · · · · · ·</span></div>

        <?php if (!empty($foodSummary)): ?>
        <?php
        $fidList = implode(',', array_map('intval', array_keys($foodSummary)));
        $fnRows  = $conn->query("SELECT Item_ID, ItemName, Price FROM food_items WHERE Item_ID IN ($fidList)");
        $fnMap   = [];
        if ($fnRows) while ($fn = $fnRows->fetch_assoc()) $fnMap[$fn['Item_ID']] = $fn;
        ?>
        <div class="panel-body" style="padding-top:0;">
            <div style="font-size:0.7rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(249,249,249,0.3);margin-bottom:10px;">🍿 Food Order</div>
            <?php foreach ($foodSummary as $fid => $fd):
                $fname = $fnMap[$fid]['ItemName'] ?? ($fd['name'] ?? 'Food Item');
                $fprice = $fd['price'];
                $fqty   = $fd['qty'];
            ?>
            <div class="receipt-row">
                <span class="r-lbl"><?= htmlspecialchars($fname) ?></span>
                <span class="r-val">x<?= $fqty ?> &nbsp; ₱<?= number_format($fprice * $fqty, 2) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="receipt-row" style="border-bottom:none;padding-top:12px;">
                <span class="r-lbl">Food Subtotal</span>
                <span class="r-val" style="color:#ff6b6b;">₱<?= number_format($foodTotal, 2) ?></span>
            </div>
            <?php if (!empty($specialRequests)): ?>
            <div style="margin-top:12px;padding:10px;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid rgba(255,255,255,0.05);">
                <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:rgba(249,249,249,0.3);margin-bottom:4px;">Special Requests</div>
                <div style="font-size:0.78rem;color:rgba(249,249,249,0.6);line-height:1.4;font-style:italic;">"<?= htmlspecialchars($specialRequests) ?>"</div>
            </div>
            <?php endif; ?>
        </div>
        <div class="tear-line"><span style="opacity:0.3;letter-spacing:3px;">· · · · · · · · · · · · · · · · · · · · · ·</span></div>
        <?php endif; ?>

        <?php if ($discountAmount > 0): ?>
        <div class="panel-body" style="padding-top:0;">
            <div class="receipt-row" style="color:#66bb6a;border-bottom:none;">
                <span class="r-lbl" style="color:#66bb6a;">🪪 <?= $discountType==='pwd'?'PWD':'Senior Citizen' ?> Discount</span>
                <span class="r-val">− ₱<?= number_format($discountAmount, 2) ?></span>
            </div>
        </div>
        <div class="tear-line"><span style="opacity:0.3;letter-spacing:3px;">· · · · · · · · · · · · · · · · · · · · · ·</span></div>
        <?php endif; ?>

        <div class="pay-section">
            <div class="pay-row">
                <span class="pl">Payment Method</span>
                <span class="pv"><?= htmlspecialchars($paymentLabel) ?></span>
            </div>
            <?php if (!empty($foodSummary)): ?>
            <div class="pay-row">
                <span class="pl">Seats Subtotal</span>
                <span class="pv">₱<?= number_format($seatSubtotal, 2) ?></span>
            </div>
            <div class="pay-row">
                <span class="pl">Food &amp; Drinks</span>
                <span class="pv">₱<?= number_format($foodTotal, 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($discountAmount > 0): ?>
            <div class="pay-row">
                <span class="pl"><?= $discountType === 'pwd' ? 'PWD Discount' : 'Senior Citizen Discount' ?></span>
                <span class="pv" style="color:#66bb6a;">− ₱<?= number_format($discountAmount, 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($paymentMethod === 'cash' && $cashGiven !== null): ?>
            <div class="pay-row">
                <span class="pl">Cash Tendered</span>
                <span class="pv">₱<?= number_format($cashGiven, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="pay-row total">
                <span class="pl">Total</span>
                <span class="pv">₱<?= number_format($totalPrice, 2) ?></span>
            </div>
        </div>

        <div class="receipt-footer">
            <div class="receipt-ref-text">Thank you for visiting Peak's Cinema! 🎬</div>
            <div class="receipt-ts">Ref: <?= htmlspecialchars($bookingRef) ?> · Issued: <?= date('Y-m-d H:i:s') ?></div>
        </div>
    </div>

    <!-- QR Code -->
    <div class="qr-section">
        <p>Scan at entrance</p>
        <img src="qrcode.png" alt="QR Code">
    </div>

    <!-- Action buttons -->
    <div class="actions">
        <button class="btn-print" onclick="downloadReceipt()">🖨 Download / Print</button>
        <a href="home.php" class="btn-new">← Back to Home</a>
    </div>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// ── Header Scroll Behavior ──
(function() {
    const h = document.getElementById('mainHeader');
    let last = window.scrollY, tick = false;
    window.addEventListener('scroll', () => {
        if (!tick) {
            requestAnimationFrame(() => {
                const cur = window.scrollY;
                if (cur > last && cur > 80) {
                    h.style.transform = 'translateY(-100%)';
                } else {
                    h.style.transform = 'translateY(0)';
                }
                last = cur; tick = false;
            });
            tick = true;
        }
    }, { passive: true });
})();

    function downloadReceipt() {
        if (typeof window.html2canvas === 'undefined' || !window.jspdf || !window.jspdf.jsPDF) {
            return;
        }

        const source = document.getElementById('receiptCard');
        if (!source) {
            return;
        }

        const exportWrap = document.createElement('div');
        exportWrap.style.position = 'fixed';
        exportWrap.style.left = '-9999px';
        exportWrap.style.top = '0';
        exportWrap.style.padding = '0';
        exportWrap.style.margin = '0';
        exportWrap.style.background = '#ffffff';
        exportWrap.style.pointerEvents = 'none';
        exportWrap.style.zIndex = '-1';
        exportWrap.style.visibility = 'visible';
        exportWrap.style.display = 'block';
        exportWrap.style.overflow = 'visible';

        const exportCard = source.cloneNode(true);
        const cardWidth = Math.ceil(source.getBoundingClientRect().width || source.offsetWidth || 560);
        exportWrap.style.width = `${cardWidth}px`;
        exportCard.style.width = `${cardWidth}px`;
        exportCard.style.maxWidth = `${cardWidth}px`;
        exportCard.style.margin = '0';
        exportCard.style.border = '1px solid #ddd';
        exportCard.style.background = '#1a1a1a';
        exportCard.style.boxShadow = 'none';
        exportCard.style.display = 'block';
        exportCard.style.overflow = 'hidden';

        exportWrap.appendChild(exportCard);
        document.body.appendChild(exportWrap);

        const bookingRef = '<?= htmlspecialchars($bookingRef) ?>';

        window.html2canvas(exportCard, {
            scale: 3,
            useCORS: true,
            backgroundColor: '#ffffff',
            width: cardWidth,
            windowWidth: cardWidth,
            scrollX: 0,
            scrollY: 0
        }).then((canvas) => {
            const imgData = canvas.toDataURL('image/png');
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const margin = 18;
            const maxWidth = pageWidth - (margin * 2);
            const maxHeight = pageHeight - (margin * 2);
            const canvasRatio = canvas.width / canvas.height;

            let renderWidth = maxWidth;
            let renderHeight = renderWidth / canvasRatio;

            if (renderHeight > maxHeight) {
                renderHeight = maxHeight;
                renderWidth = renderHeight * canvasRatio;
            }

            // Keep the receipt a little smaller than full-page for a cleaner centered PDF.
            renderWidth *= 0.9;
            renderHeight *= 0.9;

            const x = (pageWidth - renderWidth) / 2;
            const y = (pageHeight - renderHeight) / 2;

            pdf.addImage(imgData, 'PNG', x, y, renderWidth, renderHeight);
            pdf.save(`receipt_${bookingRef}.pdf`);
            exportWrap.remove();
        }).catch(() => {
            exportWrap.remove();
        });
    }

// ── Notifications Logic ──
function toggleNotif(e) {
    e.stopPropagation();
    const dd = document.getElementById('notifDropdown');
    if (dd) {
        dd.classList.toggle('open');
        if (dd.classList.contains('open')) loadNotif();
    }
}

function loadNotif() {
    fetch('notifications_api.php')
        .then(r => r.json())
        .then(data => {
            const list  = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');
            if (!list || !data || data.error) return;

            if (data.unread > 0) {
                badge.textContent = data.unread > 9 ? '9+' : data.unread;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }

            if (!data.notifications?.length) {
                list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
                return;
            }

            list.innerHTML = data.notifications.map(n => `
                <a class="notif-item ${n.IsRead == 0 ? 'unread' : ''}" href="notifications_page.php?id=${n.Notif_ID}">
                    <div class="notif-dot ${n.IsRead == 1 ? 'read' : ''}"></div>
                    <div class="notif-item-body">
                        <div class="notif-item-title">${n.Title}</div>
                        <div class="notif-item-msg">${n.Message}</div>
                        <div class="notif-item-time">${n.time_ago || 'Just now'}</div>
                    </div>
                </a>
            `).join('');
        }).catch(err => console.error('Notif error:', err));
}

function markAllRead() {
    fetch('notifications_api.php?action=mark_read')
        .then(r => r.json())
        .then(() => loadNotif())
        .catch(err => console.error('Mark read error:', err));
}

document.addEventListener('click', () => {
    document.getElementById('notifDropdown')?.classList.remove('open');
});

<?php if (isset($_SESSION['user_id'])): ?>
loadNotif();
setInterval(loadNotif, 60000);
<?php endif; ?>
</script>
</body>
</html>
