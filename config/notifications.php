<?php
/**
 * Unified Notification Service
 */

require_once 'email.php';
require_once 'sms.php';

class NotificationService {
    private $emailService;
    private $smsService;
    private $db;
    
    public function __construct() {
        $this->emailService = new EmailService();
        $this->smsService = new SMSService();
        $this->db = (new Database())->getConnection();
    }
    
    public function sendCredentials($userId, $username, $password, $userType) {
        $user = $this->getUser($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $results = [];
        
        // Send email
        if ($user['email']) {
            $emailResult = $this->emailService->sendCredentials($user['email'], $username, $password, $userType);
            $results['email'] = $emailResult;
            $this->logNotification($userId, 'email', 'credentials', $emailResult['success']);
        }
        
        // Send SMS
        if ($user['phone']) {
            $smsResult = $this->smsService->sendCredentials($user['phone'], $username, $password);
            $results['sms'] = $smsResult;
            $this->logNotification($userId, 'sms', 'credentials', $smsResult['success']);
        }
        
        return $results;
    }
    
    public function sendWelcomeMessage($userId) {
        $user = $this->getUser($userId);
        if (!$user) return false;
        
        $subject = 'Welcome to Skills Way Institute';
        $message = "Dear {$user['full_name']}, welcome to Skills Way Institute! We're excited to have you join our learning community.";
        
        $results = [];
        
        if ($user['email']) {
            $results['email'] = $this->emailService->sendNotification($user['email'], $subject, $message);
        }
        
        if ($user['phone']) {
            $results['sms'] = $this->smsService->sendNotification($user['phone'], $message);
        }
        
        return $results;
    }
    
    public function sendEnrollmentConfirmation($userId, $courseName, $batchName) {
        $user = $this->getUser($userId);
        if (!$user) return false;
        
        $subject = 'Enrollment Confirmation';
        $message = "Congratulations! You have been enrolled in $courseName - $batchName. Classes will begin soon.";
        
        $results = [];
        
        if ($user['email']) {
            $results['email'] = $this->emailService->sendNotification($user['email'], $subject, $message);
        }
        
        if ($user['phone']) {
            $results['sms'] = $this->smsService->sendNotification($user['phone'], $message);
        }
        
        $this->logNotification($userId, 'both', 'enrollment_confirmation', true);
        return $results;
    }
    
    public function sendPaymentReminder($userId, $amount, $dueDate) {
        $user = $this->getUser($userId);
        if (!$user) return false;
        
        $subject = 'Payment Reminder';
        $message = "Payment reminder: Rs. $amount is due on $dueDate. Please make payment to continue your enrollment.";
        
        $results = [];
        
        if ($user['email']) {
            $results['email'] = $this->emailService->sendNotification($user['email'], $subject, $message);
        }
        
        if ($user['phone']) {
            $results['sms'] = $this->smsService->sendNotification($user['phone'], $message);
        }
        
        return $results;
    }
    
    public function sendOTP($phone) {
        $otp = $this->smsService->generateOTP();
        $result = $this->smsService->sendOTP($phone, $otp);
        
        if ($result['success']) {
            // Store OTP in session for verification
            session_start();
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_phone'] = $phone;
            $_SESSION['otp_time'] = time();
        }
        
        return $result;
    }
    
    public function verifyOTP($phone, $otp) {
        session_start();
        
        if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_phone']) || !isset($_SESSION['otp_time'])) {
            return ['success' => false, 'message' => 'No OTP found'];
        }
        
        // Check if OTP expired (5 minutes)
        if (time() - $_SESSION['otp_time'] > 300) {
            unset($_SESSION['otp'], $_SESSION['otp_phone'], $_SESSION['otp_time']);
            return ['success' => false, 'message' => 'OTP expired'];
        }
        
        if ($_SESSION['otp'] === $otp && $_SESSION['otp_phone'] === $phone) {
            unset($_SESSION['otp'], $_SESSION['otp_phone'], $_SESSION['otp_time']);
            return ['success' => true, 'message' => 'OTP verified'];
        }
        
        return ['success' => false, 'message' => 'Invalid OTP'];
    }
    
    private function getUser($userId) {
        $stmt = $this->db->prepare("SELECT user_id, full_name, email, phone FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    private function logNotification($userId, $type, $purpose, $success) {
        try {
            $stmt = $this->db->prepare("INSERT INTO notification_logs (user_id, notification_type, purpose, success, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$userId, $type, $purpose, $success ? 1 : 0]);
        } catch (Exception $e) {
            error_log("Notification log error: " . $e->getMessage());
        }
    }
}
?>