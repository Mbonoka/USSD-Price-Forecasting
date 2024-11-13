<?php
require_once 'config.php';

class USSDHandler {
    private $sessionId;
    private $phoneNumber;
    private $text;
    private $conn;

    public function __construct($sessionId, $phoneNumber, $text) {
        $this->sessionId = $sessionId;
        $this->phoneNumber = $phoneNumber;
        $this->text = $text;
        $this->conn = getDBConnection();
    }

    public function handleRequest() {
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
    }

    private function showMainMenu() {
        $response  = "CON Welcome to Crop Price Prediction\n";
        $response .= "1. Check Current Prices\n";
        $response .= "2. Get Price Predictions\n";
        $response .= "3. Set Price Alerts\n";
        $response .= "4. My Profile";
        return $response;
    }

    private function handlePriceCheck($level, $textArray) {
        if ($level == 1) {
            return $this->getCropList();
        } elseif ($level == 2) {
            return $this->getMarketList();
        } elseif ($level == 3) {
            return $this->getCurrentPrice($textArray[1], $textArray[2]);
        }
    }

    private function getCropList() {
        $stmt = $this->conn->query("SELECT crop_id, name FROM crops WHERE is_active = 1");
        $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = "CON Select crop:\n";
        foreach ($crops as $index => $crop) {
            $response .= ($index + 1) . ". " . $crop['name'] . "\n";
        }
        return $response;
    }

   