# Bugfix Requirements Document

## Introduction

This document covers a batch of bugs and UI issues across the PeaksCinema PHP web application. The issues span multiple pages: `my_bookings.php`, `profile_edit.php`, `seat_selection.php`, `receipt.php`, `queue_tracker.php`, `home.php`, `search_movies.php`, and several Admin pages (`dashboard.php`, `theater_admin.php`, `theater_upload.php`, `mall_upload.php`, `movie_upload.php`). The fixes address incorrect data display, missing UI elements, broken responsiveness, and inconsistent branding.

---

## Bug Analysis

### Current Behavior (Defect)

**my_bookings.php — Past screenings, sort order, and booking time**

1.1 WHEN a user views their Active Bookings tab THEN the system shows bookings whose screening date/time has already passed alongside future bookings, with no visual separation or filtering

1.2 WHEN a user views their Active Bookings tab THEN the system sorts bookings by screening date ascending, so the most recently booked movie does not appear first

1.3 WHEN a user views the booking time on the Past Bookings tab THEN the system displays only the date (`M d, Y`) without the time component, losing precision compared to the Active tab

---

**profile_edit.php — Photo change/remove not reflected on home.php**

1.4 WHEN a user uploads a new profile photo or removes their photo on `profile_edit.php` and then navigates to `home.php` THEN the system still shows the old photo (or the old initials) because the session variable `$_SESSION['profile_photo']` is not reliably propagated or the browser caches the old image URL

---

**seat_selection.php — Mobile responsiveness and logo alignment**

1.5 WHEN a user opens `seat_selection.php` on a mobile device THEN the system renders the header logo using a plain `<img>` tag with the `.logo img` class, which does not match the standardized `.brand-logo-wrap` / `.brand-logo` system used on `home.php` and `profile_edit.php`, causing the logo to appear misaligned or incorrectly sized

1.6 WHEN a user opens `seat_selection.php` on a mobile device with a viewport narrower than 540 px THEN the system applies a fixed negative `margin-bottom` to compensate for the scaled seat map, but the compensation value is hardcoded and does not adapt to the actual rendered map height, causing content below the map to overlap or leave excessive whitespace

---

**receipt.php — Logo, screening time, booking time, and food order**

1.7 WHEN a user views `receipt.php` THEN the system renders the header logo with `.logo img` (plain `<img>` inside a `.logo` div) instead of the standardized `.brand-logo-wrap` / `.brand-logo` pattern used on `home.php`, causing visual inconsistency

1.8 WHEN a user views `receipt.php` and the `timeslotDetails` array uses the key `StartTime` (lowercase-first) THEN the system checks `isset($timeslotDetails['STARTTIME'])` (all-caps) which evaluates to false, causing the screening time to display as "Time not available" even when the data exists

1.9 WHEN a user views `receipt.php` THEN the system displays the booking time using `date('F d, Y g:i A', strtotime($dateTime))` where `$dateTime` is set at the top of the POST block, but if the page is loaded via a GET request (e.g., browser back/refresh) `$dateTime` is undefined, causing a PHP warning and incorrect output

1.10 WHEN a user has added food items during booking and views `receipt.php` THEN the system does not display the food order summary or food total amount in the receipt card, even though the data is available in `$foodSummary` and `$foodTotal`

---

**Admin/dashboard.php — Font inconsistency**

1.11 WHEN an admin views `dashboard.php` THEN the system renders some text elements (particularly inside stat cards and table cells) without the `'Outfit'` font because the font is declared on `body` but certain dynamically-injected or inline-styled elements fall back to the browser default sans-serif

---

**Admin pages — Queue Manager nav link missing**

1.12 WHEN an admin is on `theater_admin.php` THEN the system renders the nav bar without a link to `queue_admin.php`, making the Queue Manager inaccessible from that page

1.13 WHEN an admin is on `theater_upload.php` THEN the system renders the nav bar without a link to `queue_admin.php`, making the Queue Manager inaccessible from that page

1.14 WHEN an admin is on `mall_upload.php` THEN the system renders the nav bar without a link to `queue_admin.php`, making the Queue Manager inaccessible from that page

---

**Admin/movie_upload.php — Card layout**

1.15 WHEN an admin views the "All Movies" management section on `movie_upload.php` THEN the system renders movies in a plain table layout, whereas `theater_upload.php` uses a card-based grid layout, creating an inconsistent admin UI

---

**queue_tracker.php — Header responsiveness, last update time, and logo**

