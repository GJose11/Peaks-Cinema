<?php
/**
 * staff_guard.php — include at TOP of every staff page
 * Place in: D:\xampp\htdocs\PeaksCinema\
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: " . (strpos($_SERVER['PHP_SELF'], '/Admin/') !== false ? '../' : '') . "staff_login.php");
    exit;
}