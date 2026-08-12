# Implementation Plan

- [x] 1. Write bug condition exploration tests (B1–B18)
  - **Property 1: Bug Condition** - All 18 Bug Conditions Across Files
  - **CRITICAL**: These tests MUST FAIL on unfixed code — failure confirms the bugs exist
  - **DO NOT attempt to fix the tests or the code when they fail**
  - **NOTE**: These tests encode the expected behavior — they will validate the fixes when they pass after implementation
  - **GOAL**: Surface counterexamples that demonstrate each bug exists
  - **Scoped PBT Approach**: For deterministic bugs, scope each property to the concrete failing case(s)
  - B1 (my_bookings.php): Test that active bookings list contains no entries where `strtotime(date + ' ' + start_time) < time()` — run on unfixed code, expect FAILURE (past screenings appear in Active tab)
  - B2 (my_bookings.php): Test that active bookings are ordered by `DateTime DESC` — run on unfixed code, expect FAILURE (sorted by screening date ASC instead)
  - B3 (my_bookings.php): Test that past booking `booked_at` display matches `/[0-9]+:[0-9]+ (AM|PM)/` — run on unfixed code, expect FAILURE (time component missing)
  - B4 (profile_edit.php): Test that after photo save, the rendered image URL contains a cache-busting query string — run on unfixed code, expect FAILURE
  - B5 (seat_selection.php / receipt.php / queue_tracker.php): Test that header HTML contains `brand-logo-wrap` class — run on unfixed code, expect FAILURE (uses `.logo img` instead)
  - B6 (seat_selection.php): Test that `margin-bottom` compensation equals `actual_map_height * scale_factor` not a hardcoded 540px — run on unfixed code, expect FAILURE
  - B7 (receipt.php): Test that `isset($timeslotDetails['StartTime'])` is used (not `STARTTIME`) — run on unfixed code, expect FAILURE (screening time shows "Time not available")
  - B8 (receipt.php): Test that accessing receipt.php via GET does not produce undefined variable warning for `$dateTime` — run on unfixed code, expect FAILURE
  - B9 (receipt.php): Test that when `$foodSummary` is non-empty, receipt HTML contains a food section — run on unfixed code, expect FAILURE
  - B10 (Admin/dashboard.php): Test that all text elements use `font-family: 'Outfit'` — run on unfixed code, expect FAILURE
  - B11 (theater_admin.php, theater_upload.php, mall_upload.php): Test that nav HTML contains `href="queue_admin.php"` — run on unfixed code, expect FAILURE
  - B12 (Admin/movie_upload.php): Test that All Movies section uses card-grid layout not `<table class="mgmt-table">` — run on unfixed code, expect FAILURE (currently uses table)
  - B13 (queue_tracker.php): Test that CSS contains `@media (max-width: 768px)` with `flex-wrap: wrap` for header — run on unfixed code, expect FAILURE
  - B14 (queue_tracker.php): Test that last-update time shown after auto-refresh matches DB `updated_at` — run on unfixed code, expect FAILURE (static PHP-rendered time)
  - B15 (home.php): Test that unauthenticated hero heading does NOT contain "Gabrielle" — run on unfixed code, expect FAILURE
  - B16 (search_movies.php): Test that query "batman" returns "THE BATMAN" — run on unfixed code, expect FAILURE on case-sensitive collations
  - B17 (home.php): Test that ArrowDown/ArrowUp keydown events move the highlighted search result — run on unfixed code, expect FAILURE (no keydown handler)
  - B18 (receipt.php email): Test that confirmation email body contains screening time and food items — run on unfixed code, expect FAILURE
  - Run all tests on UNFIXED code
  - **EXPECTED OUTCOME**: All tests FAIL (this is correct — it proves the bugs exist)
  - Document counterexamples found to understand root causes
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10, 1.11, 1.12, 1.13, 1.14, 1.15, 1.16, 1.17, 1.18, 1.19, 1.20, 1.21, 1.22_


