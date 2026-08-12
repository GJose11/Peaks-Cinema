<?php
    include("peakscinemas_database.php");
    session_start();
    $profile_link = "personal_info_form.php";
      
    $Movie_ID = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
    $Mall_ID = filter_input(INPUT_GET, 'mall_id', FILTER_VALIDATE_INT);
    $Date = filter_input(INPUT_GET, 'date');

    if (!$Movie_ID || !$Mall_ID || !$Date) {
        header("Location: home.php");
        exit;
    }

    $movie_stmt = $conn -> prepare("SELECT * FROM movie WHERE Movie_ID = ?");
    $movie_stmt -> bind_param("i", $Movie_ID);
    $movie_stmt -> execute();
    $movieDetails = ($movie_stmt -> get_result()) -> fetch_assoc();

    $mall_stmt = $conn -> prepare("SELECT * FROM mall WHERE Mall_ID = ?");
    $mall_stmt -> bind_param("i", $Mall_ID);
    $mall_stmt -> execute();
    $mallDetails = ($mall_stmt -> get_result()) -> fetch_assoc();

    $theater_stmt = $conn -> prepare("SELECT DISTINCT theater.Theater_ID, theater.TheaterName FROM theater
                                      INNER JOIN timeslot ON theater.Theater_ID=timeslot.Theater_ID
                                      WHERE timeslot.Date = ? AND timeslot.Movie_ID = ? AND theater.Mall_ID = ?");
    $theater_stmt -> bind_param("sii", $Date, $Movie_ID, $Mall_ID);
    $theater_stmt -> execute();
    $theaterResult = $theater_stmt -> get_result();

    $theaterData = [];

    while ($theater = $theaterResult -> fetch_assoc()) {
        $Theater_ID = $theater['Theater_ID'];

        $timeslot_stmt = $conn -> prepare("SELECT TimeSlot_ID, ScreeningType, StartTime FROM timeslot
                                           WHERE Theater_ID = ? AND Date = ?");
        $timeslot_stmt -> bind_param("is", $Theater_ID, $Date);
        $timeslot_stmt -> execute();
        $timeslot_result = $timeslot_stmt -> get_result();

        $TimeslotDetails = [];
        while($time = $timeslot_result -> fetch_assoc()) {
            $TimeslotDetails[$time['TimeSlot_ID']] = $time['ScreeningType'] . ' - ' . date("g:i A", strtotime($time['StartTime']));
        }
        
        $theater['Timeslots'] = $TimeslotDetails;
        $theaterData[] = $theater;
    }
    
    if (!$movieDetails || !$mallDetails) {
        header("Location: home.php");
        exit;
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

           header {
                background-color: #6A7F3F;
                background: linear-gradient(90deg,rgba(106, 127, 63, 1) 0%, rgba(74, 106, 90, 1) 100%);
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 30px;
                border-bottom: 1px solid #ffffffff;
            }

            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                color: white;
                display: flex;
                flex-direction: column;
                min-height: 100vh;
                background: #5C4033;
                background: linear-gradient(360deg, rgba(92, 64, 51, 1) 0%, rgba(51, 17, 0, 1) 100%);
      
                }

            body::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 11%, transparent 100%);
                pointer-events: none;
                }
            
            .logo img {
               height: 50px;
               width: auto;
               cursor: pointer;
               transition: transform 0.2s ease;
            }

            .logo img:hover {
               transform: scale(1.05);
            }

            .profile-btn {
                background-color: #4b4b4b;
                background: linear-gradient(90deg,rgba(75, 75, 75, 1) 0%, rgba(43, 43, 43, 1) 100%);
                border: 1px solid #CCCCCC;
                border-radius: 50%;
                width: 45px;
                height: 45px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-left: auto;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }

            .profile-btn svg {
                width: 24px;
                height: 24px;
                transition: transform 0.3s ease;
            }

            .profile-btn:hover {
                background: #ffffff;
                background: linear-gradient(90deg,rgba(255, 255, 255, 1) 0%, rgba(204, 204, 204, 1) 100%);
                transform: scale(1.1);
                border: 1px solid #4b4b4b;
                box-shadow: 0 0 8px rgba(255,255,255,0.3);
            }

            .profile-btn:hover svg {
                transform: scale(1.05);
            }

            nav {
                display: flex;
                gap: 10px;
            }

            nav a {
                background-color: #4b4b4b;
                color: white;
                text-decoration: none;
                padding: 8px 15px;
                border-radius: 10px;
                border: 1px solid #a3c2b1;
                transition: 0.3s;
            }

            nav a:hover,
            nav a.active {
                background-color: #a3c2b1;
                color: #2b2b2b;
            }

            .main-container {
                background-color: #a3c2b1;
                margin: 40px auto;
                width: 85%;
                padding: 30px;
                border-radius: 10px;
                border: 3px solid #4b4b4b;
            }

            .tabs {
                display: flex;
                justify-content: center;
                margin-bottom: 15px;
            }

            .tab {
                background-color: #4b4b4b;
                color: white;
                padding: 10px 25px;
                border-radius: 10px 10px 0 0;
                margin: 0 3px;
                cursor: pointer;
                font-weight: bold;
                border: 1px solid #4b4b4b;
            }

            .tab.active {
                background-color: #a3c2b1;
                color: #2b2b2b;
                border-bottom: none;
            }

            /* Top Link Stuff */

            #topLinkSection {
                width: 50%;
                margin: 10px auto;
                display: flex;
                align-items: center;
                gap: 20px;
                border-radius: 10px;
            }

            .topLink {
                border-radius: 10px;
                width: auto;
                align-items: center;
                text-align: center;
            }

            .topLink a {
                color: #FFFFFF;
                background-color: #4b4b4b;
                background: linear-gradient(90deg,rgba(75, 75, 75, 1) 0%, rgba(43, 43, 43, 1) 100%);
                text-decoration: none;
                padding: 8px 15px;
                border-radius: 10px;
                border: 1px solid #CCCCCC;
                transition: 0.3s;
                margin-top: 10px;
                font-size: 18px;
                font-weight: 900;
                font-family: 'Poppins', sans-serif;
                font-weight: 600;
                text-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
            }

            .topLink a#active {
                background: #ffffff;
                background: linear-gradient(360deg,rgba(255, 255, 255, 1) 0%, rgba(204, 204, 204, 1) 100%);
                color: white;
                text-decoration: none;
                padding: 8px 15px;
                border-radius: 10px;
                border: 1px solid #4b4b4b;
                font-weight: bold;
                color: #363635;
                font-family: 'Poppins', sans-serif;
                font-weight: 900;
                transition: 0.3s;
            }
        

            .topLink a:hover,
            .topLink a.active {
                background-color: #ffffffff;
                background: linear-gradient(90deg,rgba(245, 245, 245, 1) 0%, rgba(255, 255, 255, 1) 100%);
                color: #2b2b2b;
                border: 1px solid #4b4b4b;
                box-shadow: 0 0 8px rgba(255,255,255,0.3);
                transition: 0.3s;
            }


            /* movie stuff*/

             .glassbox {
                background: rgba(255, 255, 255, 0.3); /* semi-transparent white */
                border: 3px solid white;
                width: 50%;
                margin: 5px auto;
                display: flex;
                align-items: flex-start;
                gap: 20px;
                border-radius: 10px;
                color: #363635;
                padding-top: 15px;
                padding-left: 15px;
                padding-bottom: 8px;
                margin-top: 25px;
            }

          

            .posterCard img {
                width: 100%;
                height: 280px;
                object-fit: cover;
                border-radius: 6px;
                border: 1px solid #4b4b4b;
                box-shadow: 0 0 8px rgba(255,255,255,0.3);
            }

           .movieInfo {
                flex: 1;
                display: flex;
                flex-direction: column;
                min-height: 280px;
            }

            .movieInfo h1 {
                margin-top: 0;
                font-size: 25px;
                font-family: 'Poppins', sans-serif;
                font-weight: 800;
                color: #FFFFFF;
                text-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
                
            }

            .movieInfo p {
                margin-top: 5px;
                margin-right: 0px;
                font-size: 16px;
                font-weight: 600;
                font-family: 'Poppins', sans-serif;
                text-align: left;
                color: #ffffffff;
                text-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
                
            }

            .movieInfo .bottomDetails {
                margin-top: auto;
            }

            .movieInfo .bottomDetails p {
                margin: 6px 0;
            }

            /* For The Mall Cards */

            * {
                box-sizing: border-box;
            }

            main {
                display: flex;
                flex-direction: column;
            }

            #mallCard {
                display: flex;
                width: 50%;
                border: 2px solid black;
                border-radius: 10px;
                min-width: 35%;
                height: auto;
                overflow: hidden;
                background-color: #595A4A;
                margin: 5px auto;
            }

            .mallName {
                padding: 15px;
                width: 25%;
                display: flex;
                justify-content: center;
                text-align: center;
                align-items: center;
                box-sizing: border-box;
                color: #4b4b4b;
                background: #ffffff;
                background: linear-gradient(90deg, rgba(255, 255, 255, 1) 0%, rgba(204, 204, 204, 1) 100%);
                font-family: 'Segoe UI', Arial, sans-serif;
                font-weight: 900;
                border-right: 2px solid #000000ff;
                text-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
                 
            }

            .detailContainer {
                display: flex;
                flex: 1 1 auto;
                flex-direction: column;
                box-sizing: border-box;
                height: auto;
                padding: 3px;
            }
            
              .mallLocation {
                display: flex;
                width: 100%;
                min-width: 35%;
                height: auto;
                overflow: hidden;
                background-color: #6A7F3F;
                background: linear-gradient(90deg,rgba(106, 127, 63, 1) 0%, rgba(74, 106, 90, 1) 100%);
                font-weight: bold;
                font-family: 'Segoe UI', Arial, sans-serif;
                text-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
                color: #FFFFFF;
                padding: 5px;
                align-items: center;
            }

            /* Available theaters stuff */

            .glassbox-2{
                background: rgba(255, 255, 255, 0.3); /* semi-transparent white */
                border: 3px solid white;
                width: 50%;
                margin: 5px auto;
                display: flex;
                align-items: flex-start;
                gap: 20px;
                border-radius: 10px;
                color: #363635;
                padding-top: 15px;
                padding-left: 15px;
                padding-bottom: 8px;
                margin-top: 5px;
            }

           #availableTheatersText {
                margin: 0px auto;
                display: inline-block;
                width: 100%;
                align-items: left;
                border-radius: 10px;
                font-weight: bold;
                font-size: 18px;
                color: #ffffffff;
                text-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
                margin-bottom: 15px;

            }

            /* Theater Cards */

            #theaterContainer {
                display: flex;
                width: 100%;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 5px;
            }

            .theaterCard {
                display: flex;
                flex-direction: row;
                width: 80%;
                border: 1px solid #000000ff;
                border-radius: 10px;
                min-width: 35%;
                height: auto;
                overflow: hidden;
                background-color: #595A4A;
            }

            .theaterName {
                padding: 20px;
                width: 50%;
                display: flex;
                justify-content: center;
                text-align: center;
                font-family: 'Segoe UI', Arial, sans-serif;
                font-weight: 900;
                font-size: 16px;
                color: #4b4b4b;
                align-items: center;
                box-sizing: border-box;
                background: #ffffff;
                background: linear-gradient(90deg,rgba(255, 255, 255, 1) 0%, rgba(204, 204, 204, 1) 100%);
                border-right: 1px solid #000000ff;
                text-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
                cursor: pointer;
            }

            .timeslotContainer {
                display: flex;
                min-height: 50%;
                justify-content: center;
                align-items: center;
                flex-wrap: wrap;
                height: auto;
                padding: 10px;
                padding-right: 5px;
                gap: 5px;
                background-color: #6A7F3F;
                background: linear-gradient(180deg,rgba(106, 127, 63, 1) 0%, rgba(74, 106, 90, 1) 100%);
            }

            .timeslots {
                display: inline-flex;
                border: 2px solid #000000ff;
                border-radius: 10px;
                padding: 5px;
                color: #000000ff;
                background: #ffffff;
                background: linear-gradient(90deg,rgba(255, 255, 255, 1) 0%, rgba(204, 204, 204, 1) 100%);
                font-weight: 400;
                font-family: 'Segoe UI', Arial, sans-serif;
                font-size: 16px;
                margin-right: 5px;
                cursor: pointer;
                text-shadow: 0 2px 5px rgba(0, 0, 0, 0.7);
            }

            .timeslots:hover {
                background-color: #4b4b4b;
                background: linear-gradient(90deg,rgba(75, 75, 75, 1) 0%, rgba(43, 43, 43, 1) 100%);
                border: 1px solid #CCCCCC;
                transition: 0.3s;
                color: #CCCCCC;
            }

        </style>
    </head>
    <body>
        <header>
         <div class="logo">
         <img src="peakscinematransparent.png" alt="PeaksCinemas Logo" onclick="window.location.href='home.php'">
        </div>
    
        <div class="header-actions">
        <button class="profile-btn" onclick="window.location.href='<?= $profile_link ?>'" title="Profile">👤</button>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M5.121 17.804A8 8 0 1118.88 6.196M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
   </svg>
   </button>
    </div>
   </header>

        <main>
            <div id="topLinkSection">
                <nav class="topLink">
                    <a href="home.php">Home</a><p>&nbsp/&nbsp</p>
                    <a href="movie.php?movie_id=<?= htmlspecialchars($movieDetails['Movie_ID']) ?> ">Malls with "<?= htmlspecialchars($movieDetails['MovieName']) ?>"</a><p>&nbsp/&nbsp</p>
                    <a id="active">Available theaters in "<?= htmlspecialchars($mallDetails['MallName']) ?>"</a>             
                </nav>
            </div>

            <section id = "movieDetailsSection">
                <div class="glassbox">
                <div class = "posterCard">

                    <?php if ($movieDetails): ?>
                        <img src = "<?= htmlspecialchars($movieDetails['MoviePoster']) ?>"
                            alt = "<?= htmlspecialchars($movieDetails['MovieName']) ?>">
                    <?php endif; ?>

                    </div>
                <div class = "movieInfo">                    
                    <?php if ($movieDetails): ?>
                            <h1><?= htmlspecialchars($movieDetails['MovieName']) ?></h1>
                            <p><?= htmlspecialchars($movieDetails['MovieDescription']) ?></p>

                            <div class = 'bottomDetails'>
                            <p> Genre: <?= htmlspecialchars($movieDetails['Genre']) ?></p>
                            <p> Rating: <?= htmlspecialchars($movieDetails['Rating']) ?></p>
                            <p> Runtime: <?= htmlspecialchars($movieDetails['Runtime']) ?> minutes</p>
                            </div>
                    <?php endif; ?>
                </div>
            </div>
            </section>
        
            <section id="mallCard">
                <div class="mallName"><?= htmlspecialchars($mallDetails['MallName']) ?></div>
                <div class="mallLocation"><?= htmlspecialchars($mallDetails['Location']) ?></div>
            </section>

            <div class="glassbox-2">
            <section id="availableTheatersSection">
                <div id="availableTheatersText">Available theaters with this movie: </div>
                <div id="theaterContainer">
                    <?php foreach ($theaterData as $theater): ?>
                        <div class="theaterCard">
                            <div class="theaterName"><?= htmlspecialchars($theater['TheaterName']) ?></div>
                            <div class="timeslotContainer">
                                <?php foreach ($theater['Timeslots'] as $timeslot_id => $details): ?>
                                <div class="timeslots" data-id='<?= htmlspecialchars($timeslot_id) ?>'><?= htmlspecialchars($details) ?></div>
                                <?php endforeach; ?>
                            </div>     
                        </div>
                    <?php endforeach; ?>
                </div>                
            </section>
            </div>
        </main>

        <script>
            document.addEventListener("DOMContentLoaded", () => {
                document.querySelectorAll(".timeslots").forEach(timeslot_button => {
                    timeslot_button.addEventListener("click", () => {
                        const timeslot_id = timeslot_button.getAttribute('data-id');

                        window.location.href = `seat_selection.php?movie_id=<?= htmlspecialchars($Movie_ID)?>&mall_id=<?= htmlspecialchars($Mall_ID) ?>&date=<?= htmlspecialchars($Date) ?>&timeslot_id=${timeslot_id}`;
                    })
                })
            })
        </script>
    </body>
</html>