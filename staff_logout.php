<?php
session_start();
unset($_SESSION['staff_logged_in'], $_SESSION['staff_name'], $_SESSION['staff_email'], $_SESSION['staff_id']);
session_destroy();
header("Location: staff_login.php"); exit;