- [x] 2. Write preservation property tests (BEFORE implementing fixes)
  - **Property 2: Preservation** - Non-Buggy Behavior Across All Affected Files
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for all non-buggy inputs (¬C cases)
  - B1 preservation: Observe that future bookings (screening time > now) still appear in Active tab — write property test for all future bookings
  - B2 preservation: Observe that booking cards still render with correct layout, seat chips, total price, and cancel button — write property test
  - B3 preservation: Observe that Active tab booking time shows full `M d, Y g:i A` format — write property test
  - B4 preservation: Observe that saving name/email/phone/password without photo change preserves existing photo and all field values — write property test
  - B5 preservation: Observe that logo on home.php and profile_edit.php already uses `.brand-logo-wrap` correctly — write property test confirming those pages are unaffected
  - B6 preservation: Observe that desktop seat map renders without margin compensation issues — write property test for viewport > 768px
  - B7 preservation: Observe that receipt renders all non-time fields (movie, cinema, seats, payment) correctly — write property test
  - B8 preservation: Observe that POST-loaded receipt correctly saves tickets, payments, e-receipt records, food orders, and notifications — write property test
  - B9 preservation: Observe that receipt with no food items shows no food section and total reflects only ticket price — write property test
  - B10 preservation: Observe that existing dashboard stats, tables, and nav links render correctly — write property test
  - B11 preservation: Observe that all existing nav links (Dashboard, Malls, Movie Upload, Theater Upload, Mall Upload) still render on all three admin pages — write property test
  - B12 preservation: Observe that movie upload form, poster preview, delete modal, and filter bar all work correctly — write property test
  - B13 preservation: Observe that queue_tracker.php desktop layout (summary strip, filter bar, tip banner, mall queue cards) renders without regression — write property test
  - B14 preservation: Observe that queue data (wait times, statuses, people counts) displays correctly on initial page load — write property test
  - B15 preservation: Observe that logged-in user hero heading still shows the user's name — write property test
  - B16 preservation: Observe that exact-case queries (e.g., "THE BATMAN") still return results — write property test
  - B17 preservation: Observe that mouse hover and click on search results still work correctly — write property test
  - B18 preservation: Observe that email is sent with movie name, date, seats, and booking ref — write property test confirming those fields are present
  - Run all preservation tests on UNFIXED code
  - **EXPECTED OUTCOME**: All tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 3.12_


- [x] 3. Fix B1 — my_bookings.php: Past screenings appear in Active tab

  - [x] 3.1 Implement the fix
    - In the active bookings rendering loop, skip (or move to past) any booking where `$b['already_passed'] === true`
    - The `already_passed` flag is already computed: `'already_passed' => ($st < time())`
    - Wrap the active booking card output in `if (!$b['already_passed'])` OR filter `$active_bookings` before the loop
    - _Bug_Condition: isBugCondition_B1(booking) — booking.status=1 AND strtotime(date+' '+start_time) < time()_
    - _Expected_Behavior: Active tab shows ONLY bookings where screening time is in the future_
    - _Preservation: Future bookings still display with full card layout, seat chips, total, cancel button (3.1)_
    - _Requirements: 2.1, 3.1_

  - [x] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - No Past Screenings in Active Tab
    - **IMPORTANT**: Re-run the SAME test from task 1 for B1 — do NOT write a new test
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.1_

  - [x] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Future Bookings Unaffected
    - **IMPORTANT**: Re-run the SAME preservation tests from task 2 for B1
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)

