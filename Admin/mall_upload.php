<?php
    include("../peakscinemas_database.php");

    $successMsg = "";
    $errorMsg   = "";

    function input_cleanup($data) {
        return stripslashes(trim($data));
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $MallName = input_cleanup($_POST['mallName']);
        $Location = input_cleanup($_POST['location']);
        $City     = input_cleanup($_POST['city'] ?? '');

        if (!$MallName || !$Location) {
            $errorMsg = "Please fill in all required fields.";
        } else {
            // Duplicate check
            $dup = $conn->prepare("SELECT Mall_ID FROM mall WHERE MallName = ?");
            $dup->bind_param("s", $MallName); $dup->execute();
            if ($dup->get_result()->num_rows > 0) {
                $errorMsg = "A mall with this name already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO mall(MallName, Location) VALUES (?, ?)");
                $fullLocation = $City ? "$Location, $City" : $Location;
                $stmt->bind_param("ss", $MallName, $fullLocation);
                if ($stmt->execute()) {
                    $successMsg = "\"$MallName\" added successfully!";
                } else {
                    $errorMsg = "Database error: " . $conn->error;
                }
            }
        }
    }

    // Fetch all existing malls
    $existingMalls = $conn->query("
        SELECT m.Mall_ID, m.MallName, m.Location,
               COUNT(t.Theater_ID) AS TheaterCount
        FROM mall m
        LEFT JOIN theater t ON t.Mall_ID = m.Mall_ID
        GROUP BY m.Mall_ID
        ORDER BY m.Mall_ID DESC
    ");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Mall Upload – PeaksCinemas Admin</title>
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
        .page-wrapper { width: 95%; max-width: 1000px; margin: 32px auto; display: flex; flex-direction: column; gap: 22px; }
        .page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
        .page-title { font-size: 1.6rem; font-weight: 800; }

        /* Layout */
        .main-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }

        /* Panel */
        .panel { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; }
        .panel-header { padding: 14px 22px; border-bottom: 1px solid rgba(255,255,255,0.07); display: flex; align-items: center; justify-content: space-between; }
        .panel-header h2 { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
        .panel-body { padding: 22px; }

        /* Form */
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .form-group label { font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); }
        .form-group input, .form-group textarea {
            background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 9px;
            color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.88rem;
            padding: 10px 13px; outline: none; transition: border-color 0.2s; width: 100%;
        }
        .form-group input:focus, .form-group textarea:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
        .form-group textarea { resize: vertical; min-height: 90px; line-height: 1.6; }

        /* City quick-pills */
        .city-pills { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
        .city-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.73rem; font-weight: 600; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.5); cursor: pointer; transition: all 0.15s; user-select: none; }
        .city-pill:hover, .city-pill.selected { background: rgba(255,77,77,0.12); border-color: rgba(255,77,77,0.4); color: #ff4d4d; }

        .btn-submit { width: 100%; padding: 13px; margin-top: 6px; background: #ff4d4d; border: none; border-radius: 9px; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: background 0.2s, transform 0.15s; }
        .btn-submit:hover { background: #e03c3c; transform: scale(1.01); }

        /* Preview card */
        .preview-sticky { position: sticky; top: 82px; }

        .mall-preview-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }
        .mall-preview-icon { font-size: 2rem; margin-bottom: 12px; }
        .mall-preview-name { font-size: 1.1rem; font-weight: 800; color: #F9F9F9; margin-bottom: 6px; min-height: 26px; }
        .mall-preview-location { font-size: 0.82rem; color: rgba(249,249,249,0.4); min-height: 18px; display: flex; align-items: center; gap: 5px; }
        .mall-preview-city { margin-top: 8px; }
        .mall-preview-city span {
            display: inline-block; padding: 3px 10px;
            background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.2);
            border-radius: 20px; font-size: 0.72rem; font-weight: 700; color: #ff6b6b;
        }

        /* Stats strip */
        .stats-strip { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-mini { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 14px; text-align: center; }
        .stat-mini-val { font-size: 1.4rem; font-weight: 800; color: #ff4d4d; }
        .stat-mini-lbl { font-size: 0.66rem; font-weight: 600; color: rgba(249,249,249,0.3); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        /* Malls table */
        .malls-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .malls-table th { text-align: left; font-size: 0.66rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.3); padding: 6px 14px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .malls-table td { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #F9F9F9; vertical-align: middle; }
        .malls-table tr:last-child td { border-bottom: none; }
        .malls-table tr:hover td { background: rgba(255,255,255,0.02); }
        .td-muted { color: rgba(249,249,249,0.4); font-size: 0.78rem; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 5px; font-size: 0.68rem; font-weight: 700; background: rgba(255,77,77,0.1); color: #ff6b6b; border: 1px solid rgba(255,77,77,0.2); }
        .badge-zero { background: rgba(255,255,255,0.05); color: rgba(249,249,249,0.3); border-color: transparent; }

        .mall-name-cell { display: flex; align-items: center; gap: 10px; }
        .mall-avatar { width: 32px; height: 32px; border-radius: 8px; background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.2); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }

        .manage-link { color: rgba(249,249,249,0.3); text-decoration: none; font-size: 0.75rem; font-weight: 600; padding: 4px 10px; border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; transition: all 0.2s; }
        .manage-link:hover { color: #ff4d4d; border-color: rgba(255,77,77,0.3); background: rgba(255,77,77,0.06); }

        .empty-state { text-align: center; padding: 40px; color: rgba(249,249,249,0.2); font-size: 0.85rem; }

        /* Toast */
        .toast { position: fixed; bottom: 28px; right: 28px; background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 14px 22px; font-size: 0.85rem; color: #F9F9F9; box-shadow: 0 8px 24px rgba(0,0,0,0.5); z-index: 9999; animation: slideIn 0.3s ease; }
        .toast.success { border-left: 3px solid #4caf50; }
        .toast.error   { border-left: 3px solid #ff4d4d; }
        @keyframes slideIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
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
        <a href="theater_upload.php">Theater Upload</a>
        <a href="mall_upload.php" class="active">Mall Upload</a>
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
        <h1 class="page-title">Add a Mall</h1>
    </div>

    <div class="main-layout">

        <!-- Left: Form + Stats -->
        <div style="display:flex;flex-direction:column;gap:16px;">

            <!-- Stats -->
            <?php
                $mallCount    = $conn->query("SELECT COUNT(*) AS c FROM mall")->fetch_assoc()['c'];
                $theaterCount = $conn->query("SELECT COUNT(*) AS c FROM theater")->fetch_assoc()['c'];
            ?>
            <div class="stats-strip">
                <div class="stat-mini">
                    <div class="stat-mini-val"><?= $mallCount ?></div>
                    <div class="stat-mini-lbl">Total Malls</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-val"><?= $theaterCount ?></div>
                    <div class="stat-mini-lbl">Total Theaters</div>
                </div>
            </div>

            <!-- Form -->
            <div class="panel">
                <div class="panel-header"><h2>🏬 Mall Details</h2></div>
                <div class="panel-body">
                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="POST" autocomplete="off">

                        <div class="form-group">
                            <label>Mall Name</label>
                            <input type="text" name="mallName" id="mallName" placeholder="e.g. SM Marikina" required oninput="updatePreviewName(this.value)">
                        </div>

                        <div class="form-group">
                            <label>City / Area</label>
                            <input type="text" name="city" id="cityInput" placeholder="e.g. Marikina City" oninput="updatePreviewCity(this.value)">
                            <div class="city-pills">
                                <?php foreach (['Marikina','Pasig','Taguig','Makati','Quezon City','Manila','Mandaluyong','Pasay','Parañaque','Las Piñas'] as $c): ?>
                                <span class="city-pill" onclick="setCity('<?= $c ?>')"><?= $c ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Full Address / Location</label>
                            <textarea name="location" id="locationInput" placeholder="e.g. Marcos Highway, Marikina City, Metro Manila" required oninput="updatePreviewLocation(this.value)"></textarea>
                        </div>

                        <button type="submit" class="btn-submit">✓ Add Mall</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Live Preview -->
        <div class="preview-sticky">
            <div class="panel">
                <div class="panel-header"><h2>👁 Live Preview</h2></div>
                <div class="panel-body">
                    <div class="mall-preview-card">
                        <div class="mall-preview-icon">🏬</div>
                        <div class="mall-preview-name" id="previewName">Mall Name</div>
                        <div class="mall-preview-location" id="previewLocation">
                            <span>📍</span><span id="previewLocationText">Address will appear here</span>
                        </div>
                        <div class="mall-preview-city" id="previewCityWrap" style="display:none;">
                            <span id="previewCityBadge"></span>
                        </div>
                    </div>

                    <div style="font-size:0.75rem;color:rgba(249,249,249,0.25);text-align:center;padding-top:4px;">
                        This is how the mall will appear in the system
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- All Malls Table -->
    <div class="panel">
        <div class="panel-header">
            <h2>🏬 All Malls (<?= $mallCount ?>)</h2>
        </div>
        <?php if ($existingMalls && $existingMalls->num_rows > 0): ?>
        <table class="malls-table">
            <thead>
                <tr>
                    <th>Mall</th>
                    <th>Location</th>
                    <th>Theaters</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($m = $existingMalls->fetch_assoc()): ?>
            <tr>
                <td>
                    <div class="mall-name-cell">
                        <div class="mall-avatar">🏬</div>
                        <span style="font-weight:600;"><?= htmlspecialchars($m['MallName']) ?></span>
                    </div>
                </td>
                <td class="td-muted"><?= htmlspecialchars($m['Location']) ?></td>
                <td>
                    <?php if ($m['TheaterCount'] > 0): ?>
                        <span class="badge"><?= $m['TheaterCount'] ?> theater<?= $m['TheaterCount'] != 1 ? 's' : '' ?></span>
                    <?php else: ?>
                        <span class="badge badge-zero">No theaters</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="manage-link" href="malls_selection_admin.php">Manage →</a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No malls added yet. Add your first mall above!</div>
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
        document.getElementById('previewName').textContent = val || 'Mall Name';
    }

    function updatePreviewLocation(val) {
        document.getElementById('previewLocationText').textContent = val || 'Address will appear here';
    }

    function updatePreviewCity(val) {
        const wrap  = document.getElementById('previewCityWrap');
        const badge = document.getElementById('previewCityBadge');
        if (val) {
            badge.textContent = '📍 ' + val;
            wrap.style.display = 'block';
        } else {
            wrap.style.display = 'none';
        }
        document.querySelectorAll('.city-pill').forEach(p => p.classList.toggle('selected', p.textContent === val));
    }

    function setCity(city) {
        document.getElementById('cityInput').value = city;
        updatePreviewCity(city);
    }
</script>
</body>
</html>
