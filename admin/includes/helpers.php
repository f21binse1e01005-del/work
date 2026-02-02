<?php
/**
 * Helper Functions
 * General utility functions for the admin panel
 * File: includes/helpers.php
 */

/**
 * Get setting value with default fallback
 */
function getSetting($key, $default = null) {
    // In a real app, this might fetch from a settings table or file
    // For now, we return valid defaults or the provided default
    $settings = [
        'records_per_page' => 12,
        'site_name' => 'Skills Way Vocational Institute',
        'currency_symbol' => 'Rs.',
        'date_format' => 'Y-m-d'
    ];
    
    return $settings[$key] ?? $default;
}

/**
 * Validate page number
 */
function validatePageNumber($page) {
    $page = intval($page);
    return ($page < 1) ? 1 : $page;
}

/**
 * Get status color class (Bootstrap)
 * Also defined in teacher-actions.php, but needed globally
 */
if (!function_exists('getStatusColor')) {
    function getStatusColor($status) {
        return match($status) {
            'active' => 'success',
            'inactive' => 'secondary',
            'suspended' => 'danger',
            'pending_verification' => 'warning',
            'completed' => 'success',
            'ongoing' => 'primary',
            'scheduled' => 'info',
            'cancelled' => 'danger',
            default => 'secondary'
        };
    }
}

/**
 * Get status badge HTML
 */
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $color = getStatusColor($status);
        $label = ucfirst(str_replace('_', ' ', $status));
        return "<span class='badge bg-{$color}'>{$label}</span>";
    }
}

/**
 * Format phone number
 */
if (!function_exists('formatPhone')) {
    function formatPhone($phone) {
        // Strip non-numeric chars
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Format as XXXX-XXXXXXX if length matches
        if (strlen($phone) == 11) {
            return substr($phone, 0, 4) . '-' . substr($phone, 4);
        }
        
        return $phone;
    }
}

/**
 * Format CNIC
 */
if (!function_exists('formatCNIC')) {
    function formatCNIC($cnic) {
        $cnic = preg_replace('/[^0-9]/', '', $cnic);
        if (strlen($cnic) == 13) {
            return substr($cnic, 0, 5) . '-' . substr($cnic, 5, 7) . '-' . substr($cnic, 12, 1);
        }
        return $cnic;
    }
}

/**
 * Validates if the request is an AJAX request
 */
if (!function_exists('isAjaxRequest')) {
    function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

/**
 * Format byte size
 */
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
?>
