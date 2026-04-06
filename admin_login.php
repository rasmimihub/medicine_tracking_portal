<?php
// Load configuration and authentication helpers
require_once 'config.php';
require_once 'auth.php';

// REDIRECT IF ALREADY LOGGED IN
if (isLoggedIn()) {
    if (hasRole('admin')) header("Location: admin/dashboard.php");
    else header("Location: supervisor/dashboard.php");
    exit;
}

$error = '';

// CHECK FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        
        // PREPARED STATEMENT: Query for admin user with password_hash column
        // This fetches the BCRYPT hash stored in database
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$username]);
        
        // Fetch the user record
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // PASSWORD VERIFICATION using password_verify()
        // password_verify() compares plain text input against the stored BCRYPT hash
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // SUCCESSFUL LOGIN - Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            
            // Redirect to admin dashboard
            header("Location: admin/dashboard.php");
            exit;
        } else {
            // Failed login - either user doesn't exist or password is wrong
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