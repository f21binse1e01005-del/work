<?php
/**
 * Bulk Email Modal
 * Send emails to multiple teachers at once
 */
?>
<div class="modal fade" id="bulkEmailModal" tabindex="-1" aria-labelledby="bulkEmailModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bulkEmailModalLabel">
                    <i class="fas fa-envelope me-2"></i>Bulk Email to Teachers
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Left Column - Recipients -->
                    <div class="col-md-4 border-end">
                        <h6 class="mb-3">
                            <i class="fas fa-users me-2"></i>Recipients
                            <span class="badge bg-primary ms-2" id="recipientCount">0</span>
                        </h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Recipients</label>
                            <div class="list-group" id="recipientList" style="max-height: 300px; overflow-y: auto;">
                                <!-- Recipients will be populated here -->
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Recipient Groups</label>
                            <select class="form-select" id="recipientGroups">
                                <option value="">Select a group</option>
                                <option value="active">Active Teachers</option>
                                <option value="inactive">Inactive Teachers</option>
                                <option value="pending">Pending Verification</option>
                                <option value="full_time">Full Time Teachers</option>
                                <option value="part_time">Part Time Teachers</option>
                            </select>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="selectAllRecipients">
                            <label class="form-check-label" for="selectAllRecipients">
                                Select All Recipients
                            </label>
                        </div>
                        
                        <div class="alert alert-info small">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Emails will be sent individually to maintain privacy.
                        </div>
                    </div>
                    
                    <!-- Right Column - Email Composition -->
                    <div class="col-md-8">
                        <form id="bulkEmailForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <input type="hidden" name="recipient_ids" id="recipientIds">
                            
                            <!-- Email Template Selection -->
                            <div class="mb-4">
                                <label class="form-label">Email Template</label>
                                <div class="input-group">
                                    <select class="form-select" id="emailTemplate">
                                        <option value="">Custom Message</option>
                                        <option value="welcome">Welcome Email</option>
                                        <option value="schedule_update">Schedule Update</option>
                                        <option value="payment_reminder">Payment Reminder</option>
                                        <option value="meeting_announcement">Meeting Announcement</option>
                                        <option value="training_notice">Training Notice</option>
                                        <option value="holiday_notice">Holiday Notice</option>
                                    </select>
                                    <button class="btn btn-outline-secondary" type="button" id="loadTemplateBtn">
                                        <i class="fas fa-download"></i> Load
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Subject -->
                            <div class="mb-3">
                                <label for="emailSubject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="emailSubject" name="subject" required 
                                       placeholder="Enter email subject...">
                            </div>
                            
                            <!-- Message Editor -->
                            <div class="mb-3">
                                <label for="emailMessage" class="form-label">Message <span class="text-danger">*</span></label>
                                
                                <!-- Email Editor Toolbar -->
                                <div class="email-toolbar mb-2 border rounded p-2 bg-light">
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('bold')" title="Bold">
                                            <i class="fas fa-bold"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('italic')" title="Italic">
                                            <i class="fas fa-italic"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('underline')" title="Underline">
                                            <i class="fas fa-underline"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertVariable('teacher_name')" title="Insert Teacher Name">
                                            <i class="fas fa-user"></i> Name
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertVariable('institute_name')" title="Insert Institute Name">
                                            <i class="fas fa-school"></i> Institute
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertVariable('current_date')" title="Insert Current Date">
                                            <i class="fas fa-calendar"></i> Date
                                        </button>
                                    </div>
                                    
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearEditor()" title="Clear Editor">
                                            <i class="fas fa-eraser"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="previewEmail()" title="Preview Email">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Message Editor -->
                                <textarea class="form-control" id="emailMessage" name="message" rows="12" required
                                          placeholder="Type your message here... You can use variables like {teacher_name}, {institute_name}, etc."></textarea>
                                
                                <div class="form-text">
                                    Available variables: {teacher_name}, {teacher_email}, {teacher_code}, {institute_name}, {current_date}
                                </div>
                            </div>
                            
                            <!-- Attachments -->
                            <div class="mb-3">
                                <label class="form-label">Attachments</label>
                                <div class="file-upload-area border rounded p-3 text-center">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <p class="mb-2">Drag & drop files here or click to browse</p>
                                    <input type="file" class="d-none" id="fileUpload" multiple>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('fileUpload').click()">
                                        <i class="fas fa-folder-open me-1"></i> Browse Files
                                    </button>
                                    <div class="mt-2 small text-muted">Max file size: 10MB each</div>
                                    
                                    <!-- File List -->
                                    <div class="mt-3" id="fileList"></div>
                                </div>
                            </div>
                            
                            <!-- Email Options -->
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="sendCopy" name="send_copy">
                                        <label class="form-check-label" for="sendCopy">
                                            Send copy to admin
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="urgentEmail" name="urgent">
                                        <label class="form-check-label" for="urgentEmail">
                                            Mark as urgent
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="readReceipt" name="read_receipt">
                                        <label class="form-check-label" for="readReceipt">
                                            Request read receipt
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="scheduleEmail" name="schedule">
                                        <label class="form-check-label" for="scheduleEmail">
                                            Schedule email
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Schedule Options (Hidden by Default) -->
                            <div class="mt-3 p-3 border rounded bg-light d-none" id="scheduleOptions">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="scheduleDate" class="form-label">Schedule Date</label>
                                        <input type="date" class="form-control" id="scheduleDate" name="schedule_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="scheduleTime" class="form-label">Schedule Time</label>
                                        <input type="time" class="form-control" id="scheduleTime" name="schedule_time">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Preview Modal -->
                <div class="modal fade" id="emailPreviewModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Email Preview</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="previewContent">
                                <!-- Preview will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-outline-primary" id="testEmailBtn">
                    <i class="fas fa-paper-plane me-1"></i> Send Test
                </button>
                <button type="button" class="btn btn-primary" id="sendBulkEmailBtn">
                    <i class="fas fa-paper-plane me-1"></i> Send to Selected
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Bulk Email Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const bulkEmailModal = document.getElementById('bulkEmailModal');
    const bulkEmailForm = document.getElementById('bulkEmailForm');
    const recipientList = document.getElementById('recipientList');
    const recipientCount = document.getElementById('recipientCount');
    const selectAllRecipients = document.getElementById('selectAllRecipients');
    const recipientGroups = document.getElementById('recipientGroups');
    const emailTemplate = document.getElementById('emailTemplate');
    const loadTemplateBtn = document.getElementById('loadTemplateBtn');
    const scheduleEmail = document.getElementById('scheduleEmail');
    const scheduleOptions = document.getElementById('scheduleOptions');
    const testEmailBtn = document.getElementById('testEmailBtn');
    const sendBulkEmailBtn = document.getElementById('sendBulkEmailBtn');
    const fileUpload = document.getElementById('fileUpload');
    const fileList = document.getElementById('fileList');
    
    let selectedTeachers = [];
    let uploadedFiles = [];
    
    // Open modal with selected teachers
    window.openBulkEmailModal = function(teacherIds) {
        selectedTeachers = Array.isArray(teacherIds) ? teacherIds : teacherIds.split(',');
        
        if (selectedTeachers.length === 0) {
            showToast('Please select teachers first', 'warning');
            return;
        }
        
        // Load teacher data
        loadTeacherData(selectedTeachers);
        
        const modal = new bootstrap.Modal(bulkEmailModal);
        modal.show();
    };
    
    // Load teacher data for the list
    function loadTeacherData(teacherIds) {
        // Clear existing list
        recipientList.innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div><p class="mt-2">Loading teachers...</p></div>';
        
        // Fetch teacher data
        fetch('get-teachers-data.php?ids=' + teacherIds.join(','))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderRecipientList(data.teachers);
                } else {
                    showToast('Failed to load teacher data', 'error');
                }
            })
            .catch(error => {
                showToast('Network error occurred', 'error');
            });
    }
    
    // Render recipient list
    function renderRecipientList(teachers) {
        recipientList.innerHTML = '';
        
        teachers.forEach(teacher => {
            const listItem = document.createElement('div');
            listItem.className = 'list-group-item list-group-item-action';
            listItem.innerHTML = `
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <div class="form-check flex-grow-1">
                        <input class="form-check-input recipient-checkbox" type="checkbox" 
                               value="${teacher.user_id}" id="recipient_${teacher.user_id}" checked>
                        <label class="form-check-label" for="recipient_${teacher.user_id}">
                            <strong>${escapeHtml(teacher.full_name)}</strong>
                            <div class="small text-muted">${escapeHtml(teacher.email)}</div>
                        </label>
                    </div>
                    <span class="badge bg-${getStatusColor(teacher.account_status)}">
                        ${teacher.account_status}
                    </span>
                </div>
            `;
            
            recipientList.appendChild(listItem);
        });
        
        updateRecipientCount();
        setupRecipientCheckboxes();
    }
    
    // Setup recipient checkbox events
    function setupRecipientCheckboxes() {
        const checkboxes = recipientList.querySelectorAll('.recipient-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateRecipientCount);
        });
        
        // Select all checkbox
        selectAllRecipients.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateRecipientCount();
        });
    }
    
    // Update recipient count
    function updateRecipientCount() {
        const selectedCount = recipientList.querySelectorAll('.recipient-checkbox:checked').length;
        recipientCount.textContent = selectedCount;
        
        // Update hidden field with selected IDs
        const selectedIds = Array.from(recipientList.querySelectorAll('.recipient-checkbox:checked'))
            .map(cb => cb.value);
        document.getElementById('recipientIds').value = selectedIds.join(',');
        
        // Enable/disable send button
        sendBulkEmailBtn.disabled = selectedCount === 0;
    }
    
    // Load email template
    loadTemplateBtn.addEventListener('click', function() {
        const template = emailTemplate.value;
        if (!template) return;
        
        fetch(`get-email-template.php?type=${template}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('emailSubject').value = data.subject;
                    document.getElementById('emailMessage').value = data.message;
                    showToast('Template loaded successfully', 'success');
                } else {
                    showToast('Failed to load template', 'error');
                }
            })
            .catch(error => {
                showToast('Network error occurred', 'error');
            });
    });
    
    // Toggle schedule options
    scheduleEmail.addEventListener('change', function() {
        if (this.checked) {
            scheduleOptions.classList.remove('d-none');
            
            // Set default schedule (tomorrow 9 AM)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('scheduleDate').value = tomorrow.toISOString().split('T')[0];
            document.getElementById('scheduleTime').value = '09:00';
        } else {
            scheduleOptions.classList.add('d-none');
        }
    });
    
    // File upload handling
    fileUpload.addEventListener('change', handleFileUpload);
    
    function handleFileUpload(e) {
        const files = Array.from(e.target.files);
        
        files.forEach(file => {
            if (file.size > 10 * 1024 * 1024) { // 10MB limit
                showToast(`File "${file.name}" exceeds 10MB limit`, 'error');
                return;
            }
            
            uploadedFiles.push(file);
            addFileToList(file);
        });
        
        // Reset file input
        fileUpload.value = '';
    }
    
    function addFileToList(file) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item d-flex justify-content-between align-items-center p-2 border rounded mb-2';
        fileItem.innerHTML = `
            <div>
                <i class="fas fa-file me-2"></i>
                <span class="file-name">${escapeHtml(file.name)}</span>
                <small class="text-muted ms-2">(${formatFileSize(file.size)})</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile('${file.name}')">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        fileList.appendChild(fileItem);
    }
    
    window.removeFile = function(fileName) {
        uploadedFiles = uploadedFiles.filter(file => file.name !== fileName);
        
        // Remove from DOM
        const fileItems = fileList.querySelectorAll('.file-item');
        fileItems.forEach(item => {
            if (item.querySelector('.file-name').textContent === fileName) {
                item.remove();
            }
        });
    };
    
    // Send test email
    testEmailBtn.addEventListener('click', function() {
        if (!validateEmailForm()) return;
        
        const formData = new FormData(bulkEmailForm);
        formData.append('action', 'test_email');
        formData.append('test_email', '<?php echo $_SESSION['email'] ?? 'admin@skillsway.edu.pk'; ?>');
        
        // Add uploaded files
        uploadedFiles.forEach(file => {
            formData.append('attachments[]', file);
        });
        
        testEmailBtn.disabled = true;
        testEmailBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';
        
        fetch('send-email.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Test email sent successfully', 'success');
            } else {
                showToast('Failed to send test email: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Network error occurred', 'error');
        })
        .finally(() => {
            testEmailBtn.disabled = false;
            testEmailBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Test';
        });
    });
    
    // Send bulk email
    sendBulkEmailBtn.addEventListener('click', function() {
        if (!validateEmailForm()) return;
        
        const selectedCount = recipientList.querySelectorAll('.recipient-checkbox:checked').length;
        if (selectedCount === 0) {
            showToast('Please select at least one recipient', 'warning');
            return;
        }
        
        if (!confirm(`Send email to ${selectedCount} teacher(s)? This may take a few moments.`)) {
            return;
        }
        
        const formData = new FormData(bulkEmailForm);
        formData.append('action', 'bulk_email');
        
        // Add uploaded files
        uploadedFiles.forEach(file => {
            formData.append('attachments[]', file);
        });
        
        sendBulkEmailBtn.disabled = true;
        sendBulkEmailBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';
        
        fetch('send-email.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Emails sent successfully to ${data.sent_count} recipients`, 'success');
                
                // Log activity
                logBulkEmailActivity(selectedCount);
                
                // Close modal after delay
                setTimeout(() => {
                    bootstrap.Modal.getInstance(bulkEmailModal).hide();
                }, 2000);
            } else {
                showToast('Failed to send emails: ' + data.message, 'error');
                sendBulkEmailBtn.disabled = false;
                sendBulkEmailBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send to Selected';
            }
        })
        .catch(error => {
            showToast('Network error occurred', 'error');
            sendBulkEmailBtn.disabled = false;
            sendBulkEmailBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send to Selected';
        });
    });
    
    // Validate email form
    function validateEmailForm() {
        const subject = document.getElementById('emailSubject').value.trim();
        const message = document.getElementById('emailMessage').value.trim();
        
        if (!subject) {
            showToast('Please enter email subject', 'warning');
            document.getElementById('emailSubject').focus();
            return false;
        }
        
        if (!message) {
            showToast('Please enter email message', 'warning');
            document.getElementById('emailMessage').focus();
            return false;
        }
        
        return true;
    }
    
    // Log bulk email activity
    function logBulkEmailActivity(count) {
        fetch('log-activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            },
            body: JSON.stringify({
                action: 'bulk_email_sent',
                count: count,
                subject: document.getElementById('emailSubject').value
            })
        });
    }
    
    // Helper functions
    function getStatusColor(status) {
        const colors = {
            'active': 'success',
            'inactive': 'secondary',
            'suspended': 'danger',
            'pending_verification': 'warning'
        };
        return colors[status] || 'secondary';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Modal events
    bulkEmailModal.addEventListener('hidden.bs.modal', function() {
        // Reset form
        bulkEmailForm.reset();
        recipientList.innerHTML = '';
        recipientCount.textContent = '0';
        uploadedFiles = [];
        fileList.innerHTML = '';
        selectedTeachers = [];
        scheduleOptions.classList.add('d-none');
    });
});

