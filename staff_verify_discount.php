<?php
include("../peakscinemas_database.php");
include("staff_guard.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

// ── Handle approve / reject ───────────────────────────────────
$msg = ''; $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $vid    = (int)$_POST['verify_id'];
    $action = $_POST['action'];
    $note   = trim($_POST['staff_note'] ?? '');

    $vr = $conn->prepare("SELECT * FROM discount_verifications WHERE Verify_ID = ?");
    $vr->bind_param("i", $vid); $vr->execute();
    $v = $vr->get_result()->fetch_assoc();

    if ($v) {
        $now = date('Y-m-d H:i:s');

        if ($action === 'approve') {
            $upd = $conn->prepare("UPDATE discount_verifications SET Status='approved', StaffNote=?, ReviewedAt=? WHERE Verify_ID=?");
            $upd->bind_param("ssi", $note, $now, $vid); $upd->execute();

            // Notify customer
            $title = "✅ Discount Approved!";
            $nmsg  = "Great news! Your " . ($v['DiscountType']==='pwd'?'PWD':'Senior Citizen') .
                     " discount for booking {$v['BookingRef']} has been approved by our staff. " .
                     "Your discount of ₱" . number_format($v['DiscountAmount'], 2) . " is confirmed.";
            sendNotif($conn, $v['Customer_ID'], $title, $nmsg, 'general');
            sendEmail($conn, $v['Customer_ID'], $title, $nmsg);
            $msg = "Booking {$v['BookingRef']} discount APPROVED."; $msgType = 'ok';

        } elseif ($action === 'reject') {
            // Reverse the discount — update ticket price back
            $refundNote = $note ?: 'ID could not be verified.';
            $upd = $conn->prepare("UPDATE discount_verifications SET Status='rejected', StaffNote=?, ReviewedAt=? WHERE Verify_ID=?");
            $upd->bind_param("ssi", $refundNote, $now, $vid); $upd->execute();

            // Notify customer
            $title = "❌ Discount Not Approved";
            $nmsg  = "Unfortunately, your " . ($v['DiscountType']==='pwd'?'PWD':'Senior Citizen') .
                     " discount for booking {$v['BookingRef']} could not be verified. Reason: $refundNote. " .
                     "Please visit our cinema counter with your valid ID for assistance.";
            sendNotif($conn, $v['Customer_ID'], $title, $nmsg, 'general');
            sendEmail($conn, $v['Customer_ID'], $title, $nmsg);
            $msg = "Booking {$v['BookingRef']} discount REJECTED."; $msgType = 'err';
        }
    }
}

function sendNotif($conn, $uid, $title, $msg, $type) {
    $n = $conn->prepare("INSERT INTO notifications (Customer_ID, Title, Message, Type) VALUES (?,?,?,?)");
    $n->bind_param("isss", $uid, $title, $msg, $type); $n->execute();
}

function sendEmail($conn, $uid, $subject, $body) {
    $es = $conn->prepare("SELECT Email, Name FROM customer WHERE Customer_ID=?");
    $es->bind_param("i", $uid); $es->execute();
    $eu = $es->get_result()->fetch_assoc();
    if (!$eu) return;
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP(); $mail->Host='smtp.gmail.com'; $mail->SMTPAuth=true;
        $mail->Username='peakscinema@gmail.com'; $mail->Password='pggs pvye frmk tmah';
        $mail->SMTPSecure='ssl'; $mail->Port=465;
        $mail->setFrom('peakscinema@gmail.com',"Peak's Cinema");
        $mail->addAddress($eu['Email'], $eu['Name']);
        $mail->isHTML(true); $mail->Subject = $subject;
        $mail->Body = "<div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;'>
            <h2 style='color:#ff4d4d;'>Peak's Cinema</h2>
            <p>{$body}</p>
            <p style='color:#888;font-size:12px;margin-top:20px;'>If you have questions, visit our cinema counter.</p>
        </div>";
        $mail->send();
    } catch (Exception $e) { /* Email failure is non-critical */ }
}

