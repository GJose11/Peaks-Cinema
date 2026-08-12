<?php
/**
 * queue_api.php
 * Returns queue summary stats for the home page panel.
 * GET ?summary=1 → { avg_wait, max_wait, open_areas, total_areas }
 */
error_reporting(0);
ob_start();
include("peakscinemas_database.php");
ob_end_clean();
header('Content-Type: application/json');

// Check table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'cinema_queue'")->num_rows > 0;
if (!$tableExists) {
    echo json_encode(['avg_wait'=>null,'max_wait'=>null,'open_areas'=>0,'total_areas'=>0,'error'=>'Table not created yet']);
    exit;
}

$res = $conn->query("SELECT current_wait_mins, status FROM cinema_queue");
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

if (empty($rows)) {
    echo json_encode(['avg_wait'=>null,'max_wait'=>null,'open_areas'=>0,'total_areas'=>0]);
    exit;
}

$open   = array_filter($rows, fn($r) => $r['status'] === 'open');
$waits  = array_column($rows, 'current_wait_mins');

echo json_encode([
    'avg_wait'    => count($waits) > 0 ? round(array_sum($waits)/count($waits)) : null,
    'max_wait'    => count($waits) > 0 ? max($waits) : null,
    'open_areas'  => count($open),
    'total_areas' => count($rows),
]);
