<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test...\n";
require_once __DIR__ . '/config.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "Connected successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}