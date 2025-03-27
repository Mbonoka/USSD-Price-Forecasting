<?php
require_once dirname(__DIR__) . '/config.php';

class PricePrediction {
    private $conn;
    private $pythonPath;
    private $scriptPath;
    private $maxPredictionDays = 30;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
        $this->pythonPath = realpath("C:\Users\user\AppData\Local\Programs\Python\Python313\python.exe");
        $this->scriptPath = realpath(__DIR__ . "/../python/predict_price.py");
        
        if (!file_exists($this->pythonPath) || !file_exists($this->scriptPath)) {
            throw new RuntimeException("Required files not found");
        }
    }
    
    public function getPrediction($cropId, $marketId, $days = 7) {
        $this->validateInputs($cropId, $marketId, $days);
        
        // Check cache first
        $cachedPrediction = $this->getCachedPrediction($cropId, $marketId, $days);
        if ($cachedPrediction) {
            return $cachedPrediction;
        }
        
        $prediction = $this->executePredictionScript($cropId, $marketId, $days);
        $this->savePrediction($prediction);
        
        return $prediction;
    }
    
    private function validateInputs($cropId, $marketId, $days) {
        if (!is_numeric($cropId) || $cropId <= 0 ||
            !is_numeric($marketId) || $marketId <= 0 ||
            !is_numeric($days) || $days < 1 || $days > $this->maxPredictionDays) {
            throw new InvalidArgumentException('Invalid input parameters');
        }
    }
    
    private function executePredictionScript($cropId, $marketId, $days) {
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        
        $process = proc_open(
            sprintf(
                '"%s" "%s" %d %d %d',
                $this->pythonPath,
                $this->scriptPath,
                $cropId,
                $marketId,
                $days
            ),
            $descriptorspec,
            $pipes
        );
        
        if (!is_resource($process)) {
            throw new RuntimeException("Failed to start prediction script");
        }
        
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        
        $exitCode = proc_close($process);
        
        if ($exitCode !== 0 || $error) {
            error_log("Prediction script error: " . $error);
            throw new RuntimeException("Prediction script failed");
        }
        
        $prediction = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid prediction output");
        }
        
        return $prediction;
    }
    
    private function getCachedPrediction($cropId, $marketId, $days) {
        $stmt = $this->conn->prepare("
            SELECT * FROM price_predictions
            WHERE crop_id = ? 
            AND market_id = ?
            AND prediction_days = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$cropId, $marketId, $days]);
        return $stmt->fetch();
    }
    
    public function savePrediction($prediction) {
        $stmt = $this->conn->prepare("
            INSERT INTO price_predictions 
            (crop_id, market_id, predicted_date, predicted_price, 
             confidence_score, prediction_days, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $prediction['crop_id'],
            $prediction['market_id'],
            $prediction['predicted_date'],
            $prediction['predicted_price'],
            $prediction['confidence_score'],
            $prediction['days']
        ]);
    }
}