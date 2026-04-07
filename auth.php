<?php
// auth.php
// Require the database connection and session start from config
require_once 'config.php';

// HELPER: Check if there is an active user ID attached to the current session browser
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// HELPER: Check if the current logged-in user has a specific defined role (e.g., 'admin' or 'supervisor')
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// STRICT ROLE CHECK FUNCTION (ACCESS CONTROL):
// Used at the extreme top of protected pages (like admin/dashboard.php).
// If the user isn't logged in, or their role doesn't match, they are forced back out to security routes.
function requireRole($role) {
    
    // 1. Kick unauthenticated users back to the public portal immediately
    if (!isLoggedIn()) {
        header("Location: /medicine_management_system/index.php");
        exit;
    }
    
    // 2. If they are logged in but have the WRONG role (e.g. Supervisor trying to access Admin tools)
    // we deflect them back to their correctly authorized dashboard.
    if (!hasRole($role)) {
        // Evaluate what role they actually have
        if (hasRole('admin')) {
            header("Location: /medicine_management_system/admin/dashboard.php");
        } else if (hasRole('supervisor')) {
            header("Location: /medicine_management_system/supervisor/dashboard.php");
        } else {
            header("Location: /medicine_management_system/index.php");
        }
        exit; // The script must exit after a header redirect or the rest of the sensitive page loads!
    }
}
?>
