<?php
require_once '../config.php';

header('Content-Type: application/json');

// Basic API rate limiting
if (!checkRateLimit($_SERVER['REMOTE_ADDR'])) {
    http_response_code(429);
    die(json_encode(['error' => 'Too many requests']));
}

$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($request) {
        case '/api/prices':
            echo handlePrices($method);
            break;
            
        case '/api/predictions':
            echo handlePredictions($method);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            break;
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handlePrices($method) {
    $conn = Database::getInstance()->getConnection();
    
    switch($method) {
        case 'GET':
            $days = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT) ?: 30;
            $stmt = $conn->prepare("
                SELECT 
                    c.name as crop_name,
                    m.name as market_name,
                    p.price_value,
                    p.price_date
                FROM prices p
                JOIN crops c ON p.crop_id = c.crop_id
                JOIN markets m ON p.market_id = m.market_id
                WHERE p.price_date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
                ORDER BY p.price_date DESC
            ");
            
            $stmt->execute([$days]);
            return json_encode([
                'status' => 'success',
                'data' => $stmt->fetchAll()
            ]);
            
        default:
            http_response_code(405);
            return json_encode(['error' => 'Method not allowed']);
    }
}

function handlePredictions($method) {
    switch($method) {
        case 'GET':
            $cropId = filter_input(INPUT_GET, 'crop_id', FILTER_VALIDATE_INT);
            $marketId = filter_input(INPUT_GET, 'market_id', FILTER_VALIDATE_INT);
            $days = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT) ?: 7;
            
            if (!$cropId || !$marketId) {
                http_response_code(400);
                return json_encode(['error' => 'Missing required parameters']);
            }
            
            $predictor = new PricePrediction();
            $prediction = $predictor->getPrediction($cropId, $marketId, $days);
            
            return json_encode([
                'status' => 'success',
                'data' => $prediction
            ]);
            
        default:
            http_response_code(405);
            return json_encode(['error' => 'Method not allowed']);
    }
}

function checkRateLimit($ip) {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    $key = "rate_limit:$ip";
    $requests = $redis->get($key) ?: 0;
    
    if ($requests > 100) { // 100 requests per hour
        return false;
    }
    
    $redis->incr($key);
    if ($requests == 0) {
        $redis->expire($key, 3600); // 1 hour
    }
    
    return true;
}