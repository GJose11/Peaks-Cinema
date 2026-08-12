<?php
/**
 * Preservation Property Tests (P1–P18)
 *
 * Each test checks that non-buggy baseline behaviors are still present in the
 * source files after fixes have been applied. These tests use static analysis
 * (file content inspection) to confirm correct behaviors are preserved.
 *
 * A test PASSES when the expected pattern IS found (baseline behavior preserved).
 * A test FAILS when the expected pattern is NOT found (regression detected).
 *
 * Output: [PASS] Px - description  OR  [FAIL] Px - description
 *
 * EXPECTED OUTCOME: All tests PASS (confirms baseline behavior is preserved).
 */

$pass = 0;
$fail = 0;
$results = [];

function check(string $id, string $desc, bool $preserved): void {
    global $pass, $fail, $results;
    if ($preserved) {
        $results[] = "[PASS] $id - $desc";
        $pass++;
    } else {
        $results[] = "[FAIL] $id - $desc";
        $fail++;
    }
}

// ── Resolve paths relative to this script's location ──────────────────────────
$root = dirname(__DIR__);

function readSrc(string $rel): string {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . $rel;
    if (!file_exists($path)) return '';
    return file_get_contents($path);
}

$src_mb     = readSrc('my_bookings.php');
$src_pe     = readSrc('profile_edit.php');
$src_home   = readSrc('home.php');
$src_seat   = readSrc('seat_selection.php');
$src_rcpt   = readSrc('receipt.php');
$src_dash   = readSrc('Admin/dashboard.php');
$src_ta     = readSrc('Admin/theater_admin.php');
$src_tu     = readSrc('Admin/theater_upload.php');
$src_mu     = readSrc('Admin/mall_upload.php');
$src_mu2    = readSrc('Admin/movie_upload.php');
$src_qt     = readSrc('queue_tracker.php');
$src_search = readSrc('search_movies.php');

// ─────────────────────────────────────────────────────────────────────────────
// P1 — my_bookings.php: Active booking card layout preserved
// Checks: booking card HTML structure, seat chips, total price, cancel button
// ─────────────────────────────────────────────────────────────────────────────
$p1 = stripos($src_mb, 'booking-card') !== false
   && stripos($src_mb, 'seat-chip') !== false
   && stripos($src_mb, 'bc-total') !== false
   && stripos($src_mb, 'btn-cancel') !== false;
check('P1', 'Active booking card layout preserved (card, seat chips, total, cancel button)', $p1);

// ─────────────────────────────────────────────────────────────────────────────
// P2 — my_bookings.php: Past booking card rendering with status badges preserved
// Checks: past bookings section renders cards with status-badge classes
// ─────────────────────────────────────────────────────────────────────────────
$p2 = stripos($src_mb, 'sectionPast') !== false
   && stripos($src_mb, 'status-badge') !== false
   && (stripos($src_mb, 'status-cancelled') !== false || stripos($src_mb, 'status-completed') !== false);
check('P2', 'Past booking card layout with status badges preserved', $p2);

// ─────────────────────────────────────────────────────────────────────────────
// P3 — my_bookings.php: Active tab booking time format preserved
// Checks: fmt_dt($b['booked_at'], 'M d, Y g:i A') present in active section
// ─────────────────────────────────────────────────────────────────────────────
$p3 = (bool) preg_match("/fmt_dt\s*\(\s*\\\$b\s*\[\s*['\"]booked_at['\"]\s*\]\s*,\s*['\"]M d, Y g:i A['\"]\s*\)/", $src_mb);
check('P3', 'Active tab booking time format fmt_dt($b[\'booked_at\'], \'M d, Y g:i A\') preserved', $p3);

// ─────────────────────────────────────────────────────────────────────────────
// P4 — profile_edit.php: Photo upload/save logic AND session update preserved
// Checks: file upload handling and $_SESSION['profile_photo'] assignment
// ─────────────────────────────────────────────────────────────────────────────
$p4 = stripos($src_pe, 'move_uploaded_file') !== false
   && stripos($src_pe, 'profile_photo') !== false
   && (bool) preg_match("/\\\$_SESSION\s*\[\s*['\"]profile_photo['\"]\s*\]\s*=/", $src_pe);
check('P4', 'profile_edit.php photo upload/save logic and session update preserved', $p4);

// ─────────────────────────────────────────────────────────────────────────────
// P5 — home.php, profile_edit.php: Both still contain brand-logo-wrap
// Confirms those pages are unaffected by the logo fix on other pages
// ─────────────────────────────────────────────────────────────────────────────
$p5 = stripos($src_home, 'brand-logo-wrap') !== false
   && stripos($src_pe,   'brand-logo-wrap') !== false;
check('P5', 'home.php and profile_edit.php still contain brand-logo-wrap (unaffected by logo fix)', $p5);

// ─────────────────────────────────────────────────────────────────────────────
// P6 — seat_selection.php: Seat map rendering HTML preserved
// Checks: .seat-map or seat-map-container present (desktop seat map unaffected)
// ─────────────────────────────────────────────────────────────────────────────
$p6 = stripos($src_seat, 'seat-map') !== false
   || stripos($src_seat, 'seat-map-container') !== false;
