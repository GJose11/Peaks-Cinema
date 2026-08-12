<?php
/**
 * queue_admin.php — Live Queue Management (Admin)
 * Place in /Admin/ folder.
 * Path: /PeaksCinema/Admin/queue_admin.php
 */
session_start();
date_default_timezone_set('Asia/Manila');
include("../peakscinemas_database.php");
// include("admin_guard.php"); // uncomment if you have an admin guard

$msg = ''; $msgType = '';

// ── Auto-create table if it doesn't exist ────────────────────────
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

// ── Handle POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_area'])) {
        $mallId = (int)$_POST['mall_id'];
        $area   = trim($_POST['area'] ?? '');
        $status = $_POST['status'] ?? 'open';
        $qLen   = max(0, (int)$_POST['queue_length']);
        $wait   = max(0, (int)$_POST['wait_mins']);
        $now    = date('Y-m-d H:i:s');

        $validAreas  = ['entrance','ticketing','concession','cinema_1','cinema_2','cinema_3','restroom','parking'];
        $validStatus = ['open','busy','closed'];

        if (!in_array($area, $validAreas) || !in_array($status, $validStatus) || !$mallId) {
            $msg = 'Invalid input.'; $msgType = 'err';
        } else {
            $a  = mysqli_real_escape_string($conn, $area);
            $st = mysqli_real_escape_string($conn, $status);
            $conn->query("INSERT INTO cinema_queue
                (mall_id, area, status, queue_length, current_wait_mins, updated_at)
                VALUES ($mallId, '$a', '$st', $qLen, $wait, '$now')
                ON DUPLICATE KEY UPDATE
                    status='$st',
                    queue_length=$qLen,
                    current_wait_mins=$wait,
                    updated_at='$now'");
            $label = ['entrance'=>'Entrance Gate','ticketing'=>'Ticketing Counter','concession'=>'Concession Stand',
                      'cinema_1'=>'Cinema Hall 1','cinema_2'=>'Cinema Hall 2','cinema_3'=>'Cinema Hall 3',
                      'restroom'=>'Restrooms','parking'=>'Parking Area'];
            $msg = 'Queue updated: ' . ($label[$area] ?? ucwords(str_replace('_',' ',$area)))
                 . ' — ' . $status . ', ' . $qLen . ' people, ' . $wait . 'min wait.';
            $msgType = 'ok';
        }
    } elseif (isset($_POST['clear_mall'])) {
        $mallId = (int)$_POST['mall_id'];
        $conn->query("DELETE FROM cinema_queue WHERE mall_id=$mallId");
        $msg = 'All queue data cleared for this mall.'; $msgType = 'ok';
    }
}

// ── Fetch data ────────────────────────────────────────────────────
$mallsRes  = $conn->query("SELECT Mall_ID, MallName FROM mall ORDER BY MallName ASC");
$malls     = $mallsRes ? $mallsRes->fetch_all(MYSQLI_ASSOC) : [];
$queueRes  = $conn->query("SELECT q.*, m.MallName FROM cinema_queue q JOIN mall m ON m.Mall_ID = q.mall_id ORDER BY m.MallName, q.area");
$queueRows = $queueRes ? $queueRes->fetch_all(MYSQLI_ASSOC) : [];
$byMall    = [];
foreach ($queueRows as $r) $byMall[$r['mall_id']][] = $r;

$areaLabels = ['entrance'=>'Entrance Gate','ticketing'=>'Ticketing Counter','concession'=>'Concession Stand',
               'cinema_1'=>'Cinema Hall 1','cinema_2'=>'Cinema Hall 2','cinema_3'=>'Cinema Hall 3',
               'restroom'=>'Restrooms','parking'=>'Parking Area'];
