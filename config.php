<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'crop_price_db');


define('AT_USERNAME', 'esther.mbonoka@strathmore.edu');
define('AT_API_KEY', 'atsk_d303f4cb3fcc2b66ede021f4f7173638b70af17213425c33a2445ac020cb354700cfc690');


session_start();


function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>