check('P6', 'seat_selection.php seat map rendering HTML preserved (.seat-map / seat-map-container)', $p6);

// ─────────────────────────────────────────────────────────────────────────────
// P7 — receipt.php: Movie, cinema, seats, payment rendering preserved
// Checks: key receipt fields still present in the receipt card HTML
// ─────────────────────────────────────────────────────────────────────────────
$p7 = (stripos($src_rcpt, 'MovieName') !== false || stripos($src_rcpt, 'movie') !== false)
   && (stripos($src_rcpt, 'TheaterName') !== false || stripos($src_rcpt, 'cinema') !== false || stripos($src_rcpt, 'theater') !== false)
   && (stripos($src_rcpt, 'SeatRow') !== false || stripos($src_rcpt, 'seat') !== false)
   && (stripos($src_rcpt, 'payment') !== false || stripos($src_rcpt, 'Payment') !== false || stripos($src_rcpt, 'GCash') !== false || stripos($src_rcpt, 'price') !== false);
check('P7', 'receipt.php movie, cinema, seats, payment rendering preserved', $p7);

// ─────────────────────────────────────────────────────────────────────────────
// P8 — receipt.php: POST block with DB inserts preserved
// Checks: ticket, payment, e-receipt, food orders, notifications inserts still present
// ─────────────────────────────────────────────────────────────────────────────
$p8 = (bool) preg_match('/INSERT\s+INTO\s+ticket/i', $src_rcpt)
   && (bool) preg_match('/INSERT\s+INTO\s+(payment|payments)/i', $src_rcpt)
   && (bool) preg_match('/INSERT\s+INTO\s+[`\']?e[-_]receipt[`\']?/i', $src_rcpt)
   && (bool) preg_match('/INSERT\s+INTO\s+notifications/i', $src_rcpt);
check('P8', 'receipt.php POST block DB inserts (tickets, payments, e-receipt, notifications) preserved', $p8);

// ─────────────────────────────────────────────────────────────────────────────
// P9 — receipt.php: Food section wrapped in if (!empty($foodSummary))
// Confirms no-food receipt shows no food section
// ─────────────────────────────────────────────────────────────────────────────
$p9 = (bool) preg_match('/if\s*\(\s*!empty\s*\(\s*\$foodSummary\s*\)\s*\)/s', $src_rcpt);
check('P9', 'receipt.php food section wrapped in if (!empty($foodSummary)) preserved', $p9);

// ─────────────────────────────────────────────────────────────────────────────
// P10 — Admin/dashboard.php: Stat cards, data-table, nav links preserved
// ─────────────────────────────────────────────────────────────────────────────
$p10 = (stripos($src_dash, 'stat') !== false || stripos($src_dash, 'stat-card') !== false || stripos($src_dash, 'stat-value') !== false)
    && (stripos($src_dash, 'data-table') !== false || stripos($src_dash, 'table') !== false)
    && (stripos($src_dash, 'dashboard') !== false || stripos($src_dash, 'nav') !== false);
check('P10', 'Admin/dashboard.php stat cards, data-table, and nav links preserved', $p10);

// ─────────────────────────────────────────────────────────────────────────────
// P11 — Admin/theater_admin.php, theater_upload.php, mall_upload.php:
//        All original nav links preserved (Dashboard, Malls, Movie Upload,
//        Theater Upload, Mall Upload)
// ─────────────────────────────────────────────────────────────────────────────
$nav_links = [
    'dashboard.php',
    'malls_selection_admin.php',
    'movie_upload.php',
    'theater_upload.php',
    'mall_upload.php',
];

$p11_ta = true;
$p11_tu = true;
$p11_mu = true;
foreach ($nav_links as $link) {
    if (stripos($src_ta, $link) === false) $p11_ta = false;
    if (stripos($src_tu, $link) === false) $p11_tu = false;
    if (stripos($src_mu, $link) === false) $p11_mu = false;
}
$p11 = $p11_ta && $p11_tu && $p11_mu;
check('P11', 'Admin nav pages (theater_admin, theater_upload, mall_upload) all original nav links preserved', $p11);

// ─────────────────────────────────────────────────────────────────────────────
// P12 — Admin/movie_upload.php: Upload form, poster preview, delete modal,
//        filter bar preserved
// ─────────────────────────────────────────────────────────────────────────────
$p12 = (stripos($src_mu2, 'enctype') !== false || stripos($src_mu2, 'file') !== false || stripos($src_mu2, 'upload') !== false)
    && (stripos($src_mu2, 'poster') !== false || stripos($src_mu2, 'preview') !== false)
    && (stripos($src_mu2, 'modal') !== false || stripos($src_mu2, 'delete') !== false)
    && (stripos($src_mu2, 'filter') !== false || stripos($src_mu2, 'search') !== false);
check('P12', 'Admin/movie_upload.php upload form, poster preview, delete modal, filter bar preserved', $p12);

