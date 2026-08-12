<?php
date_default_timezone_set('Asia/Manila');
session_start();
include("peakscinemas_database.php");

// Load user profile data if logged in
$profile_photo = null;
$user_initials = '?';
$user_name = 'Guest';

if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT Name, ProfilePhoto FROM customer WHERE Customer_ID = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        $user_name = $user['Name'] ?? 'User';
        $profile_photo = $user['ProfilePhoto'] ?? $_SESSION['profile_photo'] ?? null;
        
        // Generate initials
        $np = explode(' ', trim($user_name));
        $user_initials = strtoupper(substr($np[0]??'',0,1).substr(end($np)??'',0,1));
        if (strlen($user_initials)===1) $user_initials = strtoupper(substr($np[0]??'',0,2));
    }
}

// ── Fetch Movies Dynamically ──────────────────────────────────
// 1. Top 5 Most Booked
$topMoviesQuery = "
    SELECT m.Movie_ID, m.MovieName, m.MoviePoster, COUNT(t.Ticket_ID) as purchase_count
    FROM movie m
    LEFT JOIN ticket t ON m.Movie_ID = t.Movie_ID
    GROUP BY m.Movie_ID
    ORDER BY purchase_count DESC, m.Movie_ID DESC
    LIMIT 5
";
$topMoviesRes = $conn->query($topMoviesQuery);
$top_movies = [];
if ($topMoviesRes) {
    while ($row = $topMoviesRes->fetch_assoc()) {
        $top_movies[] = $row;
    }
}

// 2. Now Showing
$nowShowingRes = $conn->query("SELECT * FROM movie WHERE MovieAvailability = 'Now Showing' ORDER BY Movie_ID DESC");
$now_showing_movies = [];
if ($nowShowingRes) {
    while ($row = $nowShowingRes->fetch_assoc()) {
        $now_showing_movies[] = $row;
    }
}

