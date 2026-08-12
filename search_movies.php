<?php
header('Content-Type: application/json');

include_once __DIR__ . '/peakscinemas_database.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($q)) {
    echo json_encode([]);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

$movies = [];
$like = '%' . $conn->real_escape_string($q) . '%';
$sql = "SELECT Movie_ID, MovieName, MoviePoster, Genre, Rating FROM movie WHERE MovieName LIKE '$like' LIMIT 10";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $poster = $row['MoviePoster'] ?? '';
        $poster = ltrim($poster, '/');
        $poster = preg_replace('#^PeaksCinema/#i', '', $poster);
        
        $movies[] = [
            'id'     => (int)$row['Movie_ID'],
            'name'   => $row['MovieName'],
            'poster' => $poster,
            'genre'  => $row['Genre']  ?? '',
            'rating' => $row['Rating'] ?? '',
        ];
    }
}

echo json_encode($movies);
