<?php
require_once '../config.php';

class PricePrediction {
    private $conn;
    private $pythonPath;

    public function __construct() {
        $this->conn = getDBConnection();
        $this->pythonPath = "C:\Users\user\AppData\Local\Programs\Python\Python313\python.exe";
    }

    public function getPrediction($cropId, $marketId, $days = 7) {
        $command = sprintf(
            '%s ../python/predict_price.py %s %s %s',
            $this->pythonPath,
            escapeshellarg($cropId),
            escapeshellarg($marketId),
            escapeshellarg($days)
        );

        $output = shell_exec($command);
        return json_decode($output, true);
    }

    public function savePrediction($prediction) {
        $stmt = $this->conn->prepare("
            INSERT INTO price_predictions 
            (crop_id, market_id, predicted_date, predicted_price, confidence_score)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $prediction['crop_id'],
            $prediction['market_id'],
            $prediction['predicted_date'],
            $prediction['predicted_price'],
            $prediction['confidence_score']
        ]);
    }
}