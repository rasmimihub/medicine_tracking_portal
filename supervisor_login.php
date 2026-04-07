<?php
// Load configuration and authentication helpers
require_once 'config.php';
require_once 'auth.php';

// REDIRECT IF ALREADY LOGGED IN
// If an authenticated user visits here, bounce them back to their authorized dashboard.
if (isLoggedIn()) {
    if (hasRole('admin')) header("Location: admin/dashboard.php");
    else header("Location: supervisor/dashboard.php");
    exit;
}

$error = ''; // Initialize empty error string

// CHECK FORM SUBMISSION: Only run this logic if the user clicked "Sign in" (POST method)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input: Trim extra whitespaces
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic Validation: Ensure fields aren't blank
    if ($username && $password) {
        
        // PREPARED STATEMENT: We query the database for this specific username and role ('supervisor')
        // This inherently prevents Admins from logging in through the Supervisor portal.
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ? AND role = 'supervisor'");
        $stmt->execute([$username]);
        
        // Fetch the user row as an Associative Array
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // PASSWORD VERIFICATION: Compares typed plaintext to securely stored Hash via bcrypt evaluation
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // SUCCESSFUL LOGIN: Save user details into server-side session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            
            // Route the authenticated supervisor directly into their monitoring panel
            header("Location: supervisor/dashboard.php");
            exit;
        } else {
            // Failed login catch
            $error = 'Invalid supervisor credentials.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Login - Medicine Tracking Portal</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-eye login-icon"></i>
            <h2>Supervisor Login</h2>
            <p class="text-muted mt-2">Monitor transactions and export logs</p>
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="supervisor_login.php">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block mt-4" style="padding: 0.75rem;">
                Sign in to Supervisor Dashboard
            </button>
            <div class="text-center mt-6">
                <a href="index.php" class="text-muted" style="font-size: 0.875rem;">Back to Public Portal</a> | 
                <a href="admin_login.php" class="text-muted" style="font-size: 0.875rem;">Admin Login</a>
            </div>
        </form>
    </div>
</body>
</html>
