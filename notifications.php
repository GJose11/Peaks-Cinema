<?php
session_start();
include("peakscinemas_database.php"); // Ensure this path is correct
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? 'fetch';

// ── Mark all as read ──────────────────────────────────────────
if ($action === 'mark_read') {
    $conn->query("UPDATE notifications SET IsRead = 1 WHERE Customer_ID = $uid");
    echo json_encode(['ok' => true]);
    exit;
}

// ── Mark one as read ─────────────────────────────────────────
if ($action === 'mark_one' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE notifications SET IsRead = 1 WHERE Notif_ID = $id AND Customer_ID = $uid");
    echo json_encode(['ok' => true]);
    exit;
}

// ── Fetch notifications ───────────────────────────────────────
$stmt = $conn->prepare("
    SELECT Notif_ID, Title, Message, Type, IsRead, Created_At
    FROM notifications
    WHERE Customer_ID = ?
    ORDER BY Created_At DESC
    LIMIT 30
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unread = 0;
foreach ($rows as &$r) {
    if (!$r['IsRead']) $unread++;
    $ts   = strtotime($r['Created_At']);
    $diff = time() - $ts;
    if      ($diff < 60)     $r['time_ago'] = 'Just now';
    elseif  ($diff < 3600)   $r['time_ago'] = floor($diff / 60)   . 'm ago';
    elseif  ($diff < 86400)  $r['time_ago'] = floor($diff / 3600) . 'h ago';
    elseif  ($diff < 604800) $r['time_ago'] = floor($diff / 86400). 'd ago';
    else                     $r['time_ago'] = date('M d', $ts);
}

echo json_encode(['notifications' => $rows, 'unread' => $unread]);
