<?php
/**
 * Bug Condition Exploration Tests (B1–B18)
 *
 * Each test checks for the presence/absence of a specific pattern in source files
 * that indicates a bug exists in the CURRENT (unfixed) code.
 *
 * A test FAILS (bug confirmed) when the buggy pattern IS found (or the correct
 * pattern is NOT found).
 *
 * Output: [PASS] Bx - description  OR  [FAIL] Bx - description
 *
 * EXPECTED OUTCOME: Tests for already-fixed bugs will PASS; unfixed bugs will FAIL.
 */

$pass = 0;
$fail = 0;
$results = [];

function check(string $id, string $desc, bool $bugExists): void {
    global $pass, $fail, $results;
    if ($bugExists) {
        $results[] = "[FAIL] $id - $desc (bug confirmed)";
        $fail++;
    } else {
        $results[] = "[PASS] $id - $desc (bug not present / already fixed)";
        $pass++;
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

// ─────────────────────────────────────────────────────────────────────────────
// B1 — my_bookings.php: Past screenings not filtered from Active tab
// Bug: active bookings rendering loop does NOT have a guard for already_passed
// Fix present when: file contains `if ($b['already_passed']) continue;` or
//                   equivalent guard wrapping the card output
// ─────────────────────────────────────────────────────────────────────────────
$src_mb = readSrc('my_bookings.php');
$b1_bug = (stripos($src_mb, 'already_passed') === false ||
           !preg_match('/if\s*\(\s*\$b\[.already_passed.\]\s*\)\s*continue/s', $src_mb));
check('B1', 'Past screenings not filtered from Active tab', $b1_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B2 — my_bookings.php: Active bookings sorted by Date ASC not DateTime DESC
// Bug: SQL contains `ORDER BY ts.Date ASC`
// ─────────────────────────────────────────────────────────────────────────────
$b2_bug = (bool) preg_match('/ORDER\s+BY\s+ts\.Date\s+ASC/i', $src_mb);
check('B2', 'Active bookings sorted by Date ASC not DateTime DESC', $b2_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B3 — my_bookings.php: Past bookings booked_at missing time component
// Bug: fmt_dt($b['booked_at'], 'M d, Y') without time format
// Fix present when: all fmt_dt calls for booked_at include 'g:i A'
// ─────────────────────────────────────────────────────────────────────────────
// Check if there's a fmt_dt call for booked_at that uses 'M d, Y' WITHOUT time
$b3_bug = (bool) preg_match("/fmt_dt\s*\(\s*\\\$b\s*\[\s*['\"]booked_at['\"]\s*\]\s*,\s*['\"]M d, Y['\"]\s*\)/", $src_mb);
check('B3', 'Past bookings booked_at missing time component', $b3_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B4 — home.php: Profile photo img tag does NOT have cache-busting
// Bug: profile photo img src does not contain time() or '?' cache-bust
// Fix present when: img src for profile photo contains time() or ?v=
// ─────────────────────────────────────────────────────────────────────────────
$src_home = readSrc('home.php');
// Look for the profile-btn img tag and check if it has cache-busting
$b4_bug = !preg_match('/profile_photo.*\?\s*\.?\s*[\'"]?\s*\?v=|profile_photo.*time\s*\(\s*\)/s', $src_home) &&
          !preg_match('/\$profile_photo[^;]*\?v=.*time\s*\(\s*\)/s', $src_home);
// More targeted: check if the profile-btn img src contains time() or ?v=
$b4_bug = !preg_match('/profile-btn[\s\S]{0,500}img src[^>]*(?:time\(\)|[?&]v=)/i', $src_home) &&
          !preg_match('/profile_photo[^;]*\.\s*[\'"]?\?v=[\'"]?\s*\.\s*time\s*\(\s*\)/s', $src_home);
// Simplified: just check if time() appears near profile_photo in an img src context
$b4_bug = !preg_match('/htmlspecialchars\(\$profile_photo\)[^;]*time\s*\(\s*\)/s', $src_home);
check('B4', 'Profile photo img src missing cache-busting query string', $b4_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B5 — seat_selection.php / receipt.php / queue_tracker.php: Logo uses .logo img
// Bug: file contains class="logo" with img AND does NOT contain brand-logo-wrap
// Fix present when: file uses brand-logo-wrap
// ─────────────────────────────────────────────────────────────────────────────
$src_seat = readSrc('seat_selection.php');
$src_rcpt = readSrc('receipt.php');
$src_qt   = readSrc('queue_tracker.php');

// For each file: bug exists if it does NOT contain brand-logo-wrap in the HTML header section
$b5_seat_bug = stripos($src_seat, 'brand-logo-wrap') === false;
$b5_rcpt_bug = stripos($src_rcpt, 'brand-logo-wrap') === false;
$b5_qt_bug   = stripos($src_qt,   'brand-logo-wrap') === false;
$b5_bug = $b5_seat_bug || $b5_rcpt_bug || $b5_qt_bug;
check('B5', 'Logo markup inconsistency (seat_selection/receipt/queue_tracker missing brand-logo-wrap)', $b5_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B6 — seat_selection.php: Hardcoded 540px margin without dynamic measurement
// Bug: file contains 540px in margin context AND does NOT set --map-h via offsetHeight
// Fix present when: JS sets --map-h using offsetHeight
// ─────────────────────────────────────────────────────────────────────────────
$has_540_margin = (bool) preg_match('/var\s*\(--map-h,\s*540px\)/i', $src_seat);
$has_dynamic    = (bool) preg_match('/offsetHeight/i', $src_seat) &&
                  (bool) preg_match('/--map-h/i', $src_seat);
$b6_bug = $has_540_margin && !$has_dynamic;
check('B6', 'Hardcoded 540px margin compensation without dynamic offsetHeight measurement', $b6_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B7 — receipt.php: Screening time key uses STARTTIME (all caps)
// Bug: file contains isset($timeslotDetails['STARTTIME'])
// Fix present when: only 'StartTime' (correct case) is used in the isset check
// ─────────────────────────────────────────────────────────────────────────────
$b7_bug = (bool) preg_match("/isset\s*\(\s*\\\$timeslotDetails\s*\[\s*['\"]STARTTIME['\"]\s*\]\s*\)/", $src_rcpt);
check('B7', 'receipt.php checks STARTTIME (all caps) instead of StartTime', $b7_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B8 — receipt.php: $dateTime used without isset() guard
// Bug: strtotime($dateTime) appears in the receipt display without isset guard
// Fix present when: isset($dateTime) ternary guards the display strtotime call
// ─────────────────────────────────────────────────────────────────────────────
// The display line should be: isset($dateTime) ? date(..., strtotime($dateTime)) : 'N/A'
// Bug exists if strtotime($dateTime) is used in the HTML output WITHOUT an isset guard
$has_guarded_display = (bool) preg_match(
    '/isset\s*\(\s*\$dateTime\s*\)\s*\?\s*date\s*\([^)]*strtotime\s*\(\s*\$dateTime\s*\)/s',
    $src_rcpt
);
$b8_bug = !$has_guarded_display;
check('B8', '$dateTime used without isset() guard in receipt.php', $b8_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B9 — receipt.php: Food section missing or incomplete
// Bug: file does NOT contain a complete food section rendering block
// Fix present when: file contains foodSummary display HTML (e.g., foreach loop rendering food items)
// ─────────────────────────────────────────────────────────────────────────────
// Check for the food section rendering block in the HTML output area (not just the DB save)
$b9_bug = !preg_match('/if\s*\(\s*!empty\s*\(\s*\$foodSummary\s*\)\s*\)\s*:/s', $src_rcpt) ||
          !preg_match('/foreach\s*\(\s*\$foodSummary\s+as/s', $src_rcpt);
check('B9', 'Food section missing or incomplete in receipt.php', $b9_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B10 — Admin/dashboard.php: Font not explicitly set on stat-value / data-table td
// Bug: file does NOT contain explicit font-family on .stat-value or .data-table td
// Fix present when: font-family is declared on those selectors OR via universal rule
// ─────────────────────────────────────────────────────────────────────────────
$src_dash = readSrc('Admin/dashboard.php');
// Check for universal font-family rule covering all elements (*, *::before, *::after)
$has_universal_font = (bool) preg_match('/\*\s*,\s*\*::before\s*,\s*\*::after\s*\{[^}]*font-family/s', $src_dash) ||
                      (bool) preg_match('/\*\s*\{[^}]*font-family/s', $src_dash);
$has_stat_font      = (bool) preg_match('/\.stat-value\s*\{[^}]*font-family/s', $src_dash);
$has_td_font        = (bool) preg_match('/\.data-table\s+td\s*\{[^}]*font-family/s', $src_dash) ||
                      (bool) preg_match('/table\s*,\s*td\s*,\s*th[^{]*\{[^}]*font-family/s', $src_dash);
$b10_bug = !$has_universal_font && !($has_stat_font && $has_td_font);
check('B10', 'Admin/dashboard.php missing explicit font-family on stat values/table cells', $b10_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B11 — Admin pages: queue_admin.php nav link missing
// Bug: theater_admin.php, theater_upload.php, mall_upload.php do NOT contain
//      href="queue_admin.php"
// ─────────────────────────────────────────────────────────────────────────────
$src_ta  = readSrc('Admin/theater_admin.php');
$src_tu  = readSrc('Admin/theater_upload.php');
$src_mu  = readSrc('Admin/mall_upload.php');

$b11_ta_bug = stripos($src_ta,  'href="queue_admin.php"') === false;
$b11_tu_bug = stripos($src_tu,  'href="queue_admin.php"') === false;
$b11_mu_bug = stripos($src_mu,  'href="queue_admin.php"') === false;
$b11_bug = $b11_ta_bug || $b11_tu_bug || $b11_mu_bug;
check('B11', 'Queue Manager nav link missing in theater_admin/theater_upload/mall_upload', $b11_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B12 — Admin/movie_upload.php: Table layout instead of card grid
// Bug: file contains <table class="mgmt-table"
// Fix present when: file uses card-grid layout (no mgmt-table)
// ─────────────────────────────────────────────────────────────────────────────
$src_mu2 = readSrc('Admin/movie_upload.php');
$b12_bug = (bool) preg_match('/<table[^>]+class=["\'][^"\']*mgmt-table/i', $src_mu2);
check('B12', 'Admin/movie_upload.php uses table layout instead of card grid', $b12_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B13 — queue_tracker.php: No @media with flex-wrap: wrap for header
// Bug: CSS does NOT contain @media (max-width: ...) with flex-wrap: wrap for header
// Fix present when: a media query exists with flex-wrap: wrap targeting header
// ─────────────────────────────────────────────────────────────────────────────
$b13_bug = !preg_match('/@media[^{]*max-width[^{]*\{[^}]*header[^}]*flex-wrap\s*:\s*wrap/s', $src_qt) &&
           !preg_match('/@media[^{]*max-width[^{]*\{[\s\S]*?header\s*\{[\s\S]*?flex-wrap\s*:\s*wrap/s', $src_qt);
check('B13', 'queue_tracker.php header missing @media flex-wrap: wrap for mobile', $b13_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B14 — queue_tracker.php: Last updated time rendered statically from PHP date()
// Bug: file uses PHP date() for last updated display (not from DB query result)
// Fix present when: $lastUpdated is computed from DB (max updated_at) and rendered
//                   via JS (not a raw PHP date() call for the display)
// ─────────────────────────────────────────────────────────────────────────────
// The bug is using PHP date() directly for the display instead of DB value
// Check: does the file use PHP date() for the last-update display string?
$b14_bug = (bool) preg_match('/Last\s+update[^<]*<\?=\s*date\s*\(/i', $src_qt) ||
           (bool) preg_match('/lastUpdated\s*=\s*date\s*\(/i', $src_qt);
// Also check: if $lastUpdated is NOT derived from DB (updated_at column)
$has_db_last_updated = (bool) preg_match('/max\s*\(\s*array_column\s*\(\s*\$queueData\s*,\s*[\'"]updated_at[\'"]\s*\)\s*\)/s', $src_qt);
// Bug confirmed if: display uses raw date() OR $lastUpdated not from DB
$b14_bug = $b14_bug || !$has_db_last_updated;
check('B14', 'queue_tracker.php last updated time rendered statically from PHP date()', $b14_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B15 — home.php: Hardcoded "Gabrielle" in hero heading
// Bug: file contains 'Gabrielle' (hardcoded name visible to unauthenticated users)
// ─────────────────────────────────────────────────────────────────────────────
$b15_bug = stripos($src_home, 'Gabrielle') !== false;
check('B15', 'home.php contains hardcoded "Gabrielle" in hero heading', $b15_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B16 — search_movies.php: SQL uses LIKE ? without LOWER()
// Bug: SQL uses MovieName LIKE ? without LOWER() wrapping
// Fix present when: SQL uses LOWER(MovieName) LIKE LOWER(?)
// ─────────────────────────────────────────────────────────────────────────────
$src_search = readSrc('search_movies.php');
$b16_bug = (bool) preg_match('/MovieName\s+LIKE\s+\?/i', $src_search) &&
           !preg_match('/LOWER\s*\(\s*MovieName\s*\)\s+LIKE\s+LOWER\s*\(\s*\?\s*\)/i', $src_search);
check('B16', 'search_movies.php SQL uses LIKE ? without LOWER() for case-insensitive search', $b16_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B17 — home.php: No keydown event listener for arrow key navigation
// Bug: file does NOT contain ArrowDown handler for search dropdown
// Fix present when: file contains keydown listener with ArrowDown handling
// ─────────────────────────────────────────────────────────────────────────────
$b17_bug = stripos($src_home, 'ArrowDown') === false ||
           !preg_match('/addEventListener\s*\(\s*[\'"]keydown[\'"]/s', $src_home);
check('B17', 'home.php missing keydown/ArrowDown handler for search dropdown navigation', $b17_bug);

// ─────────────────────────────────────────────────────────────────────────────
// B18 — receipt.php: Email body does NOT include screening time or food items
// Bug: email construction does NOT contain StartTime or food items in email body
// Fix present when: emailBody includes StartTime and food section
// ─────────────────────────────────────────────────────────────────────────────
// Check that the email body string includes StartTime
$has_email_starttime = (bool) preg_match('/\$emailBody[^;]*StartTime|StartTime[^;]*\$emailBody/s', $src_rcpt) ||
                       (bool) preg_match('/\$emailBody\s*\.?=.*StartTime/s', $src_rcpt) ||
                       (bool) preg_match('/StartTime.*\$emailBody|emailBody.*StartTime/s', $src_rcpt);
// Check that the email body includes food items section
$has_email_food = (bool) preg_match('/\$emailBody.*Food Order|Food Order.*\$emailBody/s', $src_rcpt) ||
                  (bool) preg_match('/\$emailBody\s*\.=.*[Ff]ood/s', $src_rcpt);
$b18_bug = !$has_email_starttime || !$has_email_food;
check('B18', 'receipt.php email body missing screening time or food items', $b18_bug);

// ─────────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────────
echo PHP_EOL;
echo "=== Bug Condition Exploration Tests (B1–B18) ===" . PHP_EOL;
echo PHP_EOL;

foreach ($results as $r) {
    echo $r . PHP_EOL;
}

echo PHP_EOL;
echo "─────────────────────────────────────────────────" . PHP_EOL;
echo "Total: " . ($pass + $fail) . " | PASS: $pass | FAIL: $fail" . PHP_EOL;
echo PHP_EOL;

if ($fail === 0) {
    echo "✓ All bugs appear to be fixed." . PHP_EOL;
} else {
    echo "✗ $fail bug(s) confirmed present in source files." . PHP_EOL;
}
echo PHP_EOL;
