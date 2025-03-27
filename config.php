<?php
require_once __DIR__ . '/../bootstrap.php';
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root'); 
define('DB_PASS', '');
define('DB_NAME', 'crop_price_prediction');
define('DB_PORT', 3306);

// Africa's Talking configuration 
define('AT_USERNAME', 'esther.mbonoka@strathmore.edu');
define('AT_API_KEY', getenv('AT_API_KEY') ?: 'atsk_d303f4cb3fcc2b66ede021f4f7173638b70af17213425c33a2445ac020cb354700cfc690');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Define root path constant
define('ROOT_PATH', __DIR__);

// Helper function
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        require_once __DIR__ . '/classes/database.php';
        return Database::getInstance()->getConnection();
    }
}