<?php
// Mock Database Connection for Vercel Live Demo
// This replaces the real MySQL connection so the site doesn't crash on Vercel

class MockResult {
    private $data;
    private $index = 0;
    public $num_rows;

    public function __construct($data = []) {
        $this->data = $data;
        $this->num_rows = count($data);
    }
    public function fetch_assoc() {
        if ($this->index < count($this->data)) {
            return $this->data[$this->index++];
        }
        return null;
    }
    public function fetch_all($mode = 1) {
        return $this->data;
    }
}

class MockStmt {
    private $result;
    public function __construct($result_data = []) {
        $this->result = new MockResult($result_data);
    }
    public function bind_param(...$args) { return true; }
    public function execute() { return true; }
    public function get_result() { return $this->result; }
    public function close() { return true; }
}

class MockMySQLi {
    public $insert_id = 1;
    public $affected_rows = 1;
    public $error = "";

    public function query($sql) {
        $sql = strtolower($sql);
        
        // Mock Movies Data
        if (strpos($sql, 'from movie') !== false || strpos($sql, 'movie m') !== false) {
            $avail = 'Now Showing';
            if (strpos($sql, "coming soon") !== false) $avail = 'Coming Soon';
            
            return new MockResult([
                [
                    'Movie_ID' => 1,
                    'MovieName' => 'Project Hail Mary',
                    'MoviePoster' => 'Project Hail Mary.jpg',
                    'MovieAvailability' => $avail,
                    'Duration' => '120',
                    'Genre' => 'Sci-Fi',
                    'Description' => 'A lone astronaut must save the earth from disaster.',
                    'Director' => 'Phil Lord',
                    'Cast' => 'Ryan Gosling',
                    'purchase_count' => 150
                ],
                [
                    'Movie_ID' => 2,
                    'MovieName' => 'The Super Mario Galaxy Movie',
                    'MoviePoster' => 'The Super Mario Galaxy Movie.jpg',
                    'MovieAvailability' => $avail,
                    'Duration' => '95',
                    'Genre' => 'Animation',
                    'Description' => 'Mario travels to space.',
                    'Director' => 'Shigeru Miyamoto',
                    'Cast' => 'Chris Pratt',
                    'purchase_count' => 200
                ]
            ]);
        }
        
        // Return empty result for all other queries so they don't crash
        return new MockResult([]);
    }

    public function prepare($sql) {
        // Return dummy data for prepared statements
        $sql = strtolower($sql);
        if (strpos($sql, 'from movie') !== false) {
            return new MockStmt([
                [
                    'Movie_ID' => 1,
                    'MovieName' => 'Project Hail Mary',
                    'MoviePoster' => 'Project Hail Mary.jpg',
                    'MovieAvailability' => 'Now Showing',
                    'Duration' => '120',
                    'Genre' => 'Sci-Fi'
                ]
            ]);
        }
        return new MockStmt([]);
    }

    public function real_escape_string($str) {
        return $str;
    }

    public function close() { return true; }
    public function begin_transaction() { return true; }
    public function commit() { return true; }
    public function rollback() { return true; }
}

// Create a fake connection object
$conn = new MockMySQLi();

// Override functions that expect the real MySQL connection
function ensureCustomerPhoneNumberSchema($conn) {
    // Do nothing for the mock
}
?>