// Email Editor Functions
function formatText(command) {
    document.getElementById('emailMessage').focus();
    document.execCommand(command, false, null);
}

function insertVariable(variable) {
    const textarea = document.getElementById('emailMessage');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const variableText = `{${variable}}`;
    
    textarea.value = text.substring(0, start) + variableText + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + variableText.length;
    textarea.focus();
}

function clearEditor() {
    if (confirm('Are you sure you want to clear the editor?')) {
        document.getElementById('emailMessage').value = '';
    }
}

function previewEmail() {
    const subject = document.getElementById('emailSubject').value;
    const message = document.getElementById('emailMessage').value;
    
    if (!subject || !message) {
        showToast('Please enter subject and message first', 'warning');
        return;
    }
    
    const previewContent = document.getElementById('previewContent');
    previewContent.innerHTML = `
        <div class="email-preview">
            <div class="email-header bg-light p-3 border-bottom">
                <h6 class="mb-1"><strong>Subject:</strong> ${escapeHtml(subject)}</h6>
                <small class="text-muted"><strong>To:</strong> Selected Teachers</small>
            </div>
            <div class="email-body p-3">
                ${formatMessageForPreview(message)}
            </div>
            <div class="email-footer bg-light p-3 border-top small">
                <p class="mb-1"><strong>Note:</strong> This is a preview. Variables will be replaced when sent.</p>
            </div>
        </div>
    `;
    
    const previewModal = new bootstrap.Modal(document.getElementById('emailPreviewModal'));
    previewModal.show();
}

