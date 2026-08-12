# Final Changes to Fix — Bugfix Design

## Overview

This document covers a batch of bugs and UI inconsistencies across the PeaksCinema PHP application.
The fixes span 12 files and address: incorrect data filtering/sorting, broken UI display logic,
missing nav links, logo markup inconsistencies, case-insensitive search, a hardcoded guest name,
and missing content in receipts and confirmation emails.

The strategy is minimal and targeted: each fix changes only the specific line(s) responsible for
the defect, with no structural rewrites. Preservation of all existing behavior for non-buggy inputs
is the primary constraint.

---

## Glossary

- **Bug_Condition (C)**: The specific input state or code path that triggers the defective behavior.
- **Property (P)**: The correct output or behavior that the fixed code must produce when C holds.
- **Preservation**: All behaviors that must remain identical after the fix (¬C inputs).
- **isBugCondition**: Pseudocode function that returns true when a given input triggers the bug.
- **expectedBehavior**: Pseudocode function that returns true when the output is correct.
- **already_passed**: Boolean flag on a booking — true when screening datetime < current time.
- **$timeslotDetails**: PHP associative array fetched from the `timeslot` table; key is `StartTime`.
- **$dateTime**: PHP variable set inside the POST block in `receipt.php`; undefined on GET loads.
- **brand-logo-wrap / brand-logo**: Standardized logo CSS classes used on `home.php` and `profile_edit.php`.
- **$foodSummary**: PHP array of food items selected during booking, available in `receipt.php`.

---

## Bug Details

### Bug Condition

The bugs manifest across multiple files and code paths. Each is described below with its formal
specification and concrete examples.

---

#### B1 — my_bookings.php: Past screenings in Active tab

**Formal Specification:**
```
FUNCTION isBugCondition_B1(booking)
  INPUT: booking record with fields {date, start_time, status}
  OUTPUT: boolean

  screeningTime := strtotime(booking.date + ' ' + booking.start_time)
  RETURN booking.status = 1
         AND screeningTime < time()          -- screening has passed
         AND booking appears in active list  -- it is incorrectly shown
END FUNCTION
```

**Examples:**
- Booking for May 1, 2025 at 2:00 PM, viewed on May 10, 2025 → appears in Active tab (BUG: should be in Past)
- Booking for tomorrow at 7:00 PM → correctly appears in Active tab (not a bug)

---

#### B2 — my_bookings.php: Active Bookings sort order

**Formal Specification:**
```
FUNCTION isBugCondition_B2(bookingList)
  INPUT: list of active bookings
  OUTPUT: boolean

  RETURN EXISTS booking_i, booking_j IN bookingList
         WHERE booking_i.DateTime > booking_j.DateTime  -- i was booked more recently
         AND   position(booking_i) > position(booking_j) -- but i appears after j
END FUNCTION
```

**Examples:**
- User books Movie A on May 1, Movie B on May 5 → Movie A appears first (BUG: Movie B should be first)

---

#### B3 — my_bookings.php: Past Bookings booking time missing time

**Formal Specification:**
```
FUNCTION isBugCondition_B3(renderedBookingTime)
  INPUT: string rendered in Past Bookings tab for booked_at field
  OUTPUT: boolean

  RETURN NOT renderedBookingTime MATCHES /[0-9]+:[0-9]+ (AM|PM)/
END FUNCTION
```

**Examples:**
- Booking made at May 10, 2025 3:45 PM → displays "May 10, 2025" (BUG: should be "May 10, 2025 3:45 PM")

---

#### B4 — profile_edit.php: Photo change not reflected on other pages

**Formal Specification:**
```
FUNCTION isBugCondition_B4(imageUrl)
  INPUT: profile photo URL rendered on home.php after a photo change
  OUTPUT: boolean

  RETURN imageUrl does NOT contain a cache-busting query string
         AND browser may serve stale cached version
END FUNCTION
```

**Examples:**
- User uploads new photo → home.php still shows old photo because browser cached the old URL

---

#### B5 — seat_selection.php / receipt.php / queue_tracker.php: Logo markup inconsistency

**Formal Specification:**
```
FUNCTION isBugCondition_B5(pageHtml, page)
  INPUT: rendered HTML of page
  OUTPUT: boolean

  RETURN page IN ['seat_selection.php', 'receipt.php', 'queue_tracker.php']
         AND pageHtml CONTAINS '<div class="logo"><img'
         AND NOT pageHtml CONTAINS 'brand-logo-wrap'
END FUNCTION
```

**Examples:**
- `seat_selection.php` header logo uses `.logo img` → misaligned vs `home.php` (BUG)
- `receipt.php` header logo uses `.logo img` → inconsistent branding (BUG)
- `queue_tracker.php` header logo uses `.logo img` → inconsistent branding (BUG)

---

#### B6 — seat_selection.php: Hardcoded mobile margin compensation

**Formal Specification:**
```
FUNCTION isBugCondition_B6(viewport_width, actual_map_height)
  INPUT: viewport width in px, actual rendered seat map height in px
  OUTPUT: boolean

  scale := IF viewport_width <= 400 THEN 0.48
           ELSE IF viewport_width <= 540 THEN 0.60
           ELSE 0.80
  hardcoded_fallback := 540
  RETURN actual_map_height != hardcoded_fallback
         AND margin_compensation uses hardcoded_fallback
END FUNCTION
```

**Examples:**
- Theater with 20 rows → map height ~720px, but CSS uses 540px fallback → content overlaps (BUG)

---

#### B7 — receipt.php: Screening time key mismatch