- [x] 4. Fix B2 — my_bookings.php: Active Bookings sort order

  - [x] 4.1 Implement the fix
    - Change the SQL `ORDER BY` clause in the active bookings query from `ORDER BY ts.Date ASC, ts.StartTime ASC` to `ORDER BY tk.DateTime DESC`
    - This sorts by booking creation time descending so the most recently booked movie appears first
    - _Bug_Condition: isBugCondition_B2(bookingList) — bookings sorted by screening date ASC instead of booking DateTime DESC_
    - _Expected_Behavior: Active bookings sorted by DateTime DESC_
    - _Preservation: All booking card fields (movie, mall, theater, date, seats, total, cancel) still render correctly (3.1)_
    - _Requirements: 2.2, 3.1_

  - [x] 4.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Active Bookings Sorted by Booking Time DESC
    - **IMPORTANT**: Re-run the SAME test from task 1 for B2
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.2_

  - [x] 4.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Booking Card Layout Unaffected
    - Re-run preservation tests from task 2 for B2
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 5. Fix B3 — my_bookings.php: Past Bookings booking time missing time component

  - [x] 5.1 Implement the fix
    - In the Past Bookings rendering section, change the `fmt_dt` call for `booked_at` from `fmt_dt($b['booked_at'], 'M d, Y')` to `fmt_dt($b['booked_at'], 'M d, Y g:i A')`
    - _Bug_Condition: isBugCondition_B3(renderedBookingTime) — rendered string does NOT match /[0-9]+:[0-9]+ (AM|PM)/_
    - _Expected_Behavior: Past booking time displays as "May 10, 2025 3:45 PM"_
    - _Preservation: Past booking card layout, status badges, and seat chips unaffected (3.2)_
    - _Requirements: 2.3, 3.2_

  - [x] 5.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Past Booking Time Shows Full Datetime
    - Re-run the SAME test from task 1 for B3
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.3_

  - [x] 5.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Past Booking Cards Unaffected
    - Re-run preservation tests from task 2 for B3
    - **EXPECTED OUTCOME**: Tests PASS


- [x] 6. Fix B4 — profile_edit.php: Photo change not reflected on other pages

  - [x] 6.1 Implement the fix
    - After a successful photo save, ensure `$_SESSION['profile_photo']` is set to the new path (or `null` on removal) — this is already done but verify it propagates
    - On `home.php` and any page rendering the profile photo, append `?` + `time()` (or a hash of the file path) as a cache-busting query string to the `<img src>` attribute
    - Example: `<img src="<?= htmlspecialchars($profile_photo) . '?' . time() ?>">`
    - _Bug_Condition: isBugCondition_B4(imageUrl) — URL does NOT contain cache-busting query string_
    - _Expected_Behavior: Image URL contains cache-busting query string so browser fetches fresh photo_
    - _Preservation: Saving name/email/phone/password without photo change preserves existing photo (3.3)_
    - _Requirements: 2.4, 3.3_

  - [x] 6.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Profile Photo URL Has Cache-Busting String
    - Re-run the SAME test from task 1 for B4
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.4_

  - [x] 6.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Non-Photo Profile Fields Unaffected
    - Re-run preservation tests from task 2 for B4
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 7. Fix B5 — Logo markup inconsistency (seat_selection.php, receipt.php, queue_tracker.php)

  - [x] 7.1 Implement the fix in seat_selection.php
    - Replace `<div class="logo"><img src="peakscinematransparent.png" ...></div>` with the standardized markup:
      `<a href="home.php" class="brand-logo-wrap"><img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo"></a>`
    - Add the `.brand-logo-wrap` and `.brand-logo` CSS rules (copy from profile_edit.php)
    - _Bug_Condition: isBugCondition_B5(pageHtml, 'seat_selection.php') — HTML contains `.logo img` not `brand-logo-wrap`_
    - _Expected_Behavior: Logo uses standardized `.brand-logo-wrap` / `.brand-logo` markup_
    - _Preservation: Desktop seat map, food step, sidebar summary unaffected (3.4)_
    - _Requirements: 2.5, 3.4_

  - [x] 7.2 Implement the fix in receipt.php
    - Replace `<div class="logo"><img ...></div>` with standardized `brand-logo-wrap` markup
    - _Requirements: 2.7_

  - [x] 7.3 Implement the fix in queue_tracker.php
    - Replace `<div class="logo"><img ...></div>` with standardized `brand-logo-wrap` markup
    - _Requirements: 2.18_

  - [x] 7.4 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Logo Uses brand-logo-wrap on All Three Pages
    - Re-run the SAME test from task 1 for B5
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.5, 2.7, 2.18_

  - [x] 7.5 Verify preservation tests still pass
    - **Property 2: Preservation** - Page Layouts Unaffected
    - Re-run preservation tests from task 2 for B5
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 8. Fix B6 — seat_selection.php: Hardcoded mobile margin compensation

  - [x] 8.1 Implement the fix
    - In the JavaScript section of seat_selection.php, after the seat map renders, measure the actual rendered height of `.seat-map` using `seatMap.offsetHeight`
    - Set the CSS custom property `--map-h` on `.seat-map-container` to the measured height
    - The existing CSS already uses `calc((var(--map-h, 540px) * 0.XX) * -1)` — the JS just needs to set the actual value
    - Example: `document.querySelector('.seat-map-container').style.setProperty('--map-h', seatMap.offsetHeight + 'px')`
    - _Bug_Condition: isBugCondition_B6(viewport_width, actual_map_height) — actual_map_height != 540 AND margin uses hardcoded 540_
    - _Expected_Behavior: margin-bottom compensation = actual_map_height * (1 - scale)_
    - _Preservation: Desktop seat map (viewport > 768px) unaffected (3.4)_
    - _Requirements: 2.6, 3.4_

  - [x] 8.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Margin Compensation Uses Actual Map Height
    - Re-run the SAME test from task 1 for B6
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.6_

  - [x] 8.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Desktop Seat Map Unaffected
    - Re-run preservation tests from task 2 for B6
    - **EXPECTED OUTCOME**: Tests PASS


