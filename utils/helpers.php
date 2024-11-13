<?php
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input));
}

function generateApiKey() {
    return bin2hex(random_bytes(32));
}

function sendSMS($phoneNumber, $message) {
    // Implement Africa's Talking SMS integration
    $username = AT_USERNAME;
    $apiKey = AT_API_KEY;
    
    $AT = new AfricasTalking($username, $apiKey);
    $sms = $AT->sms();
    
    try {
        $result = $sms->send([
            'to' => $phoneNumber,
            'message' => $message
        ]);
        return $result;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}