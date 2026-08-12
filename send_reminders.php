<?php
/**
 * PeaksCinema - Showtime Reminder Script
 *
 * Sends email + in-app notifications to customers whose screening
 * starts about 30 minutes from now.
 *
 * HOW TO RUN
 *
 * Option A - Manual:
 *   Visit http://localhost/PeaksCinema/send_reminders.php?secret=peaks2024
 *
 * Option B - Task Scheduler:
 *   D:\xampp\php\php.exe D:\xampp\htdocs\PeaksCinema\send_reminders.php
 */

date_default_timezone_set('Asia/Manila');
include('peakscinemas_database.php');

define('REMINDER_KEY', 'peaks2024');

$isWeb = (php_sapi_name() !== 'cli');
$simulate = (getenv('PC_REMINDER_SIM') === '1') || ((isset($_GET['simulate']) && $_GET['simulate'] == '1'));
if (!is_dir(__DIR__ . '/logs')) { @mkdir(__DIR__ . '/logs', 0777, true); }
$logFile = __DIR__ . '/logs/reminders.log';
$log = function(string $msg) use ($logFile) {
    @error_log('[' . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL, 3, $logFile);
};

if ($isWeb && (($_GET['secret'] ?? '') !== REMINDER_KEY)) {
    http_response_code(403);
    die('Access denied. Add ?secret=peaks2024 to the URL.');
}

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

$stmt = $conn->prepare("
    SELECT
        tk.Ticket_ID,
        tk.Customer_ID,
        COALESCE(tk.BookingRef, CONCAT('TK-', tk.Ticket_ID)) AS BookingRef,
        c.Name AS CustomerName,
        c.Email AS CustomerEmail,
        m.MovieName,
        m.MoviePoster,
        ml.MallName,
        th.TheaterName,
        ts.Date,
        ts.StartTime,
        ts.ScreeningType,
        s.SeatRow,
        s.SeatColumn
    FROM ticket tk
    JOIN customer c  ON c.Customer_ID  = tk.Customer_ID
    JOIN movie m     ON m.Movie_ID     = tk.Movie_ID
    JOIN timeslot ts ON ts.TimeSlot_ID = tk.TimeSlot_ID
    JOIN theater th  ON th.Theater_ID  = ts.Theater_ID
    JOIN mall ml     ON ml.Mall_ID     = th.Mall_ID
    JOIN seats s     ON s.Seat_ID      = tk.Seat_ID
    LEFT JOIN reminders_sent rs ON rs.Ticket_ID = tk.Ticket_ID
    WHERE tk.Status = 1
      AND rs.Ticket_ID IS NULL
      AND STR_TO_DATE(CONCAT(ts.Date, ' ', ts.StartTime), '%Y-%m-%d %H:%i')
            BETWEEN DATE_ADD(NOW(), INTERVAL 25 MINUTE)
                AND DATE_ADD(NOW(), INTERVAL 35 MINUTE)
    ORDER BY tk.Customer_ID, ts.Date, ts.StartTime
");
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($rows)) {
    $log('No reminders to send');
    echo $isWeb ? '<p>No reminders to send right now.</p>' : "No reminders to send.\n";
    exit;
}

$grouped = [];
foreach ($rows as $row) {
    $key = $row['Customer_ID'] . '_' . $row['BookingRef'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'customer_id' => $row['Customer_ID'],
            'customer_name' => $row['CustomerName'],
            'email' => $row['CustomerEmail'],
            'booking_ref' => $row['BookingRef'],
            'movie' => $row['MovieName'],
            'poster' => $row['MoviePoster'],
            'mall' => $row['MallName'],
            'theater' => $row['TheaterName'],
            'date' => $row['Date'],
            'start_time' => $row['StartTime'],
            'type' => $row['ScreeningType'],
            'seats' => [],
            'ticket_ids' => [],
        ];
    }
    $grouped[$key]['seats'][] = $row['SeatRow'] . $row['SeatColumn'];
    $grouped[$key]['ticket_ids'][] = (int)$row['Ticket_ID'];
}

$sent = 0;