function formatMessageForPreview(message) {
    // Convert line breaks to <br>
    let formatted = escapeHtml(message).replace(/\n/g, '<br>');
    
    // Highlight variables
    formatted = formatted.replace(/\{([^}]+)\}/g, '<span class="badge bg-info">{$1}</span>');
    
    return formatted;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
/* Bulk Email Modal Styles */
.list-group-item {
    cursor: pointer;
    transition: all 0.2s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.list-group-item .form-check {
    margin-bottom: 0;
}

.file-upload-area {
    border: 2px dashed #dee2e6;
    transition: all 0.3s ease;
}

.file-upload-area:hover {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

.file-upload-area.dragover {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.1);
}

.file-item {
    background-color: #f8f9fa;
    transition: all 0.2s ease;
}

.file-item:hover {
    background-color: #e9ecef;
}

.email-toolbar {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
}

.email-toolbar .btn-group {
    flex-wrap: wrap;
}

.email-preview {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    overflow: hidden;
}

.email-preview .email-body {
    min-height: 200px;
    max-height: 400px;
    overflow-y: auto;
}

/* Custom scrollbar */
#recipientList::-webkit-scrollbar {
    width: 6px;
}

#recipientList::-webkit-scrollbar-track {
    background: #f1f1f1;
}

#recipientList::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

#recipientList::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Animation for file upload */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.file-item {
    animation: fadeIn 0.3s ease-out;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .border-end {
        border-right: none !important;
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
    }
}

@media (max-width: 768px) {
    .email-toolbar .btn-group {
        margin-bottom: 0.5rem;
    }
    
    .email-toolbar .btn-group-sm {
        flex-wrap: wrap;
    }
}
</style>