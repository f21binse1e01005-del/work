<?php
/**
 * Assign Course/Batch to Teacher Modal
 * Assign teachers to courses or batches
 */
?>
<div class="modal fade" id="assignCourseModal" tabindex="-1" aria-labelledby="assignCourseModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="assignCourseModalLabel">
                    <i class="fas fa-book-open me-2"></i>Assign Course/Batch to Teacher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="assignCourseForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="teacher_id" id="assignTeacherId">
                    
                    <div class="alert alert-info d-flex align-items-center mb-4">
                        <i class="fas fa-info-circle me-3 fa-2x"></i>
                        <div>
                            <strong>Assignment Information</strong>
                            <p class="mb-0 small">Assign a teacher to a course or specific batch. The system will check for schedule conflicts.</p>
                        </div>
                    </div>
                    
                    <!-- Teacher Information -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Teacher Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Selected Teacher</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="teacherNameDisplay" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Current Status</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-circle"></i></span>
                                            <input type="text" class="form-control" id="teacherStatusDisplay" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Current Assignments -->
                            <div class="mt-3" id="currentAssignments">
                                <small class="text-muted">Loading current assignments...</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assignment Type Selection -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-tasks me-2"></i>Assignment Type
                            </h6>
                            
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="assignment_type" id="assignCourse" value="course" checked>
                                <label class="btn btn-outline-primary" for="assignCourse">
                                    <i class="fas fa-book me-2"></i>Assign to Course
                                </label>
                                
                                <input type="radio" class="btn-check" name="assignment_type" id="assignBatch" value="batch">
                                <label class="btn btn-outline-primary" for="assignBatch">
                                    <i class="fas fa-calendar-alt me-2"></i>Assign to Specific Batch
                                </label>
                                
                                <input type="radio" class="btn-check" name="assignment_type" id="assignMultiple" value="multiple">
                                <label class="btn btn-outline-primary" for="assignMultiple">
                                    <i class="fas fa-layer-group me-2"></i>Assign to Multiple Batches
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Course Selection -->
                    <div class="card mb-4" id="courseSelectionCard">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-book me-2"></i>Course Selection
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="courseSelect" class="form-label">Select Course <span class="text-danger">*</span></label>
                                <select class="form-select" id="courseSelect" name="course_id" required>
                                    <option value="">Loading courses...</option>
                                </select>
                                <div class="form-text">Courses are filtered based on teacher's qualifications</div>
                            </div>
                            
                            <!-- Course Details -->
                            <div class="row g-3 d-none" id="courseDetails">
                                <div class="col-md-6">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted d-block">Course Code</small>
                                        <strong id="courseCodeDisplay"></strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted d-block">Duration</small>
                                        <strong id="courseDurationDisplay"></strong>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted d-block">Description</small>
                                        <p class="mb-0" id="courseDescriptionDisplay"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Batch Selection -->
                    <div class="card mb-4 d-none" id="batchSelectionCard">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Batch Selection
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="batchSelect" class="form-label">Select Batch <span class="text-danger">*</span></label>
                                        <select class="form-select" id="batchSelect" name="batch_id">
                                            <option value="">Select a batch...</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="batchStatus" class="form-label">Batch Status</label>
                                        <select class="form-select" id="batchStatus" name="batch_status">
                                            <option value="upcoming">Upcoming</option>
                                            <option value="ongoing">Ongoing</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Batch Details -->
                            <div class="row g-3 d-none" id="batchDetails">
                                <div class="col-md-4">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted d-block">Start Date</small>
                                        <strong id="batchStartDisplay"></strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted d-block">End Date</small>
                                        <strong id="batchEndDisplay"></strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted d-block">Schedule</small>
                                        <strong id="batchScheduleDisplay"></strong>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted d-block">Current Teacher</small>
                                        <p class="mb-0" id="batchTeacherDisplay">No teacher assigned</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Multiple Batches Selection -->
                    <div class="card mb-4 d-none" id="multipleBatchesCard">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-layer-group me-2"></i>Multiple Batches Selection
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Available Batches</label>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover" id="batchesTable">
                                        <thead>
                                            <tr>
                                                <th width="50">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="selectAllBatches">
                                                    </div>
                                                </th>
                                                <th>Batch Code</th>
                                                <th>Course</th>
                                                <th>Schedule</th>
                                                <th>Status</th>
                                                <th>Current Teacher</th>
                                            </tr>
                                        </thead>
                                        <tbody id="batchesTableBody">
                                            <tr>
                                                <td colspan="6" class="text-center p-3">
                                                    <div class="spinner-border spinner-border-sm"></div>
                                                    <p class="mt-2">Loading batches...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <input type="hidden" name="batch_ids" id="selectedBatchIds">
                        </div>
                    </div>
                    
                    <!-- Assignment Details -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-cog me-2"></i>Assignment Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="assignmentDate" class="form-label">Assignment Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="assignmentDate" name="assignment_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="assignmentRole" class="form-label">Role</label>
                                        <select class="form-select" id="assignmentRole" name="role">
                                            <option value="primary">Primary Instructor</option>
                                            <option value="assistant">Assistant Instructor</option>
                                            <option value="substitute">Substitute Teacher</option>
                                            <option value="guest">Guest Lecturer</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="assignmentNotes" class="form-label">Notes/Instructions</label>
                                        <textarea class="form-control" id="assignmentNotes" name="notes" rows="3" 
                                                  placeholder="Any specific instructions or notes for this assignment..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="notifyTeacher" name="notify_teacher" checked>
                                        <label class="form-check-label" for="notifyTeacher">
                                            Send notification email to teacher
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="checkSchedule" name="check_schedule" checked>
                                        <label class="form-check-label" for="checkSchedule">
                                            Check for schedule conflicts
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conflict Detection -->
                    <div class="alert alert-warning d-none mt-3" id="conflictAlert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Schedule Conflict Detected!</strong>
                        <div id="conflictDetails" class="mt-2"></div>
                    </div>
                    
                    <!-- Assignment Preview -->
                    <div class="card mt-4 d-none" id="assignmentPreview">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-eye me-2"></i>Assignment Preview
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
                <button type="button" class="btn btn-outline-primary" id="checkAvailabilityBtn">
                    <i class="fas fa-search me-1"></i> Check Availability
                </button>
                <button type="button" class="btn btn-success" id="saveAssignmentBtn">
                    <i class="fas fa-check me-1"></i> Confirm Assignment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Assign Course Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const assignCourseModal = document.getElementById('assignCourseModal');
    const assignCourseForm = document.getElementById('assignCourseForm');
    const courseSelectionCard = document.getElementById('courseSelectionCard');
    const batchSelectionCard = document.getElementById('batchSelectionCard');
    const multipleBatchesCard = document.getElementById('multipleBatchesCard');
    const assignmentTypeRadios = document.querySelectorAll('input[name="assignment_type"]');
    const courseSelect = document.getElementById('courseSelect');
    const batchSelect = document.getElementById('batchSelect');
    const checkAvailabilityBtn = document.getElementById('checkAvailabilityBtn');
    const saveAssignmentBtn = document.getElementById('saveAssignmentBtn');
    const assignmentPreview = document.getElementById('assignmentPreview');
    const conflictAlert = document.getElementById('conflictAlert');
    const batchesTableBody = document.getElementById('batchesTableBody');
    const selectAllBatches = document.getElementById('selectAllBatches');
    
    let currentTeacherId = null;
    let teacherData = null;
    let availableCourses = [];
    let availableBatches = [];
    let selectedBatches = new Set();
    
    // Open modal with teacher data
    window.openAssignCourseModal = function(teacherId) {
        currentTeacherId = teacherId;
        
        // Show loading state
        assignCourseForm.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-2">Loading teacher data...</p></div>';
        
        const modal = new bootstrap.Modal(assignCourseModal);
        modal.show();
        
        // Load teacher data
        Promise.all([
            loadTeacherData(teacherId),
            loadCourses(),
            loadBatches()
        ]).then(() => {
            setupEventListeners();
        }).catch(error => {
            showToast('Failed to load data', 'error');
            modal.hide();
        });
    };
    
    // Load teacher data
    function loadTeacherData(teacherId) {
        return fetch(`get-teacher-data.php?id=${teacherId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    teacherData = data.data;
                    populateTeacherInfo(data.data);
                    loadCurrentAssignments(teacherId);
                } else {
                    throw new Error('Failed to load teacher data');
                }
            });
    }
    
    // Load courses
    function loadCourses() {
        return fetch('get-courses.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    availableCourses = data.courses;
                    populateCourseSelect(data.courses);
                } else {
                    throw new Error('Failed to load courses');
                }
            });
    }
    
    // Load batches
    function loadBatches() {
        return fetch('get-batches.php?status=upcoming,ongoing')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    availableBatches = data.batches;
                    populateBatchSelect(data.batches);
                    populateBatchesTable(data.batches);
                } else {
                    throw new Error('Failed to load batches');
                }
            });
    }
    
    // Populate teacher information
    function populateTeacherInfo(teacher) {
        document.getElementById('assignTeacherId').value = teacher.user_id;
        document.getElementById('teacherNameDisplay').value = teacher.full_name || '';
        document.getElementById('teacherStatusDisplay').value = formatStatus(teacher.account_status);
    }
    
    // Format status
    function formatStatus(status) {
        const statusMap = {
            'active': 'Active',
            'inactive': 'Inactive',
            'suspended': 'Suspended',
            'pending_verification': 'Pending Verification'
        };
        return statusMap[status] || status;
    }
    
    // Load current assignments
    function loadCurrentAssignments(teacherId) {
        fetch(`get-teacher-assignments.php?id=${teacherId}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('currentAssignments');
                
                if (data.success && data.assignments.length > 0) {
                    let html = '<strong>Current Assignments:</strong><div class="mt-2">';
                    
                    data.assignments.forEach(assignment => {
                        html += `
                            <div class="badge bg-light text-dark me-2 mb-2">
                                ${assignment.course_code} - ${assignment.batch_code}
                                <small class="text-muted ms-1">${assignment.status}</small>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<span class="text-muted">No current assignments</span>';
                }
            })
            .catch(error => {
                console.error('Failed to load assignments:', error);
            });
    }
    
    // Populate course select
    function populateCourseSelect(courses) {
        courseSelect.innerHTML = '<option value="">Select a course...</option>';
        
        courses.forEach(course => {
            const option = document.createElement('option');
            option.value = course.course_id;
            option.textContent = `${course.course_code} - ${course.course_name}`;
            option.dataset.course = JSON.stringify(course);
            courseSelect.appendChild(option);
        });
    }
    
    // Populate batch select
    function populateBatchSelect(batches) {
        batchSelect.innerHTML = '<option value="">Select a batch...</option>';
        
        batches.forEach(batch => {
            const option = document.createElement('option');
            option.value = batch.batch_id;
            option.textContent = `${batch.batch_code} - ${batch.batch_name}`;
            option.dataset.batch = JSON.stringify(batch);
            batchSelect.appendChild(option);
        });
    }
    
    // Populate batches table
    function populateBatchesTable(batches) {
        batchesTableBody.innerHTML = '';
        
        if (batches.length === 0) {
            batchesTableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center p-3">
                        <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                        <p class="mb-0">No batches available</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        batches.forEach(batch => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="form-check">
                        <input class="form-check-input batch-checkbox" type="checkbox" 
                               value="${batch.batch_id}" id="batch_${batch.batch_id}">
                    </div>
                </td>
                <td>
                    <strong>${escapeHtml(batch.batch_code)}</strong>
                    <div class="small text-muted">${escapeHtml(batch.batch_name)}</div>
                </td>
                <td>${escapeHtml(batch.course_name)}</td>
                <td>
                    <small class="d-block">${formatDate(batch.start_date)} - ${formatDate(batch.end_date)}</small>
                    <small class="text-muted">${batch.schedule_details || 'No schedule'}</small>
                </td>
                <td>
                    <span class="badge bg-${getBatchStatusColor(batch.status)}">
                        ${batch.status}
                    </span>
                </td>
                <td>
                    ${batch.teacher_name ? escapeHtml(batch.teacher_name) : '<span class="text-muted">Not assigned</span>'}
                </td>
            `;
            
            batchesTableBody.appendChild(row);
        });
        
        setupBatchCheckboxes();
    }
    
    // Setup batch checkboxes
    function setupBatchCheckboxes() {
        const checkboxes = batchesTableBody.querySelectorAll('.batch-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectedBatches.add(this.value);
                } else {
                    selectedBatches.delete(this.value);
                }
                
                updateSelectedBatches();
                updateSelectAllCheckbox();
            });
        });
        
        // Select all checkbox
        selectAllBatches.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                
                if (this.checked) {
                    selectedBatches.add(checkbox.value);
                } else {
                    selectedBatches.delete(checkbox.value);
                }
            });
            
            updateSelectedBatches();
        });
    }
    
    // Update selected batches
    function updateSelectedBatches() {
        document.getElementById('selectedBatchIds').value = Array.from(selectedBatches).join(',');
    }
    
    // Update select all checkbox
    function updateSelectAllCheckbox() {
        const totalCheckboxes = batchesTableBody.querySelectorAll('.batch-checkbox').length;
        const checkedCount = selectedBatches.size;
        
        selectAllBatches.checked = checkedCount === totalCheckboxes && totalCheckboxes > 0;
        selectAllBatches.indeterminate = checkedCount > 0 && checkedCount < totalCheckboxes;
    }
    
    // Setup event listeners
    function setupEventListeners() {
        // Assignment type change
        assignmentTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                updateAssignmentType(this.value);
            });
        });
        
        // Course selection change
        courseSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                const course = JSON.parse(selectedOption.dataset.course);
                showCourseDetails(course);
            } else {
                hideCourseDetails();
            }
        });
        
        // Batch selection change
        batchSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                const batch = JSON.parse(selectedOption.dataset.batch);
                showBatchDetails(batch);
            } else {
                hideBatchDetails();
            }
        });
        
        // Check availability button
        checkAvailabilityBtn.addEventListener('click', checkAvailability);
        
        // Save assignment button
        saveAssignmentBtn.addEventListener('click', saveAssignment);
        
        // Initial update
        updateAssignmentType('course');
    }
    
    // Update assignment type
    function updateAssignmentType(type) {
        // Hide all cards first
        courseSelectionCard.classList.add('d-none');
        batchSelectionCard.classList.add('d-none');
        multipleBatchesCard.classList.add('d-none');
        
        // Show selected card
        switch (type) {
            case 'course':
                courseSelectionCard.classList.remove('d-none');
                break;
            case 'batch':
                batchSelectionCard.classList.remove('d-none');
                break;
            case 'multiple':
                multipleBatchesCard.classList.remove('d-none');
                break;
        }
        
        // Update form requirements
        updateFormRequirements(type);
    }
    
    // Update form requirements based on type
    function updateFormRequirements(type) {
        const courseRequired = type === 'course';
        const batchRequired = type === 'batch';
        
        courseSelect.required = courseRequired;
        batchSelect.required = batchRequired;
        
        // Clear preview
        assignmentPreview.classList.add('d-none');
        conflictAlert.classList.add('d-none');
    }
    
    // Show course details
    function showCourseDetails(course) {
        document.getElementById('courseCodeDisplay').textContent = course.course_code;
        document.getElementById('courseDurationDisplay').textContent = `${course.duration_months || 'N/A'} months`;
        document.getElementById('courseDescriptionDisplay').textContent = course.description || 'No description available';
        document.getElementById('courseDetails').classList.remove('d-none');
    }
    
    // Hide course details
    function hideCourseDetails() {
        document.getElementById('courseDetails').classList.add('d-none');
    }
    
    // Show batch details
    function showBatchDetails(batch) {
        document.getElementById('batchStartDisplay').textContent = formatDate(batch.start_date);
        document.getElementById('batchEndDisplay').textContent = formatDate(batch.end_date);
        document.getElementById('batchScheduleDisplay').textContent = batch.schedule_details || 'Not specified';
        document.getElementById('batchTeacherDisplay').textContent = batch.teacher_name || 'No teacher assigned';
        document.getElementById('batchDetails').classList.remove('d-none');
    }
    
    // Hide batch details
    function hideBatchDetails() {
        document.getElementById('batchDetails').classList.add('d-none');
    }
    
    // Check availability
    function checkAvailability() {
        const assignmentType = document.querySelector('input[name="assignment_type"]:checked').value;
        
        if (!validateForm(assignmentType)) {
            return;
        }
        
        const data = {
            teacher_id: currentTeacherId,
            assignment_type: assignmentType,
            course_id: courseSelect.value,
            batch_id: assignmentType === 'batch' ? batchSelect.value : null,
            batch_ids: assignmentType === 'multiple' ? Array.from(selectedBatches) : null,
            check_date: document.getElementById('assignmentDate').value
        };
        
        checkAvailabilityBtn.disabled = true;
        checkAvailabilityBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking...';
        
        fetch('check-availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAvailabilityResult(data);
            } else {
                showToast(data.message || 'Failed to check availability', 'error');
            }
        })
        .catch(error => {
            showToast('Network error occurred', 'error');
        })
        .finally(() => {
            checkAvailabilityBtn.disabled = false;
            checkAvailabilityBtn.innerHTML = '<i class="fas fa-search me-1"></i> Check Availability';
        });
    }
    
    // Show availability result
    function showAvailabilityResult(data) {
        // Show/hide conflict alert
        if (data.has_conflicts) {
            conflictAlert.classList.remove('d-none');
            
            let conflictHTML = '<ul class="mb-0">';
            data.conflicts.forEach(conflict => {
                conflictHTML += `<li>${escapeHtml(conflict.description)}</li>`;
            });
            conflictHTML += '</ul>';
            
            document.getElementById('conflictDetails').innerHTML = conflictHTML;
        } else {
            conflictAlert.classList.add('d-none');
        }
        
        // Generate preview
        generateAssignmentPreview(data);
    }
    
    // Generate assignment preview
    function generateAssignmentPreview(data) {
        const assignmentType = document.querySelector('input[name="assignment_type"]:checked').value;
        const previewContent = document.getElementById('previewContent');
        
        let previewHTML = `
            <div class="assignment-preview">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Teacher:</strong><br>
                        <span>${escapeHtml(teacherData.full_name)}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Assignment Date:</strong><br>
                        <span>${document.getElementById('assignmentDate').value}</span>
                    </div>
                </div>
                <hr>
        `;
        
        switch (assignmentType) {
            case 'course':
                const course = availableCourses.find(c => c.course_id == courseSelect.value);
                previewHTML += `
                    <strong>Course Assignment:</strong><br>
                    <div class="mt-2">
                        <span class="badge bg-primary">${escapeHtml(course.course_code)}</span>
                        <span class="badge bg-secondary ms-2">${escapeHtml(course.course_name)}</span>
                    </div>
                    <p class="mt-2">Teacher will be assigned to all upcoming batches of this course.</p>
                `;
                break;
                
            case 'batch':
                const batch = availableBatches.find(b => b.batch_id == batchSelect.value);
                previewHTML += `
                    <strong>Batch Assignment:</strong><br>
                    <div class="mt-2">
                        <span class="badge bg-primary">${escapeHtml(batch.batch_code)}</span>
                        <span class="badge bg-secondary ms-2">${escapeHtml(batch.batch_name)}</span>
                    </div>
                    <p class="mt-2 mb-0">
                        <small>Schedule: ${escapeHtml(batch.schedule_details || 'Not specified')}</small><br>
                        <small>Dates: ${formatDate(batch.start_date)} to ${formatDate(batch.end_date)}</small>
                    </p>
                `;
                break;
                
            case 'multiple':
                const selectedBatchCount = selectedBatches.size;
                const selectedBatchesList = Array.from(selectedBatches)
                    .map(id => availableBatches.find(b => b.batch_id == id))
                    .filter(b => b);
                
                previewHTML += `
                    <strong>Multiple Batch Assignment:</strong><br>
                    <div class="mt-2">
                        <span class="badge bg-primary">${selectedBatchCount} batches</span>
                    </div>
                    <div class="mt-2">
                        <strong>Selected Batches:</strong>
                        <ul class="mb-0">
                `;
                
                selectedBatchesList.forEach(batch => {
                    previewHTML += `<li>${escapeHtml(batch.batch_code)} - ${escapeHtml(batch.course_name)}</li>`;
                });
                
                previewHTML += `
                        </ul>
                    </div>
                `;
                break;
        }
        
        previewHTML += `
                <hr>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    ${data.has_conflicts ? 
                        'Assignment possible with conflicts. Review conflicts before proceeding.' : 
                        'Teacher is available for this assignment.'}
                </div>
            </div>
        `;
        
        previewContent.innerHTML = previewHTML;
        assignmentPreview.classList.remove('d-none');
        
        // Scroll to preview
        assignmentPreview.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Save assignment
    function saveAssignment() {
        const assignmentType = document.querySelector('input[name="assignment_type"]:checked').value;
        
        if (!validateForm(assignmentType)) {
            return;
        }
        
        if (!confirm('Are you sure you want to create this assignment?')) {
            return;
        }
        
        const formData = new FormData(assignCourseForm);
        formData.append('action', 'assign_course');
        formData.append('assignment_type', assignmentType);
        
        if (assignmentType === 'multiple') {
            formData.append('batch_ids', Array.from(selectedBatches).join(','));
        }
        
        saveAssignmentBtn.disabled = true;
        saveAssignmentBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
        
        fetch('teacher-actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Assignment created successfully', 'success');
                
                // Close modal after delay
                setTimeout(() => {
                    bootstrap.Modal.getInstance(assignCourseModal).hide();
                    // Refresh page or update UI
                    if (typeof refreshTeacherAssignments === 'function') {
                        refreshTeacherAssignments();
                    }
                }, 1500);
            } else {
                showToast(data.message || 'Failed to create assignment', 'error');
                saveAssignmentBtn.disabled = false;
                saveAssignmentBtn.innerHTML = '<i class="fas fa-check me-1"></i> Confirm Assignment';
            }
        })
        .catch(error => {
            showToast('Network error occurred', 'error');
            saveAssignmentBtn.disabled = false;
            saveAssignmentBtn.innerHTML = '<i class="fas fa-check me-1"></i> Confirm Assignment';
        });
    }
    
    // Validate form
    function validateForm(assignmentType) {
        let isValid = true;
        
        switch (assignmentType) {
            case 'course':
                if (!courseSelect.value) {
                    showToast('Please select a course', 'warning');
                    courseSelect.focus();
                    isValid = false;
                }
                break;
                
            case 'batch':
                if (!batchSelect.value) {
                    showToast('Please select a batch', 'warning');
                    batchSelect.focus();
                    isValid = false;
                }
                break;
                
            case 'multiple':
                if (selectedBatches.size === 0) {
                    showToast('Please select at least one batch', 'warning');
                    isValid = false;
                }
                break;
        }
        
        return isValid;
    }
    
    // Helper functions
    function getBatchStatusColor(status) {
        const colors = {
            'upcoming': 'info',
            'ongoing': 'success',
            'completed': 'secondary',
            'cancelled': 'danger'
        };
        return colors[status] || 'secondary';
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Modal events
    assignCourseModal.addEventListener('hidden.bs.modal', function() {
        // Reset form
        assignCourseForm.reset();
        currentTeacherId = null;
        teacherData = null;
        selectedBatches.clear();
        assignmentPreview.classList.add('d-none');
        conflictAlert.classList.add('d-none');
    });
});
</script>

<style>
/* Assign Course Modal Styles */
.btn-check:checked + .btn-outline-primary {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

.assignment-preview {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.04);
    cursor: pointer;
}

.badge {
    font-size: 0.8em;
    font-weight: 500;
}

.form-check-input:checked {
    background-color: #198754;
    border-color: #198754;
}

/* Animation for table rows */
@keyframes fadeInRow {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#batchesTableBody tr {
    animation: fadeInRow 0.3s ease-out;
    animation-fill-mode: both;
}

#batchesTableBody tr:nth-child(1) { animation-delay: 0.1s; }
#batchesTableBody tr:nth-child(2) { animation-delay: 0.2s; }
#batchesTableBody tr:nth-child(3) { animation-delay: 0.3s; }
#batchesTableBody tr:nth-child(4) { animation-delay: 0.4s; }
#batchesTableBody tr:nth-child(5) { animation-delay: 0.5s; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .btn-group {
        flex-wrap: wrap;
    }
    
    .btn-group .btn {
        flex: 1 0 100%;
        margin-bottom: 0.5rem;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
}

/* Card hover effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Custom scrollbar for tables */
.table-responsive::-webkit-scrollbar {
    height: 6px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>