- [x] 9. Fix B7 — receipt.php: Screening time key mismatch

  - [x] 9.1 Implement the fix
    - Locate the condition: `if (isset($timeslotDetails['ScreeningType']) && isset($timeslotDetails['STARTTIME']))`
    - Change `'STARTTIME'` to `'StartTime'` to match the actual DB column key returned by the query
    - Result: `if (isset($timeslotDetails['ScreeningType']) && isset($timeslotDetails['StartTime']))`
    - Also update the echo line to use `$timeslotDetails['StartTime']` consistently
    - _Bug_Condition: isBugCondition_B7(timeslotDetails) — code checks `STARTTIME` but DB returns `StartTime`_
    - _Expected_Behavior: Screening time and type display correctly in receipt_
    - _Preservation: All other receipt fields (movie, cinema, seats, payment) unaffected (3.5, 3.6)_
    - _Requirements: 2.8, 3.5, 3.6_

  - [x] 9.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Screening Time Displays Correctly
    - Re-run the SAME test from task 1 for B7
    - **EXPECTED OUTCOME**: Test PASSES (no more "Time not available")
    - _Requirements: 2.8_

  - [x] 9.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Receipt Fields Unaffected
    - Re-run preservation tests from task 2 for B7
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 10. Fix B8 — receipt.php: $dateTime undefined on non-POST load

  - [x] 10.1 Implement the fix
    - Add an `isset()` guard before using `$dateTime` in the Booking Time receipt row
    - Change: `date('F d, Y g:i A', strtotime($dateTime))`
    - To: `isset($dateTime) ? date('F d, Y g:i A', strtotime($dateTime)) : 'N/A'`
    - Alternatively, initialize `$dateTime = null` at the top of the file before the POST block
    - _Bug_Condition: isBugCondition_B8(requestMethod) — requestMethod != 'POST' AND $dateTime referenced without guard_
    - _Expected_Behavior: No PHP warning on GET load; booking time shows graceful fallback_
    - _Preservation: POST-loaded receipt still saves all DB records correctly (3.6)_
    - _Requirements: 2.9, 3.6_

  - [x] 10.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - No Undefined Variable on GET Load
    - Re-run the SAME test from task 1 for B8
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.9_

  - [x] 10.3 Verify preservation tests still pass
    - **Property 2: Preservation** - POST Receipt DB Saves Unaffected
    - Re-run preservation tests from task 2 for B8
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 11. Fix B9 — receipt.php: Food order not displayed

  - [x] 11.1 Implement the fix
    - The receipt.php already has a `<?php if (!empty($foodSummary)): ?>` block that fetches food item names and starts rendering
    - Verify the block is complete and renders: item name, quantity, unit price per item, and food subtotal
    - If the block is truncated or missing the closing HTML, complete it with a food section card inside `.receipt-card`
    - Ensure `$foodTotal` is included in the displayed total amount
    - _Bug_Condition: isBugCondition_B9(foodSummary, receiptHtml) — count(foodSummary) > 0 AND receipt has no food section_
    - _Expected_Behavior: Receipt shows food section with item names, quantities, prices, and subtotal_
    - _Preservation: Receipt with no food items shows no food section; ticket total unaffected (3.5)_
    - _Requirements: 2.10, 3.5_

  - [x] 11.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Food Section Appears When Food Was Ordered
    - Re-run the SAME test from task 1 for B9
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.10_

  - [x] 11.3 Verify preservation tests still pass
    - **Property 2: Preservation** - No-Food Receipt Unaffected
    - Re-run preservation tests from task 2 for B9
    - **EXPECTED OUTCOME**: Tests PASS


