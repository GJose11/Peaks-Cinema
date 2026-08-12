<?php
date_default_timezone_set('Asia/Manila');
session_start();
include("peakscinemas_database.php");

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$uid      = (int)$_SESSION['user_id'];
$movie_id = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
if (!$movie_id) { header("Location: home.php"); exit; }

// Profile
$ps = $conn->prepare("SELECT Name, ProfilePhoto FROM customer WHERE Customer_ID=?");
$ps->bind_param("i", $uid); $ps->execute();
$user = $ps->get_result()->fetch_assoc();
$profile_photo = $user['ProfilePhoto'] ?? $_SESSION['profile_photo'] ?? null;
$np = explode(' ', trim($user['Name'] ?? ''));
$user_initials = strtoupper(substr($np[0]??'',0,1).substr(end($np)??'',0,1));
if (strlen($user_initials)===1) $user_initials = strtoupper(substr($np[0]??'',0,2));

// Movie details
$ms = $conn->prepare("SELECT Movie_ID, MovieName, MoviePoster, Genre FROM movie WHERE Movie_ID=?");
$ms->bind_param("i", $movie_id); $ms->execute();
$movie = $ms->get_result()->fetch_assoc();
if (!$movie) { header("Location: home.php"); exit; }

// Handle review submission
$msg = ''; $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating    = (int)($_POST['rating'] ?? 0);
    $comment   = trim($_POST['comment'] ?? '');
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);

    if ($rating < 1 || $rating > 5) { $msg = "Please select a rating (1–5 stars)."; $msgType = 'err'; }
    elseif (strlen($comment) < 10)  { $msg = "Please write at least 10 characters."; $msgType = 'err'; }
    else {
        // Verify ticket belongs to this customer and movie
        $chk = $conn->prepare("SELECT Ticket_ID FROM ticket WHERE Ticket_ID=? AND Customer_ID=? AND Movie_ID=? AND Status=1");
        $chk->bind_param("iii", $ticket_id, $uid, $movie_id); $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            $msg = "Invalid ticket. You can only review movies you have watched."; $msgType = 'err';
        } else {
            $ins = $conn->prepare("INSERT INTO reviews (Customer_ID, Movie_ID, Ticket_ID, Rating, Comment) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE Rating=VALUES(Rating), Comment=VALUES(Comment)");
            $ins->bind_param("iiiiis", $uid, $movie_id, $ticket_id, $rating, $comment);
            // Fix: 5 params
            $ins2 = $conn->prepare("INSERT INTO reviews (Customer_ID, Movie_ID, Ticket_ID, Rating, Comment) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE Rating=VALUES(Rating), Comment=VALUES(Comment)");
            $ins2->bind_param("iiiis", $uid, $movie_id, $ticket_id, $rating, $comment);
            if ($ins2->execute()) {
                $msg = "Your review has been submitted! Thank you 🎬"; $msgType = 'ok';
            } else {
                $msg = "Error saving review. Please try again."; $msgType = 'err';
            }
        }
    }
}

