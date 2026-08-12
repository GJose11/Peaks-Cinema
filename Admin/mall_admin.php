<?php 
    include("peakscinemas_database.php");
    
    $Mall_ID = filter_input(INPUT_GET, 'mall_id', FILTER_VALIDATE_INT);

    if ($Mall_ID) {
        $stmt = $conn -> prepare("SELECT * FROM mall WHERE Mall_ID = ?");
        $stmt -> execute([$Mall_ID]);
        $mallDetails = ($stmt -> get_result()) -> fetch_assoc();

        $theater_stmt = $conn -> prepare("SELECT * FROM theater WHERE Mall_ID = ?");
        $theater_stmt -> execute([$Mall_ID]);
        $theatersInMall = $theater_stmt -> get_result();
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <style>
            body #mallDetailsSection {
                width:35%;
                border: 2px solid black;
                padding: 20px;
            }
        </style>
    </head>
    <body>
        <header>
            <nav>
                <a href="dashboard.php" target="_self">Dashboard</a>
                <a href="malls_selection_admin.php" target="_self">Malls</a>
                <a href="movie_upload.php" target="_self">Movie Upload</a>
                <a href="food_admin.php" target="_self">Food & Drinks</a>
                <a href="theater_upload.php" target="_self">Theater Upload</a>
                <a href="mall_upload.php" target="_self">Mall Upload</a>
            </nav>            
        </header>
        <main>
            <?php if($mallDetails): ?>
                    <div>Location: <?= htmlspecialchars($mallDetails['Location']) ?> </div><br>
                    <div> Theaters in <?= htmlspecialchars($mallDetails['MallName']) ?>:</div><br>

                    <?php while ($row = $theatersInMall -> fetch_assoc()): ?>
                        <a href = "theater_admin.php?mall_id=<?= urlencode($mallDetails['Mall_ID']) ?>&theater_id=<?= urlencode($row['Theater_ID']) ?>" >
                            <?= htmlspecialchars($row['TheaterName']) ?>
                        </a><br>
                    <?php endwhile; ?>
            <?php endif; ?>
            
        </main>
    </body>
</html>
