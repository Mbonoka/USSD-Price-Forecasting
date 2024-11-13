<?php
require_once '../config.php';

header('Content-Type: application/json');

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($request) {
    case '/api/prices':
        handlePrices($method);
        break;
    case '/api/predictions':
        handlePredictions($method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        break;
}

function handlePrices($method) {
    $conn = getDBConnection();
    
    if ($method === 'GET') {
        $stmt = $conn->prepare("
            SELECT 
                c.name as crop_name,
                m.name as market_name,
                p.price_value,
                p.price_date
            FROM prices p
            JOIN crops c ON p.crop_id = c.crop_id
            JOIN markets m ON p.market_id = m.market_id
            WHERE p.price_date = CURRENT_DATE
        ");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}