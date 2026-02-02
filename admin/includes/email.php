<?php
/**
 * Email Notification System
 * File: admin/includes/email.php
 */

class EmailNotification {
    private $db;
    private $config;
    
    public function __construct($db) {
        $this->db = $db;
        $this->config = [
            'from_email' => 'noreply@skillsway.edu.pk',
            'from_name' => 'Skills Way Vocational Institute',
            'smtp_host' => 'localhost',
            'smtp_port' => 25,
            'smtp_auth' => false,
            'smtp_secure' => ''
        ];
        
        // Load email settings from database
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $query = "SELECT setting_key, setting_value FROM institute_settings 
                  WHERE setting_key LIKE 'email_%'";
        $result = $this->db->query($query);
        
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $key = str_replace('email_', '', $row['setting_key']);
            $this->config[$key] = $row['setting_value'];
        }
    }
    
    /**
     * Send application status email
     */
    public function sendApplicationStatus($application_id, $status, $notes = '') {
        // Get application details
        $query = "
            SELECT u.email, u.full_name, c.course_name, 
                   ea.application_id, ea.application_status
            FROM enrollment_applications ea
            JOIN users u ON ea.user_id = u.user_id
            JOIN courses c ON ea.course_id = c.course_id
            WHERE ea.application_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$application_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }
        
        // Prepare email content
        $subject = "Application Status Update - Skills Way Vocational Institute";
        
        $body = $this->getEmailTemplate('application_status', [
            'full_name' => $result['full_name'],
            'course_name' => $result['course_name'],
            'application_id' => str_pad($application_id, 6, '0', STR_PAD_LEFT),
            'status' => ucfirst(str_replace('_', ' ', $status)),
            'notes' => $notes,
            'date' => date('F d, Y'),
            'institute_name' => 'Skills Way Vocational Institute',
            'contact_email' => 'askillswaykpr@gmail.com',
            'contact_phone' => '0307-0237356'
        ]);
        
        // Send email
        return $this->sendEmail($result['email'], $subject, $body);
    }
    
    /**
     * Send login credentials email
     */
    public function sendCredentials($user_id, $username, $password) {
        $query = "SELECT email, full_name FROM users WHERE user_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        $subject = "Your Login Credentials - Skills Way Vocational Institute";
        
        $body = $this->getEmailTemplate('credentials', [
            'full_name' => $user['full_name'],
            'username' => $username,
            'password' => $password,
            'login_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/login.php',
            'institute_name' => 'Skills Way Vocational Institute',
            'support_email' => 'askillswaykpr@gmail.com'
        ]);
        
        return $this->sendEmail($user['email'], $subject, $body);
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($template, $data) {
        $templates = [
            'application_status' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <div style="background: #4361ee; color: white; padding: 20px; text-align: center;">
                            <h1>{institute_name}</h1>
                        </div>
                        <div style="padding: 30px; background: #f8f9fa;">
                            <h2>Dear {full_name},</h2>
                            <p>This email is regarding your application for <strong>{course_name}</strong>.</p>
                            
                            <div style="padding: 10px; border-radius: 5px; margin: 20px 0; background: #d4edda; border-left: 4px solid #28a745;">
                                <h3>Application Status: {status}</h3>
                                <p><strong>Application ID:</strong> SW{application_id}</p>
                                <p><strong>Date:</strong> {date}</p>
                            </div>
                            
                            <p>You can check your application status anytime by contacting our admissions office.</p>
                        </div>
                        <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                            <p><strong>{institute_name}</strong><br>
                            Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur<br>
                            Phone: {contact_phone} | Email: {contact_email}</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            
            'credentials' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <div style="background: #6f42c1; color: white; padding: 20px; text-align: center;">
                            <h1>Your Student Portal Access</h1>
                        </div>
                        <div style="background: #f8f9fa; padding: 30px; margin: 20px 0; border-radius: 10px;">
                            <h2>Welcome to {institute_name}, {full_name}!</h2>
                            <p>Your student account has been created. Here are your login credentials:</p>
                            
                            <div style="background: white; padding: 20px; border: 2px dashed #6f42c1; border-radius: 5px; margin: 20px 0;">
                                <h3>Login Details</h3>
                                <p><strong>Portal URL:</strong> {login_url}</p>
                                <p><strong>Username:</strong> <code>{username}</code></p>
                                <p><strong>Password:</strong> <code>{password}</code></p>
                            </div>
                            
                            <p>If you face any issues logging in, please contact our support team at {support_email}.</p>
                        </div>
                        <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                            <p><strong>{institute_name}</strong><br>
                            This is an automated email containing sensitive information.</p>
                        </div>
                    </div>
                </body>
                </html>
            '
        ];
        
        $template = $templates[$template] ?? '';
        
        // Replace placeholders
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Send email using PHP mail() function
     */
    private function sendEmail($to, $subject, $body) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: "' . $this->config['from_name'] . '" <' . $this->config['from_email'] . '>',
            'Reply-To: ' . $this->config['from_email'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        try {
            $success = mail($to, $subject, $body, implode("\r\n", $headers));
            return $success;
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }
}
?>