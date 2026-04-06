<?php
// change_password.php
// Load dependencies
require_once 'config.php';
require_once 'auth.php';

// ENFORCE SECURITY: Check if user is logged in
if (!isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$response = [
    'success' => false,
    'message' => ''
];

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation: Check if all fields are filled
    if (!$old_password || !$new_password || !$confirm_password) {
        $response['message'] = 'All fields are required.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Validation: Check if new passwords match
    if ($new_password !== $confirm_password) {
        $response['message'] = 'New passwords do not match.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Validation: Check password length (minimum 6 characters)
    if (strlen($new_password) < 6) {
        $response['message'] = 'Password must be at least 6 characters long.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Validation: Check password complexity
    $has_upper = preg_match('/[A-Z]/', $new_password);
    $has_lower = preg_match('/[a-z]/', $new_password);
    $has_number = preg_match('/[0-9]/', $new_password);
    $has_special = preg_match('/[!@#$%^&*]/', $new_password);
    
    if (!($has_upper && $has_lower && $has_number)) {
        $response['message'] = 'Password must contain uppercase, lowercase, and numbers.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Fetch current user's password hash from database
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = 'User not found.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Verify old password matches stored hash
    if (!password_verify($old_password, $user['password_hash'])) {
        $response['message'] = 'Current password is incorrect.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Prevent using the same password as before
    if (password_verify($new_password, $user['password_hash'])) {
        $response['message'] = 'New password cannot be the same as current password.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Hash new password using bcrypt
    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
    
    // Update password in database
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    if ($stmt->execute([$new_password_hash, $_SESSION['user_id']])) {
        // Log the password change as a transaction for audit trail
        logTransaction($pdo, 0, 'password_changed', 0, $_SESSION['user_id']);
        
        $response['success'] = true;
        $response['message'] = 'Password changed successfully!';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        $response['message'] = 'Failed to update password. Please try again.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>