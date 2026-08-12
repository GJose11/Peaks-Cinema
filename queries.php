<?php 
    include("peakscinemas_database.php");

    $q = $_GET['q'] ?? '';
    $timeslotDate = $_GET['dateSelection'] ?? '';
    $Movie_ID = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
    $Mall_ID = filter_input(INPUT_GET, 'mall_id', FILTER_VALIDATE_INT);
    $TheaterType = filter_input(INPUT_GET, 'type');

    if ($q == 'mall') {
        $stmt = $conn->prepare("SELECT DISTINCT mall.Mall_ID, mall.MallName 
                                FROM timeslot INNER JOIN theater 
                                ON timeslot.Theater_ID=theater.Theater_ID 
                                INNER JOIN mall 
                                ON theater.Mall_ID=mall.Mall_ID
                                WHERE Movie_ID = ? AND timeslot.Date = ?");
        $stmt->bind_param("is", $Movie_ID, $timeslotDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo "No malls available with this date. Please try another date.";
        } else {
            echo '
                <option value="">Select a Mall</option>
            ';

            while($row = $result -> fetch_assoc()) {
                echo "<option value=" . $row['Mall_ID'] . ">" . $row['MallName'] . "</option>";
            }
        }        
    }
    elseif ($q == 'theater') {
        $stmt = $conn->prepare("SELECT DISTINCT theater.Theater_ID, theater.TheaterType 
                                FROM timeslot INNER JOIN theater 
                                ON timeslot.Theater_ID=theater.Theater_ID 
                                INNER JOIN mall 
                                ON theater.Mall_ID=mall.Mall_ID
                                WHERE Movie_ID = ? AND theater.Mall_ID = ? AND timeslot.Date = ?");
        $stmt->bind_param("iis", $Movie_ID, $Mall_ID, $timeslotDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo '
            <option value="">Select a Theater Type</option>
        ';

        while($row = $result -> fetch_assoc()) {
            echo '<option value="' . $row['TheaterType'] . '">' . $row['TheaterType'] . '</option>';
        }
    }
    elseif ($q == 'timeslot') {
        $stmt = $conn->prepare("SELECT DISTINCT timeslot.TimeSlot_ID, timeslot.StartTime, timeslot.Date
                                FROM timeslot INNER JOIN theater 
                                ON timeslot.Theater_ID=theater.Theater_ID 
                                INNER JOIN mall 
                                ON theater.Mall_ID=mall.Mall_ID
                                WHERE Movie_ID = ? AND theater.Mall_ID = ? AND theater.TheaterType = ? AND timeslot.Date = ?
                                ORDER BY timeslot.StartTime ASC");
        $stmt->bind_param("iiss", $Movie_ID, $Mall_ID, $TheaterType, $timeslotDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo '<option value="">Select a Time</option>';

        $now = time();
        while ($row = $result->fetch_assoc()) {
            $slotTime   = strtotime($row['Date'] . ' ' . $row['StartTime']);
            $minsLeft   = ($slotTime - $now) / 60;
            $isDisabled = $minsLeft < 30; // disable if less than 30 min away or already past

            // Format time to 12-hour
            $parts = explode(':', $row['StartTime']);
            $h = (int)$parts[0]; $m = str_pad((int)($parts[1] ?? 0), 2, '0', STR_PAD_LEFT);
            $label = ($h % 12 ?: 12) . ':' . $m . ' ' . ($h >= 12 ? 'PM' : 'AM');
            if ($isDisabled) $label .= ' — Unavailable';

            $disabled = $isDisabled ? ' disabled' : '';
            $style    = $isDisabled ? ' style="color:#888;"' : '';
            echo "<option value=\"{$row['TimeSlot_ID']}\"{$disabled}{$style}>{$label}</option>";
        }
    }
    
?>