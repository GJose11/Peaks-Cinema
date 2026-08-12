<?php
    date_default_timezone_set('Asia/Manila');
    include("../peakscinemas_database.php");

    $posterFolder = dirname(__DIR__) . '/MoviePosters';
    if (!is_dir($posterFolder)) mkdir($posterFolder, 0755, true);

    $successMsg = "";
    $errorMsg   = "";

    function input_cleanup($data) {
        return stripslashes(trim($data));
    }

    // ── Handle DELETE ─────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_movie_id'])) {
        $del_id = (int)$_POST['delete_movie_id'];
        // Get poster path first
        $ps = $conn->prepare("SELECT MoviePoster FROM movie WHERE Movie_ID = ?");
        $ps->bind_param("i", $del_id); $ps->execute();
        $pm = $ps->get_result()->fetch_assoc();

        $ds = $conn->prepare("DELETE FROM movie WHERE Movie_ID = ?");
        $ds->bind_param("i", $del_id);
        if ($ds->execute()) {
            // Delete poster file if exists
            if (!empty($pm['MoviePoster'])) {
                $fp = $_SERVER['DOCUMENT_ROOT'] . '/' . $pm['MoviePoster'];
                if (file_exists($fp)) unlink($fp);
            }
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Movie deleted successfully.'];
        } else {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Delete failed: '.$conn->error];
        }
        header("Location: movie_upload.php"); exit;
    }

    // ── Handle AVAILABILITY TOGGLE ────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_movie_id'])) {
        $tog_id  = (int)$_POST['toggle_movie_id'];
        $new_avail = input_cleanup($_POST['new_availability']);
        if (in_array($new_avail, ['Now Showing', 'Coming Soon'])) {
            $us = $conn->prepare("UPDATE movie SET MovieAvailability = ? WHERE Movie_ID = ?");
            $us->bind_param("si", $new_avail, $tog_id);
            if ($us->execute()) {
                $_SESSION['flash'] = ['type'=>'success','msg'=>"Availability updated to \"$new_avail\"."];
            } else {
                $_SESSION['flash'] = ['type'=>'error','msg'=>'Update failed: '.$conn->error];
            }
        }
        header("Location: movie_upload.php"); exit;
    }

    // ── Handle UPLOAD ─────────────────────────────────────────────
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["moviePosterUp"])) {

        $MovieName        = input_cleanup($_POST['movieName']);
        $MovieDescription = input_cleanup($_POST['movieDesc']);
        $Genre            = input_cleanup($_POST['movieGenre']);
        $Rating           = input_cleanup($_POST['movieRating']);
        $Runtime          = input_cleanup($_POST['movieRuntime']);
        $TrailerURL       = input_cleanup($_POST['TrailerURL']);
        $MovieAvailability= input_cleanup($_POST['movieAvailability']);
        $Price            = (float)$_POST['moviePrice'];
        $MoviePoster      = "";

        if ($_FILES['moviePosterUp']['error'] === UPLOAD_ERR_OK) {
            $temp     = $_FILES['moviePosterUp']['tmp_name'];
            $fileType = strtolower(pathinfo($_FILES['moviePosterUp']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','webp'];

            if (!in_array($fileType, $allowed)) {
                $errorMsg = "Invalid file type. Please upload JPG, PNG, or WEBP.";
            } else {
                $safeMovieName = trim(preg_replace('/[\\\\\/:\*\?"<>\|]/', '', $MovieName));
                $fileName      = $safeMovieName . "." . $fileType;
                $endPath       = $posterFolder . "/" . $fileName;

                if (move_uploaded_file($temp, $endPath)) {
                    $MoviePoster = 'MoviePosters/' . $fileName;
                } else {
                    $errorMsg = "Failed to upload poster. Check folder permissions.";
                }
            }
        } else {
            $errorMsg = "No poster file received.";
        }

        if (!$errorMsg) {
            $check = $conn->prepare("SELECT Movie_ID FROM movie WHERE MovieName = ?");
            $check->bind_param("s", $MovieName); $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $errorMsg = "A movie with this name already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO movie(MovieName, MovieDescription, Genre, Rating, Runtime, MoviePoster, MovieAvailability, TrailerURL, Price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssisssd", $MovieName, $MovieDescription, $Genre, $Rating, $Runtime, $MoviePoster, $MovieAvailability, $TrailerURL, $Price);
                if ($stmt->execute()) {
                    $successMsg = "\"$MovieName\" uploaded successfully!";
                } else {
                    $errorMsg = "Database error: " . $conn->error;
                }
            }
        }
    }

    // Flash from redirect
    if (isset($_SESSION['flash'])) {
        if ($_SESSION['flash']['type'] === 'success') $successMsg = $_SESSION['flash']['msg'];
        else $errorMsg = $_SESSION['flash']['msg'];
        unset($_SESSION['flash']);
    }

    // Load ALL movies for management table
    $allMovies = $conn->query("
        SELECT Movie_ID, MovieName, Genre, Rating, Runtime, MovieAvailability, MoviePoster, Price
        FROM movie
        ORDER BY Movie_ID DESC
    ");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Movie Upload - PeaksCinemas Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #0f0f0f; color: #F9F9F9; min-height: 100vh; padding-top: 70px; padding-bottom: 60px; }
        body::before { content: ''; position: fixed; inset: 0; background: url('../movie-background-collage.jpg') center/cover no-repeat; opacity: 0.04; z-index: 0; pointer-events: none; }
        body::after  { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse at center, transparent 20%, #0f0f0f 75%); z-index: 1; pointer-events: none; }
        header, nav, .page-wrapper { position: relative; z-index: 10; }

        header { background: #1C1C1C; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; height: 60px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .logo { display:flex; align-items:center; }
        .logo img { height: 42px; width: auto; filter: invert(1); display: block; }
        nav { display: flex; gap: 4px; }

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

        nav a { color: rgba(249,249,249,0.5); text-decoration: none; font-size: 0.8rem; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: all 0.2s; }
        nav a:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }

        .page-wrapper { width: 95%; max-width: 1200px; margin: 40px auto; display: flex; flex-direction: column; gap: 28px; }

        @media (max-width: 768px) {
            .page-wrapper { margin: 24px auto; }
            .upload-form { padding: 20px !important; }
            .form-grid { grid-template-columns: 1fr !important; }
            .page-header { flex-direction: column; align-items: flex-start !important; gap: 10px; }
        }
        .page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
        .page-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 4px; }

        .upload-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }
        .upload-layout > .panel:first-child { position: sticky; top: 82px; align-self: start; }

        .panel { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; }
        .panel-header { padding: 14px 22px; border-bottom: 1px solid rgba(255,255,255,0.07); display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .panel-header h2 { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
        .panel-header-count { font-size: 0.68rem; font-weight: 700; background: rgba(255,77,77,0.12); color: #ff6b6b; border: 1px solid rgba(255,77,77,0.25); padding: 2px 9px; border-radius: 10px; }
        .panel-body { padding: 22px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: span 2; }
        .form-group label { font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); }
        .form-group input, .form-group select, .form-group textarea {
            background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 9px;
            color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.88rem;
            padding: 10px 13px; outline: none; transition: border-color 0.2s; width: 100%;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
        .form-group select option { background: #222; }
        .form-group textarea { resize: vertical; min-height: 110px; line-height: 1.6; }

        .genre-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
        .genre-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.5); cursor: pointer; transition: all 0.15s; user-select: none; }
        .genre-pill:hover, .genre-pill.selected { background: rgba(255,77,77,0.12); border-color: rgba(255,77,77,0.4); color: #ff4d4d; }

        .upload-zone { border: 2px dashed rgba(255,255,255,0.12); border-radius: 10px; padding: 24px; text-align: center; cursor: pointer; transition: all 0.2s; position: relative; }
        .upload-zone:hover, .upload-zone.drag-over { border-color: rgba(255,77,77,0.5); background: rgba(255,77,77,0.04); }
        .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-zone-icon { font-size: 2rem; margin-bottom: 8px; }
        .upload-zone-text { font-size: 0.82rem; color: rgba(249,249,249,0.4); }
        .upload-zone-text strong { color: #ff4d4d; }
        .upload-zone-hint { font-size: 0.72rem; color: rgba(249,249,249,0.25); margin-top: 4px; }

        .btn-submit { width: 100%; padding: 13px; margin-top: 10px; background: #ff4d4d; border: none; border-radius: 9px; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: background 0.2s, transform 0.15s; }
        .btn-submit:hover { background: #e03c3c; transform: scale(1.01); }

        .preview-panel { position: sticky; top: 82px; }
        .poster-preview-box { aspect-ratio: 2/3; border-radius: 12px; overflow: hidden; background: #222; border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; margin-bottom: 14px; max-width: 400px; margin-left: auto; margin-right: auto; }
        .poster-preview-box img { width: 100%; height: 100%; object-fit: cover; display: none; }
        .poster-placeholder { display: flex; flex-direction: column; align-items: center; gap: 10px; color: rgba(249,249,249,0.2); font-size: 0.82rem; }
        .poster-placeholder .ph-icon { font-size: 2.5rem; }
        .preview-movie-title { font-size: 1rem; font-weight: 800; margin-bottom: 6px; min-height: 24px; color: #F9F9F9; }
        .badge-preview { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 6px; }
        .badge-now  { background: rgba(77,210,100,0.15); color: #4dd264; border: 1px solid rgba(77,210,100,0.3); }
        .badge-soon { background: rgba(255,200,50,0.12);  color: #ffc83d; border: 1px solid rgba(255,200,50,0.3); }
        .preview-meta { font-size: 0.78rem; color: rgba(249,249,249,0.35); }

        /* ── Movie Card Grid ── */
        .movie-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            padding: 24px;
        }
        .movie-mgmt-card {
            background: #222;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, border-color 0.2s;
        }
        .movie-mgmt-card:hover { transform: translateY(-2px); border-color: rgba(255,77,77,0.3); }
        .movie-mgmt-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
            background: #111;
            display: block;
        }
        .movie-mgmt-body { padding: 12px; }
        .movie-mgmt-title { font-size: 0.82rem; font-weight: 700; margin-bottom: 4px; color: #F9F9F9; }
        .movie-mgmt-genre { font-size: 0.68rem; color: rgba(249,249,249,0.4); margin-bottom: 8px; }
        .movie-mgmt-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-toggle-avail { flex: 1; padding: 5px 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.6); font-family: 'Outfit', sans-serif; font-size: 0.68rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-toggle-avail:hover { background: rgba(255,77,77,0.1); border-color: rgba(255,77,77,0.3); color: #ff4d4d; }
        .btn-delete-movie { padding: 5px 8px; border-radius: 6px; border: 1px solid rgba(255,77,77,0.25); background: rgba(255,77,77,0.06); color: #ff6b6b; font-family: 'Outfit', sans-serif; font-size: 0.68rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-delete-movie:hover { background: rgba(255,77,77,0.18); color: #fff; }
        .avail-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.62rem; font-weight: 700; margin-bottom: 6px; }
        .avail-now { background: rgba(76,175,80,0.12); border: 1px solid rgba(76,175,80,0.25); color: #81c784; }
        .avail-coming { background: rgba(255,200,50,0.12); border: 1px solid rgba(255,200,50,0.25); color: #ffd54f; }
        .avail-other { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: rgba(249,249,249,0.4); }

        /* Search/filter bar */
        .mgmt-filter {
            display: flex; gap: 10px; align-items: center; padding: 14px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.07); flex-wrap: wrap;
        }
        .mgmt-search {
            flex: 1; min-width: 180px; padding: 8px 12px;
            background: #222; border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px; color: #F9F9F9; font-family: 'Outfit',sans-serif;
            font-size: 0.82rem; outline: none;
        }
        .mgmt-search:focus { border-color: rgba(255,77,77,0.4); }
        .mgmt-filter-btn {
            padding: 7px 14px; border-radius: 8px; font-family: 'Outfit',sans-serif;
            font-size: 0.75rem; font-weight: 700; cursor: pointer; border: none;
            transition: all 0.2s;
        }
        .mgmt-filter-btn.all      { background: rgba(255,255,255,0.07); color: rgba(249,249,249,0.6); }
        .mgmt-filter-btn.showing  { background: rgba(77,210,100,0.1);   color: #4dd264; border: 1px solid rgba(77,210,100,0.25); }
        .mgmt-filter-btn.coming   { background: rgba(255,200,50,0.1);   color: #ffc83d; border: 1px solid rgba(255,200,50,0.25); }
        .mgmt-filter-btn.active, .mgmt-filter-btn:hover { opacity: 1; filter: brightness(1.2); outline: 2px solid currentColor; outline-offset: 1px; }

        /* Confirm delete modal */
        .del-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:9000; align-items:center; justify-content:center; }
        .del-modal.open { display:flex; }
        .del-modal-box { background:#1a1a1a; border:1px solid rgba(255,77,77,0.3); border-radius:14px; padding:28px 24px; max-width:380px; width:90%; }
        .del-modal-title { font-size:1rem; font-weight:800; margin-bottom:8px; color:#F9F9F9; }
        .del-modal-sub   { font-size:0.82rem; color:rgba(249,249,249,0.45); margin-bottom:20px; line-height:1.6; }
        .del-modal-movie { font-weight:700; color:#ff4d4d; }
        .del-modal-btns  { display:flex; gap:10px; }
        .del-btn-confirm { flex:1; padding:11px; border-radius:9px; border:none; background:#ff4d4d; color:#fff; font-family:'Outfit',sans-serif; font-size:0.88rem; font-weight:700; cursor:pointer; transition:background 0.2s; }
        .del-btn-confirm:hover { background:#e03c3c; }
        .del-btn-cancel  { flex:1; padding:11px; border-radius:9px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.04); color:rgba(249,249,249,0.6); font-family:'Outfit',sans-serif; font-size:0.88rem; cursor:pointer; }

        .toast { position: fixed; bottom: 28px; right: 28px; background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 14px 22px; font-size: 0.85rem; color: #F9F9F9; box-shadow: 0 8px 24px rgba(0,0,0,0.5); z-index: 9999; animation: slideIn 0.3s ease; }
        .toast.success { border-left: 3px solid #4caf50; }
        .toast.error   { border-left: 3px solid #ff4d4d; }
        @keyframes slideIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }

        .empty-state { text-align:center; padding:40px 20px; color:rgba(249,249,249,0.2); font-size:0.85rem; }
    </style>
</head>
<body>

<header>
    <div class="logo"><img src="../peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="malls_selection_admin.php">Malls</a>
        <a href="malls_selection_admin.php">➕ Add Screenings</a>
        <a href="movie_upload.php" class="active">Movie Upload</a>
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
           style="color:rgba(249,249,249,0.45);text-decoration:none;font-size:0.78rem;font-weight:500;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);transition:all 0.2s;"
           onmouseover="this.style.background='rgba(255,255,255,0.06)';this.style.color='#F9F9F9'"
           onmouseout="this.style.background='';this.style.color='rgba(249,249,249,0.45)'">
            ← Customer Site
        </a>
        <a href="admin_logout.php" class="desktop-only"
           style="color:#ff4d4d;text-decoration:none;font-size:0.78rem;font-weight:600;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,77,77,0.3);background:rgba(255,77,77,0.08);transition:all 0.2s;"
           onmouseover="this.style.background='rgba(255,77,77,0.18)'"
           onmouseout="this.style.background='rgba(255,77,77,0.08)'"
           onclick="return confirm('Log out of admin panel?')">
            → Log Out
        </a>
    </div>
</header>

<div id="mobileMenu">
    <a href="dashboard.php">Dashboard</a>
    <a href="malls_selection_admin.php">Malls</a>
    <a href="movie_upload.php" class="active">Movie Upload</a>
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

<!-- Delete confirmation modal -->
<div class="del-modal" id="delModal">
    <div class="del-modal-box">
        <div class="del-modal-title">🗑 Delete Movie?</div>
        <p class="del-modal-sub">You are about to permanently delete <span class="del-modal-movie" id="delMovieName"></span>. This will also remove the poster file and cannot be undone.</p>
        <form method="POST" id="delForm">
            <input type="hidden" name="delete_movie_id" id="delMovieId">
            <div class="del-modal-btns">
                <button type="submit" class="del-btn-confirm">Yes, Delete</button>
                <button type="button" class="del-btn-cancel" onclick="closeDelModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="page-wrapper">

    <div>
        <p class="page-label">Admin Panel</p>
        <h1 class="page-title">Movie Management</h1>
    </div>

    <!-- ══ SECTION 1: UPLOAD NEW MOVIE ══════════════════════════ -->
    <div class="upload-layout">
        <div class="panel">
            <div class="panel-header"><h2>🎬 Upload New Movie</h2></div>
            <div class="panel-body">
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <div class="form-grid">

                        <div class="form-group full">
                            <label>Movie Name</label>
                            <input type="text" name="movieName" placeholder="e.g. The Batman" required oninput="updatePreviewTitle(this.value)">
                        </div>

                        <div class="form-group full">
                            <label>Synopsis</label>
                            <textarea name="movieDesc" placeholder="Write a short synopsis..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Genre</label>
                            <input type="text" name="movieGenre" id="movieGenre" placeholder="e.g. Action/Crime" required oninput="updatePreviewMeta()">
                            <div class="genre-pills">
                                <?php foreach (['Action','Comedy','Drama','Horror','Romance','Thriller','Sci-Fi','Animation','Fantasy','Crime'] as $g): ?>
                                <span class="genre-pill" onclick="setGenre('<?= $g ?>')"><?= $g ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Age Rating</label>
                            <select name="movieRating" required>
                                <option value="">Select a rating</option>
                                <option value="G">Rated G</option>
                                <option value="PG">Rated PG</option>
                                <option value="R-13">Rated R-13</option>
                                <option value="R-16">Rated R-16</option>
                                <option value="R-18">Rated R-18</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Runtime (minutes)</label>
                            <input type="number" name="movieRuntime" placeholder="e.g. 176" min="1" max="999" required oninput="updatePreviewMeta()">
                        </div>

                        <div class="form-group">
                            <label>Base Ticket Price (₱)</label>
                            <input type="number" name="moviePrice" placeholder="e.g. 350" min="1" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label>Availability</label>
                            <select name="movieAvailability" required onchange="updatePreviewBadge(this.value)">
                                <option value="Now Showing">Now Showing</option>
                                <option value="Coming Soon">Coming Soon</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label>YouTube Trailer URL</label>
                            <input type="url" name="TrailerURL" placeholder="https://www.youtube.com/watch?v=...">
                        </div>

                        <div class="form-group full">
                            <label>Movie Poster</label>
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" name="moviePosterUp" id="moviePosterUp" accept="image/png,image/jpeg,image/jpg,image/webp" required onchange="handlePosterChange(this)">
                                <div class="upload-zone-icon">🖼</div>
                                <div class="upload-zone-text"><strong>Click to upload</strong> or drag &amp; drop</div>
                                <div class="upload-zone-hint" id="uploadHint">JPG, PNG, WEBP — 2:3 ratio recommended</div>
                            </div>
                        </div>

                    </div>
                    <button type="submit" class="btn-submit">✓ Upload Movie</button>
                </form>
            </div>
        </div>

        <!-- Live Preview -->
        <div class="preview-panel">
            <div class="panel">
                <div class="panel-header"><h2>👁 Live Preview</h2></div>
                <div class="panel-body">
                    <div class="poster-preview-box">
                        <div class="poster-placeholder" id="posterPlaceholder">
                            <span class="ph-icon">🎞</span>
                            <span>Poster appears here</span>
                        </div>
                        <img id="posterPreview" src="" alt="Poster Preview">
                    </div>
                    <div class="preview-movie-title" id="previewTitle">Movie Title</div>
                    <div><span class="badge-preview badge-now" id="previewBadge">Now Showing</span></div>
                    <div class="preview-meta" id="previewMeta" style="margin-top:6px;">Fill in the form to preview</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ SECTION 2: MANAGE ALL MOVIES ═════════════════════════ -->
    <div class="panel">
        <div class="panel-header">
            <h2>🎞 All Movies</h2>
            <span class="panel-header-count" id="movieCountBadge">
                <?= $allMovies ? $allMovies->num_rows : 0 ?> total
            </span>
        </div>

        <!-- Search + filter -->
        <div class="mgmt-filter">
            <input class="mgmt-search" type="text" id="movieSearch"
                   placeholder="🔍 Search by title, genre..." oninput="filterMovies()">
            <button class="mgmt-filter-btn all active"     id="fAll"     onclick="setFilter('all')">All</button>
            <button class="mgmt-filter-btn showing"        id="fShowing" onclick="setFilter('showing')">🟢 Now Showing</button>
            <button class="mgmt-filter-btn coming"         id="fComing"  onclick="setFilter('coming')">🟡 Coming Soon</button>
        </div>

        <?php if (!$allMovies || $allMovies->num_rows === 0): ?>
        <div class="empty-state">No movies in the database yet. Upload one above!</div>
        <?php else: ?>
        <div class="movie-card-grid" id="movieGrid">
<?php while ($m = $allMovies->fetch_assoc()):
            $avail = $m['MovieAvailability'] ?? '';
            $badgeClass = str_contains(strtolower($avail),'now') ? 'avail-now' : (str_contains(strtolower($avail),'coming') ? 'avail-coming' : 'avail-other');
        ?>
        <div class="movie-mgmt-card"
             data-name="<?= strtolower(htmlspecialchars($m['MovieName'])) ?>"
             data-genre="<?= strtolower(htmlspecialchars($m['Genre'] ?? '')) ?>"
             data-avail="<?= str_contains(strtolower($avail),'now') ? 'showing' : 'coming' ?>">
            <?php if (!empty($m['MoviePoster'])): ?>
            <img class="movie-mgmt-poster" src="../<?= htmlspecialchars($m['MoviePoster']) ?>" alt="<?= htmlspecialchars($m['MovieName']) ?>">
            <?php else: ?>
            <div class="movie-mgmt-poster" style="display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(249,249,249,0.2);">🎬</div>
            <?php endif; ?>
            <div class="movie-mgmt-body">
                <span class="avail-badge <?= $badgeClass ?>"><?= htmlspecialchars($avail ?: 'Unknown') ?></span>
                <div class="movie-mgmt-title"><?= htmlspecialchars($m['MovieName']) ?></div>
                <div class="movie-mgmt-genre"><?= htmlspecialchars($m['Genre'] ?? '—') ?></div>
                <div class="movie-mgmt-actions">
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="toggle_movie_id" value="<?= (int)$m['Movie_ID'] ?>">
                        <?php if (str_contains(strtolower($avail),'now')): ?>
                            <input type="hidden" name="new_availability" value="Coming Soon">
                            <button type="submit" class="btn-toggle-avail" style="width:100%;"
                                    onclick="return confirm('Move &quot;<?= addslashes($m['MovieName']) ?>&quot; to Coming Soon?')">
                                ⏸ Unlist
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="new_availability" value="Now Showing">
                            <button type="submit" class="btn-toggle-avail" style="width:100%;"
                                    onclick="return confirm('Move &quot;<?= addslashes($m['MovieName']) ?>&quot; to Now Showing?')">
                                ▶ List
                            </button>
                        <?php endif; ?>
                    </form>
                    <button class="btn-delete-movie"
                            onclick="openDelModal(<?= $m['Movie_ID'] ?>, '<?= addslashes(htmlspecialchars($m['MovieName'])) ?>')">
                        🗑
                    </button>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
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
function toggleMobileMenu() {
    const m = document.getElementById('mobileMenu');
    m.style.display = (m.style.display === 'block') ? 'none' : 'block';
}

// ── Upload form helpers ───────────────────────────────────────────
function handlePosterChange(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('posterPreview');
        img.src = e.target.result;
        img.style.display = 'block';
        document.getElementById('posterPlaceholder').style.display = 'none';
        document.getElementById('uploadHint').textContent = '📎 ' + input.files[0].name;
    };
    reader.readAsDataURL(input.files[0]);
}
function updatePreviewTitle(val) { document.getElementById('previewTitle').textContent = val || 'Movie Title'; }
function updatePreviewBadge(val) {
    const badge = document.getElementById('previewBadge');
    badge.textContent = val;
    badge.className = 'badge-preview ' + (val === 'Now Showing' ? 'badge-now' : 'badge-soon');
}
function setGenre(genre) {
    const input = document.getElementById('movieGenre');
    if (!input.value) input.value = genre;
    else if (!input.value.includes(genre)) input.value += '/' + genre;
    document.querySelectorAll('.genre-pill').forEach(p => p.classList.toggle('selected', input.value.includes(p.textContent)));
    updatePreviewMeta();
}
function updatePreviewMeta() {
    const genre   = document.getElementById('movieGenre').value;
    const runtime = document.querySelector('[name="movieRuntime"]')?.value;
    const parts   = [];
    if (genre)   parts.push(genre);
    if (runtime) parts.push(runtime + ' mins');
    document.getElementById('previewMeta').textContent = parts.join(' · ') || 'Fill in the form to preview';
}
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
zone.addEventListener('drop',      e  => { e.preventDefault(); zone.classList.remove('drag-over'); });

// ── Delete modal ──────────────────────────────────────────────────
function openDelModal(id, name) {
    document.getElementById('delMovieId').value  = id;
    document.getElementById('delMovieName').textContent = '"' + name + '"';
    document.getElementById('delModal').classList.add('open');
}
function closeDelModal() { document.getElementById('delModal').classList.remove('open'); }
document.getElementById('delModal').addEventListener('click', e => { if (e.target === document.getElementById('delModal')) closeDelModal(); });

// ── Search & filter ───────────────────────────────────────────────
let currentFilter = 'all';

function setFilter(f) {
    currentFilter = f;
    document.querySelectorAll('.mgmt-filter-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('f' + f.charAt(0).toUpperCase() + f.slice(1)).classList.add('active');
    filterMovies();
}

function filterMovies() {
    const q     = document.getElementById('movieSearch').value.toLowerCase();
    const cards = document.querySelectorAll('#movieGrid .movie-mgmt-card');
    let visible = 0;
    cards.forEach(card => {
        const name  = card.dataset.name  || '';
        const genre = card.dataset.genre || '';
        const avail = card.dataset.avail || '';
        const matchSearch = !q || name.includes(q) || genre.includes(q);
        const matchFilter = currentFilter === 'all' || avail === currentFilter;
        const show = matchSearch && matchFilter;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('movieCountBadge').textContent = visible + ' total';
}
</script>
</body>
</html>
