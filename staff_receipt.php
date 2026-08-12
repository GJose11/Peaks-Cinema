<?php
date_default_timezone_set('Asia/Manila');
require_once(__DIR__ . "/staff_guard.php");
if (!isset($_SESSION['staff_receipt'])) { header("Location: staff_dashboard.php"); exit; }
$r = $_SESSION['staff_receipt'];
unset($_SESSION['staff_receipt']);
$staffName = $_SESSION['staff_name'] ?? 'Staff';
$staffId   = $_SESSION['staff_id']   ?? '—';
$loginTime = $_SESSION['staff_login_time'] ?? date('g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Receipt – Staff</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Outfit',sans-serif;background:#0f0f0f;color:#F9F9F9;min-height:100vh;padding-top:70px;padding-bottom:60px}
body::before{content:'';position:fixed;inset:0;background:url('movie-background-collage.jpg') center/cover no-repeat;opacity:0.04;z-index:0;pointer-events:none}
body::after{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at center,transparent 20%,#0f0f0f 75%);z-index:1;pointer-events:none}
header,nav,.page-wrapper{position:relative;z-index:10}
header{background:#1C1C1C;display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:fixed;top:0;left:0;width:100%;z-index:1000;height:60px;border-bottom:1px solid rgba(255,255,255,0.06)}
.logo{display:flex;align-items:center}
.logo img{height:42px;width:auto;filter:invert(1);display:block}
nav{display:flex;gap:4px}
nav a{color:rgba(249,249,249,0.5);text-decoration:none;font-size:0.8rem;font-weight:500;padding:6px 14px;border-radius:6px;transition:all 0.2s}
nav a:hover{background:rgba(255,255,255,0.08);color:#F9F9F9}
nav a.active{background:rgba(255,77,77,0.12);color:#ff4d4d}
.page-wrapper{width:95%;max-width:560px;margin:32px auto;display:flex;flex-direction:column;gap:18px}
.page-label{font-size:0.72rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#ff4d4d;margin-bottom:5px}
.page-title{font-size:1.7rem;font-weight:800}
.panel{background:#1a1a1a;border:1px solid rgba(255,255,255,0.07);border-radius:14px;overflow:hidden}
.panel-header{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between}
.panel-header h2{font-size:0.78rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(249,249,249,0.45)}
.panel-body{padding:20px}
/* Success banner */
.success-banner{text-align:center;padding:26px;background:linear-gradient(135deg,rgba(76,175,80,0.12),rgba(76,175,80,0.04));border:1px solid rgba(76,175,80,0.2);border-radius:14px}
.success-icon{font-size:2.5rem;margin-bottom:10px}
.success-title{font-size:1rem;font-weight:800;color:#66bb6a;margin-bottom:4px}
.success-sub{font-size:0.78rem;color:rgba(249,249,249,0.35)}
.notice-banner{padding:14px 16px;background:rgba(255,193,7,0.08);border:1px solid rgba(255,193,7,0.2);border-left:3px solid #ffd54f;border-radius:12px;color:#ffe082;font-size:0.8rem;line-height:1.5}
/* Receipt card */
.receipt-top{background:linear-gradient(135deg,#1a0808,#1a1a1a);padding:22px 24px;border-bottom:1px solid rgba(255,255,255,0.06);text-align:center}
.receipt-logo{display:flex;justify-content:center;margin-bottom:8px}
.receipt-logo img{height:34px;width:auto;filter:invert(1);display:block}
.receipt-movie{font-size:1.05rem;font-weight:800;margin-top:10px}
.receipt-type{display:inline-block;margin-top:6px;padding:3px 10px;border-radius:20px;background:rgba(255,77,77,0.1);border:1px solid rgba(255,77,77,0.25);font-size:0.68rem;font-weight:700;color:#ff6b6b;letter-spacing:1px}
.tear-line{display:flex;align-items:center;justify-content:space-between;padding:8px 24px;border-top:2px dashed rgba(255,255,255,0.07);font-size:0.6rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(249,249,249,0.1)}
.receipt-row{display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem}
.receipt-row:last-child{border-bottom:none}
.r-lbl{color:rgba(249,249,249,0.4);flex-shrink:0;margin-right:12px}
.r-val{font-weight:600;text-align:right}
.seats-wrap{display:flex;flex-wrap:wrap;gap:5px;justify-content:flex-end}
.seat-chip{padding:3px 9px;border-radius:5px;background:rgba(255,77,77,0.1);border:1px solid rgba(255,77,77,0.2);font-size:0.72rem;font-weight:700;color:#ff6b6b}
.pay-section{padding:16px 24px;background:rgba(255,255,255,0.02);border-top:1px solid rgba(255,255,255,0.05)}
.pay-row{display:flex;justify-content:space-between;font-size:0.82rem;margin-bottom:7px}
.pay-row .pl{color:rgba(249,249,249,0.4)}
.pay-row .pv{font-weight:600}
.pay-row.total{padding-top:10px;border-top:1px solid rgba(255,255,255,0.07);margin-top:4px}
.pay-row.total .pl{font-size:0.9rem;color:#F9F9F9;font-weight:700}
.pay-row.total .pv{font-size:1rem;color:#ff4d4d;font-weight:800}
.pay-row.change .pv{color:#66bb6a}
.receipt-footer{padding:16px 24px;text-align:center;border-top:1px solid rgba(255,255,255,0.04)}
.receipt-ref{font-size:0.68rem;color:rgba(249,249,249,0.2);letter-spacing:1px;margin-bottom:4px}
.receipt-ts{font-size:0.68rem;color:rgba(249,249,249,0.15)}
/* Action buttons */
.actions{display:flex;gap:10px}
.actions a,.actions button{flex:1;padding:13px;text-align:center;border-radius:10px;font-family:'Outfit',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all 0.2s;border:none}
.btn-print{background:#ff4d4d;color:#fff}
.btn-print:hover{background:#e03c3c;transform:translateY(-1px)}
.btn-new{background:rgba(255,255,255,0.05);color:rgba(249,249,249,0.7);border:1px solid rgba(255,255,255,0.1)!important}
.btn-new:hover{background:rgba(255,255,255,0.09);color:#F9F9F9}
@media print{
  body{background:#fff;color:#000;padding:0}
  header,.actions,.success-banner{display:none!important}
  .page-wrapper{max-width:100%;margin:0;padding:0}
  .panel{border:1px solid #ddd}
  .receipt-top{background:#f9f9f9!important}
  .r-lbl,.pay-row .pl,.receipt-ref,.receipt-ts{color:#666!important}
  .r-val,.pay-row .pv{color:#000!important}
  .pay-row.total .pv{color:#c0392b!important}
  .pay-row.change .pv{color:#2e7d32!important}
  .seat-chip{background:#f0f0f0!important;border-color:#ddd!important;color:#c0392b!important}
}
</style>
</head>
<body>
<header>
  <div class="logo"><img src="peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
  <nav>
    <a href="staff_dashboard.php">Dashboard</a>
    <a href="staff_seats.php">Seat Selection</a>
    <a href="staff_payment.php">Payment</a>
    <a href="staff_receipt.php" class="active">Receipt</a>
  </nav>
  <div style="display:flex;align-items:center;gap:10px;">
    <div style="text-align:right;line-height:1.4;">
      <div style="font-size:0.78rem;font-weight:600;color:rgba(249,249,249,0.6);">👤 <?= htmlspecialchars($staffName) ?></div>
      <div style="font-size:0.62rem;color:rgba(249,249,249,0.25);">Staff #<?= htmlspecialchars($staffId) ?> · Since <?= htmlspecialchars($loginTime) ?></div>
    </div>
    <a href="home.php" style="color:rgba(249,249,249,0.45);text-decoration:none;font-size:0.78rem;font-weight:500;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.06)';this.style.color='#F9F9F9'" onmouseout="this.style.background='';this.style.color='rgba(249,249,249,0.45)'">&#8592; Customer Site</a>
    <a href="staff_logout.php" style="color:#ff4d4d;text-decoration:none;font-size:0.78rem;font-weight:600;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,77,77,0.3);background:rgba(255,77,77,0.08);transition:all 0.2s;" onmouseover="this.style.background='rgba(255,77,77,0.18)'" onmouseout="this.style.background='rgba(255,77,77,0.08)'" onclick="return confirm('Log out?')">&#x2192; Log Out</a>
  </div>
</header>

<div class="page-wrapper">
  <div><p class="page-label">Booking Complete</p><h1 class="page-title">Receipt</h1></div>

  <div class="success-banner">
    <div class="success-icon">✅</div>
    <div class="success-title">Booking Confirmed!</div>
    <div class="success-sub">Seats reserved. Hand this receipt to the customer.</div>
  </div>

  <?php if (!empty($r['notice'])): ?>
  <div class="notice-banner"><?= htmlspecialchars($r['notice']) ?></div>
  <?php endif; ?>

  <div class="panel">
    <div class="receipt-top">
      <div class="receipt-logo"><img src="peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
      <div class="receipt-movie"><?= htmlspecialchars($r['movie']) ?></div>
      <span class="receipt-type"><?= htmlspecialchars($r['type']) ?></span>
    </div>

    <div class="tear-line"><span>✂</span><span>Official Receipt</span><span>✂</span></div>

    <div class="panel-body">
      <div class="receipt-row"><span class="r-lbl">Customer</span><span class="r-val"><?= htmlspecialchars($r['customer']) ?></span></div>
      <div class="receipt-row"><span class="r-lbl">Date</span><span class="r-val"><?= date('F d, Y', strtotime($r['date'])) ?></span></div>
      <div class="receipt-row"><span class="r-lbl">Show Time</span><span class="r-val"><?= date('g:i A', strtotime($r['time'])) ?></span></div>
      <div class="receipt-row"><span class="r-lbl">Venue</span><span class="r-val"><?= htmlspecialchars($r['mall']) ?> — <?= htmlspecialchars($r['theater']) ?></span></div>
      <div class="receipt-row"><span class="r-lbl">Booked Time</span><span class="r-val"><?= htmlspecialchars($r['booked_time'] ?? $r['timestamp']) ?></span></div>
      <?php if (!empty($r['booking_ref'])): ?>
      <div class="receipt-row"><span class="r-lbl">Booking Ref</span><span class="r-val"><?= htmlspecialchars($r['booking_ref']) ?></span></div>
      <?php endif; ?>
      <div class="receipt-row">
        <span class="r-lbl">Seats</span>
        <span class="r-val">
          <div class="seats-wrap">
            <?php foreach ($r['seats'] as $seat): ?>
            <span class="seat-chip"><?= htmlspecialchars($seat['label']) ?></span>
            <?php endforeach; ?>
          </div>
        </span>
      </div>
      <div class="receipt-row"><span class="r-lbl">Served by</span><span class="r-val"><?= htmlspecialchars($r['staff']) ?></span></div>
    </div>

    <div class="tear-line"><span style="opacity:0.3;letter-spacing:3px;">· · · · · · · · · · · · · · · · · · · · · ·</span></div>

    <?php if (!empty($r['food_order'])): ?>
    <div class="panel-body" style="padding-top:0;">
      <div style="font-size:0.7rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(249,249,249,0.3);margin-bottom:10px;">Food Order</div>
      <?php foreach ($r['food_order'] as $item): ?>
      <div class="receipt-row">
        <span class="r-lbl"><?= htmlspecialchars($item['name']) ?></span>
        <span class="r-val">x<?= (int)$item['qty'] ?> &nbsp; ₱<?= number_format(($item['price'] ?? 0) * ($item['qty'] ?? 1), 2) ?></span>
      </div>
      <?php endforeach; ?>
      <div class="receipt-row" style="border-bottom:none;padding-top:12px;">
        <span class="r-lbl">Food Subtotal</span>
        <span class="r-val" style="color:#ff6b6b;">₱<?= number_format($r['food_total'] ?? 0, 2) ?></span>
      </div>
      <?php if (!empty($r['special_requests'])): ?>
      <div style="margin-top:12px;padding:10px;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid rgba(255,255,255,0.05);">
        <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:rgba(249,249,249,0.3);margin-bottom:4px;">Special Requests</div>
        <div style="font-size:0.78rem;color:rgba(249,249,249,0.6);line-height:1.4;font-style:italic;">"<?= htmlspecialchars($r['special_requests']) ?>"</div>
      </div>
      <?php endif; ?>
    </div>
    <div class="tear-line"><span style="opacity:0.3;letter-spacing:3px;">· · · · · · · · · · · · · · · · · · · · · ·</span></div>
    <?php endif; ?>

    <div class="pay-section">
      <div class="pay-row"><span class="pl">Payment Method</span><span class="pv"><?= htmlspecialchars($r['payment_method'] ?? 'Cash') ?></span></div>
      <?php if (!empty($r['food_order'])): ?>
      <div class="pay-row"><span class="pl">Seats Subtotal</span><span class="pv">₱<?= number_format($r['seat_total'] ?? (($r['total'] ?? 0) - ($r['food_total'] ?? 0)), 2) ?></span></div>
      <div class="pay-row"><span class="pl">Food & Drinks</span><span class="pv">₱<?= number_format($r['food_total'] ?? 0, 2) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($r['discount_amount'])): ?>
      <div class="pay-row"><span class="pl"><?= !empty($r['discount_type']) && $r['discount_type'] === 'senior' ? 'Senior Citizen Discount' : 'PWD Discount' ?></span><span class="pv" style="color:#81c784;">- ₱<?= number_format($r['discount_amount'], 2) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($r['cash_given'])): ?>
      <div class="pay-row"><span class="pl">Cash Tendered</span><span class="pv">₱<?= number_format($r['cash_given'],2) ?></span></div>
      <?php endif; ?>
      <div class="pay-row total"><span class="pl">Total</span><span class="pv">₱<?= number_format($r['total'],2) ?></span></div>
      <?php if (isset($r['change']) && $r['change'] !== null): ?>
      <div class="pay-row change"><span class="pl">Change</span><span class="pv">₱<?= number_format($r['change'],2) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="receipt-footer">
      <div class="receipt-ref">Thank you for visiting Peak's Cinema! 🎬</div>
      <div class="receipt-ts"><?= htmlspecialchars($r['timestamp']) ?></div>
    </div>
  </div>

  <div class="actions">
    <button class="btn-print" onclick="window.print()">🖨 Print Receipt</button>
    <a href="staff_dashboard.php" class="btn-new">+ New Booking</a>
  </div>
</div>
</body>
</html>
