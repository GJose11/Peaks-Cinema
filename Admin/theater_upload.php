<?php
    include("../peakscinemas_database.php");

    $mall_search = $conn->query("SELECT Mall_ID, MallName FROM mall ORDER BY MallName ASC");

    $successMsg = "";
    $errorMsg   = "";

    $Mall_ID = 0;
    $TheaterName = $TheaterType = $TotalSeats = "";
    $Theater_ID = 0;
    $SeatRow = $SeatType = "";
    $SeatColumn = 0;

    function input_cleanup($data) {
        return stripslashes(trim($data));
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["theaterLayoutUp"])) {

        if ($_FILES["theaterLayoutUp"]["error"] !== UPLOAD_ERR_OK) {
            $errorMsg = "Failed to receive the layout file.";
        } else {
            $jsonRaw   = file_get_contents($_FILES["theaterLayoutUp"]["tmp_name"]);
            $seatLayout = json_decode($jsonRaw, true);

            if (!$seatLayout || !isset($seatLayout['seats'])) {
                $errorMsg = "Invalid JSON file. Make sure it has a 'seats' key.";
            } else {
                $Mall_ID     = (int)$_POST['mall_id'];
                $TheaterName = input_cleanup($_POST['theaterName']);
                $TheaterType = input_cleanup($_POST['theaterType']);
                $TotalSeats  = (int)$_POST['totalSeats'];

                // Check for duplicate theater name in same mall
                $dupCheck = $conn->prepare("SELECT Theater_ID FROM theater WHERE TheaterName = ? AND Mall_ID = ?");
                $dupCheck->bind_param("si", $TheaterName, $Mall_ID);
                $dupCheck->execute();
                if ($dupCheck->get_result()->num_rows > 0) {
                    $errorMsg = "A theater with this name already exists in that mall.";
                } else {
                    $conn->begin_transaction();
                    try {
                        $theater_stmt = $conn->prepare("INSERT INTO theater(Mall_ID, TheaterName, TheaterType, TotalSeats) VALUES (?, ?, ?, ?)");
                        $theater_stmt->bind_param("issi", $Mall_ID, $TheaterName, $TheaterType, $TotalSeats);
                        $theater_stmt->execute();
                        $Theater_ID = $conn->insert_id;

                        $seats_stmt = $conn->prepare("INSERT INTO seats(SeatRow, SeatColumn, SeatType, SeatPrice, SeatAvailability, Theater_ID) VALUES (?, ?, ?, ?, ?, ?)");

                        foreach ($seatLayout['seats'] as $SeatRow => $cols) {
                            foreach ($cols as $seat) {
                                $SeatColumn = $seat['SeatColumn'];
                                $SeatType   = $seat['SeatType'];
                                $seatTypeLower = strtolower(trim((string)$SeatType));
                                if ((int)$SeatColumn === 0 && str_contains($seatTypeLower, 'empty')) {
                                    $SeatColumn = 20;
                                    $SeatType   = 'Standard';
                                    $seatTypeLower = 'standard';
                                }

                                $SeatAvailability = 'Available';
                                if (str_contains($seatTypeLower, 'premium') || str_contains($seatTypeLower, 'vip')) {
                                    $SeatPrice = 990;
                                } else {
                                    $SeatPrice = 350;
                                }
                                $seats_stmt->bind_param("sisdsi", $SeatRow, $SeatColumn, $SeatType, $SeatPrice, $SeatAvailability, $Theater_ID);
                                $seats_stmt->execute();
                            }
                        }

                        $conn->commit();
                        $successMsg = "\"$TheaterName\" uploaded successfully with $TotalSeats seats!";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $errorMsg = "Database error: " . $e->getMessage();
                    }
                }
            }
        }
    }

    // Fetch recently added theaters
    $recentTheaters = $conn->query("
        SELECT t.TheaterName, t.TheaterType, t.TotalSeats, m.MallName
        FROM theater t JOIN mall m ON t.Mall_ID = m.Mall_ID
        ORDER BY t.Theater_ID DESC LIMIT 6
    ");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Theater Upload – PeaksCinemas Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #0f0f0f; color: #F9F9F9; min-height: 100vh; padding-top: 70px; padding-bottom: 60px; }
        body::before { content: ''; position: fixed; inset: 0; background: url('../movie-background-collage.jpg') center/cover no-repeat; opacity: 0.04; z-index: 0; pointer-events: none; }
        body::after  { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse at center, transparent 20%, #0f0f0f 75%); z-index: 1; pointer-events: none; }
        header, nav, .page-wrapper { position: relative; z-index: 10; }

        /* Header */
        header { background: #1C1C1C; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; height: 60px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .logo { display:flex; align-items:center; }
        .logo img { height: 42px; width: auto; filter: invert(1); display: block; }
        nav { display: flex; gap: 4px; }
        nav a { color: rgba(249,249,249,0.5); text-decoration: none; font-size: 0.8rem; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: all 0.2s; }
        nav a:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }

        /* Page */
        .page-wrapper { width: 95%; max-width: 1200px; margin: 32px auto; display: flex; flex-direction: column; gap: 22px; }
        .page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
        .page-title { font-size: 1.6rem; font-weight: 800; }

        /* Layout */
        .main-layout { display: grid; grid-template-columns: 380px 1fr; gap: 20px; align-items: start; }

        /* Panel */
        .panel { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; }
        .panel-header { padding: 14px 22px; border-bottom: 1px solid rgba(255,255,255,0.07); display: flex; align-items: center; justify-content: space-between; }
        .panel-header h2 { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
        .panel-body { padding: 22px; }

        /* Form */
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .form-group:last-of-type { margin-bottom: 0; }
        .form-group label { font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); }
        .form-group input, .form-group select {
            background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 9px;
            color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.88rem;
            padding: 10px 13px; outline: none; transition: border-color 0.2s; width: 100%;
        }
        .form-group input:focus, .form-group select:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
        .form-group select option { background: #222; }

        /* Theater type pills */
        .type-pills { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
        .type-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.5); cursor: pointer; transition: all 0.15s; }
        .type-pill:hover, .type-pill.selected { background: rgba(255,77,77,0.12); border-color: rgba(255,77,77,0.4); color: #ff4d4d; }

        /* Upload zone */
        .upload-zone { border: 2px dashed rgba(255,255,255,0.12); border-radius: 10px; padding: 22px; text-align: center; cursor: pointer; transition: all 0.2s; position: relative; }
        .upload-zone:hover, .upload-zone.drag-over { border-color: rgba(255,77,77,0.5); background: rgba(255,77,77,0.04); }
        .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-zone-icon { font-size: 1.8rem; margin-bottom: 6px; }
        .upload-zone-text { font-size: 0.82rem; color: rgba(249,249,249,0.4); }
        .upload-zone-text strong { color: #ff4d4d; }
        .upload-zone-hint { font-size: 0.7rem; color: rgba(249,249,249,0.22); margin-top: 4px; }

        /* Seats counter */
        .seats-counter {
            background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.2);
            border-radius: 9px; padding: 12px 16px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .seats-counter-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); }
        .seats-counter-value { font-size: 1.4rem; font-weight: 800; color: #ff4d4d; }

        .btn-submit { width: 100%; padding: 13px; margin-top: 16px; background: #ff4d4d; border: none; border-radius: 9px; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: background 0.2s, transform 0.15s; }
        .btn-submit:hover { background: #e03c3c; transform: scale(1.01); }

        /* Preview panel */
        .preview-sticky { position: sticky; top: 82px; display: flex; flex-direction: column; gap: 16px; }

        .screen-bar { text-align: center; background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.2); border-radius: 6px; padding: 7px; font-size: 0.72rem; font-weight: 700; letter-spacing: 3px; color: rgba(255,77,77,0.7); margin-bottom: 16px; }

        .seat-scroll { overflow-x: auto; padding-bottom: 4px; scrollbar-width: thin; scrollbar-color: rgba(255,77,77,0.3) transparent; }

        #tablelayout { border-collapse: separate; border-spacing: 4px; margin: 0 auto; }

        .seat-row-lbl { font-size: 0.68rem; font-weight: 700; color: rgba(249,249,249,0.35); padding: 0 6px; text-align: center; }

        .theaterSeat { width: 26px; height: 26px; border-radius: 5px; text-align: center; vertical-align: middle; font-size: 0.6rem; font-weight: 700; color: rgba(249,249,249,0.7); }
        .seatFilled { background: rgba(255,77,77,0.2); border: 1px solid rgba(255,77,77,0.5); }
        .seatEmpty  { background: transparent; border: 1px solid transparent; }

        /* Legend */
        .legend { display: flex; gap: 14px; align-items: center; font-size: 0.75rem; color: rgba(249,249,249,0.4); }
        .legend-dot { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; }
        .legend-dot.filled { background: rgba(255,77,77,0.2); border: 1px solid rgba(255,77,77,0.5); }
        .legend-dot.empty  { background: transparent; border: 1px dashed rgba(255,255,255,0.15); }

        /* Stats strip */
        .stats-strip { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 0; }
        .stat-mini { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 12px 14px; text-align: center; }
        .stat-mini-val { font-size: 1.2rem; font-weight: 800; color: #ff4d4d; }
        .stat-mini-lbl { font-size: 0.66rem; font-weight: 600; color: rgba(249,249,249,0.3); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        /* Preview header info */
        .preview-info { padding: 0 2px 16px; border-bottom: 1px solid rgba(255,255,255,0.06); margin-bottom: 16px; }
        .preview-theater-name { font-size: 1rem; font-weight: 800; color: #F9F9F9; min-height: 22px; }
        .preview-theater-sub  { font-size: 0.78rem; color: rgba(249,249,249,0.35); margin-top: 3px; }

        /* No layout placeholder */
        .no-layout { text-align: center; padding: 50px 20px; color: rgba(249,249,249,0.2); font-size: 0.85rem; }
        .no-layout-icon { font-size: 2.5rem; margin-bottom: 10px; display: block; }

        /* Recent table */
        .recent-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .recent-table th { text-align: left; font-size: 0.66rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.3); padding: 6px 14px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .recent-table td { padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #F9F9F9; vertical-align: middle; }
        .recent-table tr:last-child td { border-bottom: none; }
        .recent-table tr:hover td { background: rgba(255,255,255,0.02); }
        .td-muted { color: rgba(249,249,249,0.4); font-size: 0.78rem; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 5px; font-size: 0.68rem; font-weight: 700; background: rgba(255,77,77,0.1); color: #ff6b6b; border: 1px solid rgba(255,77,77,0.2); }

        /* Toast */
        .toast { position: fixed; bottom: 28px; right: 28px; background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 14px 22px; font-size: 0.85rem; color: #F9F9F9; box-shadow: 0 8px 24px rgba(0,0,0,0.5); z-index: 9999; animation: slideIn 0.3s ease; }
        .toast.success { border-left: 3px solid #4caf50; }
        .toast.error   { border-left: 3px solid #ff4d4d; }
        @keyframes slideIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }

        /* JSON helper box */
        .json-hint { background: #1e1e1e; border: 1px solid rgba(255,255,255,0.06); border-radius: 9px; padding: 14px 16px; margin-top: 12px; }
        .json-hint-title { font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.3); margin-bottom: 8px; }
        .json-hint pre { font-size: 0.72rem; color: rgba(249,249,249,0.45); line-height: 1.7; font-family: 'Courier New', monospace; overflow-x: auto; }
        .json-hint .hl { color: #ff9a9a; }
    </style>
</head>
<body>

<header>
    <div class="logo"><img src="../peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="malls_selection_admin.php">Malls</a>
        <a href="malls_selection_admin.php">➕ Add Screenings</a>
        <a href="movie_upload.php">Movie Upload</a>
        <a href="food_admin.php">Food & Drinks</a>
        <a href="theater_upload.php" class="active">Theater Upload</a>
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

    <div>
        <p class="page-label">Admin Panel</p>
        <h1 class="page-title">Upload a Theater</h1>
    </div>

    <div class="main-layout">

        <!-- Left: Form -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div class="panel">
                <div class="panel-header"><h2>🏛 Theater Details</h2></div>
                <div class="panel-body">
                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="POST" enctype="multipart/form-data" autocomplete="off">

                        <div class="form-group">
                            <label>Theater Name</label>
                            <input type="text" name="theaterName" id="theaterName" placeholder="e.g. Director's Club 1" required oninput="updatePreviewName(this.value)">
                        </div>

                        <div class="form-group">
                            <label>Mall Location</label>
                            <select name="mall_id" required onchange="updatePreviewMall(this)">
                                <option value="">Select a mall</option>
                                <?php
                                    if ($mall_search && $mall_search->num_rows > 0) {
                                        while ($row = $mall_search->fetch_assoc()) {
                                            echo '<option value="' . (int)$row['Mall_ID'] . '">' . htmlspecialchars($row['MallName']) . '</option>';
                                        }
                                    } else {
                                        echo '<option disabled>No malls found — add one first</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Theater Type</label>
                            <input type="text" name="theaterType" id="theaterType" placeholder="e.g. Standard, VIP, IMAX" required>
                            <div class="type-pills">
                                <?php foreach (['Standard','VIP','IMAX','4DX','Director\'s Club','ScreenX','Premier'] as $t): ?>
                                <span class="type-pill" onclick="setType('<?= $t ?>')"><?= $t ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Seat Layout (JSON file)</label>
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" name="theaterLayoutUp" id="theaterLayoutUp" accept=".json" required onchange="handleLayoutUpload(this)">
                                <div class="upload-zone-icon">📋</div>
                                <div class="upload-zone-text"><strong>Click to upload</strong> JSON layout</div>
                                <div class="upload-zone-hint" id="uploadHint">Only .json files accepted</div>
                            </div>
                        </div>

                        <div class="seats-counter">
                            <span class="seats-counter-label">Total Seats Detected</span>
                            <span class="seats-counter-value" id="totalSeatsNum">—</span>
                        </div>
                        <input type="hidden" name="totalSeats" id="totalSeats" value="0">

                        <button type="submit" class="btn-submit">✓ Upload Theater</button>
                    </form>
                </div>
            </div>

            <!-- JSON format hint -->
            <div class="panel">
                <div class="panel-header"><h2>📄 JSON Format Guide</h2></div>
                <div class="panel-body">
                    <div class="json-hint">
                        <div class="json-hint-title">Expected structure</div>
                        <pre>{
  <span class="hl">"seats"</span>: {
    <span class="hl">"A"</span>: [
      { <span class="hl">"SeatColumn"</span>: 1, <span class="hl">"SeatType"</span>: "Standard" },
      { <span class="hl">"SeatColumn"</span>: 0, <span class="hl">"SeatType"</span>: "Empty" },
      { <span class="hl">"SeatColumn"</span>: 2, <span class="hl">"SeatType"</span>: "Standard" }
    ],
    <span class="hl">"B"</span>: [ ... ]
  }
}</pre>
                    </div>
                    <p style="font-size:0.75rem;color:rgba(249,249,249,0.3);margin-top:10px;">Use <strong style="color:rgba(249,249,249,0.5)">SeatColumn: 0</strong> or <strong style="color:rgba(249,249,249,0.5)">SeatType: "Empty"</strong> to create aisle gaps between seats.</p>
                </div>
            </div>
        </div>

        <!-- Right: Live Preview -->
        <div class="preview-sticky">
            <div class="panel">
                <div class="panel-header">
                    <h2>👁 Layout Preview</h2>
                    <div class="legend">
                        <div class="legend-dot filled"></div><span>Seat</span>
                        <div class="legend-dot empty"></div><span>Aisle</span>
                    </div>
                </div>
                <div class="panel-body">

                    <div class="preview-info">
                        <div class="preview-theater-name" id="previewName">Theater Name</div>
                        <div class="preview-theater-sub" id="previewMall">Select a mall above</div>
                    </div>

                    <div class="stats-strip" id="statsStrip" style="display:none;margin-bottom:16px;">
                        <div class="stat-mini"><div class="stat-mini-val" id="statRows">0</div><div class="stat-mini-lbl">Rows</div></div>
                        <div class="stat-mini"><div class="stat-mini-val" id="statSeats">0</div><div class="stat-mini-lbl">Seats</div></div>
                        <div class="stat-mini"><div class="stat-mini-val" id="statCols">0</div><div class="stat-mini-lbl">Max Cols</div></div>
                    </div>

                    <div id="noLayoutMsg" class="no-layout">
                        <span class="no-layout-icon">🎭</span>
                        Upload a JSON file to preview the seat layout
                    </div>

                    <div id="layoutContainer" style="display:none;">
                        <div class="screen-bar">SCREEN</div>
                        <div class="seat-scroll">
                            <table id="tablelayout"></table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- Recently Added Theaters -->
    <div class="panel">
        <div class="panel-header"><h2>🕒 Recently Added Theaters</h2></div>
        <?php if ($recentTheaters && $recentTheaters->num_rows > 0): ?>
        <table class="recent-table">
            <thead><tr><th>Theater Name</th><th>Mall</th><th>Type</th><th>Total Seats</th></tr></thead>
            <tbody>
            <?php while ($t = $recentTheaters->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($t['TheaterName']) ?></td>
                <td class="td-muted"><?= htmlspecialchars($t['MallName']) ?></td>
                <td><span class="badge"><?= htmlspecialchars($t['TheaterType']) ?></span></td>
                <td class="td-muted"><?= (int)$t['TotalSeats'] ?> seats</td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div style="text-align:center;padding:30px;color:rgba(249,249,249,0.2);font-size:0.85rem;">No theaters uploaded yet.</div>
        <?php endif; ?>
    </div>

</div>

<?php if ($successMsg): ?>
<div class="toast success" id="toast">✓ <?= htmlspecialchars($successMsg) ?></div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},4000);</script>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="toast error" id="toast">✕ <?= htmlspecialchars($errorMsg) ?></div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},5000);</script>
<?php endif; ?>

<script>
    function updatePreviewName(val) {
        document.getElementById('previewName').textContent = val || 'Theater Name';
    }

    function updatePreviewMall(sel) {
        const mallName = sel.options[sel.selectedIndex]?.text || 'Select a mall above';
        document.getElementById('previewMall').textContent = '📍 ' + mallName;
    }

    function setType(type) {
        document.getElementById('theaterType').value = type;
        document.querySelectorAll('.type-pill').forEach(p => {
            p.classList.toggle('selected', p.textContent === type);
        });
    }

    function handleLayoutUpload(input) {
        if (!input.files[0]) return;
        document.getElementById('uploadHint').textContent = '📎 ' + input.files[0].name;

        const reader = new FileReader();
        reader.onload = e => {
            try {
                const json = JSON.parse(e.target.result);
                if (!json.seats) throw new Error("Missing 'seats' key");
                renderLayout(json.seats);
            } catch (err) {
                document.getElementById('noLayoutMsg').style.display = 'block';
                document.getElementById('noLayoutMsg').innerHTML = '<span class="no-layout-icon">⚠️</span>Invalid JSON: ' + err.message;
                document.getElementById('layoutContainer').style.display = 'none';
                document.getElementById('statsStrip').style.display = 'none';
            }
        };
        reader.readAsText(input.files[0]);
    }

    function renderLayout(seatLayout) {
        const table = document.getElementById('tablelayout');
        table.innerHTML = '';

        let seatCount = 0, rowCount = 0, maxCols = 0;

        for (const rowKey in seatLayout) {
            const cols = seatLayout[rowKey];
            rowCount++;
            let colCount = 0;

            const tr = document.createElement('tr');
            const thL = document.createElement('th');
            thL.className = 'seat-row-lbl';
            thL.textContent = rowKey;
            tr.appendChild(thL);

            cols.forEach(seat => {
                const td = document.createElement('td');
                td.className = 'theaterSeat';
                const isEmpty = seat.SeatColumn == 0 || seat.SeatType.toLowerCase() === 'empty';
                if (isEmpty) {
                    td.classList.add('seatEmpty');
                } else {
                    td.classList.add('seatFilled');
                    td.textContent = seat.SeatColumn;
                    seatCount++;
                    colCount++;
                }
                tr.appendChild(td);
            });

            if (colCount > maxCols) maxCols = colCount;

            const thR = document.createElement('th');
            thR.className = 'seat-row-lbl';
            thR.textContent = rowKey;
            tr.appendChild(thR);

            table.appendChild(tr);
        }

        document.getElementById('totalSeatsNum').textContent = seatCount;
        document.getElementById('totalSeats').value = seatCount;

        document.getElementById('statRows').textContent  = rowCount;
        document.getElementById('statSeats').textContent = seatCount;
        document.getElementById('statCols').textContent  = maxCols;
        document.getElementById('statsStrip').style.display = 'grid';

        document.getElementById('noLayoutMsg').style.display = 'none';
        document.getElementById('layoutContainer').style.display = 'block';
    }

    // Drag & drop styling
    const zone = document.getElementById('uploadZone');
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
    zone.addEventListener('drop',      e  => { e.preventDefault(); zone.classList.remove('drag-over'); });
</script>
</body>
</html>
