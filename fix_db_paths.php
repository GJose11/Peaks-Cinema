<?php
include("peakscinemas_database.php");
$conn->query("UPDATE movie SET MoviePoster = REPLACE(MoviePoster, 'PeaksCinema/', '') WHERE MoviePoster LIKE 'PeaksCinema/%'");
echo "Database updated. " . $conn->affected_rows . " rows affected." . PHP_EOL;
?>