$areaIcons  = ['entrance'=>'🚪','ticketing'=>'🎟','concession'=>'🍿',
               'cinema_1'=>'🎬','cinema_2'=>'🎬','cinema_3'=>'🎬','restroom'=>'🚻','parking'=>'🅿️'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Queue Manager – Peak's Cinema Admin</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #0f0f0f; color: #F9F9F9; min-height: 100vh; padding-top: 70px; padding-bottom: 60px; }

        /* ── Header (matches Admin dashboard) ── */
        header { background: #1C1C1C; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; height: 60px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .logo { display: flex; align-items: center; }
        .logo img { height: 42px; width: auto; filter: invert(1); display: block; }
        nav { display: flex; gap: 4px; }
        nav a { color: rgba(249,249,249,0.5); text-decoration: none; font-size: 0.8rem; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: all 0.2s; }
        nav a:hover  { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }
        .header-actions { display: flex; align-items: center; gap: 8px; }
        .btn-ghost { color: rgba(249,249,249,0.45); text-decoration: none; font-size: 0.78rem; font-weight: 500; padding: 6px 14px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); transition: all 0.2s; }
        .btn-ghost:hover { background: rgba(255,255,255,0.06); color: #F9F9F9; }
        .btn-logout { color: #ff4d4d; text-decoration: none; font-size: 0.78rem; font-weight: 600; padding: 6px 14px; border-radius: 6px; border: 1px solid rgba(255,77,77,0.3); background: rgba(255,77,77,0.08); transition: all 0.2s; }
        .btn-logout:hover { background: rgba(255,77,77,0.18); }

        /* ── Page layout ── */
        .page-wrapper { width: 95%; max-width: 1200px; margin: 32px auto; display: flex; flex-direction: column; gap: 24px; }
        .page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
        .page-title { font-size: 1.7rem; font-weight: 800; }
        .page-sub   { font-size: 0.82rem; color: rgba(249,249,249,0.35); margin-top: 4px; }

        /* ── Panels ── */
        .panel { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; }
        .panel-header { padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: space-between; }
        .panel-header h2 { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
        .panel-body { padding: 20px; }

        /* ── Message banner ── */
        .msg { padding: 12px 16px; border-radius: 9px; font-size: 0.82rem; margin-bottom: 4px; }
        .msg.ok  { background: rgba(76,175,80,0.08);  border: 1px solid rgba(76,175,80,0.2);  color: #81c784; }
        .msg.err { background: rgba(255,77,77,0.08);  border: 1px solid rgba(255,77,77,0.2);  border-left: 3px solid #ff4d4d; color: #ff6b6b; }

        /* ── Two-column layout ── */
        .two-col { display: grid; grid-template-columns: 340px 1fr; gap: 24px; align-items: start; }
        .sticky-panel { position: sticky; top: 84px; }
        @media (max-width: 900px) {
            .two-col { grid-template-columns: 1fr; }
            .sticky-panel { position: static; top: auto; }
        }

        /* ── Form fields ── */
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); margin-bottom: 6px; }
        .field select, .field input[type=number] { width: 100%; padding: 10px 13px; border-radius: 9px; border: 1px solid rgba(255,255,255,0.1); background: #222; color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.88rem; outline: none; }
        .field select:focus, .field input:focus { border-color: rgba(255,77,77,0.5); }
        .field select option { background: #222; }

        /* ── Status toggle ── */
        .status-select { display: flex; gap: 8px; }
        .status-opt { flex: 1; padding: 9px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.5); font-family: 'Outfit', sans-serif; font-size: 0.78rem; font-weight: 600; cursor: pointer; text-align: center; transition: all 0.2s; }
        .status-opt:hover { border-color: rgba(255,255,255,0.2); color: #F9F9F9; }
        .status-opt.sel-open   { background: rgba(34,197,94,0.12);  border-color: rgba(34,197,94,0.35);  color: #22c55e; }
        .status-opt.sel-busy   { background: rgba(245,158,11,0.12); border-color: rgba(245,158,11,0.35); color: #f59e0b; }
        .status-opt.sel-closed { background: rgba(255,77,77,0.12);  border-color: rgba(255,77,77,0.35);  color: #ff4d4d; }

        /* ── Buttons ── */
        .btn-submit { width: 100%; padding: 13px; border-radius: 9px; border: none; background: #ff4d4d; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: all 0.2s; margin-top: 4px; }
        .btn-submit:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }
        .btn-clear { background: rgba(255,77,77,0.06); border: 1px solid rgba(255,77,77,0.2); color: #ff6b6b; font-family: 'Outfit', sans-serif; font-size: 0.72rem; font-weight: 600; padding: 5px 12px; border-radius: 7px; cursor: pointer; transition: all 0.2s; }
        .btn-clear:hover { background: rgba(255,77,77,0.15); color: #fff; }

        /* ── Queue table ── */
        .queue-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .queue-table th { font-size: 0.65rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.3); padding: 10px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .queue-table td { padding: 11px 14px; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
        .queue-table tr:last-child td { border-bottom: none; }
        .queue-table tr:hover td { background: rgba(255,255,255,0.02); }

        .q-badge { font-size: 0.65rem; font-weight: 700; padding: 3px 9px; border-radius: 10px; white-space: nowrap; }
        .q-open   { background: rgba(34,197,94,0.12);  border: 1px solid rgba(34,197,94,0.25);  color: #22c55e; }
        .q-busy   { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b; }
        .q-closed { background: rgba(255,77,77,0.1);   border: 1px solid rgba(255,77,77,0.2);   color: #ff4d4d; }

        .q-wait-bar { display: flex; align-items: center; gap: 8px; }
        .q-bar { width: 60px; height: 5px; background: rgba(255,255,255,0.08); border-radius: 3px; overflow: hidden; }
        .q-bar-fill { height: 100%; border-radius: 3px; }
        .q-bar-fill.green  { background: #22c55e; }
        .q-bar-fill.yellow { background: #f59e0b; }
        .q-bar-fill.red    { background: #ff4d4d; }

        .empty-row { text-align: center; color: rgba(249,249,249,0.2); padding: 40px; font-size: 0.82rem; }
        .mall-row { padding: 12px 20px 6px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body>

<!-- ── Admin Header (matches dashboard.php style) ── -->
<header>
    <div class="logo"><img src="../peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="malls_selection_admin.php">Malls</a>
        <a href="malls_selection_admin.php">+ Add Screenings</a>
        <a href="movie_upload.php">Movie Upload</a>
        <a href="food_admin.php">Food & Drinks</a>
        <a href="theater_upload.php">Theater Upload</a>
        <a href="mall_upload.php">Mall Upload</a>
        <a href="queue_admin.php" class="active">Queue Manager</a>
    </nav>
    <div class="header-actions">
        <a href="../home.php" class="btn-ghost">← Customer Site</a>
        <a href="admin_logout.php" class="btn-logout" onclick="return confirm('Log out?')">→ Log Out</a>
    </div>
</header>

<div class="page-wrapper">

    <div>
        <p class="page-label">Admin Panel</p>
        <h1 class="page-title">🚶 Live Queue Manager</h1>
        <p class="page-sub">Update crowd status for each cinema area in real time.</p>
    </div>

    <?php if ($msg): ?>
    <div class="msg <?= $msgType ?>">
        <?= $msgType === 'ok' ? '✓' : '⚠' ?> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="two-col">

        <div style="display:flex;flex-direction:column;gap:16px;">
            <div class="panel">
                <div class="panel-header"><h2>📝 Update Queue Area</h2></div>
                <div class="panel-body">
                    <form method="POST">
                        <input type="hidden" name="update_area" value="1">
                        <input type="hidden" name="status" id="statusHidden" value="open">

                        <div class="field">
                            <label>Mall Location</label>
                            <select name="mall_id" required>
                                <?php foreach ($malls as $m): ?>
                                <option value="<?= $m['Mall_ID'] ?>"><?= htmlspecialchars($m['MallName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label>Area</label>
                            <select name="area" required>
                                <?php foreach ($areaLabels as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $areaIcons[$key] ?> <?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label>Status</label>
                            <div class="status-select">
                                <div class="status-opt sel-open" onclick="setStatus('open',this)">🟢 Open</div>
                                <div class="status-opt"          onclick="setStatus('busy',this)">🟡 Busy</div>
                                <div class="status-opt"          onclick="setStatus('closed',this)">🔴 Closed</div>
                            </div>
                        </div>

                        <div class="field">
                            <label>People in Queue</label>
                            <input type="number" name="queue_length" min="0" max="200" value="0" required>
                        </div>

                        <div class="field">
                            <label>Estimated Wait (minutes)</label>
                            <input type="number" name="wait_mins" min="0" max="120" value="0" required>
                        </div>

                        <button type="submit" class="btn-submit">💾 Update Queue Data</button>
                    </form>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><h2>💡 Quick Tips</h2></div>
                <div class="panel-body" style="font-size:0.78rem;color:rgba(249,249,249,0.4);line-height:2;">
                    <p>• Update regularly — especially during peak hours</p>
                    <p>• <strong style="color:#22c55e;">Open</strong>: Short queue (&lt; 5 min)</p>
                    <p>• <strong style="color:#f59e0b;">Busy</strong>: Moderate wait (5 – 20 min)</p>
                    <p>• <strong style="color:#ff4d4d;">Closed</strong>: Area not available</p>
                    <p>• Customer-facing data refreshes every 30 seconds</p>
                </div>
            </div>
        </div>

        <div class="panel sticky-panel">
            <div class="panel-header">
                <h2>📊 Current Queue Data</h2>
                <?php if (!empty($queueRows)): ?>
                <span style="font-size:0.68rem;color:rgba(249,249,249,0.3);"><?= count($queueRows) ?> area<?= count($queueRows) > 1 ? 's' : '' ?> active</span>
                <?php endif; ?>
            </div>

            <?php if (empty($queueRows)): ?>
            <div class="empty-row">No queue data yet. Use the form to add your first entry.</div>

            <?php else: ?>
            <?php foreach ($byMall as $mallId => $areas):
                $mallName = $areas[0]['MallName'] ?? 'Mall #' . $mallId; ?>
            <div class="mall-row">
                <strong style="font-size:0.82rem;"><?= htmlspecialchars($mallName) ?></strong>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Clear all queue data for <?= htmlspecialchars($mallName) ?>?')">
                    <input type="hidden" name="clear_mall" value="1">
                    <input type="hidden" name="mall_id" value="<?= $mallId ?>">
                    <button type="submit" class="btn-clear">🗑 Clear</button>
                </form>
            </div>

            <div style="overflow-x:auto;">
                <table class="queue-table">
                    <thead><tr>
                        <th>Area</th><th>Status</th><th>Queue</th><th>Wait</th><th>Updated</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($areas as $a):
                        $wc  = $a['status'] === 'closed' ? 'red' : ($a['current_wait_mins'] <= 5 ? 'green' : ($a['current_wait_mins'] <= 15 ? 'yellow' : 'red'));
                        $pct = min(100, round($a['current_wait_mins'] / 40 * 100));
                        $lbl = $areaLabels[$a['area']] ?? ucwords(str_replace('_', ' ', $a['area']));
                        $ic  = $areaIcons[$a['area']] ?? '📍';
                    ?>
                    <tr>
                        <td><?= $ic ?> <?= htmlspecialchars($lbl) ?></td>
                        <td>
                            <span class="q-badge q-<?= $a['status'] ?>">
                                <?= $a['status'] === 'open' ? '🟢 Open' : ($a['status'] === 'busy' ? '🟡 Busy' : '🔴 Closed') ?>
                            </span>
                        </td>
                        <td><?= $a['queue_length'] ?> people</td>
                        <td>
                            <div class="q-wait-bar">
                                <div class="q-bar">
                                    <div class="q-bar-fill <?= $wc ?>" style="width:<?= $pct ?>%"></div>
                                </div>
                                <span><?= $a['current_wait_mins'] ?>m</span>
                            </div>
                        </td>
                        <td style="color:rgba(249,249,249,0.3);font-size:0.72rem;">
                            <?= date('g:i A', strtotime($a['updated_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
function setStatus(val, el) {
    document.querySelectorAll('.status-opt').forEach(o => o.className = 'status-opt');
    el.classList.add('sel-' + val);
    document.getElementById('statusHidden').value = val;
}
</script>
</body>
</html>
