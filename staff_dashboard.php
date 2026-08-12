<?php
date_default_timezone_set('Asia/Manila');
require_once(__DIR__ . "/staff_guard.php");
include(__DIR__ . "/peakscinemas_database.php");

$staffName = $_SESSION['staff_name'] ?? 'Staff';
$staffId   = $_SESSION['staff_id']   ?? '—';
if (!isset($_SESSION['staff_login_time'])) $_SESSION['staff_login_time'] = date('g:i A');
$loginTime = $_SESSION['staff_login_time'];

$movies = $conn->query("
    SELECT m.Movie_ID, m.MovieName, m.MoviePoster, m.Genre, m.Runtime, m.Rating,
           COUNT(t.TimeSlot_ID) AS ShowtimeCount
    FROM movie m
    LEFT JOIN timeslot t ON t.Movie_ID = m.Movie_ID AND t.Date >= CURDATE()
    WHERE m.MovieAvailability = 'Now Showing'
    GROUP BY m.Movie_ID
    ORDER BY ShowtimeCount DESC, m.MovieName ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Staff Dashboard – Peak's Cinema</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #0f0f0f; color: #F9F9F9; min-height: 100vh; padding-top: 70px; padding-bottom: 60px; }

        body::before {
            content: '';
            position: fixed; inset: 0;
            background: url('movie-background-collage.jpg') center/cover no-repeat;
            opacity: 0.04; z-index: 0; pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed; inset: 0;
            background: radial-gradient(ellipse at center, transparent 20%, #0f0f0f 75%);
            z-index: 1; pointer-events: none;
        }
        header, nav, .page-wrapper { position: relative; z-index: 10; }

        header { background: #1C1C1C; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; height: 60px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .logo { display: flex; align-items: center; }
        .logo img { height: 42px; width: auto; filter: invert(1); display: block; }
        nav { display: flex; gap: 4px; }
        nav a { color: rgba(249,249,249,0.5); text-decoration: none; font-size: 0.8rem; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: all 0.2s; }
        nav a:hover  { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }

        .page-wrapper { width: 95%; max-width: 1200px; margin: 32px auto; display: flex; flex-direction: column; gap: 24px; }
        .page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
        .page-title { font-size: 1.7rem; font-weight: 800; }
        .page-sub   { font-size: 0.82rem; color: rgba(249,249,249,0.35); margin-top: 4px; }

        .panel { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; }
        .panel-header { padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: space-between; }
        .panel-header h2 { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
        .panel-body { padding: 20px; }

        /* Search */
        .search-wrap { position: relative; max-width: 380px; }
        .search-wrap input { width: 100%; background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); border-radius: 9px; color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.88rem; padding: 10px 13px 10px 38px; outline: none; transition: border-color 0.2s; }
        .search-wrap input:focus { border-color: rgba(255,77,77,0.4); }
        .search-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); opacity: 0.35; pointer-events: none; }

        /* Movie grid */
        .movies-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
        .movie-card { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; overflow: hidden; cursor: pointer; transition: all 0.2s; }
        .movie-card:hover { border-color: rgba(255,77,77,0.35); transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.5); }
        .movie-card.selected { border-color: #ff4d4d; box-shadow: 0 0 0 2px rgba(255,77,77,0.2); }
        .movie-poster { aspect-ratio: 2/3; width: 100%; background: #111; background-size: cover; background-position: center; position: relative; }
        .poster-check { position: absolute; top: 8px; right: 8px; width: 24px; height: 24px; border-radius: 50%; background: #ff4d4d; display: none; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; color: #fff; }
        .movie-card.selected .poster-check { display: flex; }
        .movie-info { padding: 11px 12px; }
        .movie-name { font-size: 0.78rem; font-weight: 700; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .movie-meta { font-size: 0.67rem; color: rgba(249,249,249,0.35); margin-top: 3px; }
        .showtime-count-badge { font-size: 0.63rem; margin-top: 4px; font-weight: 600; }

        /* Showtime panel */
        .showtime-panel { display: none; }
        .showtime-panel.visible { display: block; }
        .date-group { margin-bottom: 18px; }
        .date-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 10px; }
        .time-slots { display: flex; flex-wrap: wrap; gap: 8px; }
        .time-btn { padding: 9px 16px; background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.18s; display: flex; flex-direction: column; align-items: center; gap: 1px; }
        .time-btn:hover { border-color: rgba(255,77,77,0.4); background: #2a2a2a; }
        .time-btn.selected { background: rgba(255,77,77,0.12); border-color: #ff4d4d; color: #ff4d4d; }
        .t-time { font-size: 0.88rem; font-weight: 700; }
        .t-type { font-size: 0.62rem; opacity: 0.55; }
        .t-mall { font-size: 0.62rem; opacity: 0.35; }
        .no-showtimes { color: rgba(249,249,249,0.25); font-size: 0.82rem; text-align: center; padding: 24px; }
        .showtime-loading { text-align: center; padding: 28px; color: rgba(249,249,249,0.25); font-size: 0.82rem; }

        .btn-proceed { padding: 11px 28px; background: #ff4d4d; border: none; border-radius: 9px; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all 0.2s; opacity: 0.35; pointer-events: none; }
        .btn-proceed.active { opacity: 1; pointer-events: all; }
        .btn-proceed.active:hover { background: #e03c3c; transform: translateY(-1px); }

        .empty-state { text-align: center; padding: 60px 20px; color: rgba(249,249,249,0.2); }
        .empty-icon { font-size: 2.5rem; margin-bottom: 12px; }

        .top-row { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 14px; }
    </style>
</head>
<body>
<header>
    <div class="logo"><img src="peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
    <nav>
        <a href="staff_dashboard.php" class="active">Dashboard</a>
        <a href="staff_seats.php">Seat Selection</a>
        <a href="staff_payment.php">Payment</a>
        <a href="staff_receipt.php">Receipt</a>
    </nav>
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="text-align:right;line-height:1.4;">
            <div style="font-size:0.78rem;font-weight:600;color:rgba(249,249,249,0.6);">👤 <?= htmlspecialchars($staffName) ?></div>
            <div style="font-size:0.62rem;color:rgba(249,249,249,0.25);">Staff #<?= htmlspecialchars($staffId) ?> &nbsp;·&nbsp; Since <?= htmlspecialchars($loginTime) ?></div>
        </div>
        <a href="home.php" style="color:rgba(249,249,249,0.45);text-decoration:none;font-size:0.78rem;font-weight:500;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.06)';this.style.color='#F9F9F9'" onmouseout="this.style.background='';this.style.color='rgba(249,249,249,0.45)'">&#8592; Customer Site</a>
        <a href="staff_logout.php" style="color:#ff4d4d;text-decoration:none;font-size:0.78rem;font-weight:600;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,77,77,0.3);background:rgba(255,77,77,0.08);transition:all 0.2s;" onmouseover="this.style.background='rgba(255,77,77,0.18)'" onmouseout="this.style.background='rgba(255,77,77,0.08)'" onclick="return confirm('Log out of staff panel?')">&#x2192; Log Out</a>
    </div>
</header>

<div class="page-wrapper">
    <div class="top-row">
        <div>
            <p class="page-label">Walk-in Booking</p>
            <h1 class="page-title">Select a Movie</h1>
            <p class="page-sub">Choose a movie then pick an available showtime.</p>
        </div>
        <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" placeholder="Search movie..." oninput="filterMovies(this.value)">
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>🎬 Now Showing</h2>
            <span style="font-size:0.75rem;color:rgba(249,249,249,0.3);"><?= $movies ? $movies->num_rows : 0 ?> movies</span>
        </div>
        <div class="panel-body">
            <?php if ($movies && $movies->num_rows > 0): ?>
            <div class="movies-grid" id="movieGrid">
                <?php while ($m = $movies->fetch_assoc()): ?>
                <div class="movie-card"
                     data-id="<?= $m['Movie_ID'] ?>"
                     data-movie="<?= htmlspecialchars($m['MovieName']) ?>"
                     onclick="selectMovie(<?= $m['Movie_ID'] ?>, '<?= htmlspecialchars(addslashes($m['MovieName'])) ?>')">
                    <div class="movie-poster" style="background-image:url('<?= htmlspecialchars($m['MoviePoster']) ?>');">
                        <div class="poster-check">✓</div>
                    </div>
                    <div class="movie-info">
                        <div class="movie-name"><?= htmlspecialchars($m['MovieName']) ?></div>
                        <div class="movie-meta"><?= htmlspecialchars($m['Genre']) ?> · <?= $m['Runtime'] ?>min</div>
                        <?php if ($m['ShowtimeCount'] > 0): ?>
                        <div class="showtime-count-badge" style="color:#66bb6a;">✓ <?= $m['ShowtimeCount'] ?> showtime<?= $m['ShowtimeCount'] != 1 ? 's' : '' ?></div>
                        <?php else: ?>
                        <div class="showtime-count-badge" style="color:rgba(249,249,249,0.25);">No upcoming showtimes</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="empty-icon">🎬</div><div>No Now Showing movies found.</div></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Showtime panel -->
    <div class="panel showtime-panel" id="showtimePanel">
        <div class="panel-header">
            <h2>📅 Showtimes — <span id="selectedMovieName"></span></h2>
        </div>
        <div class="panel-body">
            <div id="showtimeBody"><div class="showtime-loading">Loading showtimes...</div></div>
            <div style="margin-top:18px;text-align:right;">
                <button class="btn-proceed" id="proceedBtn" onclick="proceedToSeats()">Proceed to Seat Selection →</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedMovieId = null, selectedTimeslotId = null;

function selectMovie(movieId, movieName) {
    document.querySelectorAll('.movie-card').forEach(c => c.classList.remove('selected'));
    document.querySelector(`.movie-card[data-id="${movieId}"]`)?.classList.add('selected');
    selectedMovieId = movieId; selectedTimeslotId = null;
    document.getElementById('proceedBtn').classList.remove('active');
    document.getElementById('selectedMovieName').textContent = movieName;
    const panel = document.getElementById('showtimePanel');
    panel.classList.add('visible');
    document.getElementById('showtimeBody').innerHTML = '<div class="showtime-loading">Loading showtimes...</div>';
    fetch(`staff_showtimes.php?movie_id=${movieId}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) { document.getElementById('showtimeBody').innerHTML = `<div class="no-showtimes" style="color:#ff6b6b;">⚠️ ${data.error}</div>`; }
            else renderShowtimes(data);
        })
        .catch(() => { document.getElementById('showtimeBody').innerHTML = '<div class="no-showtimes" style="color:#ff6b6b;">⚠️ Could not connect to server.</div>'; });
    setTimeout(() => panel.scrollIntoView({ behavior:'smooth', block:'start' }), 100);
}

function renderShowtimes(data) {
    const body = document.getElementById('showtimeBody');
    if (!data || data.length === 0) { body.innerHTML = '<div class="no-showtimes">📭 No upcoming showtimes. Add screenings via the Admin panel.</div>'; return; }
    const grouped = {};
    data.forEach(s => { if (!grouped[s.date_label]) grouped[s.date_label] = []; grouped[s.date_label].push(s); });
    let html = '';
    for (const [dateLabel, slots] of Object.entries(grouped)) {
        html += `<div class="date-group"><div class="date-label">${dateLabel}</div><div class="time-slots">`;
        slots.forEach(s => {
            if (s.disabled) {
                html += `<div class="time-btn time-btn-disabled" title="Booking closed — less than 30 mins to showtime">
                    <span class="t-time">${s.time}</span><span class="t-type">${s.type}</span>
                    <span class="t-mall">${s.mall}</span><span class="t-closed">Closed</span>
                </div>`;
            } else {
                html += `<button class="time-btn" data-id="${s.timeslot_id}" onclick="selectTime(this,${s.timeslot_id})">
                    <span class="t-time">${s.time}</span><span class="t-type">${s.type}</span>
                    <span class="t-mall">${s.mall}</span>
                </button>`;
            }
        });
        html += `</div></div>`;
    }
    body.innerHTML = html;
}

function selectTime(btn, id) {
    document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected'); selectedTimeslotId = id;
    document.getElementById('proceedBtn').classList.add('active');
}

function proceedToSeats() {
    if (!selectedMovieId || !selectedTimeslotId) { alert('Please select a showtime first.'); return; }
    window.location.href = `staff_seats.php?movie_id=${selectedMovieId}&timeslot_id=${selectedTimeslotId}`;
}

function filterMovies(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('.movie-card').forEach(card => {
        card.style.display = (!q || (card.dataset.movie || '').toLowerCase().includes(q)) ? '' : 'none';
    });
}
</script>
</body>
</html>