1.16 WHEN a user opens `queue_tracker.php` on a mobile device THEN the system renders the header without responsive wrapping rules (no `flex-wrap`, no stacked search row), causing the header to overflow or compress on narrow viewports

1.17 WHEN a user views `queue_tracker.php` THEN the system displays the last update time as a static PHP-rendered string (e.g., `Last update: 3:45 PM`) that does not update when the page auto-refreshes via the JavaScript countdown, so after the first auto-refresh the displayed time is stale

1.18 WHEN a user views `queue_tracker.php` THEN the system renders the header logo using a plain `<img>` tag with `.logo img` class instead of the standardized `.brand-logo-wrap` / `.brand-logo` pattern, causing logo misalignment compared to other customer-facing pages

---

**home.php — Guest greeting**

1.19 WHEN a user who is not logged in visits `home.php` THEN the system displays the hardcoded text "Welcome back, Gabrielle!" in the hero heading instead of a generic welcome message, exposing a real user's name to unauthenticated visitors

---

**home.php — Search bar**

1.20 WHEN a user types a search query in all-lowercase (e.g., "batman") on `home.php` THEN the system sends the query to `search_movies.php` which uses `MovieName LIKE ?` without `LOWER()` or `COLLATE`, and on case-sensitive collations the query returns no results even though "THE BATMAN" exists

1.21 WHEN a user opens the search dropdown on `home.php` and presses the Up or Down arrow keys THEN the system does not move the highlighted selection through the result items, because no `keydown` event listener for `ArrowUp` / `ArrowDown` is attached to the search input

---

**receipt.php / e-receipt — Missing screening time and food order**

1.22 WHEN a booking confirmation email (e-receipt) is sent THEN the system does not include the screening time, food order items, or food total amount in the email body, leaving the customer without complete booking details in their inbox

---

### Expected Behavior (Correct)

**my_bookings.php**

2.1 WHEN a user views their Active Bookings tab THEN the system SHALL only show bookings whose screening date/time is in the future (i.e., `already_passed === false`), moving past screenings to the Past Bookings tab automatically

2.2 WHEN a user views their Active Bookings tab THEN the system SHALL sort bookings by the ticket `DateTime` (booking creation time) descending, so the most recently booked movie appears first

2.3 WHEN a user views the booking time on the Past Bookings tab THEN the system SHALL display the full date and time (e.g., `May 20, 2025 3:45 PM`) consistent with the Active tab format

---

**profile_edit.php**

2.4 WHEN a user saves a new or removed profile photo on `profile_edit.php` THEN the system SHALL update `$_SESSION['profile_photo']` with the new value (or `null`) immediately, and append a cache-busting query string to the image URL so that `home.php` and all other pages reflect the updated photo on the very next page load

---

**seat_selection.php**

2.5 WHEN a user opens `seat_selection.php` on any device THEN the system SHALL render the header logo using the standardized `.brand-logo-wrap` / `.brand-logo` markup and CSS, matching the logo size and alignment of `home.php` and `profile_edit.php`

2.6 WHEN a user opens `seat_selection.php` on a mobile device THEN the system SHALL dynamically measure the rendered seat map height via JavaScript and apply the correct negative margin compensation so that no content overlap or excessive whitespace occurs below the seat map

---

**receipt.php**

2.7 WHEN a user views `receipt.php` THEN the system SHALL render the header logo using the standardized `.brand-logo-wrap` / `.brand-logo` markup, matching `home.php`

2.8 WHEN a user views `receipt.php` THEN the system SHALL resolve the screening time using the correct key (`StartTime`) from `$timeslotDetails` and SHALL display the screening time and type in the receipt, never showing "Time not available" when the data is present

2.9 WHEN a user views `receipt.php` THEN the system SHALL display the booking time using the `$dateTime` value that was recorded during the POST transaction, and SHALL guard against undefined-variable access on non-POST loads

2.10 WHEN a user has added food items and views `receipt.php` THEN the system SHALL display a food order section in the receipt card listing each item name, quantity, unit price, and the food subtotal

---

**Admin/dashboard.php**

2.11 WHEN an admin views `dashboard.php` THEN the system SHALL apply the `'Outfit', sans-serif` font consistently to all visible text elements, including stat values, table cells, and any inline-styled content, by ensuring the font declaration cascades correctly or is explicitly set on all relevant selectors

---

**Admin pages — Queue Manager nav link**

2.12 WHEN an admin is on `theater_admin.php` THEN the system SHALL display a visible "Queue Manager" nav link pointing to `queue_admin.php` in the header navigation bar

