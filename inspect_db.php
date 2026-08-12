<?php
include "peakscinemas_database.php";

$tables = $conn->query("SHOW TABLES");
while ($row = $tables->fetch_array()) {
    $table = $row[0];
    echo "--- Table: $table ---\n";
    $columns = $conn->query("DESCRIBE `$table` ");
    while ($col = $columns->fetch_assoc()) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']} - {$col['Default']} - {$col['Extra']}\n";
    }
    echo "\n";
}
?>