- [x] 12. Fix B10 — Admin/dashboard.php: Font inconsistency

  - [x] 12.1 Implement the fix
    - Ensure the `font-family: 'Outfit', sans-serif` declaration cascades to all elements
    - Add explicit `font-family: inherit` or `font-family: 'Outfit', sans-serif` to `.stat-value`, `.data-table td`, and any inline-styled elements that override the body font
    - Check for any `<style>` blocks or inline styles that set a different font and remove or override them
    - _Bug_Condition: isBugCondition_B10(element) — computedFontFamily(element) != 'Outfit' for stat values or table cells_
    - _Expected_Behavior: All visible text in dashboard uses 'Outfit' font_
    - _Preservation: All existing dashboard stats, tables, nav links, and queue manager render correctly (3.7)_
    - _Requirements: 2.11, 3.7_

  - [x] 12.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - All Dashboard Text Uses Outfit Font
    - Re-run the SAME test from task 1 for B10
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.11_

  - [x] 12.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Dashboard Content Unaffected
    - Re-run preservation tests from task 2 for B10
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 13. Fix B11 — Admin pages: Queue Manager nav link missing (theater_admin.php, theater_upload.php, mall_upload.php)

  - [x] 13.1 Add Queue Manager link to theater_admin.php nav
    - In the `<nav>` block of theater_admin.php, add: `<a href="queue_admin.php">Queue Manager</a>` after the existing Mall Upload link
    - _Bug_Condition: isBugCondition_B11(pageHtml, 'theater_admin.php') — nav does NOT contain href="queue_admin.php"_
    - _Expected_Behavior: Queue Manager link visible in nav_
    - _Preservation: All existing nav links (Dashboard, Malls, Movie Upload, Theater Upload, Mall Upload) still present (3.7)_
    - _Requirements: 2.12, 3.7_

  - [x] 13.2 Add Queue Manager link to theater_upload.php nav
    - In the `<nav>` block of theater_upload.php, add: `<a href="queue_admin.php">Queue Manager</a>`
    - _Requirements: 2.13, 3.7_

  - [x] 13.3 Add Queue Manager link to mall_upload.php nav
    - In the `<nav>` block of mall_upload.php, add: `<a href="queue_admin.php">Queue Manager</a>`
    - _Requirements: 2.14, 3.7_

  - [x] 13.4 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Queue Manager Link Present on All Three Pages
    - Re-run the SAME test from task 1 for B11
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.12, 2.13, 2.14_

  - [x] 13.5 Verify preservation tests still pass
    - **Property 2: Preservation** - Existing Nav Links Unaffected
    - Re-run preservation tests from task 2 for B11
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 14. Fix B12 — Admin/movie_upload.php: Table layout instead of card grid

  - [x] 14.1 Implement the fix
    - Replace the `<table class="mgmt-table">` All Movies section with a card-grid layout
    - Each card should show: poster thumbnail, movie title, genre, availability badge, and action buttons (toggle availability, delete)
    - Add CSS for `.movie-card-grid`, `.movie-mgmt-card`, `.movie-mgmt-poster` consistent with the admin design system
    - Keep the existing search/filter bar, delete modal, and flash message logic intact
    - _Bug_Condition: isBugCondition_B12(pageHtml) — HTML contains `<table class="mgmt-table"` for movie list_
    - _Expected_Behavior: Movie list uses card-grid layout with poster, title, genre, badge, and action buttons_
    - _Preservation: Upload form, poster preview, delete modal, filter bar, and availability toggle all work correctly (3.7)_
    - _Requirements: 2.15, 3.7_

  - [x] 14.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Movie List Uses Card Grid Layout
    - Re-run the SAME test from task 1 for B12
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.15_

  - [x] 14.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Upload Form and Actions Unaffected
    - Re-run preservation tests from task 2 for B12
    - **EXPECTED OUTCOME**: Tests PASS


