<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/database.php';

echo "Root path: " . __DIR__ . "\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "Connected successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}