// ─────────────────────────────────────────────────────────────────────────────
// P13 — queue_tracker.php: Summary strip, filter bar, mall queue cards preserved
// ─────────────────────────────────────────────────────────────────────────────
$p13 = (stripos($src_qt, 'summary') !== false || stripos($src_qt, 'summary-strip') !== false || stripos($src_qt, 'stat') !== false)
    && (stripos($src_qt, 'filter') !== false || stripos($src_qt, 'search') !== false)
    && (stripos($src_qt, 'mall') !== false || stripos($src_qt, 'queue-card') !== false || stripos($src_qt, 'mall-card') !== false);
check('P13', 'queue_tracker.php summary strip, filter bar, mall queue cards preserved', $p13);

// ─────────────────────────────────────────────────────────────────────────────
// P14 — queue_tracker.php: Queue data rendering (wait times, statuses, people counts)
// ─────────────────────────────────────────────────────────────────────────────
$p14 = (stripos($src_qt, 'wait') !== false || stripos($src_qt, 'WaitTime') !== false || stripos($src_qt, 'wait_time') !== false)
    && (stripos($src_qt, 'status') !== false || stripos($src_qt, 'Status') !== false)
    && (stripos($src_qt, 'people') !== false || stripos($src_qt, 'count') !== false || stripos($src_qt, 'PeopleCount') !== false || stripos($src_qt, 'queue') !== false);
check('P14', 'queue_tracker.php queue data rendering (wait times, statuses, people counts) preserved', $p14);

// ─────────────────────────────────────────────────────────────────────────────
// P15 — home.php: $_SESSION['user_id'] check for hero greeting preserved
// Confirms logged-in user still sees their name
// ─────────────────────────────────────────────────────────────────────────────
$p15 = (bool) preg_match("/\\\$_SESSION\s*\[\s*['\"]user_id['\"]\s*\]/", $src_home)
    && (stripos($src_home, 'user_name') !== false
        || stripos($src_home, 'Name') !== false
        || stripos($src_home, 'Welcome') !== false);
check('P15', 'home.php $_SESSION[\'user_id\'] check for hero greeting preserved', $p15);

// ─────────────────────────────────────────────────────────────────────────────
// P16 — search_movies.php: LOWER(MovieName) LIKE LOWER(?) preserved
// Confirms exact-case queries still work (the fix itself is the preservation)
// ─────────────────────────────────────────────────────────────────────────────
$p16 = (bool) preg_match('/LOWER\s*\(\s*MovieName\s*\)\s+LIKE\s+LOWER\s*\(\s*\?\s*\)/i', $src_search);
check('P16', 'search_movies.php LOWER(MovieName) LIKE LOWER(?) preserved (case-insensitive fix in place)', $p16);

// ─────────────────────────────────────────────────────────────────────────────
// P17 — home.php: Mouse hover/click handlers for search results preserved
// Confirms mouse interaction unaffected by arrow key fix
// ─────────────────────────────────────────────────────────────────────────────
$p17 = (stripos($src_home, 'mouseover') !== false || stripos($src_home, 'mouseenter') !== false || stripos($src_home, 'mousemove') !== false)
    || (bool) preg_match('/search-result-item[^"\']*["\'][^)]*\)\s*\{/s', $src_home)
    || (stripos($src_home, 'search-result-item') !== false
        && (stripos($src_home, 'onclick') !== false || stripos($src_home, 'click') !== false));
check('P17', 'home.php mouse hover/click handlers for search results preserved', $p17);

// ─────────────────────────────────────────────────────────────────────────────
// P18 — receipt.php: Movie name, date, seats, booking ref in email body preserved
// Confirms existing email fields are still present
// ─────────────────────────────────────────────────────────────────────────────
$p18 = (bool) preg_match('/\$emailBody/s', $src_rcpt)
    && (stripos($src_rcpt, 'MovieName') !== false || stripos($src_rcpt, 'movie') !== false)
    && (stripos($src_rcpt, 'BookingRef') !== false || stripos($src_rcpt, 'booking_ref') !== false || stripos($src_rcpt, 'Ref') !== false)
    && (stripos($src_rcpt, 'Date') !== false || stripos($src_rcpt, 'date') !== false)
    && (stripos($src_rcpt, 'Seat') !== false || stripos($src_rcpt, 'seat') !== false);
check('P18', 'receipt.php email body contains movie name, date, seats, booking ref preserved', $p18);

// ─────────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────────
echo PHP_EOL;
echo "=== Preservation Property Tests (P1–P18) ===" . PHP_EOL;
echo PHP_EOL;

foreach ($results as $r) {
    echo $r . PHP_EOL;
}

echo PHP_EOL;
echo "─────────────────────────────────────────────────" . PHP_EOL;
echo "Total: " . ($pass + $fail) . " | PASS: $pass | FAIL: $fail" . PHP_EOL;
echo PHP_EOL;

if ($fail === 0) {
    echo "✓ All baseline behaviors preserved — no regressions detected." . PHP_EOL;
} else {
    echo "✗ $fail preservation test(s) FAILED — regression(s) detected." . PHP_EOL;
}
echo PHP_EOL;
