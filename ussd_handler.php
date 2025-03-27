<?php
require_once dirname(__FILE__) . '/config.php';
require_once 'vendor/autoload.php';
require_once __DIR__ . '/classes/database.php';
require_once __DIR__ . '/ml/prediction.php';

use AfricasTalking\SDK\AfricasTalking;

class USSDHandler {
    private $sessionId;
    private $phoneNumber;
    private $text;
    private $conn;
    private $AT;
    private $redis;
    
    // Constants
    const MAX_MENU_ITEMS = 5;
    const CACHE_DURATION = 300; // 5 minutes
    const CACHE_PREFIX = 'ussd_';
    const CACHE_ENABLED = true;
    
    public function __construct($sessionId, $phoneNumber, $text) {
        $this->sessionId = $this->sanitizeInput($sessionId);
        $this->phoneNumber = $this->sanitizeInput($phoneNumber);
        $this->text = $this->sanitizeInput($text);
        $this->conn = getDBConnection();
        $this->AT = new AfricasTalking(AT_USERNAME, AT_API_KEY);
        
        // Initialize Redis
        try {
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
        }
        
        $this->logRequest();
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    private function logRequest() {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO ussd_logs (session_id, phone_number, input_text, request_time)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$this->sessionId, $this->phoneNumber, $this->text]);
        } catch (Exception $e) {
            error_log("USSD log error: " . $e->getMessage());
        }
    }
    
    public function handleRequest() {
        try {
            $textArray = explode('*', $this->text);
            $level = count($textArray);
            
            if ($this->text == "") {
                return $this->showMainMenu();
            }
            
            switch($textArray[0]) {
                case "1":
                    return $this->handlePriceCheck($level, $textArray);
                case "2":
                    return $this->handlePricePrediction($level, $textArray);
                case "3":
                    return $this->handlePriceAlerts($level, $textArray);
                case "4":
                    return $this->handleProfile($level, $textArray);
                default:
                    return "END Invalid option selected";
            }
        } catch (Exception $e) {
            error_log("USSD error: " . $e->getMessage());
            return "END An error occurred. Please try again later.";
        }
    }
    
    private function showMainMenu() {
        return "CON Welcome to Crop Price Prediction\n" .
               "1. Check Current Prices\n" .
               "2. Get Price Predictions\n" .
               "3. Set Price Alerts\n" .
               "4. My Profile";
    }
    
    private function handlePriceCheck($level, $textArray) {
        try {
            switch ($level) {
                case 1:
                    return $this->getCropList();
                case 2:
                    return $this->getMarketList();
                case 3:
                    return $this->getCurrentPrice($textArray[1], $textArray[2]);
                default:
                    return "END Invalid selection";
            }
        } catch (Exception $e) {
            error_log("Price check error: " . $e->getMessage());
            return "END An error occurred while checking prices";
        }
    }
    
    private function handlePricePrediction($level, $textArray) {
        try {
            switch ($level) {
                case 1:
                    return $this->getCropList();
                case 2:
                    return $this->getMarketList();
                case 3:
                    return $this->getPredictedPrice($textArray[1], $textArray[2]);
                default:
                    return "END Invalid selection";
            }
        } catch (Exception $e) {
            error_log("Price prediction error: " . $e->getMessage());
            return "END An error occurred while getting predictions";
        }
    }
    
    private function handlePriceAlerts($level, $textArray) {
        try {
            switch ($level) {
                case 1:
                    return $this->getCropList();
                case 2:
                    return $this->getMarketList();
                case 3:
                    return "CON Enter target price for alert:";
                case 4:
                    return $this->setPriceAlert($textArray[1], $textArray[2], $textArray[3]);
                default:
                    return "END Invalid selection";
            }
        } catch (Exception $e) {
            error_log("Price alert error: " . $e->getMessage());
            return "END An error occurred while setting alert";
        }
    }
    
    private function handleProfile($level, $textArray) {
        try {
            if ($level == 1) {
                return $this->showProfile();
            }
            return "END Invalid selection";
        } catch (Exception $e) {
            error_log("Profile error: " . $e->getMessage());
            return "END An error occurred while fetching profile";
        }
    }
    
    private function getCropList() {
        try {
            if (self::CACHE_ENABLED) {
                $cacheKey = self::CACHE_PREFIX . "crops_list";
                $crops = $this->getCache($cacheKey);
                
                if ($crops) {
                    return $this->formatCropList($crops);
                }
            }
            
            $stmt = $this->conn->prepare("
                SELECT crop_id, name 
                FROM crops 
                WHERE is_active = 1 
                ORDER BY name ASC
            ");
            $stmt->execute();
            $crops = $stmt->fetchAll();
            
            if (self::CACHE_ENABLED) {
                $this->setCache($cacheKey, $crops, self::CACHE_DURATION);
            }
            
            return $this->formatCropList($crops);
            
        } catch (Exception $e) {
            error_log("Error getting crops: " . $e->getMessage());
            return "END Failed to fetch crops list";
        }
    }
    
    private function formatCropList($crops) {
        if (empty($crops)) {
            return "END No crops available";
        }
        
        $response = "CON Select crop:\n";
        foreach ($crops as $index => $crop) {
            if ($index >= self::MAX_MENU_ITEMS) break;
            $response .= ($index + 1) . ". " . $crop['name'] . "\n";
        }
        
        return rtrim($response);
    }
    
    private function getMarketList() {
        try {
            if (self::CACHE_ENABLED) {
                $cacheKey = self::CACHE_PREFIX . "markets_list";
                $markets = $this->getCache($cacheKey);
                
                if ($markets) {
                    return $this->formatMarketList($markets);
                }
            }
            
            $stmt = $this->conn->prepare("
                SELECT market_id, name 
                FROM markets 
                WHERE is_active = 1 
                ORDER BY name ASC
            ");
            $stmt->execute();
            $markets = $stmt->fetchAll();
            
            if (self::CACHE_ENABLED) {
                $this->setCache($cacheKey, $markets, self::CACHE_DURATION);
            }
            
            return $this->formatMarketList($markets);
            
        } catch (Exception $e) {
            error_log("Error getting markets: " . $e->getMessage());
            return "END Failed to fetch markets list";
        }
    }
    
    private function formatMarketList($markets) {
        if (empty($markets)) {
            return "END No markets available";
        }
        
        $response = "CON Select market:\n";
        foreach ($markets as $index => $market) {
            if ($index >= self::MAX_MENU_ITEMS) break;
            $response .= ($index + 1) . ". " . $market['name'] . "\n";
        }
        
        return rtrim($response);
    }
    
    private function getCurrentPrice($cropId, $marketId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.price_value, c.name as crop_name, m.name as market_name
                FROM prices p
                JOIN crops c ON p.crop_id = c.crop_id
                JOIN markets m ON p.market_id = m.market_id
                WHERE p.crop_id = ? AND p.market_id = ?
                ORDER BY p.price_date DESC
                LIMIT 1
            ");
            $stmt->execute([(int)$cropId, (int)$marketId]);
            $price = $stmt->fetch();
            
            if (!$price) {
                return "END Price not available for selected crop and market";
            }
            
            return "END Current price for {$price['crop_name']} at {$price['market_name']}: " .
                   "KES " . number_format($price['price_value'], 2);
                   
        } catch (Exception $e) {
            error_log("Error getting price: " . $e->getMessage());
            return "END Failed to fetch price information";
        }
    }
    
    private function getPredictedPrice($cropId, $marketId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT pp.predicted_price, c.name as crop_name, m.name as market_name
                FROM price_predictions pp
                JOIN crops c ON pp.crop_id = c.crop_id
                JOIN markets m ON pp.market_id = m.market_id
                WHERE pp.crop_id = ? AND pp.market_id = ?
                AND pp.predicted_date >= CURRENT_DATE
                ORDER BY pp.predicted_date ASC
                LIMIT 1
            ");
            $stmt->execute([(int)$cropId, (int)$marketId]);
            $prediction = $stmt->fetch();
            
            if (!$prediction) {
                return "END No prediction available for selected crop and market";
            }
            
            return "END Predicted price for {$prediction['crop_name']} at {$prediction['market_name']}: " .
                   "KES " . number_format($prediction['predicted_price'], 2);
                   
        } catch (Exception $e) {
            error_log("Error getting prediction: " . $e->getMessage());
            return "END Failed to fetch prediction";
        }
    }
    
    private function setPriceAlert($cropId, $marketId, $targetPrice) {
        try {
            if (!is_numeric($targetPrice) || $targetPrice <= 0) {
                return "END Invalid target price";
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO price_alerts (user_phone, crop_id, market_id, target_price, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$this->phoneNumber, (int)$cropId, (int)$marketId, (float)$targetPrice]);
            
            return "END Price alert set successfully. You will be notified when the price reaches your target.";
            
        } catch (Exception $e) {
            error_log("Error setting alert: " . $e->getMessage());
            return "END Failed to set price alert";
        }
    }
    
    private function showProfile() {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as alert_count
                FROM price_alerts
                WHERE user_phone = ? AND is_active = 1
            ");
            $stmt->execute([$this->phoneNumber]);
            $result = $stmt->fetch();
            
            return "END Your Profile\n" .
                   "Phone: " . $this->phoneNumber . "\n" .
                   "Active Alerts: " . $result['alert_count'];
                   
        } catch (Exception $e) {
            error_log("Error getting profile: " . $e->getMessage());
            return "END Failed to fetch profile";
        }
    }
    
    private function getCache($key) {
        try {
            if (!$this->redis) {
                return false;
            }
            
            $value = $this->redis->get($key);
            if ($value) {
                return json_decode($value, true);
            }
            return false;
        } catch (Exception $e) {
            error_log("Cache get error: " . $e->getMessage());
            return false;
        }
    }
    
    private function setCache($key, $value, $duration) {
        try {
            if (!$this->redis) {
                return false;
            }
            
            $serializedValue = json_encode($value);
            return $this->redis->setex($key, $duration, $serializedValue);
        } catch (Exception $e) {
            error_log("Cache set error: " . $e->getMessage());
            return false;
        }
    }
    
    public function __destruct() {
        if ($this->redis) {
            $this->redis->close();
        }
    }
}