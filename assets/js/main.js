/**
 * Skills Way LMS - Main JavaScript
 * Global functions and utilities for the LMS
 */

// ===== Global Configuration =====
const LMS_CONFIG = {
    sessionTimeout: 1800000, // 30 minutes in milliseconds
    autoSaveInterval: 30000, // 30 seconds
    notificationRefreshInterval: 60000, // 60 seconds
    apiEndpoints: {
        notifications: 'ajax/get_notifications.php',
        messages: 'ajax/get_messages_preview.php',
        search: 'ajax/search.php'
    }
};

// ===== Initialize Application =====
document.addEventListener('DOMContentLoaded', function () {
    initializeApp();
});

function initializeApp() {
    console.log('Initializing Skills Way LMS...');

    // Initialize core features
    initializeTheme();
    initializeTooltips();
    initializePopovers();
    initializeFormValidation();
    initializeAjaxForms();
    initializeImagePreviews();
    initializeDataTables();
    initializeCharts();
    initializeSmoothScroll();

    console.log('Skills Way LMS initialized successfully');
}

// ===== Theme Management =====
function initializeTheme() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const htmlElement = document.documentElement;

    // Check for saved theme preference or default to user's system preference
    const savedTheme = localStorage.getItem('theme') ||
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    setTheme(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const currentTheme = htmlElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
        });
    }

    function setTheme(theme) {
        htmlElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        updateThemeIcon(theme);
    }

    function updateThemeIcon(theme) {
        if (themeIcon) {
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }
}

// ===== Bootstrap Components Initialization =====
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function initializePopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// ===== Form Validation =====
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');

    Array.from(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add('was-validated');
        }, false);
    });
}

// ===== AJAX Forms =====
function initializeAjaxForms() {
    const ajaxForms = document.querySelectorAll('.ajax-form');

    ajaxForms.forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(form);
            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';

            try {
                // Show loading state
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                }

                const response = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showToast(data.message || 'Operation successful', 'success');

                    // Handle redirect
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    }

                    // Reset form if specified
                    if (data.resetForm) {
                        form.reset();
                    }
                } else {
                    showToast(data.message || 'Operation failed', 'danger');
                }
            } catch (error) {
                console.error('Form submission error:', error);
                showToast('An error occurred. Please try again.', 'danger');
            } finally {
                // Restore button state
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    });
}

// ===== Toast Notifications =====
function showToast(message, type = 'info', duration = 3000) {
    let toastContainer = document.getElementById('toastContainer');

    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '1060';
        document.body.appendChild(toastContainer);
    }

    const toastId = 'toast-' + Date.now();
    const iconMap = {
        success: 'fa-check-circle',
        danger: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    const icon = iconMap[type] || iconMap.info;

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${icon} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: duration });
    toast.show();

    toastElement.addEventListener('hidden.bs.toast', function () {
        toastElement.remove();
    });
}

// ===== Image Preview =====
function initializeImagePreviews() {
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');

    imageInputs.forEach(input => {
        input.addEventListener('change', function (e) {
            const file = e.target.files[0];
            const previewId = input.dataset.preview;

            if (file && previewId) {
                const reader = new FileReader();
                const preview = document.getElementById(previewId);

                reader.onload = function (e) {
                    if (preview) {
                        if (preview.tagName === 'IMG') {
                            preview.src = e.target.result;
                        } else {
                            preview.style.backgroundImage = `url(${e.target.result})`;
                        }
                    }
                };

                reader.readAsDataURL(file);
            }
        });
    });
}

// ===== Data Tables =====
function initializeDataTables() {
    const tables = document.querySelectorAll('.data-table');

    tables.forEach(table => {
        if (typeof $.fn.DataTable !== 'undefined') {
            $(table).DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search..."
                }
            });
        }
    });
}

// ===== Charts Initialization =====
function initializeCharts() {
    // Only initialize if Chart.js is loaded
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
        Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--gray-600').trim() || '#6c757d';
    }
}

// ===== Smooth Scroll =====
function initializeSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');

            if (href === '#' || href === '#!') return;

            const target = document.querySelector(href);

            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// ===== Debounce Utility =====
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ===== Throttle Utility=====
function throttle(func, limit) {
    let inThrottle;
    return function (...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// ===== Format Date =====
function formatDate(date, format = 'medium') {
    const d = new Date(date);

    const formats = {
        short: { month: 'short', day: 'numeric' },
        medium: { year: 'numeric', month: 'short', day: 'numeric' },
        long: { year: 'numeric', month: 'long', day: 'numeric' },
        full: { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }
    };

    return d.toLocaleDateString('en-US', formats[format] || formats.medium);
}

// ===== Format Time =====
function formatTime(date) {
    const d = new Date(date);
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

// ===== Time Ago =====
function timeAgo(date) {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);

    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60,
        second: 1
    };

    for (const [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit);
        if (interval >= 1) {
            return `${interval} ${unit}${interval !== 1 ? 's' : ''} ago`;
        }
    }

    return 'just now';
}

// ===== Copy to Clipboard =====
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copied to clipboard', 'success');
        }).catch(err => {
            console.error('Failed to copy:', err);
            showToast('Failed to copy to clipboard', 'danger');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();

        try {
            document.execCommand('copy');
            showToast('Copied to clipboard', 'success');
        } catch (err) {
            console.error('Failed to copy:', err);
            showToast('Failed to copy to clipboard', 'danger');
        }

        document.body.removeChild(textArea);
    }
}

// ===== Confirm Dialog =====
function confirmDialog(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// ===== File Upload with Progress =====
async function uploadFileWithProgress(file, url, progressCallback) {
    const formData = new FormData();
    formData.append('file', file);

    const xhr = new XMLHttpRequest();

    return new Promise((resolve, reject) => {
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                if (progressCallback) {
                    progressCallback(percentComplete);
                }
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                resolve(JSON.parse(xhr.responseText));
            } else {
                reject(new Error('Upload failed'));
            }
        });

        xhr.addEventListener('error', () => {
            reject(new Error('Upload failed'));
        });

        xhr.open('POST', url);
        xhr.send(formData);
    });
}

// ===== Export Functions =====
window.LMS = {
    showToast,
    formatDate,
    formatTime,
    timeAgo,
    copyToClipboard,
    confirmDialog,
    uploadFileWithProgress,
    debounce,
    throttle
};

console.log('Skills Way LMS main.js loaded');
