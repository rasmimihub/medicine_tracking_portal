<?php
// Load configuration and authentication helpers
require_once 'config.php';
require_once 'auth.php';

// REDIRECT IF ALREADY LOGGED IN: If an admin/supervisor somehow visits this login page, 
// just bounce them straight to their dashboard so they don't have to re-login.
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
        
        // PREPARED STATEMENT: We query the database for this specific username and role ('admin')
        // Using '?' prevents malicious users from injecting SQL commands directly into the query.
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ? AND role = 'admin'");
        $stmt->execute([$username]);
        
        // Fetch the user row as an Associative Array mapping column names to values
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // PASSWORD VERIFICATION: 
        // We do NOT store passwords in plain text. We hash them using mathematically secure Bcrypt.
        // password_verify() computes the hash of the typed password and compares it to the stored Hash.
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // SUCCESSFUL LOGIN: Save user details into server-side session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            
            // Route the authenticated user directly into the management panel
            header("Location: admin/dashboard.php");
            exit;
        } else {
            // Failed login due to wrong username or password
            $error = 'Invalid admin credentials.';
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
    <title>Admin Login - Medicine Tracking Portal</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-user-shield login-icon"></i>
            <h2>Admin Login</h2>
            <p class="text-muted mt-2">Manage inventory and stock</p>
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block mt-4" style="padding: 0.75rem;">
                Sign in to Admin Dashboard
            </button>
            <div class="text-center mt-6">
                <a href="index.php" class="text-muted" style="font-size: 0.875rem;">Back to Public Portal</a> | 
                <a href="supervisor_login.php" class="text-muted" style="font-size: 0.875rem;">Supervisor Login</a>
            </div>
        </form>
    </div>
</body>
</html>
