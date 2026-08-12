<?php
include("peakscinemas_database.php");
$res = $conn->query("SELECT Movie_ID, MovieName, MoviePoster FROM movie LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['Movie_ID'] . " | Name: " . $row['MovieName'] . " | Poster: " . $row['MoviePoster'] . PHP_EOL;
}
?>