- [x] 15. Fix B13 — queue_tracker.php: Header not responsive on mobile

  - [x] 15.1 Implement the fix
    - Add a `@media (max-width: 768px)` rule for the `header` element that includes `flex-wrap: wrap` and appropriate padding/height adjustments
    - Ensure the logo, live badge, and action buttons reflow without overflowing on narrow viewports
    - Example: `@media (max-width: 768px) { header { flex-wrap: wrap; height: auto; padding: 10px 15px; gap: 8px; } }`
    - _Bug_Condition: isBugCondition_B13(cssRules) — no @media (max-width: 768px) with flex-wrap: wrap for header_
    - _Expected_Behavior: Header wraps and reflows on mobile without overflow_
    - _Preservation: Desktop queue tracker layout (summary strip, filter bar, mall cards) unaffected (3.11)_
    - _Requirements: 2.16, 3.11_

  - [x] 15.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Header Responsive on Mobile
    - Re-run the SAME test from task 1 for B13
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.16_

  - [x] 15.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Desktop Layout Unaffected
    - Re-run preservation tests from task 2 for B13
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 16. Fix B14 — queue_tracker.php: Last update time is static

  - [x] 16.1 Implement the fix
    - The page already auto-refreshes via `location.reload()` in the countdown JS — on reload, PHP re-renders `$lastUpdated` from the DB
    - The bug is that the time string is rendered once by PHP and not updated by JS between reloads
    - Since `location.reload()` triggers a full page reload, the PHP-rendered time will be fresh after each reload
    - Verify the `$lastUpdated` value is computed from `max(array_column($queueData,'updated_at'))` and rendered in the refresh bar
    - If the issue is that the time shows the initial load time rather than DB time, ensure `$lastUpdated` is fetched from the DB (not `date()`) — it already is
    - Optionally: update the displayed time via JS after each countdown tick using a fetch to a lightweight endpoint, or simply confirm the full-page reload approach is sufficient
    - _Bug_Condition: isBugCondition_B14(displayedTime, actualLastUpdate) — after auto-refresh, displayedTime = initial PHP time != actualLastUpdate_
    - _Expected_Behavior: After auto-refresh, displayed time reflects the most recent DB updated_at_
    - _Preservation: Queue data display (wait times, statuses, people counts) unaffected (3.11)_
    - _Requirements: 2.17, 3.11_

  - [x] 16.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Last Update Time Reflects DB After Refresh
    - Re-run the SAME test from task 1 for B14
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.17_

  - [x] 16.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Queue Data Display Unaffected
    - Re-run preservation tests from task 2 for B14
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 17. Fix B15 — home.php: Guest greeting shows hardcoded name

  - [x] 17.1 Implement the fix
    - Locate the hero `<h1>` element that contains the hardcoded "Welcome back, Gabrielle!"
    - Wrap it in a PHP conditional: if `isset($_SESSION['user_id'])` show the user's name, else show a generic greeting like "Welcome to PeaksCinema"
    - _Bug_Condition: isBugCondition_B15(session, heroHtml) — NOT isset(session['user_id']) AND heroHtml CONTAINS 'Gabrielle'_
    - _Expected_Behavior: Unauthenticated visitors see "Welcome to PeaksCinema" (or equivalent generic greeting)_
    - _Preservation: Logged-in users still see their name in the hero greeting (3.8)_
    - _Requirements: 2.19, 3.8_

  - [x] 17.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Guest Sees Generic Greeting
    - Re-run the SAME test from task 1 for B15
    - **EXPECTED OUTCOME**: Test PASSES (no "Gabrielle" for unauthenticated users)
    - _Requirements: 2.19_

  - [x] 17.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Logged-In User Greeting Unaffected
    - Re-run preservation tests from task 2 for B15
    - **EXPECTED OUTCOME**: Tests PASS


