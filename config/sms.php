<?php
/**
 * SMS Service for Pakistani Providers
 */

class SMSService {
    private $provider;
    private $config;
    
    public function __construct($provider = 'eocean') {
        $this->provider = $provider;
        $this->config = $this->getProviderConfig($provider);
    }
    
    private function getProviderConfig($provider) {
        $configs = [
            'eocean' => [
                'url' => 'https://eocean.us/api/sendsms.php',
                'username' => 'your_username', // Configure
                'password' => 'your_password', // Configure
                'mask' => 'SkillsWay'
            ],
            'jazz' => [
                'url' => 'https://api.jazz.com.pk/api/sendsms',
                'api_key' => 'your_api_key', // Configure
                'sender_id' => 'SkillsWay'
            ],
            'telenor' => [
                'url' => 'https://api.telenor.com.pk/sms/send',
                'api_key' => 'your_api_key', // Configure
                'sender_id' => 'SkillsWay'
            ]
        ];
        
        return $configs[$provider] ?? $configs['eocean'];
    }
    
    public function sendCredentials($phone, $username, $password) {
        $message = "Skills Way Login - Username: $username, Password: $password. Login: skillsway.edu.pk/login.php";
        return $this->sendSMS($phone, $message);
    }
    
    public function sendNotification($phone, $message) {
        $sms = "Skills Way: $message";
        return $this->sendSMS($phone, $sms);
    }
    
    public function sendOTP($phone, $otp) {
        $message = "Skills Way OTP: $otp. Valid for 5 minutes. Do not share.";
        return $this->sendSMS($phone, $message);
    }
    
    private function sendSMS($phone, $message) {
        $phone = $this->formatPhone($phone);
        
        switch ($this->provider) {
            case 'eocean':
                return $this->sendEoceanSMS($phone, $message);
            case 'jazz':
                return $this->sendJazzSMS($phone, $message);
            case 'telenor':
                return $this->sendTelenorSMS($phone, $message);
            default:
                return $this->sendEoceanSMS($phone, $message);
        }
    }
    
    private function sendEoceanSMS($phone, $message) {
        $data = [
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'to' => $phone,
            'message' => $message,
            'mask' => $this->config['mask']
        ];
        
        return $this->makeRequest($this->config['url'], $data);
    }
    
    private function sendJazzSMS($phone, $message) {
        $data = [
            'api_key' => $this->config['api_key'],
            'sender_id' => $this->config['sender_id'],
            'to' => $phone,
            'message' => $message
        ];
        
        return $this->makeRequest($this->config['url'], $data);
    }
    
    private function sendTelenorSMS($phone, $message) {
        $data = [
            'api_key' => $this->config['api_key'],
            'sender_id' => $this->config['sender_id'],
            'to' => $phone,
            'text' => $message
        ];
        
        return $this->makeRequest($this->config['url'], $data);
    }
    
    private function makeRequest($url, $data) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'SMS sent successfully', 'response' => $response];
            } else {
                return ['success' => false, 'message' => 'SMS failed', 'response' => $response];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function formatPhone($phone) {
        // Remove spaces, dashes, and plus signs
        $phone = preg_replace('/[\s\-\+]/', '', $phone);
        
        // Add country code if missing
        if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            $phone = '92' . substr($phone, 1);
        } elseif (strlen($phone) === 10) {
            $phone = '92' . $phone;
        }
        
        return $phone;
    }
    
    public function generateOTP() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
?>