// 3. Coming Soon
$comingSoonRes = $conn->query("SELECT * FROM movie WHERE MovieAvailability = 'Coming Soon' ORDER BY Movie_ID DESC");
$coming_soon_movies = [];
if ($comingSoonRes) {
    while ($row = $comingSoonRes->fetch_assoc()) {
        $coming_soon_movies[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0f0f0f">
  <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
  <title>Peak's Cinema – Home</title>
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    html, body {
      overflow-x: hidden;
      width: 100%;
      position: relative;
    }

    body {
      font-family: 'Outfit', sans-serif;
      background: #0f0f0f;
      color: #F9F9F9;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      scroll-behavior: smooth;
    }

    /* Subtle background texture */
    body::before {
      content: '';
      position: fixed; inset: 0;
      background: url('movie-background-collage.jpg') center/cover no-repeat;
      opacity: 0.10; z-index: 0; pointer-events: none;
    }
    body::after {
      content: '';
      position: fixed; inset: 0;
      background: radial-gradient(ellipse at center, transparent 20%, rgba(15,15,15,0.6) 70%, #0f0f0f 100%);
      z-index: 1; pointer-events: none;
    }

    a { text-decoration: none; color: inherit; }

    /* ══ HEADER ══════════════════════════════════════════════════ */
    /* Updated: 2026-04-04 - Centered search bar layout */
    header {
      background: #1C1C1C;
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: center;
      padding: 0 20px;
      position: fixed; top: 0; left: 0; width: 100%;
      height: 50px; z-index: 1000;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      transition: transform 0.3s ease, all 0.3s ease;
      box-shadow: 0 2px 10px rgba(0,0,0,0.3);
      overflow: visible;
    }
    header.hidden {
      transform: translateY(-100%);
    }

    .brand-logo-wrap {
      display: inline-flex;
      align-items: center;
      text-decoration: none;
      transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      flex-shrink: 0;
      justify-self: start;
    }
    .brand-logo-wrap:hover { transform: scale(1.05); }
    .brand-logo { height: 34px; width: auto; filter: invert(1); display: block; }

    /* Search */
    .search-wrap {
      position: relative;
      max-width: 350px;
      width: 100%;
      z-index: 1001;
      display: flex; 
      align-items: center;
      justify-self: center;
    }
    .search-wrap input {
      width: 100%; padding: 0 12px 0 35px;
      border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);
      background: rgba(255,255,255,0.05);
      color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.82rem;
      outline: none; transition: all 0.3s;
      height: 34px;
    }
    .search-wrap input::placeholder { color: rgba(249,249,249,0.3); }
    .search-wrap input:focus { 
      border-color: rgba(255,77,77,0.4); 
      background: rgba(255,255,255,0.08);
      box-shadow: 0 0 15px rgba(255,77,77,0.1);
    }
    .search-icon {
      position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
      color: rgba(249,249,249,0.3); pointer-events: none;
      display: flex; align-items: center;
    }
    .search-icon svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2.5; }

    /* Right actions */
    .header-actions { 
      display: flex; 
      align-items: center; 
      gap: 8px;
      justify-self: end;
    }

    .bookings-btn, .notif-btn {
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 7px; padding: 6px 9px;
      color: rgba(249,249,249,0.65); font-size: 0.92rem;
      cursor: pointer; transition: all 0.25s;
      font-family: 'Outfit', sans-serif;
      display: flex; align-items: center;
      height: 32px;
    }
    .bookings-btn:hover, .notif-btn:hover { 
      background: rgba(255,255,255,0.12); color: #F9F9F9;
      transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .bookings-btn { gap: 5px; }
    .bookings-btn::after { content: 'My Bookings'; font-size: 0.75rem; font-weight: 600; }

    .notif-wrap { position: relative; display: flex; align-items: center; }
    .notif-badge {
      position: absolute; top: -4px; right: -4px;
      background: #ff4d4d; color: #fff;
      font-size: 0.55rem; font-weight: 800;
      min-width: 16px; height: 16px; border-radius: 8px;
      display: none; align-items: center; justify-content: center;
      padding: 0 4px;
    }

    .profile-btn {
      background: #F9F9F9; border: none; border-radius: 50%;
      width: 34px; height: 34px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; overflow: hidden; padding: 0;
      transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
      flex-shrink: 0;
    }
    .profile-btn img { width: 100%; height: 100%; object-fit: cover; }
    .profile-btn:hover { transform: scale(1.08); box-shadow: 0 4px 16px rgba(255,255,255,0.2); }
    .profile-initials {
      width: 100%; height: 100%;
      background: linear-gradient(135deg, #ff4d4d, #c0392b);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.78rem; font-weight: 800; color: #fff;
    }

    @media (max-width: 768px) {
      header { padding: 12px 20px; height: auto; flex-wrap: wrap; gap: 10px; justify-content: space-between; }
      .brand-logo-wrap { order: 1; position: static; left: auto; }
      .search-wrap { order: 2; flex: 1; margin: 0 10px; max-width: 250px; align-self: center; }
      .search-wrap input { text-align: left; padding-left: 35px; height: 36px; font-size: 0.78rem; }
      .search-icon { display: flex; left: 10px; }
      .header-actions { order: 3; position: static; right: auto; }
      .bookings-btn::after { display: none; }
      .bookings-btn, .notif-btn { width: 40px; justify-content: center; padding: 0; }
    }

    /* Search dropdown */
    #searchDrop {
      position: absolute; top: calc(100% + 8px); left: 0; width: 100%;
      background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px; max-height: none; overflow: visible;
      display: none; z-index: 2000;
      box-shadow: 0 15px 45px rgba(0,0,0,0.7);
      backdrop-filter: blur(10px);
    }
    .search-result-item {
      display: flex; align-items: center; gap: 10px; padding: 8px 12px;
      cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.06);
      transition: background 0.2s, transform 0.1s;
      text-decoration: none; color: inherit;
      overflow: hidden;
      flex-wrap: nowrap;
      justify-content: space-between;
    }
    .search-result-item:last-child { border-bottom: none; }
    .search-result-item:hover, .search-result-item.selected {
      background: rgba(255,255,255,0.06);
    }
    .search-result-item.selected {
      border-left: 3px solid #ff4d4d;
      padding-left: 9px;
    }
    .search-result-item.highlighted {
      background: rgba(255,77,77,0.12);
    }
    .search-result-item img {
      width: 36px; height: 52px; object-fit: cover; border-radius: 5px;
      border: 1px solid rgba(255,255,255,0.1);
      flex-shrink: 0;
      align-self: flex-start;
    }
    .search-result-info { 
      flex: 1; 
      display: flex; 
      flex-direction: column; 
      gap: 2px; 
      min-width: 0;
      overflow: hidden;
    }
    .search-result-title { 
      font-size: 0.82rem; 
      font-weight: 700; 
      color: #fff; 
      line-height: 1.3;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
    .search-result-meta { 
      font-size: 0.68rem; 
      color: rgba(249,249,249,0.4); 
      line-height: 1.3;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .search-result-btn {
      padding: 6px 12px; background: rgba(255,77,77,0.15);
      border: 1px solid rgba(255,77,77,0.4); border-radius: 6px;
      color: #ff6b6b; font-size: 0.7rem; font-weight: 700;
      transition: all 0.2s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      min-width: 80px;
      text-align: center;
      flex-shrink: 0;
      align-self: center;
    }
    .search-result-item:hover .search-result-btn,
    .search-result-item.selected .search-result-btn {
      background: #ff4d4d; 
      color: #fff; 
      border-color: #ff4d4d;
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(255,77,77,0.3);
    }

    @media (max-width: 768px) {
      header {
        display: flex !important;
        flex-wrap: nowrap !important;
        height: 60px;
        padding: 0 12px;
        gap: 10px;
        justify-content: space-between !important;
      }
      .brand-logo-wrap { 
        flex-shrink: 0;
        position: static !important;
        left: auto !important;
        justify-self: auto;
      }
      .brand-logo {
        height: 36px;
      }
      .header-actions {
        position: static !important;
        right: auto !important;
        justify-self: auto;
      }
      .search-wrap {
        flex: 1;
        max-width: none;
        margin: 0;
        min-width: 0;
        justify-self: auto;
      }
      .search-wrap input { 
        height: 36px; 
        font-size: 0.82rem;
        padding: 8px 10px 8px 32px;
        text-align: left;
      }
      .search-icon {
        left: 9px;
        display: flex;
      }
      .search-icon svg {
        width: 13px;
        height: 13px;
      }
      #searchDrop { 
        width: calc(100vw - 60px);
        left: 50%;
        transform: translateX(-50%);
        max-height: 60vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 8px;
      }
      .search-result-item {
        padding: 8px;
        gap: 6px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        width: 100%;
      }
      .search-result-item img {
        width: 28px;
        height: 42px;
        flex-shrink: 0;
      }
      .search-result-info {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin-right: 4px;
      }
      .search-result-title {
        font-size: 0.72rem;
        line-height: 1.2;
        font-weight: 700;
        color: #fff;
        word-wrap: break-word;
        overflow-wrap: break-word;
      }
      .search-result-meta {
        font-size: 0.62rem;
        color: rgba(249,249,249,0.4);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .search-result-btn {
        padding: 5px 7px;
        font-size: 0.62rem;
        font-weight: 700;
        flex-shrink: 0;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        background: rgba(255,77,77,0.2);
        border: 1px solid rgba(255,77,77,0.5);
        border-radius: 5px;
        color: #ff6b6b;
        min-width: 65px;
        text-align: center;
        transition: all 0.2s ease;
      }
      .header-actions { 
        flex-shrink: 0;
        gap: 6px;
      }
      .bookings-btn {
        padding: 7px 10px;
        font-size: 1rem;
      }
      .bookings-btn::after {
        display: none;
      }
      .notif-btn {
        padding: 7px 10px;
        font-size: 1rem;
      }
      .profile-btn {
        width: 38px;
        height: 38px;
      }
      .profile-initials {
        font-size: 0.75rem;
      }
    }

    @media (max-width: 480px) {
      header {
        padding: 0 10px;
        gap: 8px;
      }
      .brand-logo {
        height: 32px;
      }
      .search-wrap input {
        height: 34px;
        font-size: 0.78rem;
        padding: 6px 8px 6px 30px;
      }
      .search-icon {
        left: 8px;
      }
      .search-icon svg {
      }
      .search-result-item {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 6px;
        width: 100%;
        overflow: hidden;
        flex-wrap: nowrap;
        justify-content: space-between;
        padding: 6px;
        box-sizing: border-box;
      }
      .search-result-item img {
        width: 30px;
        height: 44px;
        flex-shrink: 0;
        object-fit: cover;
        border-radius: 4px;
      }
      .search-result-info {
        flex: 1;
        min-width: 0;
        margin-right: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
        overflow: hidden;
      }
      .search-result-title {
        font-size: 0.75rem;
        font-weight: 700;
        color: #fff;
        line-height: 1.3;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
      }
      .search-result-meta {
        font-size: 0.65rem;
        color: rgba(249,249,249,0.4);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .search-result-btn {
        flex-shrink: 0;
        align-self: center;
        text-align: center;
        padding: 4px 6px;
        font-size: 0.6rem;
        font-weight: 700;
        white-space: nowrap;
        margin: 0;
        box-sizing: border-box;
        min-width: 60px;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        background: rgba(255,77,77,0.25);
        border: 1px solid rgba(255,77,77,0.6);
        border-radius: 4px;
        color: #ff6b6b;
        transition: all 0.2s ease;
      }
      .header-actions {
        gap: 5px;
      }
      .bookings-btn {
        padding: 6px 9px;
        font-size: 0.95rem;
      }
      .notif-btn {
        padding: 6px 9px;
        font-size: 0.95rem;
      }
      .profile-btn {
        width: 36px;
        height: 36px;
      }
      .notif-dropdown {
        width: calc(100vw - 20px);
        right: 10px;
        left: auto;
        transform: none;
      }
    }

    /* Header right actions */
    .header-actions { display: flex; align-items: center; gap: 10px; }

    .hnav-link {
      color: rgba(249,249,249,0.45); text-decoration: none;
      font-size: 0.75rem; font-weight: 500;
      padding: 6px 11px; border-radius: 6px;
      border: 1px solid rgba(255,255,255,0.1);
      transition: all 0.2s; white-space: nowrap;
    }
    .hnav-link:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }

    /* Notification bell */
    .notif-wrap { position: relative; }
    .notif-badge {
      position: absolute; top: -4px; right: -4px;
      background: #ff4d4d; color: #fff;
      font-size: 0.55rem; font-weight: 800;
      min-width: 16px; height: 16px; border-radius: 8px;
      display: none; align-items: center; justify-content: center;
      padding: 0 4px;
    }
    .notif-dropdown {
      display: none; position: absolute; top: calc(100% + 8px); right: 0;
      width: 320px; background: #1a1a1a;
      border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;
      overflow: hidden; box-shadow: 0 12px 40px rgba(0,0,0,0.6); z-index: 2000;
    }
    .notif-dropdown.open { display: block; }
    .notif-header {
      padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.06);
      display: flex; align-items: center; justify-content: space-between;
    }
    .notif-header span { font-size: 0.78rem; font-weight: 700; color: rgba(249,249,249,0.5); letter-spacing: 1px; text-transform: uppercase; }
    .notif-mark-all { font-size: 0.7rem; color: #ff6b6b; cursor: pointer; background: none; border: none; font-family: 'Outfit',sans-serif; font-weight: 600; }
    .notif-list { max-height: 280px; overflow-y: auto; }
    .notif-item { padding: 12px 16px 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.15s; display: flex; gap: 6px; align-items: flex-start; text-decoration: none; color: inherit; }
    .notif-item:hover { background: rgba(255,255,255,0.04); }
    .notif-item.unread { background: rgba(255,77,77,0.05); }
    .notif-dot { width: 6px; height: 6px; border-radius: 50%; background: #ff4d4d; flex-shrink: 0; margin-top: 6px; }
    .notif-dot.read { background: transparent; }
    .notif-item-body { flex: 1; min-width: 0; }
    .notif-item-title { font-size: 0.8rem; font-weight: 700; margin-bottom: 2px; }
    .notif-item-msg { font-size: 0.72rem; color: rgba(249,249,249,0.4); line-height: 1.5; }
    .notif-item-time { font-size: 0.65rem; color: rgba(249,249,249,0.25); margin-top: 4px; }
    .notif-empty { text-align: center; padding: 30px; font-size: 0.82rem; color: rgba(249,249,249,0.2); }
    .notif-footer { padding: 10px 16px; border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; }
    .notif-footer a { font-size: 0.75rem; color: #ff6b6b; text-decoration: none; font-weight: 600; }
    .notif-footer a.dim { color: rgba(249,249,249,0.3); }
      padding: 0 4px; display: none; align-items: center; justify-content: center;
    }

    /* Profile button */
    .profile-btn {
      background: #F9F9F9; border: none; border-radius: 50%;
      width: 36px; height: 36px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; overflow: hidden; padding: 0;
      transition: all 0.3s; 
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
      flex-shrink: 0;
    }
    .profile-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .profile-btn:hover { 
      transform: scale(1.08); 
      box-shadow: 0 4px 16px rgba(255,255,255,0.2);
    }
    .profile-initials {
      width: 100%; height: 100%; border-radius: 50%;
      background: linear-gradient(135deg, #ff4d4d, #c0392b);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.75rem; font-weight: 800; color: #fff;
    }

    /* ══ HERO ════════════════════════════════════════════════════ */
    .hero {
      position: relative; z-index: 10;
      margin-top: 50px;
      min-height: 420px;
      display: flex; align-items: center;
      overflow: hidden;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .hero-bg {
      position: absolute; inset: 0; z-index: 0;
    }
    .hero-bg img {
      width: 100%; height: 100%; object-fit: cover;
      opacity: 0.35;
    }
    .hero-bg::after {
      content: '';
      position: absolute; inset: 0;
      background:
        linear-gradient(to right, #0f0f0f 0%, rgba(15,15,15,0.7) 45%, transparent 100%),
        linear-gradient(to top, #0f0f0f 0%, rgba(15,15,15,0.4) 50%, transparent 100%);
    }

    .hero-content {
      position: relative; z-index: 1;
      width: 95%; max-width: 1440px; margin: 0 auto;
      padding: 64px 0;
      display: flex; justify-content: space-between; align-items: center; gap: 60px;
    }
    .hero-text-wrap { flex: 1; min-width: 0; }

    .top-purchased {
      flex: 0 0 600px;
      background: rgba(255,255,255,0.02);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 14px;
      padding: 18px;
      backdrop-filter: blur(12px);
      max-width: 100%;
      margin-bottom: 0;
    }
    .top-purchased h4 {
      font-size: 0.78rem;
      font-weight: 800;
      color: #ff4d4d;
      text-transform: uppercase;
      letter-spacing: 1.4px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .top-movies-row {
      display: flex;
      flex-wrap: nowrap;
      gap: 12px;
      overflow: visible;
      justify-content: space-between;
    }

    .top-movie-card {
      flex: 0 0 auto;
      width: 100px;
      display: flex;
      flex-direction: column;
      gap: 7px;
      transition: transform 0.2s;
      position: relative;
    }
    .top-movie-card:hover { transform: scale(1.04); }
    .top-movie-poster {
      width: 100%; aspect-ratio: 2/3; border-radius: 10px;
      object-fit: cover; border: 1px solid rgba(255,255,255,0.07);
      box-shadow: 0 7px 20px rgba(0,0,0,0.45);
    }
    .rank-badge {
      position: absolute;
      top: 5px;
      left: 5px;
      width: 24px;
      height: 24px;
      background: linear-gradient(135deg, #ff4d4d, #ff7070);
      color: #fff;
      font-size: 0.75rem;
      font-weight: 900;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      box-shadow: 0 3px 10px rgba(255,77,77,0.5);
      z-index: 10;
    }
    .top-movie-details { display: flex; flex-direction: column; gap: 2px; }
    .top-movie-name {
      font-size: 0.68rem; font-weight: 700; color: #fff;
      display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
    }
    .top-movie-count { font-size: 0.6rem; font-weight: 600; color: rgba(249,249,249,0.4); }
    .top-movie-count strong { color: #ff4d4d; }

    .btn-buy-mini {
      display: block; width: 100%; padding: 5px;
      background: #ff4d4d; color: #fff; text-align: center;
      font-size: 0.62rem; font-weight: 700; border-radius: 5px;
      text-decoration: none; transition: background 0.2s;
    }
    .btn-buy-mini:hover { 
        background: #e03c3c; 
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255,77,77,0.4);
      }

    .hero-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.2);
      color: #ff6b6b; border-radius: 20px;
      padding: 5px 14px; font-size: 0.7rem; font-weight: 700;
      letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 20px;
    }
    .hero h1 {
      font-size: clamp(2.4rem, 6vw, 4.2rem);
      font-weight: 900;
      line-height: 1.1;
      letter-spacing: -2px;
      word-spacing: 4px;
      color: #fff;
      margin-bottom: 24px;
      max-width: 750px;
    }
    .hero h1 .accent {
      background: linear-gradient(90deg, #ff4d4d, #ff7070);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .hero-sub {
      font-size: 1rem; color: rgba(249,249,249,0.55);
      line-height: 1.75; max-width: 500px; margin-bottom: 36px; font-weight: 400;
    }
    .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }

    .btn-primary {
      display: inline-flex; align-items: center; gap: 8px;
      background: #ff4d4d; color: #fff;
      font-family: 'Outfit', sans-serif; font-size: 0.92rem; font-weight: 700;
      padding: 13px 28px; border-radius: 10px; border: none; cursor: pointer;
      transition: all 0.2s; box-shadow: 0 6px 24px rgba(255,77,77,0.35);
      text-decoration: none; scroll-behavior: smooth;
    }
    .btn-primary:hover { background: #e03c3c; transform: translateY(-2px); box-shadow: 0 10px 32px rgba(255,77,77,0.45); }

    .btn-ghost {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(255,255,255,0.07); color: #F9F9F9;
      font-family: 'Outfit', sans-serif; font-size: 0.92rem; font-weight: 600;
      padding: 13px 28px; border-radius: 10px;
      border: 1px solid rgba(255,255,255,0.14); cursor: pointer;
      transition: all 0.2s; text-decoration: none;
    }
    .btn-ghost:hover { background: rgba(255,255,255,0.13); transform: translateY(-2px); }

    /* ══ MOVIES SECTION ════════════════════════════════════════ */
    .outer {
      position: relative; z-index: 10;
      width: 95%; max-width: 1280px; margin: 40px auto 0;
    }

    .section-header {
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 16px; margin-bottom: 28px;
    }
    .section-title { font-size: 1.4rem; font-weight: 800; }

    .tabs {
      display: flex; gap: 6px;
      background: #1a1a1a; border: 1px solid rgba(255,255,255,0.08);
      border-radius: 10px; padding: 5px;
    }
    .tab-btn {
      background: none; border: none;
      font-family: 'Outfit', sans-serif; font-size: 0.82rem; font-weight: 700;
      color: rgba(249,249,249,0.5); padding: 9px 22px; border-radius: 7px;
      cursor: pointer; transition: all 0.2s;
    }
    .tab-btn.active { background: #ff4d4d; color: #fff; box-shadow: 0 4px 14px rgba(255,77,77,0.3); }
    .tab-btn:hover:not(.active) { color: #F9F9F9; background: rgba(255,255,255,0.06); }

    .movies-panel { display: none; }
    .movies-panel.active { display: block; }

    .movies-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
      gap: 16px;
    }

    .movie-card {
      background: #1a1a1a;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 14px; overflow: hidden;
      cursor: pointer;
      transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
      display: flex; flex-direction: column;
    }
    .movie-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 48px rgba(0,0,0,0.6);
      border-color: rgba(255,77,77,0.25);
    }

    .poster-wrap {
      position: relative; aspect-ratio: 2/3;
      overflow: hidden; background: #111;
    }
    .poster-wrap img {
      width: 100%; height: 100%; object-fit: cover;
      display: block; transition: transform 0.5s ease;
    }
    .movie-card:hover .poster-wrap img { transform: scale(1.07); }

    .poster-gradient {
      position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, transparent 50%);
      opacity: 0.7; transition: opacity 0.3s;
    }
    .movie-card:hover .poster-gradient { opacity: 1; }

    .rating-badge {
      position: absolute; top: 9px; right: 9px;
      background: #f59e0b; color: #000;
      font-size: 0.62rem; font-weight: 800;
      padding: 3px 8px; border-radius: 6px; z-index: 2;
    }

    @media (max-width: 768px) {
      .rating-badge { top: 6px; right: 6px; padding: 2px 6px; font-size: 0.58rem; }
    }

    .hover-overlay {
      position: absolute; inset: 0;
      background: rgba(0,0,0,0.55);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 10px; padding: 18px;
      opacity: 0; transition: opacity 0.3s; z-index: 3;
    }
    .movie-card:hover .hover-overlay { opacity: 1; }

    .overlay-btn {
      width: 100%; display: flex; align-items: center; justify-content: center; gap: 7px;
      font-family: 'Outfit', sans-serif; font-size: 0.82rem; font-weight: 700;
      padding: 12px 0; border-radius: 9px; cursor: pointer;
      transition: all 0.2s; border: none;
      min-height: 44px;
    }

    @media (max-width: 768px) {
      .hover-overlay { 
        opacity: 1; 
        background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, transparent 70%); 
        padding: 10px; 
        justify-content: flex-end;
        align-items: stretch;
        gap: 6px;
        flex-direction: column;
      }
      .overlay-btn-primary { 
        padding: 8px 12px; 
        font-size: 0.7rem; 
        min-height: 36px; 
        border-radius: 6px;
        background: #ff4d4d;
        border: 2px solid #ff4d4d;
        color: white;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(255,77,77,0.3);
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .overlay-btn-primary:hover {
        background: #e03c3c;
        border-color: #e03c3c;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(255,77,77,0.4);
      }
      .overlay-btn-ghost { 
        display: flex;
        padding: 8px 12px;
        font-size: 0.7rem;
        min-height: 36px;
        border-radius: 6px;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        color: white;
        font-weight: 600;
        backdrop-filter: blur(4px);
        width: 100%;
        align-items: center;
        justify-content: center;
      }
      .overlay-btn-ghost:hover {
        background: rgba(255,255,255,0.2);
        border-color: rgba(255,255,255,0.3);
      }
    }
    .overlay-btn-ghost {
      background: rgba(255,255,255,0.1); color: #fff;
      border: 1px solid rgba(255,255,255,0.22) !important;
    }
    .overlay-btn-ghost:hover { background: rgba(255,255,255,0.2); }
    .overlay-btn-primary {
      background: #ff4d4d; color: #fff;
      box-shadow: 0 4px 16px rgba(255,77,77,0.35);
    }
    .overlay-btn-primary:hover { background: #e03c3c; }

    .card-info { padding: 14px 16px 16px; flex: 1; }
    .card-title {
      font-size: 0.95rem; font-weight: 800; color: #F9F9F9;
      margin-bottom: 6px;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
      line-height: 1.3; min-height: 2.5em;
    }
    .card-meta {
      display: flex; align-items: center; justify-content: space-between;
      font-size: 0.72rem; color: rgba(249,249,249,0.4);
    }

    /* ══ INNOVATION ROW ═══════════════════════════════════════ */
    .innovation-row {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      width: 95%;
      max-width: 1200px;
      margin: 48px auto 24px;
      position: relative;
      z-index: 10;
      justify-content: center;
    }

    .innov-panel {
      flex: 1 1 calc(33.333% - 20px);
      min-width: 280px;
      max-width: 360px;
      background: #141414;
      border: 1px solid rgba(255,255,255,0.05);
      border-radius: 18px;
      padding: 28px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      min-height: 280px;
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      text-align: left;
    }

    .innov-panel:hover {
      transform: translateY(-6px);
      background: #1a1a1a;
      box-shadow: 0 15px 30px rgba(0,0,0,0.4);
    }

    .innov-mood { border-color: rgba(255,77,77,0.2); }
    .innov-mood::before { background: #ff4d4d; }
    .innov-queue { border-color: rgba(100,180,255,0.2); }
    .innov-queue::before { background: #64b5f6; }

    .innov-icon-box {
      width: 44px;
      height: 44px;
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      margin-bottom: 4px;
    }

    .innov-panel h3 {
      font-size: 1.4rem;
      font-weight: 800;
      color: #fff;
      margin-bottom: 0;
    }

    .innov-panel p {
      font-size: 0.92rem;
      color: rgba(249,249,249,0.5);
      line-height: 1.6;
      margin-bottom: 4px;
    }

    .mood-emojis-preview {
      display: flex;
      justify-content: center;
      gap: 14px;
      font-size: 1.5rem;
      margin: 8px 0;
      opacity: 0.8;
      font-family: "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji", sans-serif;
    }

    .innov-link-btn {
      margin-top: auto;
      background: none !important;
      border: none !important;
      color: #ff4d4d !important;
      font-family: 'Outfit', sans-serif;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      padding: 0 !important;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: gap 0.2s;
      text-decoration: none;
      width: fit-content;
      box-shadow: none !important;
    }
    .mood-emojis-preview span {
      transition: transform 0.2s;
      cursor: default;
      line-height: 1;
      display: inline-block;
    }
    .mood-emojis-preview span:hover {
      transform: scale(1.3) rotate(8deg);
      opacity: 1;
    }

    .innov-data-area {
      background: rgba(255,255,255,0.02);
      border-radius: 12px;
      padding: 12px;
      margin-bottom: 8px;
    }

    @media (max-width: 1024px) {
      .innov-panel { flex-basis: calc(50% - 20px); }
    }
    @media (max-width: 768px) {
      .innovation-row {
        flex-direction: row;
        flex-wrap: nowrap;
        justify-content: flex-start;
        align-items: stretch;
        gap: 16px;
        width: 100%;
        overflow-x: auto;
        padding: 0 20px 16px;
        margin-top: 32px;
        -webkit-overflow-scrolling: touch;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
        -ms-overflow-style: none;
      }
      .innovation-row::-webkit-scrollbar { display: none; }

      .innov-panel {
        flex: 0 0 280px;
        width: 280px;
        max-width: 280px;
        min-width: 280px;
        min-height: auto;
        padding: 24px 20px;
        gap: 14px;
        scroll-snap-align: center;
      }
      .innov-panel h3 { font-size: 1.2rem; margin-bottom: 2px; display: block; }
      .innov-panel p { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; font-size: 0.88rem; line-height: 1.5; color: rgba(249,249,249,0.5); }
      .mood-emojis-preview {
        justify-content: center;
        font-size: 1.4rem;
        gap: 12px;
        margin: 8px 0;
      }
      .innov-icon-box { width: 42px; height: 42px; font-size: 1.2rem; }
      .innov-link-btn {
        font-size: 0.85rem;
        padding: 8px 0 !important;
        min-height: 44px;
        width: 100%;
        justify-content: flex-start;
      }
      .innov-data-area { display: block; width: 100%; margin-top: 4px; }
    }
    @media (max-width: 480px) {
      .innov-panel { flex: 0 0 260px; width: 260px; padding: 20px 18px; }
      .innov-panel h3 { font-size: 1.1rem; }
      .innov-panel p { font-size: 0.82rem; }
      .mood-emojis-preview { font-size: 1.25rem; gap: 10px; }
    }

    /* Queue mini */
    .queue-mini-row { display:flex; align-items:center; justify-content:space-between; background:#222; border-radius:8px; padding:9px 12px; border:1px solid rgba(255,255,255,0.07); font-size:0.78rem; }
    .queue-mini-label { color:rgba(249,249,249,0.55); font-weight:600; }
    .queue-mini-val   { font-weight:800; font-size:0.88rem; color:#64b5f6; }
    .queue-mini-val.green  { color:#81c784; } .queue-mini-val.yellow { color:#FFD54F; } .queue-mini-val.red { color:#ff6b6b; }

    /* Nearby list inside panel */
    .nearby-list-mini { flex:1; overflow-y:auto; max-height:150px; }
    .nearby-list-mini::-webkit-scrollbar { width:3px; }
    .nearby-list-mini::-webkit-scrollbar-thumb { background:rgba(102,187,106,0.3); border-radius:2px; }
    .nearby-item-mini { display:flex; align-items:center; gap:9px; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.05); cursor:pointer; }
    .nearby-item-mini:last-child { border-bottom:none; }
    .nearby-item-mini:hover .nearby-mini-name { color:#81c784; }
    .nearby-mini-rank { font-size:0.68rem; font-weight:800; color:rgba(249,249,249,0.2); width:16px; flex-shrink:0; }
    .nearby-mini-name { font-size:0.82rem; font-weight:700; color:#F9F9F9; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; transition:color 0.15s; }
    .nearby-mini-dist { font-size:0.65rem; font-weight:700; padding:2px 7px; border-radius:8px; flex-shrink:0; }

    /* ══ TRAILER MODAL ════════════════════════════════════════ */
    .trailer-modal {
      display: none; position: fixed; inset: 0; z-index: 9999;
      background: rgba(0,0,0,0.88); align-items: center; justify-content: center;
    }
    .trailer-modal.active { display: flex; }
    .trailer-modal-box {
      position: relative; width: 90%; max-width: 860px;
      border-radius: 14px; overflow: hidden;
      box-shadow: 0 40px 80px rgba(0,0,0,0.8);
    }
    .trailer-modal-box iframe { width: 100%; aspect-ratio: 16/9; border: none; display: block; }
    .trailer-close {
      position: absolute; top: 10px; right: 12px; z-index: 10;
      background: rgba(0,0,0,0.7); border: none; color: #fff;
      font-size: 1.4rem; cursor: pointer; border-radius: 50%;
      width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
    }

    /* ══ MOOD MODAL ══════════════════════════════════════════ */
    .mood-modal-overlay {
      display: none; position: fixed; inset: 0; z-index: 9000;
      background: rgba(0,0,0,0.85); align-items: center; justify-content: center;
      padding: 20px; overflow-y: auto; backdrop-filter: blur(6px);
    }
    .mood-modal-overlay.open { display: flex; }
    .mood-modal {
      background: #141414; border: 1px solid rgba(255,77,77,0.25);
      border-radius: 24px; width: 100%; max-width: 540px;
      animation: moodIn 0.32s cubic-bezier(0.34,1.56,0.64,1); position: relative; margin: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.8);
    }
    @keyframes moodIn { from{opacity:0;transform:translateY(32px) scale(0.95)} to{opacity:1;transform:none} }
    .mood-modal-hd {
      background: linear-gradient(135deg, #2a0808, #1a1a1a);
      padding: 26px 30px 20px; border-bottom: 1px solid rgba(255,255,255,0.06);
      border-radius: 24px 24px 0 0; position: relative;
    }
    .mood-modal-hd h2 { font-size: 1.25rem; font-weight: 800; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
    .mood-modal-hd p  { font-size: 0.82rem; color: rgba(249,249,249,0.45); }
    .mood-close { position: absolute; top: 20px; right: 22px; background: rgba(255,255,255,0.08); border: none; width: 32px; height: 32px; border-radius: 50%; color: rgba(249,249,249,0.5); cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .mood-close:hover { background: rgba(255,77,77,0.2); color: #fff; transform: rotate(90deg); }
    .mood-progress-dots { display: flex; gap: 8px; justify-content: center; padding: 18px 0 0; }
    .md { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.12); transition: all 0.3s cubic-bezier(0.4,0,0.2,1); }
    .md.active { width: 24px; border-radius: 6px; background: #ff4d4d; box-shadow: 0 0 12px rgba(255,77,77,0.4); }
    .md.done { background: rgba(255,77,77,0.4); }
    .mood-step-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 2px; color: #ff4d4d; margin-bottom: 8px; text-transform: uppercase; }
    .mood-step-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 20px; color: #fff; }
    .mood-opts { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
    .mood-opt {
      background: rgba(255,255,255,0.03); border: 2px solid rgba(255,255,255,0.06); border-radius: 16px;
      padding: 18px 12px; text-align: center; cursor: pointer;
      transition: all 0.25s cubic-bezier(0.4,0,0.2,1); font-size: 0.82rem; line-height: 1.5; user-select: none;
      position: relative; overflow: hidden;
    }
    .mood-opt:hover { border-color: rgba(255,77,77,0.4); background: rgba(255,77,77,0.04); transform: translateY(-3px); }
    .mood-opt.sel { border-color: #ff4d4d; background: rgba(255,77,77,0.1); box-shadow: 0 8px 24px rgba(255,77,77,0.15); }
    .mood-opt-emoji { font-size: 2rem; display: block; margin-bottom: 10px; transition: transform 0.3s; }
    .mood-opt:hover .mood-opt-emoji { transform: scale(1.2) rotate(5deg); }
    .mood-opt strong { display: block; font-size: 0.9rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
    .mood-opt small { display: block; font-size: 0.7rem; color: rgba(249,249,249,0.4); line-height: 1.3; }
    .mood-actions { display: flex; gap: 12px; padding: 24px 30px 30px; }
    .mood-btn-back { flex: 1; padding: 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: rgba(249,249,249,0.5); font-family:'Outfit',sans-serif; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
    .mood-btn-back:hover { background: rgba(255,255,255,0.1); color: #fff; }
    .mood-btn-next { flex: 2; padding: 14px; background: #ff4d4d; border: none; border-radius: 12px; color: #fff; font-family:'Outfit',sans-serif; font-size: 0.95rem; font-weight: 800; cursor: pointer; transition: background 0.2s; box-shadow: 0 4px 15px rgba(255,77,77,0.3); }
    .mood-btn-next:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.4); }
    .mood-btn-next:disabled { background: #2a2a2a; color: rgba(249,249,249,0.2); cursor: not-allowed; box-shadow: none; transform: none; }
    .mood-spinner { width: 36px; height: 36px; border: 3px solid rgba(255,77,77,0.2); border-top-color: #ff4d4d; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 14px; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .mood-movie-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .mood-movie-card { background: #1e1e1e; border: 1px solid rgba(255,255,255,0.07); border-radius: 11px; overflow: hidden; cursor: pointer; transition: all 0.2s; }
    .mood-movie-card:hover { border-color: rgba(255,77,77,0.3); transform: translateY(-2px); }
    .mood-movie-poster { width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block; background: #111; }
    .mood-movie-info { padding: 9px; }
    .mood-movie-title { font-size: 0.78rem; font-weight: 700; margin-bottom: 3px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .mood-movie-meta  { font-size: 0.65rem; color: rgba(249,249,249,0.35); }
    .mood-movie-book  { width: calc(100% - 18px); margin: 0 9px 9px; padding: 7px; background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.25); border-radius: 7px; color: #ff6b6b; font-family:'Outfit',sans-serif; font-size: 0.72rem; font-weight: 700; cursor: pointer; transition: all 0.2s; text-align: center; display: block; text-decoration: none; }
    .mood-movie-book:hover { background: #ff4d4d; color: #fff; border-color: #ff4d4d; }

    /* ══ FOOTER ══════════════════════════════════════════════ */
    footer {
      position: relative; z-index: 10;
      background: #141414; border-top: 1px solid rgba(255,255,255,0.06);
      padding: 36px 24px; text-align: center; margin-top: 56px;
    }
    .footer-logo { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 12px; }
    .footer-logo img { height: 36px; filter: invert(1); opacity: 0.7; }
    footer p { color: rgba(249,249,249,0.25); font-size: 0.78rem; }

    /* ══ RESPONSIVE ══════════════════════════════════════════ */
    @media (max-width: 1024px) {
      .hero-content { flex-direction: column; align-items: flex-start; gap: 40px; padding: 48px 0; height: auto; }
      .top-purchased { flex: none; width: 100%; max-width: 100%; padding: 22px; }
      .top-movies-row { flex-wrap: wrap; gap: 14px; }
      .top-movie-card { flex: 1 1 calc(33.333% - 14px); min-width: 140px; }
      .search-wrap { max-width: 180px; }
      .hero h1 { font-size: clamp(2rem, 8vw, 3rem); line-height: 1.2; letter-spacing: -0.5px; }
      .movies-grid { grid-template-columns: repeat(auto-fill, minmax(140px,1fr)); gap: 12px; }
    }
    @media (max-width: 600px) {
      .hero-content { padding: 32px 20px; gap: 32px; }
      .top-purchased { padding: 18px; border-radius: 12px; max-width: 100%; }
      .top-purchased h4 { font-size: 0.85rem; margin-bottom: 14px; }
      .top-movies-row {
        display: flex;
        flex-wrap: nowrap;
        gap: 12px;
        overflow-x: auto;
        padding-bottom: 10px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
      }
      .top-movies-row::-webkit-scrollbar { display: none; }
      .top-movie-card {
        flex: 0 0 140px;
        min-width: 140px;
        gap: 8px;
      }
      .top-movie-name { font-size: 0.78rem; }
      .top-movie-count { font-size: 0.68rem; }
      .btn-buy-mini { 
        font-size: 0.75rem; 
        padding: 8px 6px; 
        font-weight: 700;
        min-height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(255,77,77,0.3);
        transition: all 0.2s ease;
      }
      .hero-actions { flex-direction: row; flex-wrap: nowrap; gap: 8px; }
      .hero-actions a { flex: 1; padding: 10px 12px; font-size: 0.8rem; justify-content: center; }
      .hero h1 { font-size: 1.8rem; line-height: 1.25; letter-spacing: 0; }
    }
    @media (max-width: 480px) {
      .hero h1 { font-size: 1.6rem; }
      .hero-sub { font-size: 0.85rem; }
      
      /* Enhanced mobile movie cards */
      .movie-card {
        border-radius: 12px;
      }
      .hover-overlay {
        opacity: 1 !important;
        background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, transparent 70%) !important;
        padding: 8px !important;
        justify-content: flex-end !important;
        align-items: stretch !important;
        gap: 4px !important;
        flex-direction: column !important;
      }
      .overlay-btn-primary {
        padding: 6px 8px !important;
        font-size: 0.65rem !important;
        font-weight: 700 !important;
        background: #ff4d4d !important;
        border: 2px solid #ff4d4d !important;
        color: white !important;
        border-radius: 6px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.3px !important;
        min-height: 32px !important;
        box-shadow: 0 4px 12px rgba(255,77,77,0.3) !important;
        transition: all 0.2s ease !important;
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
      }
      .overlay-btn-primary:hover {
        background: #e03c3c !important;
        border-color: #e03c3c !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 16px rgba(255,77,77,0.4) !important;
      }
      .overlay-btn-ghost {
        display: flex !important;
        padding: 6px 8px !important;
        font-size: 0.65rem !important;
        min-height: 32px !important;
        border-radius: 6px !important;
        background: rgba(255,255,255,0.1) !important;
        border: 1px solid rgba(255,255,255,0.2) !important;
        color: white !important;
        font-weight: 600 !important;
        backdrop-filter: blur(4px) !important;
        width: 100% !important;
        align-items: center !important;
        justify-content: center !important;
      }
      .overlay-btn-ghost:hover {
        background: rgba(255,255,255,0.2) !important;
        border-color: rgba(255,255,255,0.3) !important;
      }
    }
  </style>
</head>
<body>

<!-- ══ HEADER ════════════════════════════════════════════════ -->
<header id="mainHeader">
  <a href="home.php" class="brand-logo-wrap" title="Peak's Cinema - Home">
    <img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo">
  </a>

  <!-- Search -->
  <div class="search-wrap">
    <span class="search-icon">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </span>
    <input type="text" id="searchInput" placeholder="Search movies..." autocomplete="off">
    <div id="searchDrop"></div>
  </div>

  <!-- Right actions -->
  <div class="header-actions">
    <?php if (isset($_SESSION['user_id'])): ?>
    <button type="button" class="bookings-btn" onclick="window.location.href='my_bookings.php'" title="My Bookings">
      🎟
    </button>
    <div class="notif-wrap">
      <button class="notif-btn" id="notifBtn" onclick="toggleNotif(event)" title="Notifications">
        🔔<span class="notif-badge" id="notifBadge"></span>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
          <span>Notifications</span>
          <button type="button" class="notif-mark-all" onclick="markAllRead()">Mark all read</button>
        </div>
        <div class="notif-list" id="notifList"><div class="notif-empty">Loading...</div></div>
        <div class="notif-footer">
          <a href="notifications_page.php">View All →</a>
          <a href="my_bookings.php" class="dim">My Bookings</a>
        </div>
      </div>
    </div>
    <?php endif; ?>
        <button class="profile-btn" onclick="window.location.href='profile_dashboard.php'" title="Profile Dashboard">
            <?php if (!empty($profile_photo)): ?>
                <img src="<?= htmlspecialchars($profile_photo) ?>?v=<?= time() ?>" alt="Profile" referrerpolicy="no-referrer">
            <?php else: ?>
                <div class="profile-initials"><?= htmlspecialchars($user_initials) ?></div>
            <?php endif; ?>
        </button>
  </div>
</header>

<!-- ══ HERO ══════════════════════════════════════════════════ -->
<section class="hero">
  <div class="hero-bg">
    <img src="movie-background-collage.jpg" alt="Cinema">
  </div>
  <div class="hero-content">
    <div class="hero-text-wrap">
      <div class="hero-badge">🎬 Now Playing</div>
      <h1>
        <?php if (isset($_SESSION['user_id'])): ?>
          Welcome back,<br><span class="accent"><?= htmlspecialchars(explode(' ', $user_name)[0]) ?>!</span>
        <?php else: ?>
          Welcome to<br><span class="accent">Peak's Cinema</span>
        <?php endif; ?>
      </h1>
      <p class="hero-sub">
        Ready for your next cinematic journey? Discover the latest blockbusters and book your favourite seats in seconds.
      </p>
      <div class="hero-actions">
        <a href="#movies-section" class="btn-primary">🎬 Book Tickets</a>
        <a href="queue_tracker.php" class="btn-ghost">⏱ Live Queue Status</a>
      </div>
    </div>

    <div class="top-purchased">
      <h4>🔥 Top 5 Most Booked</h4>
      <div class="top-movies-row">
        <?php foreach ($top_movies as $index => $movie): ?>
          <div class="top-movie-card">
            <div class="rank-badge"><?= $index + 1 ?></div>
            <img src="<?= htmlspecialchars($movie['MoviePoster']) ?>" class="top-movie-poster" alt="<?= htmlspecialchars($movie['MovieName']) ?>">
            <div class="top-movie-details">
              <span class="top-movie-name"><?= htmlspecialchars($movie['MovieName']) ?></span>
              <span class="top-movie-count"><strong><?= $movie['purchase_count'] ?></strong> booked</span>
            </div>
            <a href="movie.php?movie_id=<?= $movie['Movie_ID'] ?>" class="btn-buy-mini">Buy Tickets</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ══ MOVIES ════════════════════════════════════════════════ -->
<div class="outer" id="movies-section">
  <div class="section-header">
    <h2 class="section-title">🎬 Explore Movies</h2>
    <div class="tabs">
      <button class="tab-btn active" data-tab="now-showing">Now Showing</button>
      <button class="tab-btn"        data-tab="coming-soon">Coming Soon</button>
    </div>
  </div>

  <!-- Now Showing -->
  <div class="movies-panel active" id="panel-now-showing">
    <div class="movies-grid">
      <?php if (empty($now_showing_movies)): ?>
        <p style="text-align:center;color:rgba(249,249,249,0.25);padding:60px 0;width:100%;">No movies currently showing.</p>
      <?php else: ?>
        <?php foreach ($now_showing_movies as $movie): 
          $trailerEmbed = '';
          if (!empty($movie['TrailerURL'])) {
              preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $movie['TrailerURL'], $m);
              if (!empty($m[1])) $trailerEmbed = 'https://www.youtube.com/embed/'.$m[1].'?autoplay=1';
          }
        ?>
          <div class="movie-card" onclick="window.location.href='movie.php?movie_id=<?= $movie['Movie_ID'] ?>'">
            <div class="poster-wrap">
               <img src="<?= htmlspecialchars($movie['MoviePoster']) ?>" alt="<?= htmlspecialchars($movie['MovieName']) ?>" loading="lazy">
               <div class="poster-gradient"></div>
              <div class="rating-badge"><?= htmlspecialchars($movie['Rating']) ?></div>
              <div class="hover-overlay">
                <?php if ($trailerEmbed): ?>
                  <button class="overlay-btn overlay-btn-ghost" onclick="event.stopPropagation();openTrailer('<?= $trailerEmbed ?>')">▶ Watch Trailer</button>
                <?php endif; ?>
                <button class="overlay-btn overlay-btn-primary" onclick="event.stopPropagation();window.location.href='movie.php?movie_id=<?= $movie['Movie_ID'] ?>'">🎟 Book Tickets</button>
              </div>
            </div>
            <div class="card-info">
              <div class="card-title"><?= htmlspecialchars($movie['MovieName']) ?></div>
              <div class="card-meta"><span><?= htmlspecialchars($movie['Genre']) ?></span><span><?= htmlspecialchars($movie['Runtime']) ?>m</span></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Coming Soon -->
  <div class="movies-panel" id="panel-coming-soon">
    <div class="movies-grid">
      <?php if (empty($coming_soon_movies)): ?>
        <p style="text-align:center;color:rgba(249,249,249,0.25);padding:60px 0;width:100%;">No upcoming movies at this time.</p>
      <?php else: ?>
        <?php foreach ($coming_soon_movies as $movie): 
          $trailerEmbed = '';
          if (!empty($movie['TrailerURL'])) {
              preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $movie['TrailerURL'], $m);
              if (!empty($m[1])) $trailerEmbed = 'https://www.youtube.com/embed/'.$m[1].'?autoplay=1';
          }
        ?>
          <div class="movie-card" onclick="window.location.href='movie.php?movie_id=<?= $movie['Movie_ID'] ?>'">
             <div class="poster-wrap">
               <img src="<?= htmlspecialchars($movie['MoviePoster']) ?>" alt="<?= htmlspecialchars($movie['MovieName']) ?>" loading="lazy">
               <div class="poster-gradient"></div>
              <div class="rating-badge"><?= htmlspecialchars($movie['Rating']) ?></div>
              <div class="hover-overlay">
                <?php if ($trailerEmbed): ?>
                  <button class="overlay-btn overlay-btn-ghost" onclick="event.stopPropagation();openTrailer('<?= $trailerEmbed ?>')">▶ Watch Trailer</button>
                <?php endif; ?>
                <button class="overlay-btn overlay-btn-primary" onclick="event.stopPropagation();window.location.href='movie.php?movie_id=<?= $movie['Movie_ID'] ?>'">🎟 Details</button>
              </div>
            </div>
            <div class="card-info">
              <div class="card-title"><?= htmlspecialchars($movie['MovieName']) ?></div>
              <div class="card-meta"><span><?= htmlspecialchars($movie['Genre']) ?></span><span><?= htmlspecialchars($movie['Runtime']) ?>m</span></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
  </div>
</div><!-- /outer -->

<!-- ══ PEAK'S EXPERIENCE ══════════════════════════════════════ -->
<div class="outer" style="margin-top: 100px; text-align: center; margin-bottom: 60px;">
  <h2 style="font-size: clamp(2rem, 6vw, 4rem); font-weight: 800; margin-bottom: 24px; color: #fff; letter-spacing: -1px;">
    The <span style="color: #ff4d4d;">Peak's</span> Experience
  </h2>
  <p style="font-size: clamp(1rem, 2vw, 1.25rem); color: rgba(249,249,249,0.5); max-width: 800px; margin: 0 auto; line-height: 1.6; font-weight: 400;">
    We've reimagined what going to the movies should feel like. From discovering the perfect film to skipping the lines effortlessly.
  </p>
</div>

<!-- ══ INNOVATION ROW ════════════════════════════════════════ -->
<div class="innovation-row">

  <!-- 1: Mood Finder -->
  <div class="innov-panel innov-mood">
    <div class="innov-icon-box">🎭</div>
    <h3>Mood Finder</h3>
    <p>Not sure what to watch? Tell us how you're feeling and we'll recommend the perfect cinematic journey for your current mood.</p>
    <div class="mood-emojis-preview">
      <span>😄</span><span>🥺</span><span>🤩</span><span>👻</span><span>💪</span>
    </div>
    <button class="innov-link-btn" onclick="openMoodFinder()">Find My Movie →</button>
  </div>

  <!-- 3: Live Queue -->
  <div class="innov-panel innov-queue">
    <div class="innov-icon-box">⏱</div>
    <h3>Live Queue Tracker</h3>
    <p>Skip the annoying lines. Track real-time wait times for ticketing, concessions, and restrooms right from your phone.</p>
    <div class="innov-data-area">
      <div class="queue-mini-row"><span class="queue-mini-label">⏳ Avg Wait</span><span class="queue-mini-val" id="qAvgWait">— min</span></div>
    </div>
    <a href="queue_tracker.php" class="innov-link-btn">Track Queues →</a>
  </div>

</div>

<!-- ══ FOOTER ════════════════════════════════════════════════ -->
<footer>
  <div class="footer-logo">
    <img src="peakscinematransparent.png" alt="Peak's Cinema">
  </div>
  <p>© 2026 Peak's Cinema. All rights reserved.</p>
</footer>

<!-- ══ TRAILER MODAL ════════════════════════════════════════ -->
<div class="trailer-modal" id="trailerModal">
  <div class="trailer-modal-box">
    <button class="trailer-close" onclick="closeTrailer()">✕</button>
    <iframe id="trailerFrame" src="" allowfullscreen allow="autoplay; encrypted-media"></iframe>
  </div>
</div>

<!-- ══ MOOD FINDER MODAL ════════════════════════════════════ -->
<div class="mood-modal-overlay" id="moodModal" onclick="if(event.target===this)closeMoodFinder()">
  <div class="mood-modal">
    <div class="mood-modal-hd">
      <h2>🎭 Find My Movie</h2>
      <p>3 quick questions to your perfect film</p>
      <button class="mood-close" onclick="closeMoodFinder()">✕</button>
    </div>
    <div class="mood-progress-dots">
      <div class="md active" id="md0"></div>
      <div class="md"        id="md1"></div>
      <div class="md"        id="md2"></div>
    </div>

    <!-- Step 1 -->
    <div id="ms0"><div style="padding:20px 30px 0;">
      <div class="mood-step-label">STEP 1 OF 3</div>
      <div class="mood-step-title">How are you feeling right now?</div>
      <div class="mood-opts">
        <div class="mood-opt" onclick="selMood('happy',this)"><span class="mood-opt-emoji">😄</span><strong>Happy</strong><small>Fun & uplifting</small></div>
        <div class="mood-opt" onclick="selMood('emotional',this)"><span class="mood-opt-emoji">🥺</span><strong>Emotional</strong><small>Deep & moving</small></div>
        <div class="mood-opt" onclick="selMood('thrilled',this)"><span class="mood-opt-emoji">🤩</span><strong>Thrilled</strong><small>Action-packed</small></div>
        <div class="mood-opt" onclick="selMood('scared',this)"><span class="mood-opt-emoji">👻</span><strong>Scared</strong><small>Horror & mystery</small></div>
        <div class="mood-opt" onclick="selMood('inspired',this)"><span class="mood-opt-emoji">💪</span><strong>Inspired</strong><small>Motivating</small></div>
      </div>
    </div>
    <div class="mood-actions">
      <button class="mood-btn-next" id="mn0" onclick="moodStep(1)" disabled>Next Step →</button>
    </div></div>

    <!-- Step 2 -->
    <div id="ms1" style="display:none;"><div style="padding:20px 30px 0;">
      <div class="mood-step-label">STEP 2 OF 3</div>
      <div class="mood-step-title">Who are you watching with?</div>
      <div class="mood-opts">
        <div class="mood-opt" onclick="selComp('alone',this)"><span class="mood-opt-emoji">🧍</span><strong>Solo</strong><small>Personal time</small></div>
        <div class="mood-opt" onclick="selComp('date',this)"><span class="mood-opt-emoji">💑</span><strong>Date Night</strong><small>Romantic vibe</small></div>
        <div class="mood-opt" onclick="selComp('friends',this)"><span class="mood-opt-emoji">👫</span><strong>Friends</strong><small>Group fun</small></div>
        <div class="mood-opt" onclick="selComp('family',this)"><span class="mood-opt-emoji">👨‍👩‍👧</span><strong>Family</strong><small>All ages</small></div>
        <div class="mood-opt" onclick="selComp('kids',this)"><span class="mood-opt-emoji">🧒</span><strong>With Kids</strong><small>Child friendly</small></div>
      </div>
    </div>
    <div class="mood-actions">
      <button class="mood-btn-back" onclick="moodStep(0)">← Back</button>
      <button class="mood-btn-next" id="mn1" onclick="moodStep(2)" disabled>Next Step →</button>
    </div></div>

    <!-- Step 3 -->
    <div id="ms2" style="display:none;"><div style="padding:20px 30px 0;">
      <div class="mood-step-label">STEP 3 OF 3</div>
      <div class="mood-step-title">Preferred viewing duration?</div>
      <div class="mood-opts" style="grid-template-columns:repeat(3,1fr);">
        <div class="mood-opt" onclick="selRun('short',this)"><span class="mood-opt-emoji">⚡</span><strong>Quick</strong><small>Under 2hrs</small></div>
        <div class="mood-opt" onclick="selRun('medium',this)"><span class="mood-opt-emoji">🎬</span><strong>Standard</strong><small>1.5–2.5hrs</small></div>
        <div class="mood-opt" onclick="selRun('any',this)"><span class="mood-opt-emoji">🌙</span><strong>Any</strong><small>No limit</small></div>
      </div>
    </div>
    <div class="mood-actions">
      <button class="mood-btn-back" onclick="moodStep(1)">← Back</button>
      <button class="mood-btn-next" id="mn2" onclick="submitMood()" disabled>🎬 Find My Movie</button>
    </div></div>

    <!-- Loading -->
    <div id="msLoad" style="display:none;text-align:center;padding:40px;">
      <div class="mood-spinner"></div>
      <p style="color:rgba(249,249,249,0.4);font-size:0.85rem;">Finding your perfect movie...</p>
    </div>

    <!-- Results -->
    <div id="msRes" style="display:none;padding:0 26px 24px;">
      <p id="msResHead" style="font-size:0.78rem;color:rgba(249,249,249,0.4);text-align:center;margin-bottom:14px;"></p>
      <div id="msResGrid" class="mood-movie-grid"></div>
      <button onclick="resetMoodFinder()" style="width:100%;margin-top:12px;padding:10px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:rgba(249,249,249,0.5);font-family:'Outfit',sans-serif;font-size:0.82rem;font-weight:600;cursor:pointer;">🔄 Try Again</button>
    </div>
  </div>
</div>

<!-- ══ JAVASCRIPT ════════════════════════════════════════════ -->
<script>
// ── Handle back/forward cache restoration ─────────────────────
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        // Page was restored from bfcache - just reset search state
        console.log('[BFCache] Page restored, resetting search');
        const searchInput = document.getElementById('searchInput');
        const searchDrop = document.getElementById('searchDrop');
        if (searchInput) searchInput.value = '';
        if (searchDrop) {
            searchDrop.style.display = 'none';
            searchDrop.innerHTML = '';
        }
    }
});

// ── PWA Service Worker - DISABLED for now ─────────────────────
// Service worker is causing caching issues, disabling temporarily
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for(let registration of registrations) {
            registration.unregister();
            console.log('SW Unregistered to prevent caching issues');
        }
    });
}

// ── Tab switching ─────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.movies-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
    });
});

// ── Search ────────────────────────────────────────────────────
const searchInput = document.getElementById('searchInput');
const searchDrop  = document.getElementById('searchDrop');
let searchTimer   = null;
let selectedIndex = -1;


function updateSearchHighlight() {
    const items = searchDrop.querySelectorAll('.search-result-item');
    items.forEach((item, index) => {
        if (index === selectedIndex) {
            item.classList.add('selected', 'highlighted');
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('selected', 'highlighted');
        }
    });
}

searchInput.addEventListener('input', function(e) {
    const q = e.target.value.trim();
    clearTimeout(searchTimer);
    selectedIndex = -1;
    if (!q) {
        searchDrop.style.display = 'none';
        searchDrop.innerHTML = '';
        return;
    }
    searchTimer = setTimeout(() => {
        fetch('/PeaksCinema/search_movies.php?q=' + encodeURIComponent(q))
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (!Array.isArray(data) || !data.length) {
                    searchDrop.innerHTML = '<div style="padding:15px; text-align:center; color:rgba(255,255,255,0.3); font-size:0.8rem;">No movies found</div>';
                    searchDrop.style.display = 'block';
                    return;
                }
                searchDrop.innerHTML = data.map(m => `
                    <a href="movie.php?movie_id=${m.id}" class="search-result-item" data-id="${m.id}">
                        <img src="${m.poster}" onerror="this.src='https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=400&q=80'">
                        <div class="search-result-info">
                            <span class="search-result-title">${m.name}</span>
                            <span class="search-result-meta">${m.genre || ''}${m.rating ? ' • ' + m.rating : ''}</span>
                        </div>
                        <div class="search-result-btn">Buy Tickets</div>
                    </a>
                `).join('');
                searchDrop.style.display = 'block';
            }).catch(err => {
                console.error('[Search] Fetch error:', err);
                searchDrop.style.display = 'none';
            });
    }, 200);
});

searchInput.addEventListener('keydown', function(e) {
    const items = searchDrop.querySelectorAll('.search-result-item');
    if (searchDrop.style.display === 'none' || !items.length) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = (selectedIndex + 1) % items.length;
        updateSearchHighlight();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = (selectedIndex - 1 + items.length) % items.length;
        updateSearchHighlight();
    } else if (e.key === 'Enter') {
        if (selectedIndex >= 0) { e.preventDefault(); items[selectedIndex].click(); }
    } else if (e.key === 'Escape') {
        searchDrop.style.display = 'none';
    }
});

document.addEventListener('click', e => {
    if (!searchDrop.contains(e.target) && e.target !== searchInput)
        searchDrop.style.display = 'none';
});

// ── Trailer modal ─────────────────────────────────────────────
function openTrailer(url) {
    document.getElementById('trailerFrame').src = url;
    document.getElementById('trailerModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeTrailer() {
    document.getElementById('trailerFrame').src = '';
    document.getElementById('trailerModal').classList.remove('active');
    document.body.style.overflow = '';
}
document.getElementById('trailerModal').addEventListener('click', e => {
    if (e.target === document.getElementById('trailerModal')) closeTrailer();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeTrailer(); closeMoodFinder(); }
});

// ── Notifications ─────────────────────────────────────────────
function toggleNotif(e) {
    e.stopPropagation();
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('open');
    if (dd.classList.contains('open')) loadNotif();
}

document.addEventListener('click', () => {
    document.getElementById('notifDropdown')?.classList.remove('open');
});

function loadNotif() {
    fetch('notifications_api.php')
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            const list  = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');

            // Guard: unauthorized or unexpected response
            if (!data || data.error) {
                list.innerHTML = '<div class="notif-empty">Please log in again.</div>';
                return;
            }

            // Badge
            if (data.unread > 0) {
                badge.textContent   = data.unread > 9 ? '9+' : data.unread;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }

            // List
            if (!data.notifications?.length) {
                list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
                return;
            }

            list.innerHTML = data.notifications.map(n => `
                <a class="notif-item ${n.IsRead == 0 ? 'unread' : ''}"
                   href="notifications_page.php?id=${n.Notif_ID}"
                   style="text-decoration:none;color:inherit;">
                    <div class="notif-dot ${n.IsRead == 1 ? 'read' : ''}"></div>
                    <div class="notif-item-body">
                        <div class="notif-item-title">${n.Title}</div>
                        <div class="notif-item-msg">${n.Message}</div>
                        <div class="notif-item-time">${n.time_ago ?? 'Just now'}</div>
                    </div>
                </a>
            `).join('');
        })
        .catch(err => {
            console.error('Notification load error:', err);
            const list = document.getElementById('notifList');
            if (list) list.innerHTML = '<div class="notif-empty">Could not load notifications.</div>';
        });
}

function markAllRead() {
    fetch('notifications_api.php?action=mark_read')
        .then(r => r.json())
        .then(() => loadNotif())
        .catch(err => console.error('Mark read error:', err));
}

loadNotif();
setInterval(loadNotif, 60000);

// ── Header hide on scroll ─────────────────────────────────────
(function() {
    const h = document.getElementById('mainHeader');
    let last = window.scrollY, tick = false;
    window.addEventListener('scroll', () => {
        if (!tick) {
            requestAnimationFrame(() => {
                const cur = window.scrollY;
                cur > last && cur > 80 ? h.classList.add('hidden') : h.classList.remove('hidden');
                last = cur; tick = false;
            });
            tick = true;
        }
    }, { passive: true });
})();

// ── Keyboard shortcuts ────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.shiftKey && e.key === 'A') { e.preventDefault(); window.location.href = 'Admin/admin_login.php'; }
    if (e.ctrlKey && e.shiftKey && e.key === 'F') { e.preventDefault(); window.location.href = 'staff_login.php'; }
});

// ── Mood Finder ───────────────────────────────────────────────
let moodSel = null, compSel = null, runSel = null;
function openMoodFinder()  { document.getElementById('moodModal').classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeMoodFinder() { document.getElementById('moodModal').classList.remove('open'); document.body.style.overflow = ''; }
function moodStep(s) {
    [0,1,2].forEach(i => { document.getElementById('ms'+i).style.display = i===s ? 'block' : 'none'; });
    document.getElementById('msLoad').style.display = 'none';
    document.getElementById('msRes').style.display  = 'none';
    ['md0','md1','md2'].forEach((id,i) => {
        const d = document.getElementById(id);
        d.className = 'md' + (i===s?' active':i<s?' done':'');
    });
}
function selMood(v,el) { moodSel=v; document.querySelectorAll('#ms0 .mood-opt').forEach(o=>o.classList.remove('sel')); el.classList.add('sel'); document.getElementById('mn0').disabled=false; }
function selComp(v,el) { compSel=v; document.querySelectorAll('#ms1 .mood-opt').forEach(o=>o.classList.remove('sel')); el.classList.add('sel'); document.getElementById('mn1').disabled=false; }
function selRun(v,el)  { runSel=v;  document.querySelectorAll('#ms2 .mood-opt').forEach(o=>o.classList.remove('sel')); el.classList.add('sel'); document.getElementById('mn2').disabled=false; }
function submitMood() {
    [0,1,2].forEach(i => document.getElementById('ms'+i).style.display = 'none');
    document.getElementById('msLoad').style.display = 'block';
    fetch('mood_finder.php?mood='+encodeURIComponent(moodSel)+'&company='+encodeURIComponent(compSel)+'&runtime='+encodeURIComponent(runSel))
        .then(r=>r.json()).then(data => {
            document.getElementById('msLoad').style.display='none';
            document.getElementById('msRes').style.display='block';
            ['md0','md1','md2'].forEach(id=>{ document.getElementById(id).className='md done'; });
            const grid=document.getElementById('msResGrid'), head=document.getElementById('msResHead');
            if(!data.movies?.length){
                grid.innerHTML='<div style="grid-column:1/-1;text-align:center;color:rgba(249,249,249,0.3);padding:20px;">No matches found — try different answers!</div>';
                head.textContent=''; return;
            }
            head.innerHTML='Found <strong style="color:#ff4d4d;">'+data.movies.length+' movie'+(data.movies.length>1?'s':'')+'</strong> matching your vibe';
            grid.innerHTML=data.movies.map(m=>`
                <div class="mood-movie-card" onclick="window.location.href='movie.php?movie_id=${m.Movie_ID}'">
                    <img class="mood-movie-poster" src="${m.MoviePoster}" onerror="this.style.background='#222'">
                    <div class="mood-movie-info">
                        <div class="mood-movie-title">${m.MovieName}</div>
                        <div class="mood-movie-meta">${(m.Genre||'').split(',')[0]} · ${m.Rating||''}</div>
                    </div>
                    <a class="mood-movie-book" href="movie.php?movie_id=${m.Movie_ID}">🎟 Book Now</a>
                </div>
            `).join('');
        }).catch(()=>{
            document.getElementById('msLoad').style.display='none';
            document.getElementById('msRes').style.display='block';
            document.getElementById('msResGrid').innerHTML='<div style="grid-column:1/-1;text-align:center;color:rgba(249,249,249,0.3);padding:20px;">⚠️ Something went wrong.</div>';
        });
}
function resetMoodFinder() {
    moodSel=compSel=runSel=null;
    document.querySelectorAll('.mood-opt').forEach(o=>o.classList.remove('sel'));
    ['mn0','mn1','mn2'].forEach(id=>document.getElementById(id).disabled=true);
    document.getElementById('msRes').style.display='none';
    moodStep(0);
}

// ── Queue mini stats ──────────────────────────────────────────
function loadQueueMini() {
    fetch('queue_api.php?summary=1').then(r=>r.json()).then(data=>{
        const avg=document.getElementById('qAvgWait');
        const open=document.getElementById('qOpenAreas');
        const max=document.getElementById('qMaxWait');
        if(avg)  avg.textContent  = (data.avg_wait??'—')+(data.avg_wait!=null?' min':'');
        if(open) open.textContent = data.open_areas??'—';
        if(max)  {
            max.textContent = (data.max_wait??'—')+(data.max_wait!=null?' min':'');
            max.className   = 'queue-mini-val '+(data.max_wait>20?'red':data.max_wait>10?'yellow':'green');
        }
    }).catch(()=>{});
}
loadQueueMini();
setInterval(loadQueueMini, 60000);
</script>
</body>
</html>