// Check if user has an eligible ticket (watched this movie)
$eligible = $conn->prepare("
    SELECT tk.Ticket_ID, ts.Date, ts.StartTime
    FROM ticket tk
    JOIN timeslot ts ON ts.TimeSlot_ID = tk.TimeSlot_ID
    LEFT JOIN reviews rv ON rv.Ticket_ID = tk.Ticket_ID
    WHERE tk.Customer_ID=? AND tk.Movie_ID=? AND tk.Status=1
      AND CONCAT(ts.Date,' ',ts.StartTime) < NOW()
      AND rv.Review_ID IS NULL
    LIMIT 1
");
$eligible->bind_param("ii", $uid, $movie_id); $eligible->execute();
$eligibleTicket = $eligible->get_result()->fetch_assoc();

// Check existing review
$existing = $conn->prepare("SELECT * FROM reviews WHERE Customer_ID=? AND Movie_ID=? ORDER BY Created_At DESC LIMIT 1");
$existing->bind_param("ii", $uid, $movie_id); $existing->execute();
$myReview = $existing->get_result()->fetch_assoc();

// Fetch ALL reviews for this movie
$all = $conn->prepare("
    SELECT rv.*, c.Name AS CustomerName, c.ProfilePhoto
    FROM reviews rv
    JOIN customer c ON c.Customer_ID = rv.Customer_ID
    WHERE rv.Movie_ID = ?
    ORDER BY rv.Created_At DESC
");
$all->bind_param("i", $movie_id); $all->execute();
$allReviews = $all->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$totalReviews = count($allReviews);
$avgRating    = $totalReviews > 0 ? array_sum(array_column($allReviews, 'Rating')) / $totalReviews : 0;
$ratingCounts = [5=>0,4=>0,3=>0,2=>0,1=>0];
foreach ($allReviews as $r) $ratingCounts[(int)$r['Rating']]++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Reviews — <?= htmlspecialchars($movie['MovieName']) ?></title>
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Outfit',sans-serif; background:#0f0f0f; color:#F9F9F9; min-height:100vh; padding-top:70px; padding-bottom:60px; }
body::before { content:''; position:fixed; inset:0; background:url('movie-background-collage.jpg') center/cover no-repeat; opacity:0.12; z-index:0; pointer-events:none; }
body::after  { content:''; position:fixed; inset:0; background:radial-gradient(ellipse at center,transparent 10%,rgba(15,15,15,0.55) 60%,#0f0f0f 100%); z-index:1; pointer-events:none; }
header { background:#1C1C1C; display:flex; align-items:center; justify-content:space-between; padding:0 30px; position:fixed; top:0; left:0; width:100%; height:60px; z-index:1000; border-bottom:1px solid rgba(255,255,255,0.06); transition:transform 0.35s cubic-bezier(0.4,0,0.2,1); }
.logo img { height:46px; cursor:pointer; filter:invert(1); }
.profile-btn { background:#F9F9F9; border:none; border-radius:50%; width:42px; height:42px; display:flex; align-items:center; justify-content:center; cursor:pointer; overflow:hidden; padding:0; }
.profile-btn img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
.profile-initials { width:100%; height:100%; border-radius:50%; background:linear-gradient(135deg,#ff4d4d,#c0392b); display:flex; align-items:center; justify-content:center; font-size:0.82rem; font-weight:800; color:#fff; }
.outer { position:relative; z-index:10; width:95%; max-width:900px; margin:28px auto; }
.page-label { font-size:0.72rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#ff4d4d; margin-bottom:5px; }
.page-title  { font-size:1.7rem; font-weight:800; margin-bottom:20px; }
.panel { background:#1a1a1a; border:1px solid rgba(255,255,255,0.07); border-radius:14px; overflow:hidden; margin-bottom:16px; }
.panel-header { padding:14px 20px; border-bottom:1px solid rgba(255,255,255,0.06); }
.panel-header h2 { font-size:0.78rem; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:rgba(249,249,249,0.45); }
.panel-body { padding:20px; }
.msg { padding:12px 16px; border-radius:9px; font-size:0.82rem; margin-bottom:16px; }
.msg.ok  { background:rgba(76,175,80,0.08); border:1px solid rgba(76,175,80,0.2); color:#81c784; }
.msg.err { background:rgba(255,77,77,0.08); border:1px solid rgba(255,77,77,0.2); border-left:3px solid #ff4d4d; color:#ff6b6b; }

/* Movie info strip */
.movie-strip { display:flex; gap:16px; align-items:center; padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.06); }
.movie-strip-poster { width:48px; height:72px; border-radius:6px; background-size:cover; background-position:center; background-color:#111; flex-shrink:0; border:1px solid rgba(255,255,255,0.08); }
.movie-strip h3 { font-size:0.95rem; font-weight:800; margin-bottom:3px; }
.movie-strip p  { font-size:0.75rem; color:rgba(249,249,249,0.4); }
.back-link { font-size:0.78rem; color:#ff6b6b; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:4px; }
.back-link:hover { color:#ff4d4d; }

/* Rating summary */
.rating-summary { display:flex; gap:24px; align-items:center; margin-bottom:20px; }
.avg-score { text-align:center; flex-shrink:0; }
.avg-num  { font-size:3rem; font-weight:800; color:#F9F9F9; line-height:1; }
.avg-stars{ font-size:1.2rem; margin:4px 0; }
.avg-count{ font-size:0.72rem; color:rgba(249,249,249,0.35); }
.rating-bars { flex:1; }
.rbar-row { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.rbar-label { font-size:0.72rem; color:rgba(249,249,249,0.45); width:12px; flex-shrink:0; }
.rbar-track { flex:1; height:6px; background:rgba(255,255,255,0.08); border-radius:3px; overflow:hidden; }
.rbar-fill  { height:100%; background:#ff4d4d; border-radius:3px; transition:width 0.4s ease; }
.rbar-count { font-size:0.68rem; color:rgba(249,249,249,0.3); width:22px; text-align:right; flex-shrink:0; }

/* Star selector */
.star-row { display:flex; gap:6px; margin-bottom:14px; }
.star-btn { font-size:1.6rem; cursor:pointer; background:none; border:none; color:rgba(255,255,255,0.2); transition:color 0.15s; line-height:1; padding:0; }
.star-btn.lit { color:#f5a623; }
.star-btn:hover { color:#f5a623; }
input#rating-val { display:none; }

/* Fields */
.field { margin-bottom:14px; }
.field label { display:block; font-size:0.68rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:rgba(249,249,249,0.4); margin-bottom:6px; }
.field textarea { width:100%; padding:10px 13px; border-radius:9px; border:1px solid rgba(255,255,255,0.1); background:#222; color:#F9F9F9; font-family:'Outfit',sans-serif; font-size:0.88rem; outline:none; resize:vertical; min-height:100px; transition:border-color 0.2s; }
.field textarea:focus { border-color:rgba(255,77,77,0.5); background:#252525; }
.btn-submit { padding:11px 28px; border-radius:9px; border:none; background:#ff4d4d; color:#fff; font-family:'Outfit',sans-serif; font-size:0.88rem; font-weight:700; cursor:pointer; transition:all 0.2s; }
.btn-submit:hover { background:#e03c3c; transform:translateY(-1px); box-shadow:0 6px 18px rgba(255,77,77,0.3); }

/* Review cards */
.review-card { padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.04); }
.review-card:last-child { border-bottom:none; }
.review-top { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.reviewer-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#ff4d4d,#c0392b); display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:800; color:#fff; flex-shrink:0; overflow:hidden; }
.reviewer-avatar img { width:100%; height:100%; object-fit:cover; }
.reviewer-name { font-size:0.85rem; font-weight:700; }
.review-date   { font-size:0.68rem; color:rgba(249,249,249,0.3); margin-left:auto; }
.review-stars  { font-size:0.9rem; margin-bottom:6px; }
.review-comment { font-size:0.82rem; color:rgba(249,249,249,0.65); line-height:1.6; }
.no-reviews { text-align:center; padding:40px; color:rgba(249,249,249,0.2); font-size:0.85rem; }
.my-review-badge { display:inline-block; font-size:0.6rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; background:rgba(255,77,77,0.1); border:1px solid rgba(255,77,77,0.2); color:#ff6b6b; padding:2px 8px; border-radius:8px; margin-left:6px; vertical-align:middle; }
</style>
</head>
<body>

<header>
    <div class="logo"><img src="peakscinematransparent.png" alt="Peak's Cinema" onclick="window.location.href='home.php'"></div>
    <button class="profile-btn" onclick="window.location.href='profile_dashboard.php'">
        <?php if (!empty($profile_photo)): ?>
            <img src="<?= htmlspecialchars($profile_photo) ?>" referrerpolicy="no-referrer">
        <?php elseif (!empty($user_initials)): ?>
            <div class="profile-initials"><?= htmlspecialchars($user_initials) ?></div>
        <?php else: ?>
            <div class="profile-initials">?</div>
        <?php endif; ?>
    </button>
</header>

<div class="outer">
    <p class="page-label">Community</p>
    <h1 class="page-title">Movie Reviews</h1>

    <!-- Movie strip -->
    <div class="panel">
        <div class="movie-strip">
            <div class="movie-strip-poster" style="background-image:url('<?= htmlspecialchars($movie['MoviePoster']) ?>');"></div>
            <div>
                <h3><?= htmlspecialchars($movie['MovieName']) ?></h3>
                <p><?= htmlspecialchars($movie['Genre']) ?></p>
                <a href="movie.php?movie_id=<?= $movie_id ?>" class="back-link" style="margin-top:6px;display:inline-flex;">← Back to Movie</a>
            </div>
        </div>

        <!-- Rating summary -->
        <?php if ($totalReviews > 0): ?>
        <div class="panel-body" style="border-top:1px solid rgba(255,255,255,0.06);">
            <div class="rating-summary">
                <div class="avg-score">
                    <div class="avg-num"><?= number_format($avgRating, 1) ?></div>
                    <div class="avg-stars"><?= str_repeat('★', round($avgRating)) . str_repeat('☆', 5 - round($avgRating)) ?></div>
                    <div class="avg-count"><?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></div>
                </div>
                <div class="rating-bars">
                    <?php for ($s = 5; $s >= 1; $s--):
                        $pct = $totalReviews > 0 ? ($ratingCounts[$s] / $totalReviews) * 100 : 0;
                    ?>
                    <div class="rbar-row">
                        <span class="rbar-label"><?= $s ?></span>
                        <div class="rbar-track"><div class="rbar-fill" style="width:<?= $pct ?>%;"></div></div>
                        <span class="rbar-count"><?= $ratingCounts[$s] ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Write a review -->
    <?php if (!empty($msg)): ?>
    <div class="msg <?= $msgType ?>"><?= $msgType==='ok'?'✓':'⚠' ?> <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($myReview): ?>
    <div class="panel" style="border-color:rgba(255,77,77,0.2);">
        <div class="panel-header"><h2>⭐ Your Review</h2></div>
        <div class="review-card">
            <div class="review-stars"><?= str_repeat('★', $myReview['Rating']) . str_repeat('☆', 5 - $myReview['Rating']) ?></div>
            <p class="review-comment"><?= htmlspecialchars($myReview['Comment']) ?></p>
            <p style="font-size:0.68rem;color:rgba(249,249,249,0.25);margin-top:8px;"><?= date('F d, Y', strtotime($myReview['Created_At'])) ?></p>
        </div>
    </div>
    <?php elseif ($eligibleTicket): ?>
    <div class="panel">
        <div class="panel-header"><h2>✍️ Write a Review</h2></div>
        <div class="panel-body">
            <form method="POST">
                <input type="hidden" name="ticket_id" value="<?= $eligibleTicket['Ticket_ID'] ?>">
                <input type="hidden" name="submit_review" value="1">
                <input type="hidden" id="rating-val" name="rating" value="0">
                <div class="field">
                    <label>Your Rating</label>
                    <div class="star-row">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <button type="button" class="star-btn" data-val="<?= $s ?>" onclick="setRating(<?= $s ?>)">☆</button>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="field">
                    <label>Your Review</label>
                    <textarea name="comment" placeholder="Share your thoughts about this movie..."></textarea>
                </div>
                <button type="submit" class="btn-submit">Submit Review →</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="panel">
        <div class="panel-body" style="text-align:center;padding:24px;color:rgba(249,249,249,0.3);font-size:0.85rem;">
            🎟 You can only review movies you have watched.<br>
            <a href="home.php" style="color:#ff6b6b;font-weight:600;text-decoration:none;margin-top:8px;display:inline-block;">Browse Movies →</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- All reviews -->
    <div class="panel">
        <div class="panel-header"><h2>💬 All Reviews (<?= $totalReviews ?>)</h2></div>
        <?php if (empty($allReviews)): ?>
        <div class="no-reviews">No reviews yet. Be the first to review this movie!</div>
        <?php else: ?>
        <?php foreach ($allReviews as $rv):
            $rvParts = explode(' ', trim($rv['CustomerName']));
            $rvInit  = strtoupper(substr($rvParts[0]??'',0,1).substr(end($rvParts)??'',0,1));
            $isMe    = ($rv['Customer_ID'] == $uid);
        ?>
        <div class="review-card">
            <div class="review-top">
                <div class="reviewer-avatar">
                    <?php if (!empty($rv['ProfilePhoto'])): ?>
                    <img src="<?= htmlspecialchars($rv['ProfilePhoto']) ?>" referrerpolicy="no-referrer">
                    <?php else: ?>
                    <?= htmlspecialchars($rvInit) ?>
                    <?php endif; ?>
                </div>
                <span class="reviewer-name">
                    <?= htmlspecialchars($rv['CustomerName']) ?>
                    <?php if ($isMe): ?><span class="my-review-badge">You</span><?php endif; ?>
                </span>
                <span class="review-date"><?= date('M d, Y', strtotime($rv['Created_At'])) ?></span>
            </div>
            <div class="review-stars" style="color:#f5a623;"><?= str_repeat('★', $rv['Rating']) ?><span style="color:rgba(255,255,255,0.15);"><?= str_repeat('★', 5 - $rv['Rating']) ?></span></div>
            <p class="review-comment"><?= htmlspecialchars($rv['Comment']) ?></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function setRating(val) {
    document.getElementById('rating-val').value = val;
    document.querySelectorAll('.star-btn').forEach((btn, i) => {
        btn.textContent = i < val ? '★' : '☆';
        btn.classList.toggle('lit', i < val);
    });
}
(function(){
    const h=document.querySelector('header');let last=window.scrollY,tick=false;
    window.addEventListener('scroll',function(){if(!tick){requestAnimationFrame(function(){const cur=window.scrollY;h.style.transform=(cur>last&&cur>80)?'translateY(-100%)':'translateY(0)';last=cur;tick=false;});tick=true;}},{passive:true});
})();
</script>
</body>
</html>
