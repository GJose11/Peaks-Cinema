<?php
    include("../peakscinemas_database.php");

    // Fetch all malls with their theaters in one go
    $result = $conn->query("
        SELECT m.Mall_ID, m.MallName,
               t.Theater_ID, t.TheaterName
        FROM mall m
        LEFT JOIN theater t ON t.Mall_ID = m.Mall_ID
        ORDER BY m.MallName ASC, t.TheaterName ASC
    ");

    $malls = [];
    while ($row = $result->fetch_assoc()) {
        $mid = $row['Mall_ID'];
        if (!isset($malls[$mid])) {
            $malls[$mid] = ['id' => $mid, 'name' => $row['MallName'], 'theaters' => []];
        }
        if ($row['Theater_ID']) {
            $malls[$mid]['theaters'][] = ['id' => $row['Theater_ID'], 'name' => $row['TheaterName']];
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Select Mall – PeaksCinemas Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

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

        .logo {
            display:flex;
            align-items:center;
        }
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
            max-width: 900px;
            margin: 36px auto;
        }

        @media (max-width: 768px) {
            .page-wrapper { margin: 24px auto; }
            .theater-grid { grid-template-columns: 1fr !important; }
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin-bottom: 6px;
        }

        .page-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #F9F9F9;
            margin-bottom: 4px;
        }

        .page-subtitle {
            font-size: 0.85rem;
            color: rgba(249,249,249,0.35);
            margin-top: 4px;
        }

        /* ── Mall Cards ── */
        .malls-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .mall-card {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 14px;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .mall-card:hover { border-color: rgba(255,77,77,0.25); }
        .mall-card.open  { border-color: rgba(255,77,77,0.3); }

        .mall-toggle {
            width: 100%;
            background: none;
            border: none;
            color: #F9F9F9;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-align: left;
            transition: background 0.2s;
        }
        .mall-toggle:hover { background: rgba(255,255,255,0.03); }

        .mall-icon {
            width: 40px; height: 40px;
            background: rgba(255,77,77,0.1);
            border: 1px solid rgba(255,77,77,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .mall-card.open .mall-icon { background: rgba(255,77,77,0.18); }

        .mall-info { flex: 1; }

        .mall-name {
            font-size: 1rem;
            font-weight: 700;
            color: #F9F9F9;
            line-height: 1.3;
        }

        .mall-meta {
            font-size: 0.75rem;
            color: rgba(249,249,249,0.35);
            margin-top: 2px;
        }

        .chevron {
            width: 22px; height: 22px;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: rgba(249,249,249,0.4);
            transition: transform 0.25s ease, border-color 0.2s, color 0.2s;
            flex-shrink: 0;
        }
        .mall-card.open .chevron {
            transform: rotate(180deg);
            border-color: rgba(255,77,77,0.4);
            color: #ff4d4d;
        }

        /* ── Theaters dropdown ── */
        .theaters-panel {
            display: none;
            padding: 0 22px 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .mall-card.open .theaters-panel { display: block; }

        .theaters-label {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.3);
            padding: 14px 0 10px;
        }

        .theater-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
        }

        .theater-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            color: #F9F9F9;
            text-decoration: none;
            font-family: 'Outfit', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .theater-btn:hover {
            background: rgba(255,77,77,0.1);
            border-color: rgba(255,77,77,0.35);
            color: #ff4d4d;
            transform: translateX(3px);
        }

        .theater-btn-icon {
            font-size: 1rem;
            opacity: 0.6;
        }
        .theater-btn:hover .theater-btn-icon { opacity: 1; }

        .theater-btn-arrow {
            margin-left: auto;
            font-size: 0.7rem;
            opacity: 0.3;
            transition: opacity 0.2s, transform 0.2s;
        }
        .theater-btn:hover .theater-btn-arrow { opacity: 0.8; transform: translateX(3px); }

        .no-theaters {
            font-size: 0.82rem;
            color: rgba(249,249,249,0.25);
            padding: 10px 0;
            font-style: italic;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: rgba(249,249,249,0.2);
            font-size: 0.95rem;
        }
        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 12px; }
    </style>
</head>
<body>

<header>
    <div class="logo"><img src="../peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="malls_selection_admin.php" class="active">Malls</a>
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
    <a href="dashboard.php">Dashboard</a>
    <a href="malls_selection_admin.php" class="active">Malls</a>
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

    <div class="page-header">
        <p class="page-label">Admin Panel</p>
        <h1 class="page-title">Select a Mall</h1>
        <p class="page-subtitle">Choose a mall, then pick a theater to manage its screenings.</p>
    </div>

    <?php if (empty($malls)): ?>
        <div class="empty-state">
            <div class="empty-icon">🏬</div>
            No malls found. Add one via Mall Upload.
        </div>
    <?php else: ?>
    <div class="malls-list">
        <?php foreach ($malls as $mall): ?>
        <div class="mall-card" id="mall-<?= $mall['id'] ?>">

            <button class="mall-toggle" onclick="toggleMall(<?= $mall['id'] ?>)">
                <div class="mall-icon">🏬</div>
                <div class="mall-info">
                    <div class="mall-name"><?= htmlspecialchars($mall['name']) ?></div>
                    <div class="mall-meta">
                        <?= count($mall['theaters']) ?> theater<?= count($mall['theaters']) !== 1 ? 's' : '' ?>
                    </div>
                </div>
                <div class="chevron">▼</div>
            </button>

            <div class="theaters-panel">
                <div class="theaters-label">Select a Theater</div>
                <?php if (empty($mall['theaters'])): ?>
                    <p class="no-theaters">No theaters added to this mall yet.</p>
                <?php else: ?>
                <div class="theater-grid">
                    <?php foreach ($mall['theaters'] as $theater): ?>
                    <a class="theater-btn"
                       href="theater_admin.php?mall_id=<?= $mall['id'] ?>&theater_id=<?= $theater['id'] ?>">
                        <span class="theater-btn-icon">🎭</span>
                        <?= htmlspecialchars($theater['name']) ?>
                        <span class="theater-btn-arrow">›</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
    function toggleMall(id) {
        const card = document.getElementById('mall-' + id);
        card.classList.toggle('open');
    }
</script>
<script>
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        const isVisible = menu.style.display === 'block';
        menu.style.display = isVisible ? 'none' : 'block';
    }
</script>
</body>
</html>