- [x] 18. Fix B16 — search_movies.php: Case-sensitive search

  - [x] 18.1 Implement the fix
    - In search_movies.php, change the SQL from:
      `WHERE MovieName LIKE ?`
    - To:
      `WHERE LOWER(MovieName) LIKE LOWER(?)`
    - Update the `$searchTerm` binding accordingly (the `%` wrapping stays the same)
    - _Bug_Condition: isBugCondition_B16(query, dbCollation) — dbCollation is case-sensitive AND query != UPPER(query) AND SQL uses LIKE without LOWER()_
    - _Expected_Behavior: Query "batman" returns "THE BATMAN" on case-sensitive collations_
    - _Preservation: Exact-case queries (e.g., "THE BATMAN") still return results (3.9)_
    - _Requirements: 2.20, 3.9_

  - [x] 18.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Case-Insensitive Search Returns Results
    - Re-run the SAME test from task 1 for B16
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.20_

  - [x] 18.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Exact-Case Queries Unaffected
    - Re-run preservation tests from task 2 for B16
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 19. Fix B17 — home.php: No arrow key navigation in search dropdown

  - [x] 19.1 Implement the fix
    - In the search input's JavaScript section on home.php, add a `keydown` event listener on the search input
    - On `ArrowDown`: move highlight to the next `.search-result-item` (add/move `.selected` class)
    - On `ArrowUp`: move highlight to the previous `.search-result-item`
    - On `Enter`: navigate to the highlighted item's href (or trigger click)
    - Prevent default scroll behavior for arrow keys when dropdown is open
    - _Bug_Condition: isBugCondition_B17(keyEvent, dropdownOpen) — keyEvent.key IN ['ArrowUp','ArrowDown'] AND dropdownOpen AND no handler moves selection_
    - _Expected_Behavior: Arrow keys move highlight through search results; Enter navigates to selected movie_
    - _Preservation: Mouse hover and click on search results still work correctly (3.10)_
    - _Requirements: 2.21, 3.10_

  - [x] 19.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Arrow Keys Navigate Search Dropdown
    - Re-run the SAME test from task 1 for B17
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.21_

  - [x] 19.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Mouse Interaction Unaffected
    - Re-run preservation tests from task 2 for B17
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 20. Fix B18 — receipt.php / e-receipt: Missing screening time and food in email

  - [x] 20.1 Implement the fix
    - Locate the email body construction in receipt.php (inside the POST transaction block, after the notification insert)
    - Add the screening time to the email body: use `fmtTime($timeslotDetails['StartTime'] ?? '')` and `$timeslotDetails['ScreeningType']`
    - Add the food order items to the email body: iterate `$foodSummary` and include item names, quantities, and prices
    - Add the food subtotal (`$foodTotal`) to the email body
    - _Bug_Condition: isBugCondition_B18(emailBody, bookingHasFood) — emailBody does NOT contain screeningTime OR (bookingHasFood AND emailBody does NOT contain foodItems)_
    - _Expected_Behavior: Confirmation email includes screening time, food items with quantities/prices, and food subtotal_
    - _Preservation: Email still contains movie name, date, seats, booking ref, and is sent to correct address (3.6)_
    - _Requirements: 2.22, 3.6_

  - [x] 20.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Email Contains Screening Time and Food Items
    - Re-run the SAME test from task 1 for B18
    - **EXPECTED OUTCOME**: Test PASSES
    - _Requirements: 2.22_

  - [x] 20.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Existing Email Fields Unaffected
    - Re-run preservation tests from task 2 for B18
    - **EXPECTED OUTCOME**: Tests PASS

- [x] 21. Checkpoint — Ensure all tests pass
  - Re-run the full test suite (all Property 1 and Property 2 tests from tasks 1 and 2)
  - Verify all 18 bug condition exploration tests now PASS (bugs are fixed)
  - Verify all preservation property tests still PASS (no regressions)
  - Confirm no PHP warnings or errors are introduced by any fix
  - Ask the user if any questions arise before closing the spec
