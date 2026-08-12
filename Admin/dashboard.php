<?php
    date_default_timezone_set('Asia/Manila');
    include("../peakscinemas_database.php");
    require_once(dirname(__DIR__) . "/cancellation_request_helpers.php");
    ensureCancellationRequestSchema($conn);

    // ── Stats ─────────────────────────────────────────────────────
    $totalMovies    = $conn->query("SELECT COUNT(*) AS c FROM movie")->fetch_assoc()['c'];
    $totalMalls     = $conn->query("SELECT COUNT(*) AS c FROM mall")->fetch_assoc()['c'];
    $totalTheaters  = $conn->query("SELECT COUNT(*) AS c FROM theater")->fetch_assoc()['c'];
    $totalScreenings= $conn->query("SELECT COUNT(*) AS c FROM timeslot WHERE Date >= CURDATE()")->fetch_assoc()['c'];
    $totalBookings  = $conn->query("SELECT COUNT(*) AS c FROM ticket")->fetch_assoc()['c'];

    $totalRevenue = 0;
    $colResult = $conn->query("SHOW COLUMNS FROM payment");
    $paymentCol = null;
    $numericTypes = ['int','bigint','decimal','float','double','numeric','mediumint','smallint','tinyint'];
    if ($colResult) {
        while ($col = $colResult->fetch_assoc()) {
            $baseType = strtolower(explode('(', $col['Type'])[0]);
            if (in_array($baseType, $numericTypes) && stripos($col['Field'], 'ID') === false) {
                $paymentCol = $col['Field'];
                break;
            }
        }
    }
    if ($paymentCol) {
        $revRow = $conn->query("SELECT COALESCE(SUM(`$paymentCol`),0) AS r FROM payment");
        if ($revRow) $totalRevenue = $revRow->fetch_assoc()['r'];
    }

    // Recent bookings (last 5)
    $recentBookings = $conn->query("
        SELECT t.Ticket_ID, c.Name AS CustomerName, m.MovieName, ts.Date, ts.StartTime
        FROM ticket t
        JOIN customer c ON t.Customer_ID = c.Customer_ID
        JOIN timeslot ts ON t.TimeSlot_ID = ts.TimeSlot_ID
        JOIN movie m ON ts.Movie_ID = m.Movie_ID
        ORDER BY t.Ticket_ID DESC
        LIMIT 5
    ");

    // Upcoming screenings (next 5)
    $upcomingScreenings = $conn->query("
        SELECT m.MovieName, ts.Date, ts.StartTime, ts.ScreeningType, th.TheaterName, ml.MallName
        FROM timeslot ts
        JOIN movie m ON ts.Movie_ID = m.Movie_ID
        JOIN theater th ON ts.Theater_ID = th.Theater_ID
        JOIN mall ml ON th.Mall_ID = ml.Mall_ID
        WHERE ts.Date >= CURDATE()
        ORDER BY ts.Date ASC, ts.StartTime ASC
        LIMIT 5
    ");

    // Now showing movies
    $nowShowing = $conn->query("SELECT MovieName, MoviePoster FROM movie WHERE MovieAvailability = 'Now Showing' LIMIT 6");

    // ── Queue Manager ─────────────────────────────────────────────
    $conn->query("CREATE TABLE IF NOT EXISTS `cinema_queue` (
        `Queue_ID`          INT AUTO_INCREMENT PRIMARY KEY,
        `mall_id`           INT NOT NULL,
        `area`              VARCHAR(50) NOT NULL,
        `status`            ENUM('open','busy','closed') DEFAULT 'open',
        `queue_length`      INT DEFAULT 0,
        `current_wait_mins` INT DEFAULT 0,
        `updated_at`        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `mall_area` (`mall_id`,`area`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $qMsg = ''; $qMsgType = '';
    $cancelMsg = ''; $cancelMsgType = '';

    $qAreaLabels = ['entrance'=>'Entrance Gate','ticketing'=>'Ticketing Counter','concession'=>'Concession Stand',
                    'cinema_1'=>'Cinema Hall 1','cinema_2'=>'Cinema Hall 2','cinema_3'=>'Cinema Hall 3',
                    'restroom'=>'Restrooms','parking'=>'Parking Area'];
    $qAreaIcons  = ['entrance'=>'🚪','ticketing'=>'🎟','concession'=>'🍿',
                    'cinema_1'=>'🎬','cinema_2'=>'🎬','cinema_3'=>'🎬','restroom'=>'🚻','parking'=>'🅿️'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['review_cancel_request'])) {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $decision = $_POST['decision'] ?? '';

            if (!$requestId || !in_array($decision, ['approve', 'reject'], true)) {
                $cancelMsg = 'Invalid cancellation review request.';
                $cancelMsgType = 'err';
            } else {
                $conn->begin_transaction();
                try {
                    $reqStmt = $conn->prepare("SELECT Request_ID, BookingRef, Customer_ID, TimeSlot_ID, Reason, Status FROM booking_cancellation_requests WHERE Request_ID = ? LIMIT 1");
                    $reqStmt->bind_param("i", $requestId);
                    $reqStmt->execute();
                    $request = $reqStmt->get_result()->fetch_assoc();

                    if (!$request || ($request['Status'] ?? '') !== 'pending') {
                        throw new RuntimeException('This cancellation request is no longer pending.');
                    }

                    $reviewedAt = date('Y-m-d H:i:s');

                    if ($decision === 'approve') {
                        $ticketStmt = $conn->prepare("SELECT Ticket_ID, Seat_ID FROM ticket WHERE BookingRef = ? AND Customer_ID = ? AND Status = 1");
                        $ticketStmt->bind_param("si", $request['BookingRef'], $request['Customer_ID']);
                        $ticketStmt->execute();
                        $tickets = $ticketStmt->get_result()->fetch_all(MYSQLI_ASSOC);

                        if (empty($tickets)) {
                            throw new RuntimeException('No active tickets are left for this booking.');
                        }

                        // Calculate total refund amount from payments
                        $refundAmount = 0;
                        foreach ($tickets as $ticket) {
                            $paymentStmt = $conn->prepare("SELECT COALESCE(SUM(AmountPaid), 0) AS total FROM payment WHERE Ticket_ID = ?");
                            $paymentStmt->bind_param("i", $ticket['Ticket_ID']);
                            $paymentStmt->execute();
                            $paymentResult = $paymentStmt->get_result()->fetch_assoc();
                            $refundAmount += (float)($paymentResult['total'] ?? 0);
                        }

                        foreach ($tickets as $ticket) {
                            $cancelTicket = $conn->prepare("UPDATE ticket SET Status = 2, CancelledAt = ? WHERE Ticket_ID = ?");
                            $cancelTicket->bind_param("si", $reviewedAt, $ticket['Ticket_ID']);
                            $cancelTicket->execute();

                            if (!empty($ticket['Seat_ID'])) {
                                $releaseSeat = $conn->prepare("UPDATE seats SET SeatAvailability = 'Available', HoldToken = NULL, HoldExpiresAt = NULL WHERE Seat_ID = ?");
                                $releaseSeat->bind_param("i", $ticket['Seat_ID']);
                                $releaseSeat->execute();
                            }
                        }

                        $updateRequest = $conn->prepare("UPDATE booking_cancellation_requests SET Status = 'approved', ReviewedAt = ?, AdminNote = ? WHERE Request_ID = ?");
                        $approveNote = 'Approved by admin';
                        $updateRequest->bind_param("ssi", $reviewedAt, $approveNote, $requestId);
                        $updateRequest->execute();

                        $refundMessage = 'Your cancellation/refund request for booking ' . $request['BookingRef'] . ' has been approved. Your tickets are now cancelled.';
                        if ($refundAmount > 0) {
                            $refundMessage .= ' A refund of ₱' . number_format($refundAmount, 2) . ' will be processed within 3-5 business days.';
                        }

                        addCustomerNotification(
                            $conn,
                            (int) $request['Customer_ID'],
                            'Cancellation Approved',
                            $refundMessage,
                            'cancellation'
                        );

                        $cancelMsg = 'Cancellation request approved for booking ' . $request['BookingRef'] . '.';
                        $cancelMsgType = 'ok';
                    } else {
                        $updateRequest = $conn->prepare("UPDATE booking_cancellation_requests SET Status = 'rejected', ReviewedAt = ?, AdminNote = ? WHERE Request_ID = ?");
                        $rejectNote = 'Rejected by admin';
                        $updateRequest->bind_param("ssi", $reviewedAt, $rejectNote, $requestId);
                        $updateRequest->execute();

                        addCustomerNotification(
                            $conn,
                            (int) $request['Customer_ID'],
                            'Cancellation Request Rejected',
                            'Your cancellation/refund request for booking ' . $request['BookingRef'] . ' was reviewed and rejected. Please contact the cinema if you need help.',
                            'cancellation'
                        );

                        $cancelMsg = 'Cancellation request rejected for booking ' . $request['BookingRef'] . '.';
                        $cancelMsgType = 'ok';
                    }

                    $conn->commit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    $cancelMsg = $e->getMessage() ?: 'Could not review the cancellation request.';
                    $cancelMsgType = 'err';
                }
            }
        } elseif (isset($_POST['q_update_area'])) {
            $qMallId = (int)$_POST['q_mall_id'];
            $qArea   = trim($_POST['q_area'] ?? '');
            $qStatus = $_POST['q_status'] ?? 'open';
            $qLen    = max(0, (int)$_POST['q_queue_length']);
            $qWait   = max(0, (int)$_POST['q_wait_mins']);
            $qNow    = date('Y-m-d H:i:s');

            if (!in_array($qArea, array_keys($qAreaLabels)) || !in_array($qStatus, ['open','busy','closed']) || !$qMallId) {
                $qMsg = 'Invalid queue input.'; $qMsgType = 'err';
            } else {
                $qa  = mysqli_real_escape_string($conn, $qArea);
                $qst = mysqli_real_escape_string($conn, $qStatus);
                $conn->query("INSERT INTO cinema_queue (mall_id,area,status,queue_length,current_wait_mins,updated_at)
                    VALUES ($qMallId,'$qa','$qst',$qLen,$qWait,'$qNow')
                    ON DUPLICATE KEY UPDATE status='$qst',queue_length=$qLen,current_wait_mins=$qWait,updated_at='$qNow'");
                $qMsg = 'Queue updated: ' . ($qAreaLabels[$qArea] ?? $qArea) . ' — ' . $qStatus . ', ' . $qLen . ' people, ' . $qWait . 'min wait.';
                $qMsgType = 'ok';
            }
        } elseif (isset($_POST['q_clear_mall'])) {
            $qMallId = (int)$_POST['q_mall_id'];
            $conn->query("DELETE FROM cinema_queue WHERE mall_id=$qMallId");
            $qMsg = 'Queue data cleared for this mall.'; $qMsgType = 'ok';
        }
    }

    $qMallsRes  = $conn->query("SELECT Mall_ID, MallName FROM mall ORDER BY MallName ASC");
    $qMalls     = $qMallsRes ? $qMallsRes->fetch_all(MYSQLI_ASSOC) : [];
    $qRes       = $conn->query("SELECT q.*, m.MallName FROM cinema_queue q JOIN mall m ON m.Mall_ID=q.mall_id ORDER BY m.MallName, q.area");
    $qRows      = $qRes ? $qRes->fetch_all(MYSQLI_ASSOC) : [];
    $qByMall    = [];
    foreach ($qRows as $r) $qByMall[$r['mall_id']][] = $r;

    $pendingCancelCountRow = $conn->query("SELECT COUNT(*) AS total FROM booking_cancellation_requests WHERE Status = 'pending'");
    $pendingCancelCount = $pendingCancelCountRow ? (int) ($pendingCancelCountRow->fetch_assoc()['total'] ?? 0) : 0;

    $pendingCancelStmt = $conn->prepare("
        SELECT
            r.Request_ID,
            r.BookingRef,
            r.Reason,
            r.RequestedAt,
            c.Name AS CustomerName,
            ts.Date,
            ts.StartTime,
            COALESCE(MIN(m.MovieName), 'Booking') AS MovieName
        FROM booking_cancellation_requests r
        JOIN customer c ON c.Customer_ID = r.Customer_ID
        LEFT JOIN timeslot ts ON ts.TimeSlot_ID = r.TimeSlot_ID
        LEFT JOIN ticket tk ON tk.BookingRef = r.BookingRef AND tk.Customer_ID = r.Customer_ID
        LEFT JOIN movie m ON m.Movie_ID = tk.Movie_ID
        WHERE r.Status = 'pending'
        GROUP BY r.Request_ID, r.BookingRef, r.Reason, r.RequestedAt, c.Name, ts.Date, ts.StartTime
        ORDER BY r.RequestedAt ASC
        LIMIT 8
    ");
    $pendingCancelRequests = [];
    if ($pendingCancelStmt) {
        $pendingCancelStmt->execute();
        $pendingCancelRequests = $pendingCancelStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Dashboard – PeaksCinemas Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        *, *::before, *::after { font-family: 'Outfit', sans-serif; }
        table, td, th, input, button, select, textarea { font-family: 'Outfit', sans-serif; }

        body {
            font-family: 'Outfit', sans-serif;
            background: #0f0f0f;
            color: #F9F9F9;
            min-height: 100vh;
            padding-top: 70px;
            padding-bottom: 60px;
        }
        body::before {
            content: '';
            position: fixed; inset: 0;
            background: url('../movie-background-collage.jpg') center/cover no-repeat;
            opacity: 0.04; z-index: 0; pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed; inset: 0;
            background: radial-gradient(ellipse at center, transparent 20%, #0f0f0f 75%);
            z-index: 1; pointer-events: none;
        }
        header, nav, .page-wrapper { position: relative; z-index: 10; }

        /* ── Header ── */
        header {
            background: #1C1C1C;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: fixed;
            top: 0; left: 0; width: 100%;
            z-index: 1000;
            height: 60px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .logo { display:flex; align-items:center; }
        .logo img { height: 42px; width: auto; filter: invert(1); display: block; }

        nav { display: flex; gap: 4px; }
        nav a { color: rgba(249,249,249,0.5); text-decoration: none; font-size: 0.8rem; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: all 0.2s; }
        nav a:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }

        @media (max-width: 1024px) {
            header { padding: 0 15px; }
            nav { display: none; }
            .mobile-nav-toggle { display: flex !important; }
        }

        .mobile-nav-toggle {
            display: none;
            flex-direction: column;
            gap: 4px;
            cursor: pointer;
            padding: 10px;
        }
        .mobile-nav-toggle span {
            width: 24px;
            height: 2px;
            background: #fff;
            border-radius: 2px;
        }

        #mobileMenu {
            display: none;
            position: fixed;
            top: 60px; left: 0; width: 100%;
            background: #1C1C1C;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            z-index: 999;
            padding: 10px 0;
        }
        #mobileMenu a {
            display: block;
            padding: 12px 20px;
            color: rgba(249,249,249,0.6);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }
        #mobileMenu a.active { color: #ff4d4d; background: rgba(255,77,77,0.05); }

        /* ── Page ── */
        .page-wrapper {
            width: 95%;
            max-width: 1200px;
            margin: 32px auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        @media (max-width: 768px) {
            .page-wrapper { margin: 20px auto; gap: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr !important; }
            .two-col { grid-template-columns: 1fr !important; }
            .q-two-col { grid-template-columns: 1fr !important; }
            .page-header { flex-direction: column; align-items: flex-start !important; gap: 10px; }
            .page-date { text-align: left !important; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr !important; }
            .quick-grid { grid-template-columns: 1fr !important; }
            .quick-btn { grid-column: span 1 !important; }
        }

        .page-header { display: flex; align-items: flex-end; justify-content: space-between; }

        .page-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin-bottom: 5px;
        }

        .page-title { font-size: 1.6rem; font-weight: 800; color: #F9F9F9; margin-bottom: 4px; }

        .page-date {
            font-size: 0.8rem;
            color: rgba(249,249,249,0.3);
            text-align: right;
        }

        /* ── Stats grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .stat-card {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 14px;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: border-color 0.2s, transform 0.2s;
        }
        .stat-card:hover { border-color: rgba(255,77,77,0.25); transform: translateY(-2px); }

        .stat-icon {
            width: 46px; height: 46px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-icon.red    { background: rgba(255,77,77,0.12);  border: 1px solid rgba(255,77,77,0.2); }
        .stat-icon.blue   { background: rgba(77,150,255,0.12); border: 1px solid rgba(77,150,255,0.2); }
        .stat-icon.green  { background: rgba(77,210,120,0.12); border: 1px solid rgba(77,210,120,0.2); }
        .stat-icon.yellow { background: rgba(255,200,50,0.12); border: 1px solid rgba(255,200,50,0.2); }
        .stat-icon.purple { background: rgba(180,100,255,0.12);border: 1px solid rgba(180,100,255,0.2); }
        .stat-icon.teal   { background: rgba(50,210,200,0.12); border: 1px solid rgba(50,210,200,0.2); }

        .stat-info { flex: 1; }

        .stat-label {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.35);
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: #F9F9F9;
            line-height: 1;
        }

        .stat-sub {
            font-size: 0.72rem;
            color: rgba(249,249,249,0.3);
            margin-top: 3px;
        }

        /* ── Two-col layout ── */
        .two-col { display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; }

        /* ── Panel ── */
        .panel {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 14px;
            overflow: hidden;
        }

        .panel-header {
            padding: 14px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-header h2 {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.45);
        }

        .panel-link {
            font-size: 0.75rem;
            color: #ff4d4d;
            text-decoration: none;
            font-weight: 600;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .panel-link:hover { opacity: 1; }

        .panel-body { padding: 16px 20px; }

        /* ── Table ── */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }

        @media (max-width: 600px) {
            .data-table thead { display: none; }
            .data-table tr { display: block; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.06); }
            .data-table td { display: block; padding: 4px 10px; border: none; text-align: left !important; }
            .data-table td::before { content: attr(data-label); font-size: 0.65rem; font-weight: 700; color: rgba(249,249,249,0.3); display: block; text-transform: uppercase; margin-bottom: 2px; }
            .data-table tr:last-child { border-bottom: none; }
        }

        .data-table th {
            text-align: left;
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.3);
            padding: 6px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .data-table td {
            padding: 10px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            color: #F9F9F9;
            vertical-align: middle;
        }

        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(255,255,255,0.02); }

        .td-muted { color: rgba(249,249,249,0.4); font-size: 0.78rem; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .badge-2d     { background: rgba(255,255,255,0.08); color: rgba(249,249,249,0.7); }
        .badge-3d     { background: rgba(77,150,255,0.12);  color: #7ec8ff; }
        .badge-imax   { background: rgba(255,200,50,0.12);  color: #ffc83d; }
        .badge-4dx    { background: rgba(180,100,255,0.12); color: #c87eff; }

        /* ── Quick actions ── */
        .quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .quick-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            color: #F9F9F9;
            text-decoration: none;
            font-family: 'Outfit', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
        }
        .quick-btn:hover {
            background: rgba(255,77,77,0.08);
            border-color: rgba(255,77,77,0.3);
            color: #ff4d4d;
            transform: translateY(-1px);
        }

        .quick-btn-icon {
            width: 34px; height: 34px;
            background: rgba(255,77,77,0.1);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .quick-btn:hover .quick-btn-icon { background: rgba(255,77,77,0.2); }

        /* ── Now Showing strip ── */
        .movies-strip {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,77,77,0.3) transparent;
        }

        .movie-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 10px 14px;
            white-space: nowrap;
            flex-shrink: 0;
            font-size: 0.82rem;
            font-weight: 600;
            color: #F9F9F9;
        }

        .movie-dot { width: 8px; height: 8px; background: #ff4d4d; border-radius: 50%; flex-shrink: 0; }

        .empty-state {
            text-align: center;
            padding: 28px;
            color: rgba(249,249,249,0.2);
            font-size: 0.82rem;
        }

        .request-banner {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: rgba(255,213,79,0.08);
            border: 1px solid rgba(255,213,79,0.18);
            color: #ffd54f;
        }
        .request-banner strong { color: #fff2b3; }
        .request-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(255,213,79,0.12);
            border: 1px solid rgba(255,213,79,0.2);
            color: #ffd54f;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .request-reason {
            font-size: 0.78rem;
            color: rgba(249,249,249,0.62);
            line-height: 1.55;
            max-width: 420px;
        }
        .request-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .request-btn {
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Outfit', sans-serif;
        }
        .request-btn.approve {
            background: rgba(34,197,94,0.12);
            border: 1px solid rgba(34,197,94,0.24);
            color: #81c784;
        }
        .request-btn.reject {
            background: rgba(255,77,77,0.08);
            border: 1px solid rgba(255,77,77,0.2);
            color: #ff6b6b;
        }
        .request-btn:hover { transform: translateY(-1px); }
        .request-meta {
            font-size: 0.72rem;
            color: rgba(249,249,249,0.38);
            line-height: 1.5;
        }
        .request-msg {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.82rem;
            margin-bottom: 14px;
        }
        .request-msg.ok {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.2);
            color: #81c784;
        }
        .request-msg.err {
            background: rgba(255,77,77,0.08);
            border: 1px solid rgba(255,77,77,0.2);
            color: #ff6b6b;
        }

        /* ── Queue Manager (prefixed q-) ── */
        .q-msg { padding: 12px 16px; border-radius: 9px; font-size: 0.82rem; }
        .q-msg.ok  { background: rgba(76,175,80,0.08);  border: 1px solid rgba(76,175,80,0.2);  color: #81c784; }
        .q-msg.err { background: rgba(255,77,77,0.08);  border: 1px solid rgba(255,77,77,0.2);  border-left: 3px solid #ff4d4d; color: #ff6b6b; }

        .q-two-col { display: grid; grid-template-columns: 320px 1fr; gap: 20px; align-items: start; }
        @media (max-width: 900px) { .q-two-col { grid-template-columns: 1fr; } }

        .q-field { margin-bottom: 13px; }
        .q-field label { display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); margin-bottom: 5px; }
        .q-field select, .q-field input[type=number] { width: 100%; padding: 10px 13px; border-radius: 9px; border: 1px solid rgba(255,255,255,0.1); background: #222; color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.85rem; outline: none; }
        .q-field select:focus, .q-field input:focus { border-color: rgba(255,77,77,0.5); }
        .q-field select option { background: #222; }

        .q-status-sel { display: flex; gap: 8px; }
        .q-status-opt { flex: 1; padding: 8px 6px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.5); font-family: 'Outfit', sans-serif; font-size: 0.75rem; font-weight: 600; cursor: pointer; text-align: center; transition: all 0.2s; }
        .q-status-opt:hover { border-color: rgba(255,255,255,0.2); color: #F9F9F9; }
        .q-status-opt.sel-open   { background: rgba(34,197,94,0.12);  border-color: rgba(34,197,94,0.35);  color: #22c55e; }
        .q-status-opt.sel-busy   { background: rgba(245,158,11,0.12); border-color: rgba(245,158,11,0.35); color: #f59e0b; }
        .q-status-opt.sel-closed { background: rgba(255,77,77,0.12);  border-color: rgba(255,77,77,0.35);  color: #ff4d4d; }

        .q-btn { width: 100%; padding: 12px; border-radius: 9px; border: none; background: #ff4d4d; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all 0.2s; margin-top: 4px; }
        .q-btn:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

        .q-clear-btn { background: rgba(255,77,77,0.06); border: 1px solid rgba(255,77,77,0.2); color: #ff6b6b; font-family: 'Outfit', sans-serif; font-size: 0.72rem; font-weight: 600; padding: 5px 12px; border-radius: 7px; cursor: pointer; transition: all 0.2s; }
        .q-clear-btn:hover { background: rgba(255,77,77,0.15); color: #fff; }

        .q-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }

        @media (max-width: 600px) {
            .q-table thead { display: none; }
            .q-table tr { display: block; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.06); }
            .q-table td { display: block; padding: 4px 14px; border: none; text-align: left !important; }
            .q-table td::before { content: attr(data-label); font-size: 0.65rem; font-weight: 700; color: rgba(249,249,249,0.3); display: block; text-transform: uppercase; margin-bottom: 2px; }
            .q-table tr:last-child { border-bottom: none; }
        }
        .q-table th { font-size: 0.62rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.3); padding: 10px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .q-table td { padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
        .q-table tr:last-child td { border-bottom: none; }
        .q-table tr:hover td { background: rgba(255,255,255,0.02); }

        .qb { font-size: 0.63rem; font-weight: 700; padding: 3px 9px; border-radius: 10px; white-space: nowrap; }
        .qb-open   { background: rgba(34,197,94,0.12);  border: 1px solid rgba(34,197,94,0.25);  color: #22c55e; }
        .qb-busy   { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b; }
        .qb-closed { background: rgba(255,77,77,0.1);   border: 1px solid rgba(255,77,77,0.2);   color: #ff4d4d; }

        .q-bar-wrap { display: flex; align-items: center; gap: 8px; }
        .q-bar { width: 55px; height: 4px; background: rgba(255,255,255,0.08); border-radius: 3px; overflow: hidden; }
        .q-bar-fill { height: 100%; border-radius: 3px; }
        .qf-green  { background: #22c55e; }
        .qf-yellow { background: #f59e0b; }
        .qf-red    { background: #ff4d4d; }

        .q-empty { text-align: center; color: rgba(249,249,249,0.2); padding: 32px; font-size: 0.8rem; }
        .q-mall-row { padding: 10px 18px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .q-tips { font-size: 0.78rem; color: rgba(249,249,249,0.4); line-height: 2; }
    </style>
</head>
<body>

<header>
    <div class="logo"><img src="../peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
    <nav>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="malls_selection_admin.php">Malls</a>
        <a href="malls_selection_admin.php">➕ Add Screenings</a>
        <a href="movie_upload.php">Movie Upload</a>
        <a href="food_admin.php">Food & Drinks</a>
        <a href="theater_upload.php">Theater Upload</a>
        <a href="mall_upload.php">Mall Upload</a>
        <a href="queue_admin.php">Queue Manager</a>
    </nav>
    <div style="display:flex;align-items:center;gap:8px;">
        <div class="mobile-nav-toggle" onclick="toggleMobileMenu()">
            <span></span><span></span><span></span>
        </div>
        <a href="../home.php" class="desktop-only"
           style="color:rgba(249,249,249,0.45);text-decoration:none;font-size:0.78rem;font-weight:500;
                  padding:6px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);
                  transition:all 0.2s;display:flex;align-items:center;gap:5px;"
           onmouseover="this.style.background='rgba(255,255,255,0.06)';this.style.color='#F9F9F9'"
           onmouseout="this.style.background='';this.style.color='rgba(249,249,249,0.45)'">
            &#8592; Customer Site
        </a>
        <a href="admin_logout.php" class="desktop-only"
           style="color:#ff4d4d;text-decoration:none;font-size:0.78rem;font-weight:600;
                  padding:6px 14px;border-radius:6px;border:1px solid rgba(255,77,77,0.3);
                  background:rgba(255,77,77,0.08);transition:all 0.2s;display:flex;align-items:center;gap:5px;"
           onmouseover="this.style.background='rgba(255,77,77,0.18)'"
           onmouseout="this.style.background='rgba(255,77,77,0.08)'"
           onclick="return confirm('Log out of admin panel?')">
            &#x2192; Log Out
        </a>
    </div>
</header>

<div id="mobileMenu">
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="malls_selection_admin.php">Malls</a>
    <a href="movie_upload.php">Movie Upload</a>
    <a href="food_admin.php">Food & Drinks</a>
    <a href="theater_upload.php">Theater Upload</a>
    <a href="mall_upload.php">Mall Upload</a>
    <a href="queue_admin.php">Queue Manager</a>
    <hr style="opacity:0.1; margin:10px 20px;">
    <a href="../home.php">← Customer Site</a>
    <a href="admin_logout.php" style="color:#ff4d4d;">→ Log Out</a>
</div>

<style>
@media (max-width: 1024px) {
    .desktop-only { display: none !important; }
}
</style>

<div class="page-wrapper">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <p class="page-label">Admin Panel</p>
            <h1 class="page-title">Dashboard</h1>
        </div>
        <div class="page-date">
            📅 <?= date('l, F j, Y') ?><br>
            <span style="font-size:0.72rem;"><?= date('g:i A') ?></span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon red">🎬</div>
            <div class="stat-info">
                <div class="stat-label">Movies</div>
                <div class="stat-value"><?= $totalMovies ?></div>
                <div class="stat-sub">In library</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">🏬</div>
            <div class="stat-info">
                <div class="stat-label">Malls</div>
                <div class="stat-value"><?= $totalMalls ?></div>
                <div class="stat-sub"><?= $totalTheaters ?> theater<?= $totalTheaters != 1 ? 's' : '' ?> total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">📅</div>
            <div class="stat-info">
                <div class="stat-label">Upcoming Screenings</div>
                <div class="stat-value"><?= $totalScreenings ?></div>
                <div class="stat-sub">From today onwards</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">🎟</div>
            <div class="stat-info">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-value"><?= $totalBookings ?></div>
                <div class="stat-sub">All time</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal">💰</div>
            <div class="stat-info">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">₱<?= number_format($totalRevenue, 0) ?></div>
                <div class="stat-sub">From payments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">🏛</div>
            <div class="stat-info">
                <div class="stat-label">Theaters</div>
                <div class="stat-value"><?= $totalTheaters ?></div>
                <div class="stat-sub">Across all malls</div>
            </div>
        </div>
    </div>

    <!-- Now Showing strip -->
    <div class="panel">
        <div class="panel-header">
            <h2>🔴 Now Showing</h2>
            <a href="movie_upload.php" class="panel-link">+ Add Movie</a>
        </div>
        <div class="panel-body">
            <?php if ($nowShowing && $nowShowing->num_rows > 0): ?>
            <div class="movies-strip">
                <?php while ($m = $nowShowing->fetch_assoc()): ?>
                <div class="movie-pill">
                    <span class="movie-dot"></span>
                    <?= htmlspecialchars($m['MovieName']) ?>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <div class="empty-state">No movies marked as "Now Showing".</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($pendingCancelCount > 0): ?>
    <div class="request-banner">
        <div><strong><?= $pendingCancelCount ?></strong> cancellation / refund request<?= $pendingCancelCount !== 1 ? 's are' : ' is' ?> waiting for admin review.</div>
        <span class="request-badge">Pending Review</span>
    </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-header">
            <h2>Refund / Cancellation Requests</h2>
            <span class="request-badge"><?= $pendingCancelCount ?> Pending</span>
        </div>
        <div class="panel-body">
            <?php if ($cancelMsg): ?>
            <div class="request-msg <?= $cancelMsgType ?>"><?= htmlspecialchars($cancelMsg) ?></div>
            <?php endif; ?>

            <?php if (!empty($pendingCancelRequests)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Customer</th>
                        <th>Reason</th>
                        <th>Requested</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendingCancelRequests as $request): ?>
                    <tr>
                        <td data-label="Booking">
                            <div style="font-weight:700;"><?= htmlspecialchars($request['BookingRef']) ?></div>
                            <div class="request-meta"><?= htmlspecialchars($request['MovieName']) ?></div>
                            <div class="request-meta"><?= !empty($request['Date']) ? date('M d, Y', strtotime($request['Date'])) : 'Schedule unavailable' ?><?= !empty($request['StartTime']) ? ' · ' . date('g:i A', strtotime($request['StartTime'])) : '' ?></div>
                        </td>
                        <td data-label="Customer">
                            <div><?= htmlspecialchars($request['CustomerName']) ?></div>
                        </td>
                        <td data-label="Reason">
                            <div class="request-reason"><?= nl2br(htmlspecialchars($request['Reason'])) ?></div>
                        </td>
                        <td class="td-muted" data-label="Requested"><?= date('M d, Y g:i A', strtotime($request['RequestedAt'])) ?></td>
                        <td data-label="Actions" style="text-align:right;">
                            <div class="request-actions">
                                <form method="POST">
                                    <input type="hidden" name="review_cancel_request" value="1">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['Request_ID'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="request-btn approve">Approve</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="review_cancel_request" value="1">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['Request_ID'] ?>">
                                    <input type="hidden" name="decision" value="reject">
                                    <button type="submit" class="request-btn reject">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">No pending cancellation or refund requests right now.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Bookings + Quick Actions -->
    <div class="two-col">

        <div class="panel">
            <div class="panel-header"><h2>🎟 Recent Bookings</h2></div>
            <?php if ($recentBookings && $recentBookings->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Customer</th><th>Movie</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php while ($b = $recentBookings->fetch_assoc()): ?>
                    <tr>
                        <td class="td-muted" data-label="ID">#<?= $b['Ticket_ID'] ?></td>
                        <td data-label="Customer"><?= htmlspecialchars($b['CustomerName']) ?></td>
                        <td class="td-muted" data-label="Movie"><?= htmlspecialchars($b['MovieName']) ?></td>
                        <td class="td-muted" data-label="Date"><?= date('M d', strtotime($b['Date'])) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">No bookings yet.</div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-header"><h2>⚡ Quick Actions</h2></div>
            <div class="panel-body">
                <div class="quick-grid">
                    <a href="malls_selection_admin.php" class="quick-btn">
                        <div class="quick-btn-icon">🎭</div>
                        Manage Screenings
                    </a>
                    <a href="movie_upload.php" class="quick-btn">
                        <div class="quick-btn-icon">🎬</div>
                        Upload Movie
                    </a>
                    <a href="theater_upload.php" class="quick-btn">
                        <div class="quick-btn-icon">🏛</div>
                        Add Theater
                    </a>
                    <a href="mall_upload.php" class="quick-btn">
                        <div class="quick-btn-icon">🏬</div>
                        Add Mall
                    </a>
                    <a href="queue_admin.php" class="quick-btn">
                        <div class="quick-btn-icon">🚶</div>
                        Manage Queue
                    </a>
                    <a href="food_admin.php" class="quick-btn">
                        <div class="quick-btn-icon">🍿</div>
                        Manage Food
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Upcoming Screenings -->
    <div class="panel">
        <div class="panel-header">
            <h2>📅 Upcoming Screenings</h2>
            <a href="malls_selection_admin.php" class="panel-link">Manage →</a>
        </div>
        <?php if ($upcomingScreenings && $upcomingScreenings->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Movie</th><th>Mall</th><th>Theater</th><th>Date</th><th>Time</th><th>Type</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($s = $upcomingScreenings->fetch_assoc()): ?>
                <tr>
                    <td data-label="Movie"><?= htmlspecialchars($s['MovieName']) ?></td>
                    <td class="td-muted" data-label="Mall"><?= htmlspecialchars($s['MallName']) ?></td>
                    <td class="td-muted" data-label="Theater"><?= htmlspecialchars($s['TheaterName']) ?></td>
                    <td class="td-muted" data-label="Date"><?= date('M d, Y', strtotime($s['Date'])) ?></td>
                    <td class="td-muted" data-label="Time"><?= date('g:i A', strtotime($s['StartTime'])) ?></td>
                    <td data-label="Type">
                        <?php
                            $typeKey = strtolower($s['ScreeningType']);
                            if      ($typeKey === '2d')   echo '<span class="badge badge-2d">2D</span>';
                            elseif  ($typeKey === '3d')   echo '<span class="badge badge-3d">3D</span>';
                            elseif  ($typeKey === 'imax') echo '<span class="badge badge-imax">IMAX</span>';
                            elseif  ($typeKey === '4dx')  echo '<span class="badge badge-4dx">4DX</span>';
                            else    echo '<span class="badge badge-2d">' . htmlspecialchars($s['ScreeningType']) . '</span>';
                        ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No upcoming screenings. Add one via Malls → Theater.</div>
        <?php endif; ?>
    </div>

    <!-- ══ Queue Preview ══════════════════════════════════════════ -->
    <div class="panel">
        <div class="panel-header">
            <h2>🚶 Cinema Queue</h2>
            <a href="queue_admin.php"
               style="color:rgba(249,249,249,0.5);text-decoration:none;font-size:0.72rem;font-weight:600;
                      transition:color 0.2s;"
               onmouseover="this.style.color='#ff4d4d'"
               onmouseout="this.style.color='rgba(249,249,249,0.5)'">
                Manage Queue →
            </a>
        </div>
        <div class="panel-body">
            <?php if (empty($qRows)): ?>
            <div style="text-align:center;padding:30px 20px;color:rgba(249,249,249,0.2);font-size:0.82rem;">
                <div style="font-size:2rem;margin-bottom:8px;">🚶</div>
                No queue data yet. <a href="queue_admin.php" style="color:#ff4d4d;text-decoration:none;">Set up queue tracking →</a>
            </div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
                <?php 
                $displayCount = 0;
                foreach ($qByMall as $mId => $areas): 
                    if ($displayCount >= 6) break; // Show max 6 areas
                    foreach ($areas as $a):
                        if ($displayCount >= 6) break;
                        $lbl = $qAreaLabels[$a['area']] ?? ucwords(str_replace('_',' ',$a['area']));
                        $ic  = $qAreaIcons[$a['area']] ?? '📍';
                        $statusColor = $a['status'] === 'open' ? '#22c55e' : ($a['status'] === 'busy' ? '#f59e0b' : '#ff4d4d');
                        $statusIcon = $a['status'] === 'open' ? '🟢' : ($a['status'] === 'busy' ? '🟡' : '🔴');
                        $displayCount++;
                ?>
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:12px;">
                    <div style="font-size:0.7rem;color:rgba(249,249,249,0.4);margin-bottom:4px;"><?= htmlspecialchars($a['MallName']) ?></div>
                    <div style="font-size:0.85rem;font-weight:700;margin-bottom:6px;"><?= $ic ?> <?= htmlspecialchars($lbl) ?></div>
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.72rem;">
                        <span style="color:<?= $statusColor ?>"><?= $statusIcon ?> <?= ucfirst($a['status']) ?></span>
                        <span style="color:rgba(249,249,249,0.5);"><?= $a['queue_length'] ?> people · <?= $a['current_wait_mins'] ?>m</span>
                    </div>
                </div>
                <?php 
                    endforeach;
                endforeach; 
                ?>
            </div>
            <?php if (count($qRows) > 6): ?>
            <div style="text-align:center;margin-top:12px;">
                <a href="queue_admin.php" style="color:#ff4d4d;text-decoration:none;font-size:0.75rem;font-weight:600;">
                    View all <?= count($qRows) ?> areas →
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- ══ End Queue Preview ══════════════════════════════════════ -->

</div>

<script>
function qSetStatus(val, el) {
    document.querySelectorAll('.q-status-opt').forEach(o => o.className = 'q-status-opt');
    el.classList.add('sel-' + val);
    document.getElementById('qStatusHidden').value = val;
}

function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const isVisible = menu.style.display === 'block';
    menu.style.display = isVisible ? 'none' : 'block';
}
</script>

</body>
</html>
