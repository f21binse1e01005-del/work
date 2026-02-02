<?php
/**
 * Admin Dashboard Footer - Professional Version
 * File: admin/includes/footer.php
 */
?>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="footer mt-auto py-3 bg-light border-top">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <span class="text-muted">
                            <i class="fas fa-code me-1"></i> 
                            Skills Way Vocational Institute &copy; <?php echo date('Y'); ?> 
                            | v1.0.0
                        </span>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="text-muted small">
                            <i class="fas fa-server me-1"></i>
                            Memory: <?php echo round(memory_get_usage(true) / 1024 / 1024, 2); ?>MB |
                            <i class="fas fa-clock me-1 ms-2"></i>
                            Load Time: <?php echo round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3); ?>s
                        </span>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div class="toast" id="liveToast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle">Notification</strong>
                <small id="toastTime">Just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage">
                Hello, world! This is a toast message.
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmationModalBody">
                    Are you sure you want to perform this action?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmationModalConfirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 bg-transparent">
                <div class="modal-body text-center">
                    <div class="loading-spinner" style="width: 50px; height: 50px; border-width: 4px;"></div>
                    <p class="mt-3 text-white">Processing...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
    // CSRF Token for AJAX requests
    const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    
    // Sidebar Toggle
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        sidebar.classList.toggle('collapsed');
        
        // Save preference to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
    
    // Mobile Sidebar Toggle
    document.getElementById('mobileSidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('show');
    });
    
    // Theme Toggle
    document.getElementById('themeToggle').addEventListener('click', function() {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', newTheme);
        
        // Update icon
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-moon');
        icon.classList.toggle('fa-sun');
        
        // Save preference to localStorage
        localStorage.setItem('theme', newTheme);
        
        // Save to server via AJAX
        $.ajax({
            url: 'update-theme.php',
            method: 'POST',
            data: { theme: newTheme, csrf_token: csrfToken },
            error: function() {
                console.log('Failed to save theme preference');
            }
        });
    });
    
    // Apply saved preferences
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar state
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            document.getElementById('sidebar').classList.add('collapsed');
        }
        
        // Theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
        
        const themeIcon = document.getElementById('themeToggle').querySelector('i');
        themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    });
    
    // Toast Notification Function
    function showToast(title, message, type = 'info') {
        const toastEl = document.getElementById('liveToast');
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');
        const toastTime = document.getElementById('toastTime');
        
        // Set content
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toastTime.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        // Set type-based styling
        toastEl.className = 'toast';
        if (type === 'success') {
            toastEl.classList.add('text-bg-success');
        } else if (type === 'error') {
            toastEl.classList.add('text-bg-danger');
        } else if (type === 'warning') {
            toastEl.classList.add('text-bg-warning');
        }
        
        // Show toast
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
    
    // Confirmation Dialog
    function showConfirmation(title, message, confirmCallback) {
        document.getElementById('confirmationModalTitle').textContent = title;
        document.getElementById('confirmationModalBody').textContent = message;
        
        const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        modal.show();
        
        // Set up confirm button callback
        document.getElementById('confirmationModalConfirm').onclick = function() {
            modal.hide();
            if (typeof confirmCallback === 'function') {
                confirmCallback();
            }
        };
    }
    
    // Loading Modal
    function showLoading(message = 'Processing...') {
        const modal = new bootstrap.Modal(document.getElementById('loadingModal'));
        document.querySelector('#loadingModal .modal-body p').textContent = message;
        modal.show();
    }
    
    function hideLoading() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
        if (modal) modal.hide();
    }
    
    // Refresh Page
    function refreshPage() {
        location.reload();
    }
    
    // Mark all notifications as read
    function markAllNotificationsAsRead() {
        $.ajax({
            url: 'mark-notifications-read.php',
            method: 'POST',
            data: { csrf_token: csrfToken },
            success: function(response) {
                showToast('Success', 'All notifications marked as read', 'success');
                location.reload();
            },
            error: function() {
                showToast('Error', 'Failed to update notifications', 'error');
            }
        });
    }
    
    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        $.ajax({
            url: 'check-notifications.php',
            method: 'GET',
            success: function(response) {
                const data = JSON.parse(response);
                if (data.unread > 0) {
                    // Update notification badge
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        badge.textContent = data.unread;
                    } else {
                        // Create badge if it doesn't exist
                        const bellIcon = document.querySelector('#notificationDropdown i.fa-bell');
                        if (bellIcon) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'notification-badge badge bg-danger rounded-pill';
                            newBadge.textContent = data.unread;
                            newBadge.style.position = 'absolute';
                            newBadge.style.top = '-5px';
                            newBadge.style.right = '-5px';
                            bellIcon.parentNode.style.position = 'relative';
                            bellIcon.parentNode.appendChild(newBadge);
                        }
                    }
                    
                    // Show desktop notification
                    if (data.unread > 0 && Notification.permission === "granted") {
                        new Notification('New Notification', {
                            body: 'You have ' + data.unread + ' unread notifications',
                            icon: '/assets/favicon.ico'
                        });
                    }
                }
            }
        });
    }, 30000);
    
    // Request notification permission
    if (Notification.permission === "default") {
        Notification.requestPermission();
    }
    
    // Session timeout warning
    let sessionTimeout;
    function startSessionTimer() {
        // Clear existing timer
        clearTimeout(sessionTimeout);
        
        // Set new timer (25 minutes for 30-minute session)
        sessionTimeout = setTimeout(function() {
            showToast('Session Expiring', 'Your session will expire in 5 minutes. Please save your work.', 'warning');
            
            // Final warning after 4 more minutes
            setTimeout(function() {
                showToast('Session Expiring Soon', 'Your session will expire in 1 minute. Please save your work immediately.', 'error');
                
                // Logout after 1 more minute
                setTimeout(function() {
                    window.location.href = '../logout.php?reason=timeout';
                }, 60000);
            }, 240000);
        }, 1500000); // 25 minutes
    }
    
    // Start session timer on page load
    startSessionTimer();
    
    // Reset timer on user activity
    document.addEventListener('mousemove', startSessionTimer);
    document.addEventListener('keypress', startSessionTimer);
    document.addEventListener('click', startSessionTimer);
    
    // DataTables default configuration
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            zeroRecords: "No matching records found",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        pageLength: 10,
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        processing: true,
        serverSide: false
    });
    
    // AJAX setup with CSRF token
    $.ajaxSetup({
        headers: {
            'X-CSRF-Token': csrfToken
        }
    });
    
    // Handle AJAX errors globally
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        if (jqxhr.status === 401) {
            showToast('Session Expired', 'Please login again', 'error');
            setTimeout(function() {
                window.location.href = '../logout.php';
            }, 2000);
        } else if (jqxhr.status === 403) {
            showToast('Permission Denied', 'You do not have permission to perform this action', 'error');
        } else if (jqxhr.status === 500) {
            showToast('Server Error', 'An internal server error occurred', 'error');
        }
    });
    
    // Print function
    function printPage() {
        window.print();
    }
    
    // Export to Excel
    function exportToExcel(tableId, filename = 'export') {
        const table = document.getElementById(tableId);
        const html = table.outerHTML;
        const blob = new Blob([html], {type: 'application/vnd.ms-excel'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename + '.xls';
        a.click();
        URL.revokeObjectURL(url);
    }
    
    // Export to PDF
    function exportToPDF(tableId, filename = 'export') {
        showToast('Info', 'PDF export feature requires additional setup', 'info');
        // Implement PDF export using jsPDF or similar library
    }
    </script>
</body>
</html>