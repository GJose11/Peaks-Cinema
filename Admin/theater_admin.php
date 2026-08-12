<?php
date_default_timezone_set('Asia/Manila');
include("../peakscinemas_database.php");

$Mall_ID    = filter_input(INPUT_GET, 'mall_id',    FILTER_VALIDATE_INT);
$Theater_ID = filter_input(INPUT_GET, 'theater_id', FILTER_VALIDATE_INT);

// Redirect back to mall selection if no IDs provided
if (!$Mall_ID || !$Theater_ID) {
    header("Location: malls_selection_admin.php");
    exit;
}

$movie_search = $conn->query("SELECT Movie_ID, MovieName FROM movie ORDER BY MovieName ASC");

// Initialize with defaults so HTML never gets undefined variable warnings
$mallDetails        = [];
$theaterDetails     = [];
$layoutProper       = [];
$existingScreenings = null;

$stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$stmt->bind_param("i", $Mall_ID); $stmt->execute();
$mallDetails = $stmt->get_result()->fetch_assoc() ?? [];

$theater_stmt = $conn->prepare("SELECT * FROM theater WHERE Theater_ID = ?");
$theater_stmt->bind_param("i", $Theater_ID); $theater_stmt->execute();
$theaterDetails = $theater_stmt->get_result()->fetch_assoc() ?? [];

// Seat layout — distinct positions so preview works even after screenings are added
$seats_stmt = $conn->prepare("
    SELECT SeatRow, SeatColumn, SeatType, SeatPrice
    FROM seats
    WHERE Theater_ID = ?
    GROUP BY SeatRow, SeatColumn
    ORDER BY SeatRow ASC, CAST(SeatColumn AS UNSIGNED) ASC
");
$seats_stmt->bind_param("i", $Theater_ID); $seats_stmt->execute();
$seatLayout = $seats_stmt->get_result();
while ($seat = $seatLayout->fetch_assoc()) {
    $layoutProper[$seat['SeatRow']][] = $seat;
}
// Natural-sort the rows (A, B, C... or 1, 2, 3...)
uksort($layoutProper, 'strnatcasecmp');

// Fetch existing screenings for this theater
$screenings_stmt = $conn->prepare("
    SELECT t.TimeSlot_ID, t.Date, t.StartTime, t.ScreeningType, m.MovieName
    FROM timeslot t
    JOIN movie m ON t.Movie_ID = m.Movie_ID
    WHERE t.Theater_ID = ?
    ORDER BY t.Date DESC, t.StartTime ASC
");
$screenings_stmt->bind_param("i", $Theater_ID); $screenings_stmt->execute();
$existingScreenings = $screenings_stmt->get_result();

// Handle POST — insert one or more screening slots
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['screenings'])) {
    $screenings  = $_POST['screenings'];   // array of {date, time, movie_id, type, price}
    $Theater_ID_post = (int)$_POST['theater_id'];

    $conn->begin_transaction();
    try {
        foreach ($screenings as $s) {
            $StartTime     = trim($s['startTime']);
            $Date          = trim($s['date']);
            $ScreeningType = trim($s['screeningType']);
            $Movie_ID_s    = (int)$s['movie_id'];
            $SeatPrice     = (float)$s['seatPrice'];

            if (!$StartTime || !$Date || !$ScreeningType || !$Movie_ID_s || $SeatPrice <= 0) continue;

            // Insert timeslot
            $ts = $conn->prepare("INSERT INTO timeslot(StartTime, Date, ScreeningType, Movie_ID, Theater_ID) VALUES (?, ?, ?, ?, ?)");
            $ts->bind_param("sssii", $StartTime, $Date, $ScreeningType, $Movie_ID_s, $Theater_ID_post);
            $ts->execute();
            $TimeSlot_ID = $conn->insert_id;

            // Copy seats for this timeslot
            $base_seats = $conn->prepare("SELECT * FROM seats WHERE Theater_ID = ? AND TimeSlot_ID IS NULL");
            $base_seats->bind_param("i", $Theater_ID_post); $base_seats->execute();
            $baseResult = $base_seats->get_result();
            $ins = $conn->prepare("INSERT INTO seats(SeatRow, SeatColumn, SeatType, SeatPrice, SeatAvailability, Theater_ID, TimeSlot_ID) VALUES (?, ?, ?, ?, ?, ?, ?)");
            while ($seat = $baseResult->fetch_assoc()) {
                $copySeatColumn = (int)($seat['SeatColumn'] ?? 0);
                $copySeatType   = (string)($seat['SeatType'] ?? 'Standard');
                $copyTypeLower  = strtolower(trim($copySeatType));

                if ($copySeatColumn === 0 && $copyTypeLower === 'empty') {
                    $copySeatColumn = 20;
                    $copySeatType   = 'Standard';
                    $copyTypeLower  = 'standard';
                }

                $SeatAvailability = 'Available';
                $ins->bind_param("sisdsii", $seat['SeatRow'], $copySeatColumn, $copySeatType, $SeatPrice, $SeatAvailability, $Theater_ID_post, $TimeSlot_ID);
                $ins->execute();
            }
        }
        $conn->commit();
        // Redirect to refresh and avoid re-POST
        header("Location: theater_admin.php?mall_id=$Mall_ID&theater_id=$Theater_ID_post&success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Error saving screenings: " . $e->getMessage();
    }
}

// Handle DELETE screening
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_timeslot'])) {
    $del_id = (int)$_POST['delete_timeslot'];
    $conn->query("DELETE FROM seats WHERE TimeSlot_ID = $del_id");
    $conn->query("DELETE FROM timeslot WHERE TimeSlot_ID = $del_id");
    header("Location: theater_admin.php?mall_id=$Mall_ID&theater_id=$Theater_ID&success=2");
    exit;
}