foreach ($grouped as $booking) {
    $log("Processing booking {$booking['booking_ref']} for customer {$booking['customer_id']}");
    $parts = explode(':', $booking['start_time']);
    $hour = (int)($parts[0] ?? 0);
    $minute = str_pad((string)((int)($parts[1] ?? 0)), 2, '0', STR_PAD_LEFT);
    $ampm = $hour >= 12 ? 'PM' : 'AM';
    $hour12 = $hour % 12 ?: 12;
    $timeStr = "{$hour12}:{$minute} {$ampm}";
    $dateStr = date('F d, Y', strtotime($booking['date']));
    $seatsStr = implode(', ', $booking['seats']);
    $firstName = explode(' ', trim((string)$booking['customer_name']))[0] ?? 'Customer';

    $notifTitle = '🎬 Your movie starts soon!';
    $notifMsg = "Hey, {$firstName}! Your movie '{$booking['movie']}' at {$booking['mall']} starts in about 30 minutes. Don't miss it! Ref: {$booking['booking_ref']}";
    $notifType = 'reminder';
    $notif = $conn->prepare('INSERT INTO notifications (Customer_ID, Title, Message, Type) VALUES (?,?,?,?)');
    $notif->bind_param('isss', $booking['customer_id'], $notifTitle, $notifMsg, $notifType);
    if (!$notif->execute()) {
        $log('Notification insert failed: ' . $conn->error);
    }

    $mail = new PHPMailer(true);
    $emailOk = false;
    if ($simulate) {
        $emailOk = true;
        $log("Simulated email OK for booking {$booking['booking_ref']}");
    } else {
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'peakscinema@gmail.com';
            $mail->Password = 'pggs pvye frmk tmah';
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;
            $mail->setFrom('peakscinema@gmail.com', "Peak's Cinema");
            $mail->addAddress($booking['email'], $booking['customer_name']);
            $mail->isHTML(true);
            $mail->Subject = "🎬 Your movie starts in 30 minutes – don't miss it!";
            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Your Movie Reminder!</title>
                <style>
                    body { font-family: 'Outfit', Arial, sans-serif; background-color: #0f0f0f; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                    .container { max-width: 520px; margin: 20px auto; background-color: #1a1a1a; border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); }
                    .header { background-color: #ff4d4d; padding: 24px 28px; text-align: center; }
                    .header h1 { color: #ffffff; font-size: 24px; margin: 0; font-weight: 800; }
                    .header p { color: rgba(255,255,255,0.8); font-size: 15px; margin: 4px 0 0; }
                    .content { padding: 24px 28px; color: #F9F9F9; }
                    .content h2 { color: #ff4d4d; font-size: 20px; margin-top: 0; margin-bottom: 16px; font-weight: 700; }
                    .content p { font-size: 15px; line-height: 1.6; margin-bottom: 16px; color: rgba(249,249,249,0.8); }
                    .movie-details { background-color: #0f0f0f; border-radius: 8px; padding: 18px 22px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.08); }
                    .movie-details table { width: 100%; border-collapse: collapse; }
                    .movie-details th, .movie-details td { padding: 8px 0; text-align: left; font-size: 14px; border-bottom: 1px solid rgba(255,255,255,0.05); }
                    .movie-details th { color: rgba(249,249,249,0.5); font-weight: 500; width: 100px; }
                    .movie-details td { color: #F9F9F9; font-weight: 600; }
                    .movie-details tr:last-child th, .movie-details tr:last-child td { border-bottom: none; }
                    .cta-button-wrap { text-align: center; margin-top: 24px; margin-bottom: 16px; }
                    .cta-button { background-color: #ff4d4d; color: #ffffff; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 16px; display: inline-block; transition: background-color 0.2s ease; }
                    .cta-button:hover { background-color: #e03c3c; }
                    .footer { background-color: #0f0f0f; padding: 16px 28px; text-align: center; font-size: 12px; color: rgba(249,249,249,0.4); border-top: 1px solid rgba(255,255,255,0.08); }
                    .footer p { margin: 0; }
                    .seats-list { display: block; margin-top: 5px; }
                    .seat-chip { background-color: rgba(255,77,77,0.1); color: #ff4d4d; padding: 4px 8px; border-radius: 5px; font-size: 12px; margin-right: 5px; margin-bottom: 5px; display: inline-block; }
                    @media only screen and (max-width: 600px) {
                        .container { width: 100%; margin: 0; border-radius: 0; }
                        .content, .header, .footer { padding: 20px; }
                        .header h1 { font-size: 22px; }
                        .header p { font-size: 14px; }
                        .content h2 { font-size: 18px; }
                        .content p { font-size: 14px; }
                        .movie-details th, .movie-details td { font-size: 13px; }
                        .cta-button { padding: 10px 20px; font-size: 14px; }
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🎬 Heads Up, {$firstName}!</h1>
                        <p>Your movie is about to start at Peak's Cinema!</p>
                    </div>
                    <div class='content'>
                        <h2>It's almost showtime!</h2>
                        <p>Just a friendly reminder that <strong>{$booking['movie']}</strong> is starting in about 30 minutes. Get ready for an amazing cinematic experience!</p>
                        
                        <div class='movie-details'>
                            <table>
                                <tr><th>Movie:</th><td>{$booking['movie']}</td></tr>
                                <tr><th>When:</th><td>{$dateStr} at {$timeStr}</td></tr>
                                <tr><th>Where:</th><td>{$booking['mall']} - {$booking['theater']}</td></tr>
                                <tr><th>Your Seat(s):</th><td><span class='seats-list'>{$seatsStr}</span></td></tr>
                                <tr><th>Booking Ref:</th><td>{$booking['booking_ref']}</td></tr>
                            </table>
                        </div>
                        
                        <p>We recommend arriving a few minutes early to grab your snacks and find your perfect spot. Enjoy the show!</p>
                        
                        <div class='cta-button-wrap'>
                            <a href='{$_SERVER['HTTP_ORIGIN']}/PeaksCinema/my_bookings.php' class='cta-button'>View My Booking</a>
                        </div>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " Peak's Cinema. This is an automated reminder.</p>
                        <p>Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            $mail->AltBody = "Reminder: {$booking['movie']} at {$booking['mall']} starts at {$timeStr}. Your seat(s): {$seatsStr}. Ref: {$booking['booking_ref']}. View your booking: {$_SERVER['HTTP_ORIGIN']}/PeaksCinema/my_bookings.php";
            $mail->send();
            $emailOk = true;
        } catch (Exception $e) {
            $log("Email send failed for booking {$booking['booking_ref']}: " . $e->getMessage());
        }
    }

    if ($emailOk) {
        $ok = true;
        $conn->begin_transaction();
        try {
            foreach ($booking['ticket_ids'] as $ticketId) {
                // We use INSERT IGNORE to prevent duplicate errors if the script runs twice rapidly,
                // but we still want to know if it failed for other reasons.
                $insert = $conn->prepare('INSERT IGNORE INTO reminders_sent (Ticket_ID) VALUES (?)');
                if (!$insert) { 
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $insert->bind_param('i', $ticketId);
                if (!$insert->execute()) {
                    throw new Exception("Execute failed for Ticket_ID $ticketId: " . $insert->error);
                }
                
                if ($conn->affected_rows === 0) {
                    $log("Reminder already existed for Ticket_ID $ticketId - skipping.");
                } else {
                    $log("Inserted reminder record for Ticket_ID $ticketId.");
                }
            }
            $conn->commit();
            $log("Successfully committed reminders_sent for booking {$booking['booking_ref']}");
        } catch (Exception $e) {
            $conn->rollback();
            $ok = false;
            $log("Failed to record reminders_sent for booking {$booking['booking_ref']}: " . $e->getMessage());
        }
    } else {
        $log("Skipped reminders_sent insert for booking {$booking['booking_ref']} due to email failure");
    }

    $sent++;
    if ($isWeb) {
        echo '<p>Sent reminder to ' . htmlspecialchars($booking['customer_name']) . ' for ' . htmlspecialchars($booking['movie']) . ' at ' . htmlspecialchars($timeStr) . ($emailOk ? ' (email + in-app)' : ' (in-app only)') . "</p>\n";
    } else {
        echo 'Sent: ' . $booking['customer_name'] . ' - ' . $booking['movie'] . ' at ' . $timeStr . "\n";
    }
}

echo $isWeb
    ? '<p><strong>Done. ' . $sent . ' reminder(s) sent.</strong></p>'
    : "Done. {$sent} reminder(s) sent.\n";
