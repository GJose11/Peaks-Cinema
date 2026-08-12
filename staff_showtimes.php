<?php
date_default_timezone_set('Asia/Manila');

// Guard inline (can't redirect mid-JSON response)
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

include(__DIR__ . "/peakscinemas_database.php");

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . ($conn->connect_error ?? 'no connection')]); exit;
}

$movie_id = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
if (!$movie_id) { echo json_encode(['error' => 'Invalid movie ID']); exit; }

$stmt = $conn->prepare("
    SELECT t.TimeSlot_ID, t.Date, t.StartTime, t.ScreeningType,
           ml.MallName, th.TheaterName
    FROM timeslot t
    JOIN theater  th ON th.Theater_ID = t.Theater_ID
    JOIN mall     ml ON ml.Mall_ID    = th.Mall_ID
    WHERE t.Movie_ID = ?
      AND (
          t.Date > CURDATE()
          OR (t.Date = CURDATE() AND t.StartTime > CURTIME())
      )
    ORDER BY t.Date ASC, t.StartTime ASC
");

if (!$stmt) {
    echo json_encode(['error' => 'Query prepare failed: ' . $conn->error]); exit;
}

$stmt->bind_param("i", $movie_id);
$stmt->execute();
$result = $stmt->get_result();

$slots    = [];
$today    = strtotime(date('Y-m-d'));
$tomorrow = strtotime(date('Y-m-d', strtotime('+1 day')));

while ($row = $result->fetch_assoc()) {
    $ts = strtotime($row['Date']);
    if ($ts === $today)        $dateLabel = 'TODAY — ' . date('F d', $ts);
    elseif ($ts === $tomorrow) $dateLabel = 'TOMORROW — ' . date('F d', $ts);
    else                       $dateLabel = strtoupper(date('D', $ts)) . ' — ' . date('F d', $ts);

    $slotTime = strtotime($row['Date'] . ' ' . $row['StartTime']);
    $slots[] = [
        'timeslot_id' => $row['TimeSlot_ID'],
        'date'        => $row['Date'],
        'date_label'  => $dateLabel,
        'time'        => date('g:i A', strtotime($row['StartTime'])),
        'type'        => $row['ScreeningType'],
        'mall'        => $row['MallName'],
        'theater'     => $row['TheaterName'],
        'disabled'    => ($slotTime - time()) < 1800,
    ];
}

echo json_encode($slots);