?>
<?php // Note: connection stays open until end of script ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Theater Admin – PeaksCinemas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: #0f0f0f;
            color: #F9F9F9;
            min-height: 100vh;
            padding-top: 70px;
            padding-bottom: 50px;
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
            background-color: #1C1C1C;
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

        .logo {
            display:flex;
            align-items:center;
        }
        .logo img { height: 42px; width: auto; filter: invert(1); display: block; }

        nav { display: flex; gap: 4px; }

        nav a {
            color: rgba(249,249,249,0.5);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        nav a:hover  { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }

        /* ── Page ── */
        .page-wrapper {
            width: 95%;
            max-width: 1200px;
            margin: 28px auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .page-title {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #F9F9F9;
        }

        .page-title span {
            color: rgba(249,249,249,0.4);
            font-weight: 400;
            font-size: 0.85rem;
            margin-left: 8px;
        }

        /* ── Panel ── */
        .panel {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px;
            overflow: hidden;
        }

        .panel-header {
            padding: 14px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-header h2 {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.5);
        }

        .panel-body { padding: 20px; }

        /* ── Two-column layout ── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 20px;
        }

        /* ── Seat layout preview ── */
        .screen-bar {
            text-align: center;
            background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 4px 4px 40% 40% / 4px 4px 12px 12px;
            padding: 7px 30px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 4px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(255,255,255,0.06);
        }

        /* ── Seat layout — flexbox rows ── */
        .seat-preview-wrap {
            overflow: hidden;
            padding: 4px 0 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            /* Fixed height so seats never push panel taller */
            height: 460px;
        }

        .seat-grid-admin {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
            flex-shrink: 0;
        }

        .seat-row-admin {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 5px;
            height: 37px;
        }

        .seat-row-label {
            width: 22px;
            min-width: 22px;
            flex-shrink: 0;
            font-size: 0.68rem;
            font-weight: 700;
            color: rgba(249,249,249,0.35);
            text-align: center;
            line-height: 1;
        }

        .seat-cell {
            width: 32px;
            height: 32px;
            flex-shrink: 0;
            flex-grow: 0;
            border-radius: 7px 7px 4px 4px;
            text-align: center;
            line-height: 32px;
            font-size: 0.64rem;
            font-weight: 800;
            cursor: default;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.1s ease, filter 0.1s ease;
        }
        .seat-cell:hover {
            transform: scale(1.18);
            filter: brightness(1.3);
            cursor: pointer;
            z-index: 2;
            position: relative;
        }

        /* Seat types */
        .seat-standard {
            background: #303030;
            border-bottom: 3px solid #555;
            color: rgba(249,249,249,0.55);
        }
        .seat-vip {
            background: #3d1a1a;
            border-bottom: 3px solid #ff4d4d;
            color: #ff7070;
        }
        .seat-imax {
            background: #332b00;
            border-bottom: 3px solid #ffc107;
            color: #ffd54f;
        }
        .seat-4dx {
            background: #1a2d1a;
            border-bottom: 3px solid #4caf50;
            color: #81c784;
        }
        .seat-premier {
            background: #1a1a3d;
            border-bottom: 3px solid #7c4dff;
            color: #b388ff;
        }
        .seat-wheelchair {
            background: #0d2137;
            border-bottom: 3px solid #2196f3;
            color: #64b5f6;
        }
        .seat-empty-cell {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            flex-grow: 0;
            background: #303030;
            border-bottom: 3px solid #555;
            border-radius: 6px 6px 3px 3px;
        }
        .seat-aisle  { width: 22px; flex-shrink: 0; display: block; }
        .seat-section { width: 12px; flex-shrink: 0; display: block; }

        /* Legend */
        .seat-legend {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.06);
            justify-content: center;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.68rem;
            color: rgba(249,249,249,0.45);
        }
        .legend-swatch {
            width: 14px; height: 14px;
            border-radius: 3px;
        }

        /* ── Screening form ── */
        .slots-container { display: flex; flex-direction: column; gap: 12px; }

        .slot-card {
            background: #222;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 14px 16px;
            position: relative;
        }

        .slot-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .slot-number {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #ff4d4d;
            text-transform: uppercase;
        }

        .btn-remove-slot {
            background: rgba(255,77,77,0.1);
            border: 1px solid rgba(255,77,77,0.3);
            color: #ff4d4d;
            border-radius: 5px;
            padding: 3px 10px;
            font-size: 0.72rem;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            transition: background 0.2s;
        }
        .btn-remove-slot:hover { background: rgba(255,77,77,0.2); }

        .slot-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .form-group { display: flex; flex-direction: column; gap: 4px; }

        .form-group label {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.4);
        }

        .form-group input,
        .form-group select {
            background: #2a2a2a;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 7px;
            color: #F9F9F9;
            font-family: 'Outfit', sans-serif;
            font-size: 0.82rem;
            padding: 7px 10px;
            outline: none;
            transition: border-color 0.2s;
            width: 100%;
        }

        .form-group input:focus,
        .form-group select:focus { border-color: rgba(255,77,77,0.5); }

        .form-group select option { background: #2a2a2a; }

        /* date/time input color fix */
        .form-group input[type="date"],
        .form-group input[type="time"] { color-scheme: dark; }

        /* ── Buttons ── */
        .btn-add-slot {
            background: rgba(255,255,255,0.04);
            border: 1px dashed rgba(255,255,255,0.15);
            border-radius: 8px;
            color: rgba(249,249,249,0.5);
            font-family: 'Outfit', sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 10px;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 4px;
        }
        .btn-add-slot:hover { border-color: rgba(255,77,77,0.4); color: #ff4d4d; background: rgba(255,77,77,0.05); }

        .btn-submit {
            background: #ff4d4d;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 11px;
            width: 100%;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s, transform 0.15s;
        }
        .btn-submit:hover { background: #e03c3c; transform: scale(1.01); }

        /* ── Existing screenings table ── */
        .screenings-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .screenings-table th {
            text-align: left;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.35);
            padding: 8px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }

        .screenings-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #F9F9F9;
            vertical-align: middle;
        }

        .screenings-table tr:last-child td { border-bottom: none; }
        .screenings-table tr:hover td { background: rgba(255,255,255,0.02); }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .badge-2d { background: rgba(255,255,255,0.08); color: rgba(249,249,249,0.6); }
        .badge-3d { background: rgba(255,77,77,0.12); color: #ff4d4d; border: 1px solid rgba(255,77,77,0.3); }

        .btn-delete {
            background: rgba(255,77,77,0.08);
            border: 1px solid rgba(255,77,77,0.25);
            color: #ff4d4d;
            border-radius: 5px;
            padding: 4px 10px;
            font-size: 0.7rem;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            transition: background 0.2s;
        }
        .btn-delete:hover { background: rgba(255,77,77,0.18); }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: rgba(249,249,249,0.25);
            font-size: 0.85rem;
        }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 28px; right: 28px;
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 0.85rem;
            color: #F9F9F9;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }
        .toast.success { border-left: 3px solid #4caf50; }
        .toast.error   { border-left: 3px solid #ff4d4d; }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<header>
<div class="logo"><img src="../peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="malls_selection_admin.php">Malls</a>
        <a href="malls_selection_admin.php" class="active">➕ Add Screenings</a>
        <a href="movie_upload.php">Movie Upload</a>
        <a href="food_admin.php">Food & Drinks</a>
        <a href="theater_upload.php">Theater Upload</a>
        <a href="mall_upload.php">Mall Upload</a>
        <a href="queue_admin.php">Queue Manager</a>
    </nav>
    <div style="display:flex;align-items:center;gap:8px;">
        <a href="../home.php"
           style="color:rgba(249,249,249,0.45);text-decoration:none;font-size:0.78rem;font-weight:500;
                  padding:6px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);
                  transition:all 0.2s;display:flex;align-items:center;gap:5px;"
           onmouseover="this.style.background='rgba(255,255,255,0.06)';this.style.color='#F9F9F9'"
           onmouseout="this.style.background='';this.style.color='rgba(249,249,249,0.45)'">
            &#8592; Customer Site
        </a>
        <a href="admin_logout.php"
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

<div class="page-wrapper">

    <div class="page-title">
        <?= htmlspecialchars($mallDetails['MallName'] ?? 'Unknown Mall') ?> — <?= htmlspecialchars($theaterDetails['TheaterName'] ?? 'Unknown Theater') ?>
        <span>Manage Screenings</span>
    </div>

    <!-- Two column: seat preview + add screenings -->
    <div class="two-col">

        <!-- Seat Layout Preview -->
        <div class="panel">
            <div class="panel-header"><h2>🪑 Seat Layout Preview</h2></div>
            <div class="panel-body">
                <div style="display:flex;justify-content:center;margin-bottom:18px;">
                    <div class="screen-bar">── SCREEN ──</div>
                </div>

                <?php if (empty($layoutProper)): ?>
                    <div style="text-align:center;padding:40px;color:rgba(249,249,249,0.2);font-size:0.82rem;">
                        No seat layout found for this theater.
                    </div>
                <?php else: ?>
                <div class="seat-preview-wrap" id="seatPreviewWrap">
                <div class="seat-grid-admin" id="seatGridAdmin">
                    <?php foreach ($layoutProper as $row => $columns): ?>
                    <div class="seat-row-admin">
                        <span class="seat-row-label"><?= htmlspecialchars($row) ?></span>
                        <?php
                        // Pad to 20 seats
                        $taCols = array_values(array_slice($columns, 0, 20));
                        while (count($taCols) < 20) {
                $taCols[] = ['SeatType'=>'standard','SeatRow'=>$row,'SeatColumn'=>count($taCols)+1,'SeatPrice'=>0,'SeatAvailability'=>'Available','Seat_ID'=>0];
                        }
                        foreach ($taCols as $taPos => $seat):
                            $type = strtolower(trim($seat['SeatType'] ?? 'standard'));
                            $dispNum = ($taPos < 10) ? (10 - $taPos) : ($taPos - 10 + 1);
                            if (str_contains($type,'vip')||str_contains($type,'premium')) { $cssClass='seat-vip'; $icon='★'; }
                            elseif (str_contains($type,'imax'))  { $cssClass='seat-imax';  $icon='I'; }
                            elseif (str_contains($type,'4dx')||str_contains($type,'4d')) { $cssClass='seat-4dx'; $icon='4'; }
                            elseif (str_contains($type,'premier')||str_contains($type,'director')) { $cssClass='seat-premier'; $icon='P'; }
                            elseif (str_contains($type,'wheel')||str_contains($type,'pwd')) { $cssClass='seat-wheelchair'; $icon='W'; }
                            else { $cssClass='seat-standard'; $icon=$dispNum; }
                        ?>
                        <?php if ($taPos === 10): ?><div class="seat-aisle"></div><?php endif; ?>
                        <?php if ($taPos === 5 || $taPos === 15): ?><div class="seat-section"></div><?php endif; ?>
                            <div class="seat-cell <?= $cssClass ?>" title="<?= htmlspecialchars($seat['SeatRow'].$dispNum) ?>">
                                <?= $icon ?>
                            </div>
                        <?php endforeach; ?>
                        <span class="seat-row-label"><?= htmlspecialchars($row) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                </div>

                <!-- Seat count summary -->
                <?php
                $seatCounts = ['standard'=>0,'vip'=>0,'imax'=>0,'4dx'=>0,'premier'=>0,'wheelchair'=>0];
                $totalSeats = 0;
                foreach ($layoutProper as $rowSeats) {
                    foreach ($rowSeats as $s) {
                        $t = strtolower(trim($s['SeatType'] ?? 'standard'));
                        if ($t === 'empty') continue;
                        $totalSeats++;
                        if (str_contains($t,'vip')||str_contains($t,'premium'))           $seatCounts['vip']++;
                        elseif (str_contains($t,'imax'))                                   $seatCounts['imax']++;
                        elseif (str_contains($t,'4dx')||str_contains($t,'4d'))            $seatCounts['4dx']++;
                        elseif (str_contains($t,'premier')||str_contains($t,'director'))  $seatCounts['premier']++;
                        elseif (str_contains($t,'wheel')||str_contains($t,'pwd'))         $seatCounts['wheelchair']++;
                        else                                                               $seatCounts['standard']++;
                    }
                }
                ?>
                <div style="text-align:center;font-size:0.72rem;color:rgba(249,249,249,0.3);margin:10px 0 6px;">
                    <?= $totalSeats ?> seats · <?= count($layoutProper) ?> rows
                </div>
                <div class="seat-legend">
                    <?php if($seatCounts['standard']>0): ?>
                    <div class="legend-item">
                        <div class="legend-swatch" style="background:#303030;border-bottom:3px solid #555;border-radius:3px;"></div>
                        Standard (<?= $seatCounts['standard'] ?>)
                    </div>
                    <?php endif; ?>
                    <?php if($seatCounts['vip']>0): ?>
                    <div class="legend-item">
                        <div class="legend-swatch" style="background:#3d1a1a;border-bottom:3px solid #ff4d4d;border-radius:3px;"></div>
                        VIP (<?= $seatCounts['vip'] ?>)
                    </div>
                    <?php endif; ?>
                    <?php if($seatCounts['imax']>0): ?>
                    <div class="legend-item">
                        <div class="legend-swatch" style="background:#332b00;border-bottom:3px solid #ffc107;border-radius:3px;"></div>
                        IMAX (<?= $seatCounts['imax'] ?>)
                    </div>
                    <?php endif; ?>
                    <?php if($seatCounts['4dx']>0): ?>
                    <div class="legend-item">
                        <div class="legend-swatch" style="background:#1a2d1a;border-bottom:3px solid #4caf50;border-radius:3px;"></div>
                        4DX (<?= $seatCounts['4dx'] ?>)
                    </div>
                    <?php endif; ?>
                    <?php if($seatCounts['premier']>0): ?>
                    <div class="legend-item">
                        <div class="legend-swatch" style="background:#1a1a3d;border-bottom:3px solid #7c4dff;border-radius:3px;"></div>
                        Premier (<?= $seatCounts['premier'] ?>)
                    </div>
                    <?php endif; ?>
                    <?php if($seatCounts['wheelchair']>0): ?>
                    <div class="legend-item">
                        <div class="legend-swatch" style="background:#0d2137;border-bottom:3px solid #2196f3;border-radius:3px;"></div>
                        Wheelchair (<?= $seatCounts['wheelchair'] ?>)
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Screenings Form -->
        <div class="panel">
            <div class="panel-header"><h2>➕ Add Screenings</h2></div>
            <div class="panel-body">
                <form method="POST" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) . '?mall_id=' . urlencode($Mall_ID) . '&theater_id=' . urlencode($Theater_ID) ?>" id="screeningForm">
                    <input type="hidden" name="theater_id" value="<?= htmlspecialchars($Theater_ID) ?>">

                    <div class="slots-container" id="slotsContainer">
                        <!-- JS will render slots here -->
                    </div>

                    <button type="button" class="btn-add-slot" onclick="addSlot()">+ Add Another Time Slot</button>
                    <button type="submit" class="btn-submit">✓ Save All Screenings</button>
                </form>
            </div>
        </div>

    </div>

    <!-- Existing Screenings -->
    <div class="panel">
        <div class="panel-header">
            <h2>📅 Existing Screenings</h2>
        </div>
        <?php
        // Re-query since we closed conn above — need to re-open for display
        // Actually we closed conn — let's just show what we fetched before POST
        if (isset($existingScreenings) && $existingScreenings->num_rows > 0):
        ?>
        <table class="screenings-table">
            <thead>
                <tr>
                    <th>Movie</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($s = $existingScreenings->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($s['MovieName']) ?></td>
                    <td><?= htmlspecialchars(date('M d, Y', strtotime($s['Date']))) ?></td>
                    <td><?= htmlspecialchars(date('g:i A', strtotime($s['StartTime']))) ?></td>
                    <td><span class="badge badge-<?= strtolower($s['ScreeningType']) ?>"><?= htmlspecialchars($s['ScreeningType']) ?></span></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this screening and all its seats?')">
                            <input type="hidden" name="delete_timeslot" value="<?= $s['TimeSlot_ID'] ?>">
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No screenings scheduled yet. Add one above!</div>
        <?php endif; ?>
    </div>

</div>

<?php if (isset($_GET['success'])): ?>
<div class="toast success" id="toast">
    <?= $_GET['success'] == 2 ? '🗑 Screening deleted.' : '✓ Screenings saved successfully!' ?>
</div>
<script>setTimeout(() => { const t = document.getElementById('toast'); if(t) t.remove(); }, 3500);</script>
<?php endif; ?>

<?php if (isset($errorMsg)): ?>
<div class="toast error" id="toast"><?= htmlspecialchars($errorMsg) ?></div>
<script>setTimeout(() => { const t = document.getElementById('toast'); if(t) t.remove(); }, 4000);</script>
<?php endif; ?>

<script>
    // Build movie options from PHP
    const movieOptions = [
        <?php
        if (isset($movie_search)) {
            $movie_search->data_seek(0);
            while ($m = $movie_search->fetch_assoc()) {
                echo '{ id: ' . (int)$m['Movie_ID'] . ', name: ' . json_encode($m['MovieName']) . ' },';
            }
        }
        ?>
    ];

    let slotCount = 0;

    function buildMovieOptions(selected = '') {
        let opts = '<option value="">Select a movie</option>';
        movieOptions.forEach(m => {
            opts += `<option value="${m.id}" ${m.id == selected ? 'selected' : ''}>${m.name}</option>`;
        });
        return opts;
    }

    function addSlot(data = {}) {
        slotCount++;
        const today = new Date().toISOString().split('T')[0];
        const container = document.getElementById('slotsContainer');

        const card = document.createElement('div');
        card.className = 'slot-card';
        card.id = `slot-${slotCount}`;
        card.innerHTML = `
            <div class="slot-card-header">
                <span class="slot-number">Slot #${slotCount}</span>
                <button type="button" class="btn-remove-slot" onclick="removeSlot(${slotCount})">✕ Remove</button>
            </div>
            <div class="slot-grid">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="screenings[${slotCount}][date]" min="${today}" value="${data.date || ''}" required>
                </div>
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="screenings[${slotCount}][startTime]" value="${data.startTime || ''}" required>
                </div>
                <div class="form-group">
                    <label>Movie</label>
                    <select name="screenings[${slotCount}][movie_id]" required>
                        ${buildMovieOptions(data.movie_id || '')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Screening Type</label>
                    <select name="screenings[${slotCount}][screeningType]" required>
                        <option value="">Select type</option>
                        <option value="2D" ${data.type === '2D' ? 'selected' : ''}>2D</option>
                        <option value="3D" ${data.type === '3D' ? 'selected' : ''}>3D</option>
                        <option value="IMAX" ${data.type === 'IMAX' ? 'selected' : ''}>IMAX</option>
                        <option value="4DX" ${data.type === '4DX' ? 'selected' : ''}>4DX</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Seat Price (₱)</label>
                    <input type="number" name="screenings[${slotCount}][seatPrice]" min="1" step="0.01" value="${data.seatPrice || ''}" placeholder="e.g. 350" required>
                </div>
            </div>
        `;
        container.appendChild(card);
    }

    function removeSlot(id) {
        const card = document.getElementById(`slot-${id}`);
        if (card) card.remove();
        // If no slots left, add a fresh one
        if (document.querySelectorAll('.slot-card').length === 0) addSlot();
    }

    // Start with 1 slot
    addSlot();

// ── Auto-scale seat grid to fill panel — no scroll ──
function scaleAdminSeats(){
    const wrap = document.getElementById('seatPreviewWrap');
    const grid = document.getElementById('seatGridAdmin');
    if(!wrap||!grid) return;
    grid.style.transform = '';
    grid.style.transformOrigin = '';
    const cw = wrap.clientWidth  - 8;
    const ch = wrap.clientHeight - 8;
    const gw = grid.offsetWidth;
    const gh = grid.offsetHeight;
    if(!gw||!gh) return;
    const scale = Math.min(cw/gw, ch/gh, 1);
    grid.style.transform = 'scale('+scale+')';
    grid.style.transformOrigin = 'center center';
}
window.addEventListener('load',   scaleAdminSeats);
window.addEventListener('resize', scaleAdminSeats);
</script>
</body>
</html>
