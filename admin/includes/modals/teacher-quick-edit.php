<?php
/**
 * Quick Edit Teacher Modal
 * Allows quick editing of teacher details without page reload
 */
?>
<div class="modal fade" id="quickEditModal" tabindex="-1" aria-labelledby="quickEditModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="quickEditModalLabel">
                    <i class="fas fa-user-edit me-2"></i>Quick Edit Teacher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="quickEditForm" class="needs-validation" novalidate>
                    <input type="hidden" id="quickEditTeacherId" name="teacher_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    
                    <div class="alert alert-info d-flex align-items-center mb-4">
                        <i class="fas fa-info-circle me-3 fa-2x"></i>
                        <div>
                            <strong>Quick Edit Mode</strong>
                            <p class="mb-0 small">Edit basic information quickly. Use full edit for detailed changes.</p>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-user me-2 text-primary"></i>Personal Information
                            </h6>
                            
                            <div class="mb-3">
                                <label for="quickEditFullName" class="form-label">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="quickEditFullName" name="full_name" required>
                                <div class="invalid-feedback">Please enter teacher's full name.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quickEditEmail" class="form-label">
                                    Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="quickEditEmail" name="email" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quickEditPhone" class="form-label">Phone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" class="form-control" id="quickEditPhone" name="phone" 
                                           placeholder="0300-1234567" pattern="[\d\s\-\(\)]{10,15}">
                                </div>
                                <div class="form-text">Format: 0300-1234567 or 051-1234567</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quickEditCnic" class="form-label">CNIC</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" id="quickEditCnic" name="cnic" 
                                           placeholder="12345-1234567-1" pattern="\d{5}-\d{7}-\d{1}">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Professional Information -->
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-briefcase me-2 text-success"></i>Professional Information
                            </h6>
                            
                            <div class="mb-3">
                                <label for="quickEditStatus" class="form-label">Account Status</label>
                                <select class="form-select" id="quickEditStatus" name="account_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="pending_verification">Pending Verification</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quickEditExperience" class="form-label">Years of Experience</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quickEditExperience" 
                                           name="years_experience" min="0" max="50" step="0.5">
                                    <span class="input-group-text">years</span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quickEditEducation" class="form-label">Education Level</label>
                                <select class="form-select" id="quickEditEducation" name="education_level">
                                    <option value="">Select Education Level</option>
                                    <option value="matric">Matric</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="bachelor">Bachelor's Degree</option>
                                    <option value="master">Master's Degree</option>
                                    <option value="phd">PhD</option>
                                    <option value="diploma">Diploma/Certification</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quickEditSpecialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="quickEditSpecialization" name="specialization">
                            </div>
                        </div>
                        
                        <!-- Additional Information -->
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-info-circle me-2 text-info"></i>Additional Information
                            </h6>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Employment Type</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="is_full_time" id="fullTimeYes" value="1">
                                            <label class="form-check-label" for="fullTimeYes">Full Time</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="is_full_time" id="fullTimeNo" value="0">
                                            <label class="form-check-label" for="fullTimeNo">Part Time</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="quickEditHourlyRate" class="form-label">Hourly Rate</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rs.</span>
                                            <input type="number" class="form-control" id="quickEditHourlyRate" 
                                                   name="hourly_rate" min="0" step="50">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="quickEditMonthlySalary" class="form-label">Monthly Salary</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rs.</span>
                                            <input type="number" class="form-control" id="quickEditMonthlySalary" 
                                                   name="monthly_salary" min="0" step="1000">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quickEditAddress" class="form-label">Address</label>
                                <textarea class="form-control" id="quickEditAddress" name="address" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quickEditBio" class="form-label">Bio/Notes</label>
                                <textarea class="form-control" id="quickEditBio" name="bio" rows="2" 
                                          placeholder="Brief description or notes about the teacher..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview Section -->
                    <div class="card mt-4 d-none" id="previewCard">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-eye me-2"></i>Preview Changes
                            </h6>
                        </div>
                        <div class="card-body" id="previewContent">
                            <!-- Preview will be loaded here -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-outline-primary" id="previewChangesBtn">
                    <i class="fas fa-eye me-1"></i> Preview Changes
                </button>
                <button type="button" class="btn btn-primary" id="saveQuickEditBtn">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Quick Edit Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const quickEditModal = document.getElementById('quickEditModal');
    const quickEditForm = document.getElementById('quickEditForm');
    const previewChangesBtn = document.getElementById('previewChangesBtn');
    const saveQuickEditBtn = document.getElementById('saveQuickEditBtn');
    const previewCard = document.getElementById('previewCard');
    const previewContent = document.getElementById('previewContent');
    
    let currentTeacherData = {};
    
    // Initialize form validation
    const validation = new bootstrapValidation(quickEditForm);
    
    // Open modal with teacher data
    window.openQuickEditModal = function(teacherId) {
        // Show loading state
        quickEditForm.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-2">Loading teacher data...</p></div>';
        
        const modal = new bootstrap.Modal(quickEditModal);
        modal.show();
        
        // Fetch teacher data
        fetch(`get-teacher-data.php?id=${teacherId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentTeacherData = data.data;
                    populateQuickEditForm(data.data);
                    setupFormListeners();
                } else {
                    showToast('Failed to load teacher data', 'error');
                    modal.hide();
                }
            })
            .catch(error => {
                showToast('Network error occurred', 'error');
                modal.hide();
            });
    };
    
    // Populate form with teacher data
    function populateQuickEditForm(teacher) {
        // Personal Information
        document.getElementById('quickEditTeacherId').value = teacher.user_id;
        document.getElementById('quickEditFullName').value = teacher.full_name || '';
        document.getElementById('quickEditEmail').value = teacher.email || '';
        document.getElementById('quickEditPhone').value = teacher.phone || '';
        document.getElementById('quickEditCnic').value = teacher.cnic || '';
        
        // Professional Information
        document.getElementById('quickEditStatus').value = teacher.account_status || 'active';
        document.getElementById('quickEditExperience').value = teacher.years_experience || 0;
        document.getElementById('quickEditEducation').value = teacher.education_level || '';
        document.getElementById('quickEditSpecialization').value = teacher.specialization || '';
        
        // Additional Information
        if (teacher.is_full_time !== undefined) {
            document.getElementById(teacher.is_full_time ? 'fullTimeYes' : 'fullTimeNo').checked = true;
        }
        document.getElementById('quickEditHourlyRate').value = teacher.hourly_rate || 0;
        document.getElementById('quickEditMonthlySalary').value = teacher.monthly_salary || 0;
        document.getElementById('quickEditAddress').value = teacher.address || '';
        document.getElementById('quickEditBio').value = teacher.bio || '';
    }
    
    // Setup form change listeners
    function setupFormListeners() {
        // Auto-format phone number
        const phoneInput = document.getElementById('quickEditPhone');
        phoneInput.addEventListener('input', function(e) {
            formatPhoneNumber(this);
        });
        
        // Auto-format CNIC
        const cnicInput = document.getElementById('quickEditCnic');
        cnicInput.addEventListener('input', function(e) {
            formatCNIC(this);
        });
        
        // Preview button
        previewChangesBtn.addEventListener('click', generatePreview);
        
        // Save button
        saveQuickEditBtn.addEventListener('click', saveQuickEdit);
    }
    
    // Format phone number
    function formatPhoneNumber(input) {
        let value = input.value.replace(/\D/g, '');
        
        if (value.length > 0) {
            if (value.length <= 4) {
                input.value = value;
            } else if (value.length <= 11) {
                input.value = value.substring(0, 4) + '-' + value.substring(4);
            } else {
                input.value = value.substring(0, 4) + '-' + value.substring(4, 11);
            }
        }
    }
    
    // Format CNIC
    function formatCNIC(input) {
        let value = input.value.replace(/\D/g, '');
        
        if (value.length > 0) {
            if (value.length <= 5) {
                input.value = value;
            } else if (value.length <= 12) {
                input.value = value.substring(0, 5) + '-' + value.substring(5);
            } else {
                input.value = value.substring(0, 5) + '-' + value.substring(5, 12) + '-' + value.substring(12, 13);
            }
        }
    }
    
    // Generate preview of changes
    function generatePreview() {
        if (!quickEditForm.checkValidity()) {
            quickEditForm.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(quickEditForm);
        const changes = [];
        
        // Compare with original data
        for (let [key, value] of formData.entries()) {
            const originalValue = currentTeacherData[key] || '';
            const newValue = value || '';
            
            if (String(originalValue).trim() !== String(newValue).trim()) {
                changes.push({
                    field: formatFieldName(key),
                    original: originalValue || '(empty)',
                    new: newValue
                });
            }
        }
        
        if (changes.length === 0) {
            previewCard.classList.add('d-none');
            showToast('No changes detected', 'info');
            return;
        }
        
        // Build preview HTML
        let previewHTML = '<div class="changes-list">';
        
        changes.forEach(change => {
            previewHTML += `
                <div class="change-item mb-3 p-3 border rounded">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <strong>${change.field}</strong>
                        <span class="badge bg-warning">Changed</span>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Previous Value:</small>
                            <div class="bg-light p-2 rounded">${escapeHtml(change.original)}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">New Value:</small>
                            <div class="bg-success bg-opacity-10 p-2 rounded border border-success">
                                ${escapeHtml(change.new)}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        previewHTML += '</div>';
        previewContent.innerHTML = previewHTML;
        previewCard.classList.remove('d-none');
        
        // Scroll to preview
        previewCard.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Save quick edit changes
    function saveQuickEdit() {
        if (!quickEditForm.checkValidity()) {
            quickEditForm.classList.add('was-validated');
            return;
        }
        
        const teacherId = document.getElementById('quickEditTeacherId').value;
        const formData = new FormData(quickEditForm);
        
        // Show loading state
        saveQuickEditBtn.disabled = true;
        saveQuickEditBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        
        // Send AJAX request
        fetch('teacher-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            },
            body: JSON.stringify({
                action: 'quick_edit',
                teacher_id: teacherId,
                data: Object.fromEntries(formData)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Teacher updated successfully', 'success');
                
                // Close modal
                bootstrap.Modal.getInstance(quickEditModal).hide();
                
                // Refresh teacher row or page
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showToast(data.message || 'Failed to update teacher', 'error');
                saveQuickEditBtn.disabled = false;
                saveQuickEditBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Changes';
            }
        })
        .catch(error => {
            showToast('Network error occurred', 'error');
            saveQuickEditBtn.disabled = false;
            saveQuickEditBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Changes';
        });
    }
    
    // Helper function to format field names
    function formatFieldName(field) {
        const fieldMap = {
            'full_name': 'Full Name',
            'email': 'Email',
            'phone': 'Phone',
            'cnic': 'CNIC',
            'account_status': 'Account Status',
            'years_experience': 'Years of Experience',
            'education_level': 'Education Level',
            'specialization': 'Specialization',
            'is_full_time': 'Employment Type',
            'hourly_rate': 'Hourly Rate',
            'monthly_salary': 'Monthly Salary',
            'address': 'Address',
            'bio': 'Bio/Notes'
        };
        
        return fieldMap[field] || field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Modal events
    quickEditModal.addEventListener('hidden.bs.modal', function() {
        // Reset form
        quickEditForm.reset();
        quickEditForm.classList.remove('was-validated');
        previewCard.classList.add('d-none');
        currentTeacherData = {};
    });
    
    quickEditModal.addEventListener('shown.bs.modal', function() {
        // Focus on first input
        document.getElementById('quickEditFullName').focus();
    });
});

// Bootstrap Validation Class
class bootstrapValidation {
    constructor(form) {
        this.form = form;
        this.setupValidation();
    }
    
    setupValidation() {
        this.form.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        }, false);
        
        // Real-time validation
        const inputs = this.form.querySelectorAll('input[required], select[required], textarea[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                this.checkValidity();
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    this.checkValidity();
                }
            });
        });
    }
    
    validate() {
        return this.form.checkValidity();
    }
    
    reset() {
        this.form.classList.remove('was-validated');
    }
}
</script>

<style>
/* Quick Edit Modal Styles */
.change-item {
    transition: all 0.3s ease;
}

.change-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

/* Smooth transitions */
.modal-content {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Loading animation */
.spinner-border {
    width: 3rem;
    height: 3rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .change-item .row > div {
        margin-bottom: 0.5rem;
    }
}
</style>