<?php
// Minimal test - no DB, just echo JSON
header('Content-Type: application/json');
echo json_encode([
    ['id' => 1, 'name' => 'Test Movie', 'poster' => '', 'genre' => 'Action', 'rating' => 'PG']
]);
