/**
 * Skills Way LMS - User Session Manager
 * Handles session timeout, auto-logout, and session activity tracking
 */

class UserSessionManager {
    constructor(options = {}) {
        this.sessionTimeout = options.sessionTimeout || 1800000; // 30 minutes default
        this.warningTime = options.warningTime || 300000; // 5 minutes before timeout
        this.checkInterval = options.checkInterval || 60000; // Check every minute
        this.logoutUrl = options.logoutUrl || '../logout.php';
        this.keepAliveUrl = options.keepAliveUrl || 'ajax/keep_alive.php';

        this.lastActivity = Date.now();
        this.warningShown = false;
        this.intervalId = null;
        this.warningTimeoutId = null;
        this.logoutTimeoutId = null;

        this.init();
    }

    init() {
        console.log('Initializing User Session Manager...');
        this.setupActivityListeners();
        this.startSessionCheck();
        this.updateActivity(); // Initial activity
    }

    setupActivityListeners() {
        // Track user activity
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

        events.forEach(event => {
            document.addEventListener(event, () => {
                this.updateActivity();
            }, { passive: true });
        });

        // Track visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.updateActivity();
            }
        });
    }

    updateActivity() {
        this.lastActivity = Date.now();

        // Hide warning if shown
        if (this.warningShown) {
            this.hideWarning();
            this.warningShown = false;
        }

        // Clear existing timeouts
        if (this.warningTimeoutId) {
            clearTimeout(this.warningTimeoutId);
        }
        if (this.logoutTimeoutId) {
            clearTimeout(this.logoutTimeoutId);
        }

        // Set new timeouts
        this.setTimeouts();
    }

    setTimeouts() {
        // Show warning before timeout
        const timeUntilWarning = this.sessionTimeout - this.warningTime;
        this.warningTimeoutId = setTimeout(() => {
            this.showWarning();
        }, timeUntilWarning);

        // Auto logout
        this.logoutTimeoutId = setTimeout(() => {
            this.logout(true);
        }, this.sessionTimeout);
    }

    startSessionCheck() {
        // Periodically check session status with server
        this.intervalId = setInterval(async () => {
            const inactive = Date.now() - this.lastActivity;

            // If user is active, send keep-alive ping
            if (inactive < this.checkInterval) {
                await this.keepAlive();
            }

            // Check for timeout
            if (inactive >= this.sessionTimeout) {
                this.logout(true);
            }
        }, this.checkInterval);
    }

    async keepAlive() {
        try {
            const response = await fetch(this.keepAliveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ timestamp: Date.now() })
            });

            const data = await response.json();

            if (!data.success || data.sessionExpired) {
                this.logout(false);
            }
        } catch (error) {
            console.error('Keep-alive request failed:', error);
        }
    }

    showWarning() {
        this.warningShown = true;

        const remainingTime = Math.floor(this.warningTime / 1000 / 60);

        // Create modal if it doesn't exist
        let modal = document.getElementById('sessionWarningModal');

        if (!modal) {
            const modalHtml = `
                <div class="modal fade" id="sessionWarningModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Session Timeout Warning
                                </h5>
                            </div>
                            <div class="modal-body">
                                <p class="mb-3">
                                    Your session will expire in <strong id="sessionTimeRemaining">${remainingTime}</strong> minutes due to inactivity.
                                </p>
                                <p class="mb-0 text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Click "Stay Logged In" to continue your session.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" id="sessionLogoutBtn">
                                    <i class="fas fa-sign-out-alt me-1"></i>
                                    Logout Now
                                </button>
                                <button type="button" class="btn btn-primary" id="sessionContinueBtn">
                                    <i class="fas fa-check me-1"></i>
                                    Stay Logged In
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            modal = document.getElementById('sessionWarningModal');

            // Setup event listeners
            document.getElementById('sessionContinueBtn').addEventListener('click', () => {
                this.updateActivity();
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });

            document.getElementById('sessionLogoutBtn').addEventListener('click', () => {
                this.logout(false);
            });
        }

        // Show modal
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();

        // Update countdown
        this.updateCountdown();
    }

    updateCountdown() {
        if (!this.warningShown) return;

        const timeElement = document.getElementById('sessionTimeRemaining');
        if (!timeElement) return;

        const elapsed = Date.now() - this.lastActivity;
        const remaining = Math.max(0, this.sessionTimeout - elapsed);
        const minutes = Math.floor(remaining / 1000 / 60);
        const seconds = Math.floor((remaining / 1000) % 60);

        timeElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        if (remaining > 0) {
            setTimeout(() => this.updateCountdown(), 1000);
        }
    }

    hideWarning() {
        const modal = document.getElementById('sessionWarningModal');
        if (modal) {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
    }

    logout(autoLogout = false) {
        console.log('Logging out...', autoLogout ? '(Auto logout)' : '(Manual logout)');

        // Clear intervals and timeouts
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
        if (this.warningTimeoutId) {
            clearTimeout(this.warningTimeoutId);
        }
        if (this.logoutTimeoutId) {
            clearTimeout(this.logoutTimeoutId);
        }

        // Show logout message
        if (autoLogout) {
            sessionStorage.setItem('logoutReason', 'timeout');
        }

        // Redirect to logout
        window.location.href = this.logoutUrl + (autoLogout ? '?reason=timeout' : '');
    }

    destroy() {
        // Clean up
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
        if (this.warningTimeoutId) {
            clearTimeout(this.warningTimeoutId);
        }
        if (this.logoutTimeoutId) {
            clearTimeout(this.logoutTimeoutId);
        }
    }
}

// Initialize session manager when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    // Only initialize if user is logged in (check for a session indicator)
    const isLoggedIn = document.body.dataset.userLoggedIn === 'true' ||
        document.querySelector('[data-user-session]') !== null;

    if (isLoggedIn) {
        window.sessionManager = new UserSessionManager({
            sessionTimeout: 1800000, // 30 minutes
            warningTime: 300000, // 5 minutes warning
            checkInterval: 60000 // Check every minute
        });

        console.log('User session manager initialized');
    }

    // Show logout reason if exists
    const logoutReason = sessionStorage.getItem('logoutReason');
    if (logoutReason === 'timeout') {
        sessionStorage.removeItem('logoutReason');

        // Show toast or alert
        if (typeof LMS !== 'undefined' && LMS.showToast) {
            setTimeout(() => {
                LMS.showToast('Your session has expired due to inactivity. Please log in again.', 'warning', 5000);
            }, 500);
        }
    }
});

// Export for use in other modules
window.UserSessionManager = UserSessionManager;

console.log('Skills Way LMS user-session.js loaded');
