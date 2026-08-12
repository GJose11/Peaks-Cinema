<?php 
    include("peakscinemas_database.php");
    // Creates a directory for the movie posters in case it doesn't exist yet
    $posterFolder = $_SERVER['DOCUMENT_ROOT'] . '/PeaksCinema/MoviePosters';
    if (!is_dir($posterFolder)) {
        mkdir($posterFolder, 0755, true);
    }

    // empty variables for later use
    $MovieName = $MovieDescription = $Genre = $Rating = $Runtime = $MoviePoster = $MovieAvailability = $TrailerUrl = "";


    // kung nagsubmit nung admin nung form tapos nandun rin nung poster
    if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["moviePosterUp"])) {
        // input cleanup func for later use   
        function input_cleanup($data) {
            $data = trim($data);
            $data = stripslashes($data);
            return $data;
        }

        // prepared statement for later use
        $stmt = $conn -> prepare("INSERT INTO movie(MovieName, MovieDescription, Genre, Rating, Runtime, MoviePoster, MovieAvailability, TrailerURL)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt -> bind_param("ssssisss", $MovieName, $MovieDescription, $Genre, $Rating, $Runtime, $MoviePoster, $MovieAvailability, $TrailerURL);
        
        // more input cleanup
        $MovieName = input_cleanup($_POST['movieName']);
        $MovieDescription = input_cleanup($_POST['movieDesc']);
        $Genre = input_cleanup($_POST['movieGenre']);
        $Rating = input_cleanup($_POST['movieRating']);
        $Runtime = input_cleanup($_POST['movieRuntime']);
        $TrailerURL = input_cleanup($_POST['TrailerURL']);

        // this makes a "path" to the uploaded file
        $temp = $_FILES['moviePosterUp']['tmp_name'];
        // this cuts off the file type from the image name
        $fileType = pathinfo($_FILES['moviePosterUp']['name'], PATHINFO_EXTENSION);

        // FIXED: Remove characters that are invalid in Windows file paths
        // Colons, slashes, asterisks, quotes, etc. cause upload to fail on Windows/XAMPP
        $safeMovieName = preg_replace('/[\\\\\/:\*\?"<>\|]/', '', $MovieName);
        $safeMovieName = trim($safeMovieName);

        // rename the file to match the (sanitized) movie name
        $fileName = $safeMovieName . "." . $fileType;
        // create the full path where the file will be saved
        $endPath = $posterFolder . "/" . $fileName;

        // Move the temporary file to the actual folder
        if (move_uploaded_file($temp, $endPath)) {
            $MoviePoster = 'MoviePosters/' . $fileName;
        } else {
            echo "There was an error with uploading the poster. Please try again. ";
        }

        $MovieAvailability = input_cleanup($_POST['movieAvailability']); 

        // actual execution of the prepared statement
        $stmt -> execute();
    }

    mysqli_close($conn);
?>

<!DOCTYPE html>
<html>
    <head>
        <style>
            body.movieUpload main{
                display: flex;
            }

            body.movieUpload section {
                width:50%;
                border: 2px solid black;
            }

            body.movieUpload #movieFormSection {
                width: auto;
                padding: 20px;
            }

            body.movieUpload #posterPreviewSection {
                width: auto;
                padding: 20px;
            }

            body.movieUpload #posterPreview {
                width: 100%;
                height: 280px;
                object-fit: cover;
                border-radius: 6px;
                background-color: #fff;
                border: 2px solid black; 
                display: none;
            }
        </style>
    </head>
    <body class="movieUpload">
        <header>
            <nav>
                <a href="dashboard.php" target="_self">Dashboard</a>
                <a href="malls_selection_admin.php" target="_self">Malls</a>
                <a href="movie_upload.php" target="_self">Movie Upload</a>
                <a href="theater_upload.php" target="_self">Theater Upload</a>
                <a href="mall_upload.php" target="_self">Mall Upload</a>
            </nav>
        </header>
        <main>
            <section id="movieFormSection">
                <form id="movieDetails" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <div>
                        <label for="movieName">Movie Name: </label>
                        <input type="text" id="movieName" name="movieName" placeholder="Movie Name" required>
                    </div>
                    <br>

                    <div>
                        <label for="movieDesc">Movie Description: </label><br>
                        <textarea id="movieDesc" name="movieDesc" rows="10" cols="75" placeholder="Movie Description" required></textarea>
                    </div>
                    <br>

                    <div>
                        <label for="movieGenre">Movie Genre: </label><br>
                        <input type="text" id="movieGenre" name="movieGenre" placeholder="Movie Genre" required>
                    </div>
                    <br>

                    <div>
                        <label for="movieRating">Movie Rating: </label><br>
                        <select name="movieRating" id="movieRating">
                            <option value="">Select a rating:</option>
                            <option value="G">Rated G</option>
                            <option value="PG">Rated PG</option>
                            <option value="R-13">Rated R-13</option>
                            <option value="R-16">Rated R-16</option>
                            <option value="R-18">Rated R-18</option> 
                        </select>
                    </div>
                    <br>

                    <div>
                        <label for="movieRuntime">Movie Runtime (in minutes): </label>
                        <input type="number" id="movieRuntime" name="movieRuntime" placeholder="Runtime (in minutes)" min="0" required>
                    </div>
                    <br>

                    <div>
                        <label for="moviePosterUp">Movie Poster: </label><br>
                        <input type="file" id="moviePosterUp" name="moviePosterUp" accept="image/png, image/jpeg, image/jpg" required>
                    </div>
                    <br>

                    <div>
                        <label for="movieAvailability">Movie Availability: </label>
                        <select id="movieAvailability" name="movieAvailability" required>
                            <option value="Now Showing">Now Showing</option>
                            <option value="Coming Soon">Coming Soon</option>
                        </select>
                    </div>
                    <br>

                    <div>
                        <label for="TrailerURL">Movie Trailer: </label><br>
                        <input type="text" id="TrailerURL" name="TrailerURL" placeholder="Trailer Link" required>
                    </div>
                    <br>

                    <div>
                        <button type="submit" name="movieDetails" value="movieDetails">Upload</button>
                    </div>
                </form>
            </section>
            <section id="posterPreviewSection">
                <h2>Poster Preview:</h2>
                <img id="posterPreview" src="" alt="Poster Preview">
            </section>                     
        </main>
        
        <script> 
            const moviePosterUp = document.getElementById("moviePosterUp");
            const posterPreview = document.getElementById("posterPreview");
            var uploadedPoster = "";

            moviePosterUp.addEventListener('change', function() {
                const reader = new FileReader();
                reader.addEventListener('load', () => {
                    uploadedPoster = reader.result;
                    const posterPreview = document.getElementById('posterPreview');
                    posterPreview.src = uploadedPoster;
                    posterPreview.style.display = "block";
                })
                reader.readAsDataURL(this.files[0]);
            })
        </script>
    </body>
</html>