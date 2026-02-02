<?php
/**
 * Credential Generator - Auto-generate LMS Credentials
 * File: admin/credential-generator.php
 */

session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

$db = (new Database())->getConnection();
$message = '';
$messageType = '';
$generatedCredentials = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_single') {
        try {
            $auth = new Authentication();
            
            // Generate random password
            $password = generateSecurePassword();
            
            $userData = [
                'full_name' => trim($_POST['full_name']),
                'email' => trim($_POST['email']),
                'cnic' => trim($_POST['cnic']),
                'phone' => trim($_POST['phone'] ?? ''),
                'password' => $password,
                'user_type' => $_POST['user_type'],
                'account_status' => 'active'
            ];
            
            $result = $auth->register($userData);
            
            if ($result['success']) {
                $generatedCredentials[] = [
                    'full_name' => $userData['full_name'],
                    'username' => $result['data']['username'],
                    'password' => $password,
                    'email' => $userData['email'],
                    'user_type' => $userData['user_type']
                ];
                $message = 'Credentials generated successfully!';
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'generate_bulk') {
        $count = (int)$_POST['bulk_count'];
        $userType = $_POST['bulk_user_type'];
        $prefix = $_POST['name_prefix'] ?? 'User';
        
        try {
            $auth = new Authentication();
            
            for ($i = 1; $i <= $count; $i++) {
                $password = generateSecurePassword();
                $userData = [
                    'full_name' => $prefix . ' ' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'email' => strtolower($prefix) . $i . '@temp.skillsway.edu.pk',
                    'cnic' => '00000-0000000-' . $i,
                    'password' => $password,
                    'user_type' => $userType,
                    'account_status' => 'active'
                ];
                
                $result = $auth->register($userData);
                
                if ($result['success']) {
                    $generatedCredentials[] = [
                        'full_name' => $userData['full_name'],
                        'username' => $result['data']['username'],
                        'password' => $password,
                        'email' => $userData['email'],
                        'user_type' => $userData['user_type']
                    ];
                }
            }
            
            $message = count($generatedCredentials) . ' credentials generated successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    return substr(str_shuffle($chars), 0, $length);
}

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-key me-2"></i>Credential Generator</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Single User Generation -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user me-1"></i>Generate Single User</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_single">
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">CNIC *</label>
                            <input type="text" name="cnic" class="form-control" required 
                                   pattern="[0-9]{5}-[0-9]{7}-[0-9]{1}" placeholder="12345-1234567-1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="03001234567">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">User Type *</label>
                            <select name="user_type" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-magic me-1"></i>Generate Credentials
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bulk Generation -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-users me-1"></i>Bulk Generation</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_bulk">
                        
                        <div class="mb-3">
                            <label class="form-label">Number of Users *</label>
                            <input type="number" name="bulk_count" class="form-control" required min="1" max="100" value="10">
                            <small class="text-muted">Maximum 100 users at once</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Name Prefix *</label>
                            <input type="text" name="name_prefix" class="form-control" required value="Student" placeholder="e.g., Student, Teacher">
                            <small class="text-muted">Will generate: Prefix 001, Prefix 002, etc.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">User Type *</label>
                            <select name="bulk_user_type" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Bulk generated users will have temporary emails and CNICs that need to be updated later.
                        </div>
                        
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="fas fa-bolt me-1"></i>Generate Bulk Credentials
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Generated Credentials -->
    <?php if (!empty($generatedCredentials)): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-list me-1"></i>Generated Credentials</h6>
            <div>
                <button class="btn btn-outline-primary btn-sm" onclick="exportCredentials()">
                    <i class="fas fa-download me-1"></i>Export CSV
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="printCredentials()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="credentialsTable">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Email</th>
                            <th>User Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generatedCredentials as $cred): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cred['full_name']); ?></td>
                            <td><code><?php echo htmlspecialchars($cred['username']); ?></code></td>
                            <td>
                                <span class="password-field" data-password="<?php echo htmlspecialchars($cred['password']); ?>">
                                    ••••••••••••
                                </span>
                                <button class="btn btn-sm btn-outline-secondary ms-1" onclick="togglePassword(this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                            <td><?php echo htmlspecialchars($cred['email']); ?></td>
                            <td><span class="badge bg-primary"><?php echo ucfirst($cred['user_type']); ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="copyCredentials(this)" 
                                        data-username="<?php echo htmlspecialchars($cred['username']); ?>"
                                        data-password="<?php echo htmlspecialchars($cred['password']); ?>">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<script>
function togglePassword(btn) {
    const passwordField = btn.previousElementSibling;
    const icon = btn.querySelector('i');
    
    if (passwordField.textContent === '••••••••••••') {
        passwordField.textContent = passwordField.dataset.password;
        icon.className = 'fas fa-eye-slash';
    } else {
        passwordField.textContent = '••••••••••••';
        icon.className = 'fas fa-eye';
    }
}

function copyCredentials(btn) {
    const username = btn.dataset.username;
    const password = btn.dataset.password;
    const text = `Username: ${username}\nPassword: ${password}`;
    
    navigator.clipboard.writeText(text).then(() => {
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-copy"></i>';
        }, 2000);
    });
}

function exportCredentials() {
    const table = document.getElementById('credentialsTable');
    let csv = 'Full Name,Username,Password,Email,User Type\n';
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const password = cells[2].querySelector('.password-field').dataset.password;
        csv += `"${cells[0].textContent}","${cells[1].textContent}","${password}","${cells[3].textContent}","${cells[4].textContent.trim()}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'credentials_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
}

function printCredentials() {
    window.print();
}

// CNIC formatting
document.querySelector('input[name="cnic"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 5) value = value.substring(0,5) + '-' + value.substring(5);
    if (value.length >= 13) value = value.substring(0,13) + '-' + value.substring(13,14);
    e.target.value = value;
});
</script>

<?php require_once 'includes/footer.php'; ?>