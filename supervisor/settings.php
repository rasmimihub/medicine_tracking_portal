<?php
// supervisor/settings.php
// Load dependencies
require_once '../config.php';
require_once '../auth.php';

// ENFORCE SECURITY: Check if user is logged in AND holds the 'supervisor' role
requireRole('supervisor');

// Fetch current user details
$stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Settings - Medicine Tracking Portal</title>
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .settings-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .password-strength {
            height: 6px;
            border-radius: 3px;
            margin-top: 0.5rem;
            background-color: #E5E7EB;
            transition: all 0.3s ease;
        }
        
        .password-strength.weak {
            background-color: #EF4444;
            width: 33%;
        }
        
        .password-strength.medium {
            background-color: #F59E0B;
            width: 66%;
        }
        
        .password-strength.strong {
            background-color: #10B981;
            width: 100%;
        }
        
        .requirements {
            background-color: var(--surface-hover);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.875rem;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }
        
        .requirement-item i {
            width: 16px;
        }
        
        .requirement-item.met {
            color: #10B981;
        }
        
        .requirement-item.unmet {
            color: #9CA3AF;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #ECFDF5;
            color: #065F46;
            border: 1px solid #34D399;
        }
        
        .alert-danger {
            background-color: #FEF2F2;
            color: #991B1B;
            border: 1px solid #F87171;
        }
    </style>
</head>
<body class="dashboard-layout">
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header" style="background-color: var(--primary); color: white;">
            <div class="nav-brand" style="font-size: 1.1rem; color: white;">
                <i class="fas fa-eye text-white text-xl"></i>
                <span style="font-size: 0.9rem;">Supervisor Dashboard</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="logs.php" class="sidebar-link">
                <i class="fas fa-clipboard-list"></i>
                <span>Audit Logs</span>
            </a>
            <a href="settings.php" class="sidebar-link active">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="../logout.php" class="sidebar-link" style="margin-top: 2rem; color: var(--danger);">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
        <div class="p-4" style="border-top: 1px solid var(--border-color);">
            <div class="flex items-center gap-2">
                <div style="width: 2rem; height: 2rem; border-radius: 50%; background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                    <?php echo strtoupper($current_user['username'][0]); ?>
                </div>
                <div>
                    <p class="text-sm font-medium" style="margin: 0; line-height: 1.2;"><?php echo htmlspecialchars($current_user['username']); ?></p>
                    <p class="text-xs text-muted" style="margin: 0;">Supervisor</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-wrapper">
            <!-- Header -->
            <header class="mb-6">
                <h1 class="text-2xl font-semibold">Settings</h1>
                <p class="text-muted">Manage your account settings and security</p>
            </header>

            <div class="settings-container">
                <!-- User Profile Section -->
                <div class="card mb-8">
                    <div class="p-4" style="border-bottom: 1px solid var(--border-color); background-color: var(--surface-hover);">
                        <h2 class="text-lg font-medium" style="margin: 0;"><i class="fas fa-user mr-2"></i>Profile Information</h2>
                    </div>
                    <div class="p-6">
                        <div class="form-group mb-4">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['username']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="Supervisor" disabled>
                        </div>
                    </div>
                </div>

                <!-- Change Password Section -->
                <div class="card">
                    <div class="p-4" style="border-bottom: 1px solid var(--border-color); background-color: var(--surface-hover);">
                        <h2 class="text-lg font-medium" style="margin: 0;"><i class="fas fa-lock mr-2"></i>Change Password</h2>
                    </div>
                    <div class="p-6">
                        <form id="changePasswordForm" method="POST" action="../change_password.php">
                            <!-- Alert Messages -->
                            <div id="alertContainer"></div>

                            <!-- Current Password -->
                            <div class="form-group mb-4">
                                <label for="old_password" class="form-label">Current Password</label>
                                <input type="password" id="old_password" name="old_password" class="form-control" required placeholder="Enter your current password">
                            </div>

                            <!-- New Password -->
                            <div class="form-group mb-4">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="Enter new password" oninput="checkPasswordStrength()">
                                <div id="passwordStrength" class="password-strength"></div>
                            </div>

                            <!-- Confirm New Password -->
                            <div class="form-group mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Confirm new password" oninput="checkPasswordStrength()">
                            </div>

                            <!-- Password Requirements -->
                            <div class="requirements">
                                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem;">Password Requirements:</h4>
                                <div class="requirement-item unmet" id="req-length">
                                    <i class="fas fa-circle"></i>
                                    <span>At least 6 characters</span>
                                </div>
                                <div class="requirement-item unmet" id="req-upper">
                                    <i class="fas fa-circle"></i>
                                    <span>Contains uppercase letter (A-Z)</span>
                                </div>
                                <div class="requirement-item unmet" id="req-lower">
                                    <i class="fas fa-circle"></i>
                                    <span>Contains lowercase letter (a-z)</span>
                                </div>
                                <div class="requirement-item unmet" id="req-number">
                                    <i class="fas fa-circle"></i>
                                    <span>Contains number (0-9)</span>
                                </div>
                                <div class="requirement-item unmet" id="req-match">
                                    <i class="fas fa-circle"></i>
                                    <span>Passwords match</span>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check mr-2"></i>Update Password
                                </button>
                                <a href="dashboard.php" class="btn btn-outline">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function checkPasswordStrength() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check requirements
            const length = newPassword.length >= 6;
            const hasUpper = /[A-Z]/.test(newPassword);
            const hasLower = /[a-z]/.test(newPassword);
            const hasNumber = /[0-9]/.test(newPassword);
            const matches = newPassword === confirmPassword && newPassword.length > 0;
            
            // Update requirement indicators
            updateRequirement('req-length', length);
            updateRequirement('req-upper', hasUpper);
            updateRequirement('req-lower', hasLower);
            updateRequirement('req-number', hasNumber);
            updateRequirement('req-match', matches);
            
            // Calculate strength
            let strength = 0;
            if (length) strength++;
            if (hasUpper) strength++;
            if (hasLower) strength++;
            if (hasNumber) strength++;
            
            const strengthBar = document.getElementById('passwordStrength');
            strengthBar.classList.remove('weak', 'medium', 'strong');
            
            if (newPassword.length === 0) {
                strengthBar.classList.remove('weak', 'medium', 'strong');
            } else if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength === 3) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        }
        
        function updateRequirement(id, met) {
            const element = document.getElementById(id);
            if (met) {
                element.classList.remove('unmet');
                element.classList.add('met');
                element.querySelector('i').className = 'fas fa-check-circle';
            } else {
                element.classList.add('unmet');
                element.classList.remove('met');
                element.querySelector('i').className = 'fas fa-circle';
            }
        }
        
        document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('../change_password.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                const alertContainer = document.getElementById('alertContainer');
                
                if (data.success) {
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i>
                            ${data.message}
                        </div>
                    `;
                    document.getElementById('changePasswordForm').reset();
                    document.getElementById('passwordStrength').classList.remove('weak', 'medium', 'strong');
                    
                    // Reset requirements
                    document.querySelectorAll('.requirement-item').forEach(item => {
                        item.classList.add('unmet');
                        item.classList.remove('met');
                        item.querySelector('i').className = 'fas fa-circle';
                    });
                    
                    // Redirect after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            ${data.message}
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('alertContainer').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        An error occurred. Please try again.
                    </div>
                `;
            }
        });
    </script>
</body>
</html>