// ── Fetch pending verifications ───────────────────────────────
$pending = $conn->query("
    SELECT dv.*, c.Name AS CustomerName, c.Email
    FROM discount_verifications dv
    JOIN customer c ON c.Customer_ID = dv.Customer_ID
    WHERE dv.Status = 'pending'
    ORDER BY dv.Created_At ASC
")->fetch_all(MYSQLI_ASSOC);

$reviewed = $conn->query("
    SELECT dv.*, c.Name AS CustomerName
    FROM discount_verifications dv
    JOIN customer c ON c.Customer_ID = dv.Customer_ID
    WHERE dv.Status != 'pending'
    ORDER BY dv.ReviewedAt DESC
    LIMIT 30
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>ID Verification – Staff</title>
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Outfit',sans-serif; background:#0f0f0f; color:#F9F9F9; min-height:100vh; padding:30px; }
.page-label { font-size:0.72rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#ff4d4d; margin-bottom:5px; }
.page-title  { font-size:1.5rem; font-weight:800; margin-bottom:20px; }
.msg { padding:12px 16px; border-radius:9px; font-size:0.82rem; margin-bottom:16px; }
.msg.ok  { background:rgba(76,175,80,0.08); border:1px solid rgba(76,175,80,0.2); color:#81c784; }
.msg.err { background:rgba(255,77,77,0.08); border:1px solid rgba(255,77,77,0.2); border-left:3px solid #ff4d4d; color:#ff6b6b; }
.panel { background:#1a1a1a; border:1px solid rgba(255,255,255,0.07); border-radius:14px; overflow:hidden; margin-bottom:20px; }
.panel-header { padding:14px 20px; border-bottom:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:space-between; }
.panel-header h2 { font-size:0.78rem; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:rgba(249,249,249,0.45); }
.panel-body { padding:20px; }
.back-link { color:rgba(249,249,249,0.4); text-decoration:none; font-size:0.78rem; display:inline-flex; align-items:center; gap:4px; margin-bottom:20px; }
.back-link:hover { color:#F9F9F9; }

/* Verification card */
.verify-card { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px; margin-bottom:14px; }
.verify-card:last-child { margin-bottom:0; }
.vc-top { display:flex; gap:20px; margin-bottom:16px; align-items:flex-start; flex-wrap:wrap; }
.vc-info { flex:1; min-width:200px; }
.vc-info h3 { font-size:0.9rem; font-weight:800; margin-bottom:6px; }
.vc-meta { font-size:0.75rem; color:rgba(249,249,249,0.4); display:flex; flex-direction:column; gap:4px; }
.vc-ref { display:inline-block; font-size:0.62rem; font-weight:700; letter-spacing:1px; background:rgba(255,77,77,0.1); border:1px solid rgba(255,77,77,0.22); color:#ff6b6b; padding:2px 9px; border-radius:10px; margin-top:4px; }
.disc-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:10px; font-size:0.72rem; font-weight:700; margin-left:8px; }
.disc-pwd    { background:rgba(100,180,255,0.1); border:1px solid rgba(100,180,255,0.25); color:#64b5f6; }
.disc-senior { background:rgba(255,183,77,0.1); border:1px solid rgba(255,183,77,0.25); color:#ffb74d; }

/* ID photo */
.id-photo-wrap { flex-shrink:0; }
.id-photo-wrap img { width:200px; max-height:150px; object-fit:cover; border-radius:8px; border:1px solid rgba(255,255,255,0.1); cursor:pointer; transition:transform 0.2s; }
.id-photo-wrap img:hover { transform:scale(1.03); }
.no-photo { width:200px; height:120px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.04); border:1px dashed rgba(255,255,255,0.1); border-radius:8px; font-size:0.78rem; color:rgba(249,249,249,0.25); }

/* Action area */
.vc-actions { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
.note-field { flex:1; min-width:200px; }
.note-field label { display:block; font-size:0.65rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:rgba(249,249,249,0.35); margin-bottom:5px; }
.note-field input { width:100%; padding:8px 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:#222; color:#F9F9F9; font-family:'Outfit',sans-serif; font-size:0.82rem; outline:none; }
.note-field input:focus { border-color:rgba(255,77,77,0.5); }
.btn-approve { padding:9px 20px; border-radius:8px; border:none; background:#4caf50; color:#fff; font-family:'Outfit',sans-serif; font-size:0.82rem; font-weight:700; cursor:pointer; transition:all 0.2s; }
.btn-approve:hover { background:#388e3c; }
.btn-reject  { padding:9px 20px; border-radius:8px; border:1px solid rgba(255,77,77,0.35); background:rgba(255,77,77,0.08); color:#ff6b6b; font-family:'Outfit',sans-serif; font-size:0.82rem; font-weight:600; cursor:pointer; transition:all 0.2s; }
.btn-reject:hover { background:rgba(255,77,77,0.2); color:#fff; }

/* Status badges */
.status-approved { background:rgba(76,175,80,0.12); border:1px solid rgba(76,175,80,0.25); color:#81c784; padding:3px 10px; border-radius:10px; font-size:0.65rem; font-weight:700; }
.status-rejected  { background:rgba(255,77,77,0.1);  border:1px solid rgba(255,77,77,0.2);  color:#ff6b6b; padding:3px 10px; border-radius:10px; font-size:0.65rem; font-weight:700; }
.status-pending   { background:rgba(255,152,0,0.1);  border:1px solid rgba(255,152,0,0.25); color:#ffb74d; padding:3px 10px; border-radius:10px; font-size:0.65rem; font-weight:700; }

.empty-state { text-align:center; padding:40px; color:rgba(249,249,249,0.2); font-size:0.85rem; }

/* Fullscreen lightbox */
.lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:9999; align-items:center; justify-content:center; }
.lightbox.active { display:flex; }
.lightbox img { max-width:90vw; max-height:88vh; object-fit:contain; border-radius:10px; }
.lightbox-close { position:absolute; top:16px; right:20px; color:#fff; font-size:1.6rem; cursor:pointer; background:none; border:none; font-family:'Outfit',sans-serif; }
</style>
</head>
<body>

<!-- Lightbox for full ID photo view -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">✕ Close</button>
    <img id="lightboxImg" src="" alt="ID Photo">
</div>

<a href="staff_dashboard.php" class="back-link">← Back to Dashboard</a>
<p class="page-label">Staff Panel</p>
<h1 class="page-title">🪪 ID Discount Verification</h1>

<?php if ($msg): ?>
<div class="msg <?= $msgType ?>"><?= $msgType==='ok'?'✓':'⚠' ?> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Pending -->
<div class="panel">
    <div class="panel-header">
        <h2>⏳ Pending Verification (<?= count($pending) ?>)</h2>
        <?php if (!empty($pending)): ?>
        <span style="font-size:0.72rem;color:#ffb74d;font-weight:600;">Requires Action</span>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <?php if (empty($pending)): ?>
        <div class="empty-state">✓ No pending verifications</div>
        <?php else: ?>
        <?php foreach ($pending as $v): ?>
        <div class="verify-card">
            <div class="vc-top">
                <!-- ID Photo -->
                <div class="id-photo-wrap">
                    <?php if (!empty($v['DiscountPhoto'])): ?>
                    <img src="<?= htmlspecialchars($v['DiscountPhoto']) ?>"
                         alt="ID Photo"
                         onclick="openLightbox('<?= htmlspecialchars($v['DiscountPhoto']) ?>')"
                         title="Click to enlarge">
                    <p style="font-size:0.65rem;color:rgba(249,249,249,0.3);margin-top:4px;text-align:center;">Click to enlarge</p>
                    <?php else: ?>
                    <div class="no-photo">No photo uploaded</div>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="vc-info">
                    <h3>
                        <?= htmlspecialchars($v['CustomerName']) ?>
                        <span class="disc-badge <?= $v['DiscountType']==='pwd' ? 'disc-pwd' : 'disc-senior' ?>">
                            <?= $v['DiscountType']==='pwd' ? '♿ PWD' : '👴 Senior' ?>
                        </span>
                    </h3>
                    <div class="vc-meta">
                        <span>📧 <?= htmlspecialchars($v['Email']) ?></span>
                        <span>🪪 ID No: <strong style="color:#F9F9F9;"><?= htmlspecialchars($v['DiscountIdNo']) ?></strong></span>
                        <span>💰 Discount: <strong style="color:#ff4d4d;">−₱<?= number_format($v['DiscountAmount'], 2) ?></strong></span>
                        <span>🕒 Submitted: <?= date('M d, Y g:i A', strtotime($v['Created_At'])) ?></span>
                    </div>
                    <span class="vc-ref"><?= htmlspecialchars($v['BookingRef'] ?? 'TK-'.$v['Ticket_ID']) ?></span>
                    <span class="status-pending" style="margin-left:6px;">Pending</span>
                </div>
            </div>

            <!-- Action form -->
            <form method="POST">
                <input type="hidden" name="verify_id" value="<?= $v['Verify_ID'] ?>">
                <div class="vc-actions">
                    <div class="note-field">
                        <label>Staff Note (optional for approve, required for reject)</label>
                        <input type="text" name="staff_note"
                               placeholder="e.g. ID verified, matches name · or: Photo blurry, ID unreadable">
                    </div>
                    <button type="submit" name="action" value="approve" class="btn-approve"
                            onclick="return confirm('Approve this discount for <?= htmlspecialchars($v['CustomerName']) ?>?')">
                        ✓ Approve
                    </button>
                    <button type="submit" name="action" value="reject" class="btn-reject"
                            onclick="return confirm('Reject this discount? The customer will be notified.')">
                        ✕ Reject
                    </button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Reviewed -->
<div class="panel">
    <div class="panel-header"><h2>📋 Recently Reviewed (last 30)</h2></div>
    <div class="panel-body">
        <?php if (empty($reviewed)): ?>
        <div class="empty-state">No reviewed verifications yet.</div>
        <?php else: ?>
        <?php foreach ($reviewed as $v): ?>
        <div class="verify-card" style="opacity:0.7;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <div>
                    <strong><?= htmlspecialchars($v['CustomerName']) ?></strong>
                    <span class="disc-badge <?= $v['DiscountType']==='pwd'?'disc-pwd':'disc-senior' ?>" style="margin-left:6px;">
                        <?= $v['DiscountType']==='pwd'?'♿ PWD':'👴 Senior' ?>
                    </span>
                    <span class="vc-ref" style="margin-left:6px;"><?= htmlspecialchars($v['BookingRef'] ?? 'TK-'.$v['Ticket_ID']) ?></span>
                    <br>
                    <span style="font-size:0.72rem;color:rgba(249,249,249,0.35);">
                        ID: <?= htmlspecialchars($v['DiscountIdNo']) ?> ·
                        −₱<?= number_format($v['DiscountAmount'], 2) ?> ·
                        <?= date('M d, Y g:i A', strtotime($v['ReviewedAt'])) ?>
                    </span>
                    <?php if ($v['StaffNote']): ?>
                    <br><span style="font-size:0.72rem;color:rgba(249,249,249,0.3);">Note: <?= htmlspecialchars($v['StaffNote']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="status-<?= $v['Status'] ?>"><?= ucfirst($v['Status']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('active');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.getElementById('lightboxImg').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>
