<?php
/**
 * Email Configuration - Simplified Version
 */

class EmailService {
    private $config;
    
    public function __construct() {
        $this->config = [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'your-email@gmail.com', // Configure
            'smtp_password' => 'your-app-password', // Configure
            'from_email' => 'noreply@skillsway.edu.pk',
            'from_name' => 'Skills Way Institute'
        ];
    }
    
    public function sendCredentials($email, $username, $password, $userType) {
        $subject = 'Your Skills Way Login Credentials';
        $message = $this->getCredentialTemplate($username, $password, $userType);
        
        return $this->sendEmail($email, $subject, $message);
    }
    
    public function sendNotification($email, $subject, $message) {
        $htmlMessage = $this->getNotificationTemplate($subject, $message);
        return $this->sendEmail($email, $subject, $htmlMessage);
    }
    
    private function sendEmail($to, $subject, $message) {
        // Use PHP's built-in mail function for now
        // In production, replace with PHPMailer
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>',
            'Reply-To: ' . $this->config['from_email'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        
        return [
            'success' => $result,
            'message' => $result ? 'Email sent successfully' : 'Failed to send email'
        ];
    }
    
    private function getCredentialTemplate($username, $password, $userType) {
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #28a745; color: white; padding: 20px; text-align: center;'>
                <h2>Skills Way Institute</h2>
                <p>Your Login Credentials</p>
            </div>
            <div style='padding: 20px; background: #f8f9fa;'>
                <h3>Welcome to Skills Way!</h3>
                <p>Your account has been created successfully. Here are your login credentials:</p>
                
                <div style='background: white; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <p><strong>Username:</strong> $username</p>
                    <p><strong>Password:</strong> $password</p>
                    <p><strong>Account Type:</strong> " . ucfirst($userType) . "</p>
                </div>
                
                <p><strong>Login URL:</strong> <a href='https://skillsway.edu.pk/login.php'>https://skillsway.edu.pk/login.php</a></p>
                
                <div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 15px 0;'>
                    <p><strong>Security Note:</strong> Please change your password after first login.</p>
                </div>
            </div>
            <div style='background: #6c757d; color: white; padding: 10px; text-align: center; font-size: 12px;'>
                <p>Skills Way Institute - Empowering Skills for Tomorrow</p>
            </div>
        </div>";
    }
    
    private function getNotificationTemplate($subject, $message) {
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #007bff; color: white; padding: 20px; text-align: center;'>
                <h2>Skills Way Institute</h2>
            </div>
            <div style='padding: 20px; background: #f8f9fa;'>
                <h3>$subject</h3>
                <div style='background: white; padding: 15px; border-radius: 5px;'>
                    $message
                </div>
            </div>
            <div style='background: #6c757d; color: white; padding: 10px; text-align: center; font-size: 12px;'>
                <p>Skills Way Institute - Empowering Skills for Tomorrow</p>
            </div>
        </div>";
    }
}
?>