2.13 WHEN an admin is on `theater_upload.php` THEN the system SHALL display a visible "Queue Manager" nav link pointing to `queue_admin.php` in the header navigation bar

2.14 WHEN an admin is on `mall_upload.php` THEN the system SHALL display a visible "Queue Manager" nav link pointing to `queue_admin.php` in the header navigation bar

---

**Admin/movie_upload.php**

2.15 WHEN an admin views the "All Movies" management section on `movie_upload.php` THEN the system SHALL render the movie list using a card-based grid layout consistent with the style used in `theater_upload.php`, with each card showing the poster thumbnail, title, genre, availability badge, and action buttons

---

**queue_tracker.php**

2.16 WHEN a user opens `queue_tracker.php` on a mobile device THEN the system SHALL render the header with responsive wrapping so that the logo, live badge, and action buttons stack or reflow without overflowing the viewport

2.17 WHEN the page auto-refreshes via the JavaScript countdown on `queue_tracker.php` THEN the system SHALL display the last update time dynamically from the freshly loaded queue data, so the displayed time always reflects the most recent server-side update

2.18 WHEN a user views `queue_tracker.php` THEN the system SHALL render the header logo using the standardized `.brand-logo-wrap` / `.brand-logo` markup, matching other customer-facing pages

---

**home.php — Guest greeting**

2.19 WHEN a user who is not logged in visits `home.php` THEN the system SHALL display "Welcome to PeaksCinema" (or equivalent generic greeting) in the hero heading instead of any real user's name

---

**home.php — Search bar**

2.20 WHEN a user types a search query in any combination of upper and lowercase letters on `home.php` THEN the system SHALL return matching movies case-insensitively by using `LOWER(MovieName) LIKE LOWER(?)` (or equivalent) in `search_movies.php`

2.21 WHEN a user opens the search dropdown on `home.php` and presses the Down arrow key THEN the system SHALL move the highlight to the next result item; WHEN the user presses the Up arrow key THEN the system SHALL move the highlight to the previous result item; WHEN the user presses Enter on a highlighted item THEN the system SHALL navigate to that movie's page

---

**receipt.php / e-receipt**

2.22 WHEN a booking confirmation email is sent THEN the system SHALL include the screening time, a list of food order items with quantities and prices, and the food subtotal in the email body

---

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a logged-in user views their Active Bookings tab and all their bookings are for future screenings THEN the system SHALL CONTINUE TO display all those bookings correctly with their existing card layout, seat chips, total price, and cancel button

3.2 WHEN a logged-in user views their Past Bookings tab THEN the system SHALL CONTINUE TO display cancelled and completed bookings with their existing status badges and card layout

3.3 WHEN a user saves profile changes (name, email, phone, password) without changing the photo THEN the system SHALL CONTINUE TO preserve the existing profile photo and all other field values

3.4 WHEN a user selects seats on `seat_selection.php` on a desktop browser THEN the system SHALL CONTINUE TO render the full seat map, AI recommendation banner, food step, and sidebar summary without any layout regression

3.5 WHEN a user completes a booking and views `receipt.php` with no food items THEN the system SHALL CONTINUE TO display the receipt card without a food section, and the total SHALL CONTINUE TO reflect only the ticket price

3.6 WHEN a user completes a booking and views `receipt.php` THEN the system SHALL CONTINUE TO save tickets, payments, e-receipt records, discount verifications, food orders, and notifications to the database exactly as before

3.7 WHEN an admin navigates between all existing admin pages (dashboard, malls, movie upload, theater upload, mall upload, queue admin) THEN the system SHALL CONTINUE TO render all existing nav links and page content correctly

3.8 WHEN a logged-in user visits `home.php` THEN the system SHALL CONTINUE TO display the user's name in the hero greeting (the fix only affects the guest/unauthenticated state)

3.9 WHEN a user types a search query that exactly matches a movie title in its original casing THEN the system SHALL CONTINUE TO return that movie in the search results

3.10 WHEN a user navigates the search dropdown using the mouse THEN the system SHALL CONTINUE TO highlight items on hover and navigate to the movie page on click

3.11 WHEN a user views `queue_tracker.php` on a desktop browser THEN the system SHALL CONTINUE TO display the summary strip, filter bar, tip banner, and mall queue cards without any layout regression

3.12 WHEN an admin submits the queue update form on `queue_admin.php` THEN the system SHALL CONTINUE TO save the queue data and display the success message as before