**Formal Specification:**
```
FUNCTION isBugCondition_B7(timeslotDetails)
  INPUT: associative array from timeslot DB query
  OUTPUT: boolean

  RETURN isset(timeslotDetails['StartTime'])
         AND NOT isset(timeslotDetails['STARTTIME'])
         AND code checks isset(timeslotDetails['ScreeningType'])
             AND isset(timeslotDetails['STARTTIME'])  -- wrong key
END FUNCTION
```

**Examples:**
- DB returns `['StartTime' => '14:00:00', 'ScreeningType' => '2D']`
  → condition `isset($timeslotDetails['STARTTIME'])` is false
  → receipt shows "Time not available" (BUG)

---

#### B8 — receipt.php: $dateTime undefined on non-POST load

**Formal Specification:**
```
FUNCTION isBugCondition_B8(requestMethod)
  INPUT: HTTP request method
  OUTPUT: boolean

  RETURN requestMethod != 'POST'
         AND code references $dateTime without isset() guard
END FUNCTION
```

**Examples:**
- User presses browser back button → receipt.php loads via GET → PHP warning, wrong booking time

---

#### B9 — receipt.php: Food order not displayed

**Formal Specification:**
```
FUNCTION isBugCondition_B9(foodSummary, receiptHtml)
  INPUT: $foodSummary array, rendered receipt HTML
  OUTPUT: boolean

  RETURN count(foodSummary) > 0
         AND NOT receiptHtml CONTAINS food section with item names and prices
END FUNCTION
```

**Examples:**
- User orders 2 popcorns + 1 drink → receipt card shows no food section (BUG)

---

#### B10 — Admin/dashboard.php: Font inconsistency

**Formal Specification:**
```
FUNCTION isBugCondition_B10(element)
  INPUT: a visible text element in dashboard.php
  OUTPUT: boolean

  RETURN computedFontFamily(element) != 'Outfit'
         AND element is inside .stat-value, .data-table td, or inline-styled content
END FUNCTION
```

---

#### B11 — Admin pages: Queue Manager nav link missing

**Formal Specification:**
```
FUNCTION isBugCondition_B11(pageHtml, page)
  INPUT: rendered HTML of admin page
  OUTPUT: boolean

  RETURN page IN ['theater_admin.php', 'theater_upload.php', 'mall_upload.php']
         AND NOT pageHtml CONTAINS '<a href="queue_admin.php"'
END FUNCTION
```

**Examples:**
- Admin on `theater_admin.php` → no "Queue Manager" link in nav (BUG)
- Admin on `theater_upload.php` → no "Queue Manager" link in nav (BUG)
- Admin on `mall_upload.php` → no "Queue Manager" link in nav (BUG)

---

#### B12 — Admin/movie_upload.php: Table layout instead of card grid

**Formal Specification:**
```
FUNCTION isBugCondition_B12(pageHtml)
  INPUT: rendered HTML of movie_upload.php All Movies section
  OUTPUT: boolean

  RETURN pageHtml CONTAINS '<table class="mgmt-table"'
         AND NOT pageHtml CONTAINS card-grid layout for movie list
END FUNCTION
```

---

#### B13 — queue_tracker.php: Header not responsive on mobile

**Formal Specification:**
```
FUNCTION isBugCondition_B13(cssRules)
  INPUT: CSS rules for queue_tracker.php header
  OUTPUT: boolean

  RETURN NOT cssRules CONTAINS '@media (max-width: 768px)' with 'flex-wrap: wrap' for header
END FUNCTION
```

---

#### B14 — queue_tracker.php: Last update time is static

**Formal Specification:**
```
FUNCTION isBugCondition_B14(displayedTime, actualLastUpdate)
  INPUT: time string shown on page, actual last update from DB
  OUTPUT: boolean

  RETURN page has auto-refreshed at least once
         AND displayedTime = PHP-rendered time from initial page load
         AND displayedTime != actualLastUpdate
END FUNCTION
```

---

#### B15 — home.php: Guest greeting shows hardcoded name

**Formal Specification:**
```
FUNCTION isBugCondition_B15(session, heroHtml)
  INPUT: PHP session state, rendered hero heading HTML
  OUTPUT: boolean

  RETURN NOT isset(session['user_id'])
         AND heroHtml CONTAINS 'Gabrielle'
END FUNCTION
```

**Examples:**
- Unauthenticated visitor → hero shows "Welcome back, Gabrielle!" (BUG: exposes real user name)

---

#### B16 — search_movies.php: Case-sensitive search

**Formal Specification:**
```
FUNCTION isBugCondition_B16(query, dbCollation)
  INPUT: search query string, database collation
  OUTPUT: boolean

  RETURN dbCollation IS case-sensitive
         AND query != UPPER(query)
         AND SQL uses MovieName LIKE ? without LOWER()
END FUNCTION
```

**Examples:**
- Query "batman" → no results even though "THE BATMAN" exists (BUG on case-sensitive collations)

---

#### B17 — home.php: No arrow key navigation in search dropdown

**Formal Specification:**
```
FUNCTION isBugCondition_B17(keyEvent, dropdownOpen)
  INPUT: keyboard event, dropdown visibility state
  OUTPUT: boolean

  RETURN keyEvent.key IN ['ArrowUp', 'ArrowDown']
         AND dropdownOpen = true
         AND no keydown handler moves the highlighted selection
END FUNCTION
```

---

#### B18 — receipt.php / e-receipt: Missing screening time and food in email

**Formal Specification:**
```
FUNCTION isBugCondition_B18(emailBody, bookingHasFood)
  INPUT: confirmation email body string, boolean
  OUTPUT: boolean

  RETURN NOT emailBody CONTAINS screeningTime
         OR (bookingHasFood AND NOT emailBody CONTAINS foodItems)
END FUNCTION
```
