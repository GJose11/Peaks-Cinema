<?php
date_default_timezone_set('Asia/Manila');
include __DIR__ . '/../peakscinemas_database.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    } else {
        echo "[OK] $msg\n";
    }
}

$r = $conn->query("SELECT Ticket_ID FROM ticket ORDER BY Ticket_ID DESC LIMIT 1");
$row = $r ? $r->fetch_assoc() : null;
assert_true(!empty($row), "Ticket table has at least one row");
$testTicketId = (int)$row['Ticket_ID'];
$conn->query("DELETE FROM reminders_sent WHERE Ticket_ID = $testTicketId");

$conn->begin_transaction();
$ins = $conn->prepare("INSERT IGNORE INTO reminders_sent (Ticket_ID) VALUES (?)");
$ins->bind_param("i", $testTicketId);
$ok = $ins->execute();
if ($ok) {
    $conn->commit();
} else {
    $conn->rollback();
}

assert_true($ok, "Insert executed");
$check = $conn->prepare("SELECT COUNT(*) AS c FROM reminders_sent WHERE Ticket_ID = ?");
$check->bind_param("i", $testTicketId);
$check->execute();
$res = $check->get_result()->fetch_assoc();
assert_true(($res['c'] ?? 0) == 1, "Reminder record persisted");

$conn->query("DELETE FROM reminders_sent WHERE Ticket_ID = $testTicketId");
echo "All tests passed.\n";
?>
