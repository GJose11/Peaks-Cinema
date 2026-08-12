<?php
/**
 * mood_finder.php
 * Returns movies matching the user's mood, company, and runtime preference.
 * Called via AJAX from home.php
 */
date_default_timezone_set('Asia/Manila');
include("peakscinemas_database.php");
header('Content-Type: application/json');

$mood    = trim($_GET['mood']    ?? '');
$company = trim($_GET['company'] ?? '');
$runtime = trim($_GET['runtime'] ?? '');

// ── Mood → Genre keyword mapping ────────────────────────────────
// Each mood maps to genre keywords (case-insensitive LIKE match)
$moodMap = [
    'happy'    => ['Comedy', 'Animation', 'Family', 'Musical', 'Adventure'],
    'emotional'=> ['Drama', 'Romance', 'Biography'],
    'thrilled' => ['Action', 'Thriller', 'Superhero', 'Sci-Fi', 'Science Fiction'],
    'scared'   => ['Horror', 'Mystery', 'Psychological'],
    'inspired' => ['Biography', 'Superhero', 'Adventure', 'Sci-Fi', 'Documentary'],
];

// ── Company → Rating filter ──────────────────────────────────────
$ratingMap = [
    'alone'   => ['G','PG','R-13','R-16','R-18'],
    'date'    => ['G','PG','R-13','R-16','R-18'],
    'friends' => ['G','PG','R-13','R-16','R-18'],
    'family'  => ['G','PG'],
    'kids'    => ['G'],
];

// ── Runtime → minutes filter ─────────────────────────────────────
$runtimeMap = [
    'short'  => [0,   110],
    'medium' => [90,  150],
    'any'    => [0,  9999],
];

// Validate inputs
if (!array_key_exists($mood, $moodMap)) {
    echo json_encode(['error' => 'Invalid mood']); exit;
}

$genres      = $moodMap[$mood];
$ratings     = $ratingMap[$company]   ?? $ratingMap['alone'];
$runtimeRange= $runtimeMap[$runtime]  ?? $runtimeMap['any'];

// Build genre LIKE conditions
$genreConditions = implode(' OR ', array_fill(0, count($genres), 'Genre LIKE ?'));

// Build rating IN placeholders
$ratingPlaceholders = implode(',', array_fill(0, count($ratings), '?'));

$sql = "
    SELECT Movie_ID, MovieName, MoviePoster, Genre, Rating, Runtime, Price, TrailerURL, MovieAvailability
    FROM movie
    WHERE MovieAvailability = 'Now Showing'
      AND ($genreConditions)
      AND Rating IN ($ratingPlaceholders)
      AND Runtime BETWEEN ? AND ?
    ORDER BY RAND()
    LIMIT 4
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Query failed: ' . $conn->error]); exit;
}

// Build bind types and params
$types  = str_repeat('s', count($genres)) . str_repeat('s', count($ratings)) . 'ii';
$params = [];
foreach ($genres  as $g) $params[] = "%$g%";
foreach ($ratings as $r) $params[] = $r;
$params[] = $runtimeRange[0];
$params[] = $runtimeRange[1];

$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// If no exact match, relax to just genre (any rating, any runtime)
if (empty($rows)) {
    $sql2 = "
        SELECT Movie_ID, MovieName, MoviePoster, Genre, Rating, Runtime, Price, TrailerURL, MovieAvailability
        FROM movie
        WHERE MovieAvailability = 'Now Showing'
          AND ($genreConditions)
        ORDER BY RAND()
        LIMIT 4
    ";
    $stmt2 = $conn->prepare($sql2);
    $types2 = str_repeat('s', count($genres));
    $params2 = [];
    foreach ($genres as $g) $params2[] = "%$g%";
    $stmt2->bind_param($types2, ...$params2);
    $stmt2->execute();
    $rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
}

// If STILL nothing, return any Now Showing movie
if (empty($rows)) {
    $fallback = $conn->query("SELECT Movie_ID, MovieName, MoviePoster, Genre, Rating, Runtime, Price, TrailerURL, MovieAvailability FROM movie WHERE MovieAvailability='Now Showing' ORDER BY RAND() LIMIT 4");
    $rows = $fallback ? $fallback->fetch_all(MYSQLI_ASSOC) : [];
}

// Mood labels for the response header
$moodLabels = [
    'happy'     => '😄 Happy & Fun',
    'emotional' => '🥺 Emotional',
    'thrilled'  => '🤩 Thrilled',
    'scared'    => '👻 Scared',
    'inspired'  => '💪 Inspired',
];

$companyLabels = [
    'alone'   => 'Solo',
    'date'    => 'Date Night',
    'friends' => 'With Friends',
    'family'  => 'Family',
    'kids'    => 'With Kids',
];

echo json_encode([
    'movies'        => $rows,
    'mood_label'    => $moodLabels[$mood]    ?? $mood,
    'company_label' => $companyLabels[$company] ?? $company,
    'count'         => count($rows),
]);
