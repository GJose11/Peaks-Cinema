<?php
    date_default_timezone_set('Asia/Manila');
    include("peakscinemas_database.php");
    session_start();
    $profile_link  = "personal_info_form.php";
    $profile_photo = $_SESSION['profile_photo'] ?? null;

    $user_initials = '';
    if (isset($_SESSION['user_id'])) {
        $profile_link = "profile_dashboard.php";
        $ps = $conn->prepare("SELECT Name, ProfilePhoto FROM customer WHERE Customer_ID = ?");
        $ps->bind_param("i", $_SESSION['user_id']); $ps->execute();
        $pr = $ps->get_result()->fetch_assoc();
        if (!empty($pr['ProfilePhoto'])) {
            $profile_photo = $pr['ProfilePhoto'];
            $_SESSION['profile_photo'] = $profile_photo;
        }
        $nameParts = explode(' ', trim($pr['Name'] ?? ''));
        $user_initials = strtoupper(substr($nameParts[0]??'',0,1).substr(end($nameParts)??'',0,1));
        if (strlen($user_initials)===1) $user_initials = strtoupper(substr($nameParts[0]??'',0,2));
    }

    $Movie_ID = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
    if (!$Movie_ID) { header("Location: home.php"); exit; }

    $stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
    $stmt->bind_param("i", $Movie_ID);
    $stmt->execute();
    $movieDetails = ($stmt->get_result())->fetch_assoc();
    if (!$movieDetails) { header("Location: home.php"); exit; }

    // Fetch all upcoming screenings
    $sched_stmt = $conn->prepare("
        SELECT t.TimeSlot_ID, t.Date, t.StartTime, t.ScreeningType,
               th.TheaterName, th.Theater_ID,
               m.MallName, m.Mall_ID
        FROM timeslot t
        JOIN theater th ON t.Theater_ID = th.Theater_ID
        JOIN mall m ON th.Mall_ID = m.Mall_ID
        WHERE t.Movie_ID = ?
          AND (
              t.Date > CURDATE()
              OR (t.Date = CURDATE() AND t.StartTime > CURTIME())
          )
        ORDER BY t.Date ASC, m.MallName ASC, t.ScreeningType ASC, t.StartTime ASC
    ");
    $sched_stmt->bind_param("i", $Movie_ID);
    $sched_stmt->execute();
    $schedResult = $sched_stmt->get_result();

    $scheduleData   = [];
    $availableDates = [];
    $now = time();
    while ($row = $schedResult->fetch_assoc()) {
        $d    = $row['Date'];
        $mi   = $row['Mall_ID'];
        $type = $row['ScreeningType'];
        if (!isset($scheduleData[$d])) { $scheduleData[$d] = []; $availableDates[] = $d; }
        if (!isset($scheduleData[$d][$mi])) {
            $scheduleData[$d][$mi] = ['mall_name' => $row['MallName'], 'mall_id' => $mi, 'types' => []];
        }
        if (!isset($scheduleData[$d][$mi]['types'][$type])) {
            $scheduleData[$d][$mi]['types'][$type] = [];
        }
        $slotTime    = strtotime($row['Date'] . ' ' . $row['StartTime']);
        $isDisabled  = ($slotTime - $now) < 1800;
        $scheduleData[$d][$mi]['types'][$type][] = [
            'id'           => $row['TimeSlot_ID'],
            'time'         => $row['StartTime'],
            'theater_name' => $row['TheaterName'],
            'theater_id'   => $row['Theater_ID'],
            'disabled'     => $isDisabled,
        ];
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
    <title><?= htmlspecialchars($movieDetails['MovieName']) ?> - PeaksCinemas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: #0d0d0d;
            color: #F9F9F9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 50px;
        }
        body::before {
            content: '';
            position: fixed; inset: 0;
            background: url('movie-background-collage.jpg') center/cover no-repeat fixed;
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

        /* Standardized Header */
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

        /* Right actions */
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

        .notif-wrap { position: relative; display: flex; align-items: center; }
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

        @media (max-width: 768px) {
            header {
                flex-wrap: nowrap;
                height: 50px;
                padding: 0 12px;
                gap: 8px;
            }
            .brand-logo-wrap { flex-shrink: 0; }
            .brand-logo { height: 32px; }
            .bookings-btn::after { display: none; }
            .bookings-btn, .notif-btn { width: 40px; justify-content: center; padding: 0; }
        }

        @media (max-width: 480px) {
            header { padding: 0 10px; gap: 8px; }
            .brand-logo { height: 32px; }
            .header-actions { gap: 5px; }
            .bookings-btn { padding: 6px 9px; font-size: 0.95rem; }
            .notif-btn { padding: 6px 9px; font-size: 0.95rem; }
            .profile-btn { width: 36px; height: 36px; }
        }

        /* Notification dropdown styles - using DIV instead of A to avoid purple links */
        .notif-dropdown {
            display: none; position: absolute; top: calc(100% + 8px); right: 0;
            width: 320px; background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;
            overflow: hidden; box-shadow: 0 12px 40px rgba(0,0,0,0.6); z-index: 2000;
        }
        @media (max-width: 480px) {
            .notif-dropdown {
                position: fixed;
                top: 60px;
                left: 10px;
                right: 10px;
                width: auto;
            }
        }
        .notif-dropdown.open { display: block; }
        .notif-header {
            padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: space-between;
        }
        .notif-header span { font-size: 0.78rem; font-weight: 700; color: rgba(249,249,249,0.5); letter-spacing: 1px; text-transform: uppercase; }
        .notif-mark-all { font-size: 0.7rem; color: #ff6b6b; cursor: pointer; background: none; border: none; font-family: 'Outfit',sans-serif; font-weight: 600; }
        .notif-list { max-height: 280px; overflow-y: auto; }
        .notif-item { padding: 12px 16px 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.15s; display: flex; gap: 6px; align-items: flex-start; text-decoration: none; color: inherit; }
        .notif-item:hover { background: rgba(255,255,255,0.04); }
        .notif-item.unread { background: rgba(255,77,77,0.05); }
        .notif-dot { width: 6px; height: 6px; border-radius: 50%; background: #ff4d4d; flex-shrink: 0; margin-top: 6px; }
        .notif-dot.read { background: transparent; }
        .notif-item-body { flex: 1; min-width: 0; }
        .notif-item-title { font-size: 0.8rem; font-weight: 700; margin-bottom: 2px; }
        .notif-item-msg { font-size: 0.72rem; color: rgba(249,249,249,0.4); line-height: 1.5; }
        .notif-item-time { font-size: 0.65rem; color: rgba(249,249,249,0.25); margin-top: 4px; }
        .notif-empty { text-align: center; padding: 30px; font-size: 0.82rem; color: rgba(249,249,249,0.2); }
        .notif-footer { padding: 10px 16px; border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; }
        .notif-footer a { font-size: 0.75rem; color: #ff6b6b; text-decoration: none; font-weight: 600; }

        /* Hero Banner */
        .hero-banner {
            position: relative;
            z-index: 10;
            width: 100%;
            height: 550px;
            overflow: hidden;
        }

        .hero-backdrop {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center top;
            filter: blur(18px) brightness(0.4);
            transform: scale(1.08);
        }

        .hero-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to right,
                rgba(0,0,0,0.92) 0%,
                rgba(0,0,0,0.6) 50%,
                rgba(0,0,0,0.2) 100%
            );
        }

        .hero-gradient::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 120px;
            background: linear-gradient(to bottom, transparent, #111);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 20px;
            height: 100%;
            padding: 0 60px 60px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        
        .hero-info {
            margin-top: auto;
            padding-top: 220px;
            max-width: 700px;
            padding-right: 0;
        }

        .hero-availability {
            font-size: 0.8rem;
            font-weight: 600;
            color: #aaa;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .hero-title {
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 12px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.8);
        }

        .hero-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .badge-rating {
            background: #f5a623;
            color: #000;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 3px 10px;
            border-radius: 4px;
        }

        .badge-genre {
            background: rgba(255,255,255,0.1);
            color: #ddd;
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .badge-runtime {
            color: #aaa;
            font-size: 0.85rem;
        }

        /* Trailer Button on Hero */
        .hero-trailer-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.25);
            color: white;
            padding: 9px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            transition: all 0.2s ease;
            backdrop-filter: blur(4px);
            margin-top: 40px;
        }
        .hero-trailer-btn:hover {
            background: rgba(255,77,77,0.8);
            border-color: #ff4d4d;
        }

        /* Centered Play Button Overlay */
        .hero-play-overlay {
            position: absolute;
            inset: 0;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            padding-bottom: 120px;
        }

        .hero-play-btn {
            pointer-events: all;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 77, 77, 0.85);
            border: 3px solid rgba(255,255,255,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 30px rgba(255,77,77,0.5);
            backdrop-filter: blur(4px);
        }

        .hero-play-btn:hover {
            transform: scale(1.12);
            background: rgba(255,77,77,1);
            box-shadow: 0 0 50px rgba(255,77,77,0.8);
        }

        .hero-play-btn svg {
            width: 32px;
            height: 32px;
            fill: white;
            margin-left: 4px;
        }

        .badge-price {
            color: #ff4d4d;
            font-size: 0.95rem;
            font-weight: 700;
        }

        /* Main Content */
        .main-content-wrapper {
            position: relative;
            z-index: 10;
        }

        .main-content {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            padding: 40px 60px;
            display: flex;
            gap: 40px;
        }

        .poster-column {
            width: 200px;
            min-width: 200px;
        }

        .poster-column img {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7);
            border: 2px solid rgba(255,255,255,0.08);
        }

        .left-column { flex: 1; }

        /* Synopsis */
        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin-bottom: 12px;
        }

        .synopsis-text {
            font-size: 0.95rem;
            line-height: 1.8;
            color: #ccc;
            margin-bottom: 35px;
        }

        /* Movie Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 35px;
        }

        .detail-item label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 4px;
        }

        .detail-item span {
            font-size: 0.95rem;
            color: #F9F9F9;
            font-weight: 500;
        }

        /* Schedule Section */
        .schedule-wrapper {
            position: relative;
            z-index: 10;
        }
        .schedule-inner {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 60px 60px;
        }
        .schedule-title {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin-bottom: 20px;
        }

        /* Date tabs */
        .date-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }
        .date-tab {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.04);
            color: rgba(249,249,249,0.55);
            font-family: 'Outfit', sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 90px;
            text-align: center;
        }
        .date-tab .tab-day   { display: block; font-size: 0.68rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
        .date-tab .tab-date  { display: block; font-size: 1.1rem; font-weight: 800; line-height: 1.2; }
        .date-tab .tab-month { display: block; font-size: 0.7rem; font-weight: 500; opacity: 0.7; }
        .date-tab:hover { border-color: rgba(255,77,77,0.4); color: #F9F9F9; }
        .date-tab.active { background: #ff4d4d; border-color: #ff4d4d; color: #fff; }

        /* Date panels */
        .date-panel { display: none; }
        .date-panel.active { display: block; }

        /* Mall block */
        .mall-block {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .mall-block:last-child { border-bottom: none; margin-bottom: 0; }

        .mall-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .mall-icon {
            width: 32px; height: 32px;
            background: rgba(255,77,77,0.12);
            border: 1px solid rgba(255,77,77,0.25);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .mall-name {
            font-size: 1rem;
            font-weight: 700;
            color: #F9F9F9;
        }

        /* Screening type group */
        .type-group {
            margin-bottom: 20px;
            padding: 16px 18px;
            background: rgba(255,255,255,0.035);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
        }
        .type-group:last-child { margin-bottom: 0; }

        .type-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .type-label-text {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.4);
        }

        .type-badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 5px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .type-badge-2d     { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        .type-badge-3d     { background: rgba(100,180,255,0.12); color: #7ec8ff; border: 1px solid rgba(100,180,255,0.25); }
        .type-badge-imax   { background: rgba(255,200,50,0.1);   color: #ffc83d; border: 1px solid rgba(255,200,50,0.25); }
        .type-badge-4dx    { background: rgba(180,100,255,0.1);  color: #c87eff; border: 1px solid rgba(180,100,255,0.25); }
        .type-badge-screenx{ background: rgba(50,220,150,0.1);   color: #3de0a0; border: 1px solid rgba(50,220,150,0.25); }
        .type-badge-other  { background: rgba(255,255,255,0.06); color: rgba(249,249,249,0.6); }

        /* Time slot buttons */
        .time-slots { display: flex; gap: 10px; flex-wrap: wrap; }

        .time-btn {
            display: block;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color: #F9F9F9;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            min-width: 115px;
        }
        .time-btn-disabled {
            opacity: 0.35;
            cursor: not-allowed;
            pointer-events: none;
            position: relative;
            border-color: rgba(255,255,255,0.05);
        }
        .t-closed {
            display: block;
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin-top: 2px;
        }
        .time-btn:hover {
            border-color: #ff4d4d;
            background: rgba(255,77,77,0.12);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(255,77,77,0.2);
            color: #F9F9F9;
        }
        .t-time {
            display: block;
            font-size: 1.05rem;
            font-weight: 800;
            color: #F9F9F9;
            line-height: 1.3;
            letter-spacing: -0.3px;
        }
        .t-ampm {
            font-size: 0.65rem;
            font-weight: 600;
            color: rgba(249,249,249,0.55);
            margin-left: 2px;
            vertical-align: middle;
        }
        .t-theater {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.4);
            margin-top: 4px;
        }
        .time-btn:hover .t-theater { color: rgba(255,100,100,0.8); }

        /* No screenings */
        .no-screenings {
            text-align: center;
            padding: 50px 20px;
            color: rgba(249,249,249,0.25);
            font-size: 0.9rem;
        }

        /* Trailer Modal */
        .trailer-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .trailer-modal.active { display: flex; }
        .trailer-modal-content {
            position: relative;
            width: 80%;
            max-width: 900px;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
        }
        .close-modal {
            position: absolute;
            top: -38px; right: 0;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            font-family: 'Outfit', sans-serif;
        }

        @media (max-width: 900px) {
            .hero-banner { height: auto; min-height: 400px; padding-top: 40px; }
            .hero-content { 
                flex-direction: column; 
                align-items: center; 
                text-align: center; 
                padding: 30px 20px; 
                gap: 15px;
            }
            .hero-info { max-width: 100%; padding-bottom: 10px; }
            .hero-title { font-size: 2.2rem; margin-bottom: 15px; }
            .hero-badges { justify-content: center; margin-bottom: 20px; }
            .hero-trailer-btn { margin-top: 15px; }
            .hero-play-overlay { position: relative; margin-bottom: 15px; }
            
            .main-content { 
                flex-direction: column; 
                padding: 25px 20px; 
                gap: 25px; 
                align-items: center;
                text-align: center;
            }
            .poster-column { width: 120px; min-width: unset; }
            .left-column { width: 100%; }
            .details-grid { grid-template-columns: 1fr; gap: 10px; text-align: left; }
            
            .schedule-inner { padding: 25px 20px; }
        }

        @media (max-width: 480px) {
            .hero-content { padding: 25px 15px; gap: 12px; }
            .hero-info { padding-bottom: 8px; }
            .hero-title { font-size: 1.8rem; margin-bottom: 12px; }
            .hero-badges { margin-bottom: 15px; }
            .hero-trailer-btn { margin-top: 12px; }
            
            .main-content { padding: 20px 15px; gap: 20px; }
            .poster-column { width: 100px; }
            .details-grid { gap: 8px; }
            
            .schedule-inner { padding: 20px 15px; }
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
            <div class="notif-list" id="notifList"><div class="notif-empty">No notifications yet.</div></div>
            <div class="notif-footer">
              <a href="notifications_page.php">View All</a>
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

<!-- Hero Banner -->
<div class="hero-banner">
    <div class="hero-backdrop" style="background-image: url('<?= str_replace(' ', '%20', htmlspecialchars($movieDetails['MoviePoster'])) ?>');"></div>
    <div class="hero-gradient"></div>

    <?php if (!empty($movieDetails['TrailerURL'])): ?>
    <div class="hero-play-overlay">
        <button class="hero-play-btn" onclick="playTrailer('<?= htmlspecialchars($movieDetails['TrailerURL']) ?>')" title="Watch Trailer">
            <svg viewBox="0 0 24 24"><polygon points="5,3 19,12 5,21"/></svg>
        </button>
    </div>
    <?php endif; ?>

    <div class="hero-content">
        <div class="hero-info">
            <p class="hero-availability">
                <?= htmlspecialchars($movieDetails['MovieAvailability']) ?>
            </p>
            <h1 class="hero-title"><?= htmlspecialchars($movieDetails['MovieName']) ?></h1>
            <div class="hero-badges">
                <?php if (!empty($movieDetails['Rating'])): ?>
                    <span class="badge-rating"><?= htmlspecialchars($movieDetails['Rating']) ?></span>
                <?php endif; ?>
                <?php if (!empty($movieDetails['Genre'])): ?>
                    <span class="badge-genre"><?= htmlspecialchars($movieDetails['Genre']) ?></span>
                <?php endif; ?>
                <?php if (!empty($movieDetails['Runtime'])): ?>
                    <span class="badge-runtime">⏱ <?= htmlspecialchars($movieDetails['Runtime']) ?> mins</span>
                <?php endif; ?>
                <?php if (!empty($movieDetails['Price']) && $movieDetails['Price'] > 0): ?>
                    <span class="badge-price">From ₱<?= number_format($movieDetails['Price'], 2) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($movieDetails['TrailerURL'])): ?>
            <button class="hero-trailer-btn" onclick="scrollToShowtimes()">
                🎬 View Showtimes
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="poster-column">
        <img src="<?= htmlspecialchars($movieDetails['MoviePoster']) ?>"
             alt="<?= htmlspecialchars($movieDetails['MovieName']) ?>">
    </div>
    <div class="left-column">
        <p class="section-label">Synopsis</p>
        <p class="synopsis-text"><?= htmlspecialchars($movieDetails['MovieDescription']) ?></p>

        <p class="section-label">Details</p>
        <div class="details-grid">
            <div class="detail-item">
                <label>Genre</label>
                <span><?= htmlspecialchars($movieDetails['Genre']) ?></span>
            </div>
            <div class="detail-item">
                <label>Rating</label>
                <span><?= htmlspecialchars($movieDetails['Rating']) ?></span>
            </div>
            <div class="detail-item">
                <label>Runtime</label>
                <span><?= htmlspecialchars($movieDetails['Runtime']) ?> minutes</span>
            </div>
            <div class="detail-item">
                <label>Availability</label>
                <span><?= htmlspecialchars($movieDetails['MovieAvailability']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Section -->
<div class="schedule-wrapper" id="showtimes-section">
<div class="schedule-inner">
    <p class="schedule-title">🎬 Choose Your Showtime</p>

    <?php if (empty($availableDates)): ?>
        <div class="no-screenings">No screenings available for this movie yet. Check back soon!</div>
    <?php else: ?>

        <!-- Date tabs -->
        <div class="date-tabs">
            <?php foreach ($availableDates as $i => $d): ?>
                <?php
                    $ts       = strtotime($d);
                    date_default_timezone_set('Asia/Manila');
                    $today    = strtotime(date('Y-m-d'));
                    $tomorrow = strtotime(date('Y-m-d', strtotime('+1 day')));
                    if ($ts === $today)        $dayLabel = 'TODAY';
                    elseif ($ts === $tomorrow) $dayLabel = 'TOMORROW';
                    else                       $dayLabel = strtoupper(date('D', $ts));
                ?>
                <button class="date-tab <?= $i === 0 ? 'active' : '' ?>"
                        onclick="switchDate('<?= $d ?>')"
                        id="tab-<?= $d ?>">
                    <span class="tab-day"><?= $dayLabel ?></span>
                    <span class="tab-date"><?= date('d', $ts) ?></span>
                    <span class="tab-month"><?= date('M', $ts) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Date panels -->
        <?php foreach ($availableDates as $i => $d): ?>
        <div class="date-panel <?= $i === 0 ? 'active' : '' ?>" id="panel-<?= $d ?>">

            <?php foreach ($scheduleData[$d] as $mall_id => $mall): ?>
            <div class="mall-block">

                <!-- Mall header -->
                <div class="mall-header">
                    <div class="mall-icon">📍</div>
                    <span class="mall-name"><?= htmlspecialchars($mall['mall_name']) ?></span>
                </div>

                <!-- Grouped by screening type -->
                <?php foreach ($mall['types'] as $type => $slots): ?>
                <?php
                    $typeKey = strtolower(str_replace(' ', '', $type));
                    if      ($typeKey === '2d')      $badgeClass = 'type-badge-2d';
                    elseif  ($typeKey === '3d')      $badgeClass = 'type-badge-3d';
                    elseif  ($typeKey === 'imax')    $badgeClass = 'type-badge-imax';
                    elseif  ($typeKey === '4dx')     $badgeClass = 'type-badge-4dx';
                    elseif  ($typeKey === 'screenx') $badgeClass = 'type-badge-screenx';
                    else                             $badgeClass = 'type-badge-other';
                ?>
                <div class="type-group">
                    <div class="type-header">
                        <span class="type-label-text">All showtimes</span>
                        <span class="type-badge <?= $badgeClass ?>"><?= htmlspecialchars($type) ?></span>
                    </div>
                    <div class="time-slots">
                        <?php foreach ($slots as $slot):
                            $timeFormatted = date('g:i', strtotime($slot['time']));
                            $ampm          = date('A',   strtotime($slot['time']));
                        ?>
                        <?php
                        $isDisabled = !empty($slot['disabled']);
                        ?>
                        <?php if ($isDisabled): ?>
                        <div class="time-btn time-btn-disabled" title="Booking closed — less than 30 minutes to showtime">
                            <span class="t-time"><?= $timeFormatted ?><span class="t-ampm"><?= $ampm ?></span></span>
                            <span class="t-theater"><?= htmlspecialchars($slot['theater_name']) ?></span>
                            <span class="t-closed">Closed</span>
                        </div>
                        <?php else: ?>
                        <a class="time-btn"
                           href="seat_selection.php?movie_id=<?= $Movie_ID ?>&mall_id=<?= $mall_id ?>&date=<?= urlencode($d) ?>&timeslot_id=<?= $slot['id'] ?>">
                            <span class="t-time"><?= $timeFormatted ?><span class="t-ampm"><?= $ampm ?></span></span>
                            <span class="t-theater"><?= htmlspecialchars($slot['theater_name']) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
            <?php endforeach; ?>

        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>
</div><!-- end schedule-wrapper -->

<!-- Trailer Modal -->
<div id="trailerModal" class="trailer-modal">
    <div class="trailer-modal-content">
        <button class="close-modal" onclick="closeTrailer()">✕ Close</button>
        <iframe id="trailerFrame" width="100%" height="100%" frameborder="0"
            allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>
    </div>
</div>

<script>
    function switchDate(date) {
        document.querySelectorAll('.date-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.date-tab').forEach(t => t.classList.remove('active'));
        const panel = document.getElementById('panel-' + date);
        const tab   = document.getElementById('tab-'   + date);
        if (panel) panel.classList.add('active');
        if (tab)   tab.classList.add('active');
    }

    function playTrailer(url) {
        let videoId = '';
        if (url.includes('watch?v=')) videoId = url.split('watch?v=')[1].split('&')[0];
        else if (url.includes('youtu.be/')) videoId = url.split('youtu.be/')[1].split('?')[0];
        if (videoId) {
            document.getElementById('trailerFrame').src = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
            document.getElementById('trailerModal').classList.add('active');
        }
    }

    function closeTrailer() {
        document.getElementById('trailerModal').classList.remove('active');
        document.getElementById('trailerFrame').src = "";
    }

    document.getElementById('trailerModal').addEventListener('click', function(e) {
        if (e.target === this) closeTrailer();
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeTrailer(); });

    function scrollToShowtimes() {
        const el = document.getElementById('showtimes-section');
        if (el) {
            const headerH = document.querySelector('header')?.offsetHeight || 60;
            const top = el.getBoundingClientRect().top + window.scrollY - headerH;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    }

    // Header Scroll Behavior
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

    // Notifications Logic
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
                const list = document.getElementById('notifList');
                const badge = document.getElementById('notifBadge');
                if (!list || !data || data.error) return;

                // Update badge count
                if (badge) {
                    if (data.unread > 0) {
                        badge.textContent = data.unread > 9 ? '9+' : data.unread;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }

                if (!data.notifications?.length) {
                    list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
                    return;
                }

                // Using DIV elements instead of A tags to avoid purple visited links
                list.innerHTML = data.notifications.map(n => `
                    <div class="notif-item ${n.IsRead == 0 ? 'unread' : ''}" onclick="window.location.href='notifications_page.php?id=${n.Notif_ID}'">
                        <div class="notif-dot ${n.IsRead == 1 ? 'read' : ''}"></div>
                        <div class="notif-item-body">
                            <div class="notif-item-title">${n.Title}</div>
                            <div class="notif-item-msg">${n.Message}</div>
                            <div class="notif-item-time">${n.time_ago || 'Just now'}</div>
                        </div>
                    </div>
                `).join('');
            }).catch(err => console.error('Notif error:', err));
    }

    function markAllRead() {
        fetch('notifications_api.php?action=mark_read')
            .then(r => r.json())
            .then(() => loadNotif())
            .catch(err => console.error('Mark read error:', err));
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.notif-wrap')) {
            document.getElementById('notifDropdown')?.classList.remove('open');
        }
    });
</script>
</body>
</html>
