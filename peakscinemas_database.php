<?php
    function ensureCustomerPhoneNumberSchema(mysqli $conn): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $columnResult = $conn->query("SHOW COLUMNS FROM customer LIKE 'PhoneNumber'");
        if (!$columnResult || $columnResult->num_rows === 0) {
            return;
        }

        $column = $columnResult->fetch_assoc();
        if (!preg_match('/varchar\((\d+)\)/i', (string) ($column['Type'] ?? ''), $matches)) {
            return;
        }

        if ((int) $matches[1] >= 15) {
            return;
        }

        $nullSql = strtoupper((string) ($column['Null'] ?? 'YES')) === 'YES' ? 'NULL' : 'NOT NULL';
        $defaultSql = '';
        if (array_key_exists('Default', $column) && $column['Default'] !== null) {
            $defaultSql = " DEFAULT '" . $conn->real_escape_string((string) $column['Default']) . "'";
        }

        $conn->query("ALTER TABLE customer MODIFY PhoneNumber VARCHAR(15) {$nullSql}{$defaultSql}");
    }

    $servername = "localhost";
    $user = "root";
    $pass = "";
    $db = "peakscinemadb";

    try {        
        $conn = mysqli_connect($servername, $user, $pass, $db);
        $conn->query("SET time_zone = '+08:00'");
        ensureCustomerPhoneNumberSchema($conn);
    }
    catch(mysqli_sql_exception) {
        echo "Could not connect to the database. Please try again or message the database administrator.";
    }
?>
