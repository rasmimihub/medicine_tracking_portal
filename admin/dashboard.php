<?php
// Load dependencies
require_once '../config.php';
require_once '../auth.php';

// ENFORCE SECURITY: Check if user is logged in AND holds the 'admin' execution role.
requireRole('admin');
// Fetch current user details
$stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// SECURE PASSWORD CHANGE HANDLER - ADMIN ONLY
$password_message = '';
$password_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    // INPUT VALIDATION
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'Password must be at least 6 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $password_error = 'Password must contain uppercase, lowercase, and numbers.';
    } else {
        // FETCH current hash from database
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // VERIFY old password against stored hash
        if (!$user || !password_verify($old_password, $user['password_hash'])) {
            $password_error = 'Current password is incorrect.';
        } elseif (password_verify($new_password, $user['password_hash'])) {
            $password_error = 'New password cannot be the same as current password.';
        } else {
            // HASH new password using PASSWORD_BCRYPT
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            
            // UPDATE ONLY users table - NO transaction logging
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            
            if ($stmt->execute([$new_password_hash, $user_id])) {
                // VERIFY update was successful
                $verify_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
                $verify_stmt->execute([$user_id]);
                $updated_user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                
                // CONFIRM new password works
                if ($updated_user && password_verify($new_password, $updated_user['password_hash'])) {
                    // DESTROY session - force re-login with new password
                    session_destroy();
                    $password_message = 'Password changed successfully! Please login with your new password.';
                    
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "../admin_login.php";
                        }, 3000);
                    </script>';
                } else {
                    $password_error = 'Password verification failed. Please try again.';
                }
            } else {
                $password_error = 'Failed to update password. Please try again.';
            }
        }
    }
}
// Handle Clear Stock logic for Expired Items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_stock') {
    $id = intval($_POST['id']);
    $stmt = $pdo->prepare("SELECT stock FROM medicines WHERE id = ?");
    $stmt->execute([$id]);
    $old_med = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($old_med && $old_med['stock'] > 0) {
        $stmt = $pdo->prepare("UPDATE medicines SET stock = 0 WHERE id = ?");
        if ($stmt->execute([$id])) {
            logTransaction($pdo, $id, 'reduction', $old_med['stock'], $_SESSION['user_id']);
            header("Location: dashboard.php");
            exit;
        }
    }
}

// 1. Fetch total count of all medicines via aggregation
$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines");
$total_medicines = $stmt->fetchColumn();

// 2. Fetch critical stock alerts (stock exactly equal to 0)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE stock = 0");
$out_of_stock = $stmt->fetchColumn();

// 3. Fetch warnings (stock running low, but not empty)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE stock > 0 AND stock <= min_stock");
$low_stock = $stmt->fetchColumn();

// 4. Detailed list of alerts to display in the UI list
$stmt = $pdo->query("SELECT * FROM medicines WHERE stock <= min_stock AND stock > 0 ORDER BY stock ASC LIMIT 5");
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Expired Medicines
$stmt = $pdo->query("SELECT * FROM medicines WHERE expiry_date < CURDATE() AND stock > 0 ORDER BY expiry_date ASC");
$expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard-layout">
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="nav-brand" style="font-size: 1.1rem;">
                <i class="fas fa-pills"></i>
                <span>Medicine Tracking Portal</span>
            </div>
        </div>
               <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="medicines.php" class="sidebar-link">
                <i class="fas fa-box"></i>
                <span>Manage Medicines</span>
            </a>
            <a href="#changePasswordModal" class="sidebar-link" onclick="openChangePasswordModal(event)">
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
                <div style="width: 2rem; height: 2rem; border-radius: 50%; background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.875rem;">A</div>
                <div>
                    <p class="text-sm font-medium" style="margin: 0; line-height: 1.2;">Admin User</p>
                    <p class="text-xs text-muted" style="margin: 0;">Pharmacist</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-wrapper">
        <!-- Header -->
        <header class="mb-6">
            <h1 class="text-2xl font-semibold">Dashboard Overview</h1>
        </header>

        <div class="stats-grid">
            <!-- Card 1 -->
            <div class="card stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-cube"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Medicines</h3>
                    <p><?php echo $total_medicines; ?></p>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="card stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-details">
                    <h3>Out of Stock</h3>
                    <p><?php echo $out_of_stock; ?></p>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="card stat-card">
                <div class="stat-icon secondary" style="background-color: #F59E0B;">
                    <i class="fas fa-battery-quarter"></i>
                </div>
                <div class="stat-details">
                    <h3>Low Stock (Alerts)</h3>
                    <p><?php echo $low_stock; ?></p>
                </div>
            </div>
        </div>

        <!-- Alerts and Expired Section Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-top: 2rem;">
            
            <!-- Alerts Section -->
            <div class="card">
                <div class="p-4" style="border-bottom: 1px solid var(--border-color); background-color: var(--surface-hover);">
                    <h2 class="text-lg font-medium" style="margin:0;">Inventory Alerts</h2>
                </div>
                <div class="p-0">
                    <?php if(empty($alerts)): ?>
                        <div class="p-8 text-center text-muted">
                            <i class="fas fa-check-circle text-4xl mb-3 text-success"></i>
                            <p>Inventory is looking good. No low stock items.</p>
                        </div>
                    <?php else: ?>
                        <ul style="list-style: none;">
                            <?php foreach($alerts as $alert): ?>
                            <li class="px-6 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--border-color);">
                                <div class="flex items-center">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; margin-right: 12px; background-color: #F59E0B;"></div>
                                    <span class="font-medium"><?php echo htmlspecialchars($alert['name']); ?></span>
                                </div>
                                <span style="background-color: var(--surface-hover); color: var(--text-muted); border: 1px solid var(--border-color); padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                                    Stock: <?php echo $alert['stock']; ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Expired Section -->
            <div class="card">
                <div class="p-4" style="border-bottom: 1px solid var(--border-color); background-color: #FEF2F2;">
                    <h2 class="text-lg font-medium" style="margin:0; color: var(--danger);"><i class="fas fa-biohazard mr-2"></i> Expired Stock</h2>
                </div>
                <div class="p-0">
                    <?php if(empty($expired)): ?>
                        <div class="p-8 text-center text-muted">
                            <i class="fas fa-check-circle text-4xl mb-3 text-success"></i>
                            <p>No expired inventory.</p>
                        </div>
                    <?php else: ?>
                        <ul style="list-style: none;">
                            <?php foreach($expired as $exp): ?>
                            <li class="px-6 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--border-color);">
                                <div class="flex items-center">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; margin-right: 12px; background-color: var(--danger);"></div>
                                    <div style="display: flex; flex-direction: column;">
                                        <span class="font-medium"><?php echo htmlspecialchars($exp['name']); ?></span>
                                        <span class="text-xs text-danger" style="margin-top: 0.1rem;">Exp: <?php echo date('M d, Y', strtotime($exp['expiry_date'])); ?></span>
                                    </div>
                                </div>
                                <form method="POST" action="dashboard.php" style="margin: 0;" onsubmit="return confirm('Clear all stock for this expired item?');">
                                    <input type="hidden" name="action" value="clear_stock">
                                    <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                                    <button type="submit" class="btn btn-sm" style="background: transparent; border: 1px solid var(--danger); color: var(--danger); padding: 0.25rem 0.75rem;">Clear Stock (<?php echo $exp['stock']; ?>)</button>
                                </form>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        </div>
    </main>
    <!-- Change Password Modal -->
    <div id="changePasswordModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background-color: white; border-radius: 8px; width: 90%; max-width: 500px; padding: 2rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <button onclick="closeChangePasswordModal()" style="float: right; background: none; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
            
            <h2 style="margin-top: 0; margin-bottom: 1.5rem;"><i class="fas fa-lock mr-2"></i>Change Password</h2>
            
            <?php if($password_message): ?>
            <div style="background-color: #ECFDF5; color: #065F46; border: 1px solid #34D399; border-radius: 6px; padding: 1rem; margin-bottom: 1rem;">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($password_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if($password_error): ?>
            <div style="background-color: #FEF2F2; color: #991B1B; border: 1px solid #F87171; border-radius: 6px; padding: 1rem; margin-bottom: 1rem;">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($password_error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="dashboard.php">
                <input type="hidden" name="change_password" value="1">
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Current Password</label>
                    <input type="password" name="old_password" required style="width: 100%; padding: 0.75rem; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 1rem; box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">New Password</label>
                    <input type="password" id="adminNewPassword" name="new_password" required oninput="checkAdminPasswordStrength()" style="width: 100%; padding: 0.75rem; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 1rem; box-sizing: border-box;">
                    <div id="adminPasswordStrength" style="height: 6px; border-radius: 3px; margin-top: 0.5rem; background-color: #E5E7EB;"></div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Confirm New Password</label>
                    <input type="password" id="adminConfirmPassword" name="confirm_password" required oninput="checkAdminPasswordStrength()" style="width: 100%; padding: 0.75rem; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 1rem; box-sizing: border-box;">
                </div>
                
                <div style="background-color: #F3F4F6; border: 1px solid #E5E7EB; border-radius: 6px; padding: 1rem; margin-bottom: 1rem; font-size: 0.875rem;">
                    <div id="adminReq-length" style="display: flex; align-items: center; gap: 0.5rem; margin: 0.5rem 0; color: #9CA3AF;"><i class="fas fa-circle"></i><span>At least 6 characters</span></div>
                    <div id="adminReq-upper" style="display: flex; align-items: center; gap: 0.5rem; margin: 0.5rem 0; color: #9CA3AF;"><i class="fas fa-circle"></i><span>Uppercase (A-Z)</span></div>
                    <div id="adminReq-lower" style="display: flex; align-items: center; gap: 0.5rem; margin: 0.5rem 0; color: #9CA3AF;"><i class="fas fa-circle"></i><span>Lowercase (a-z)</span></div>
                    <div id="adminReq-number" style="display: flex; align-items: center; gap: 0.5rem; margin: 0.5rem 0; color: #9CA3AF;"><i class="fas fa-circle"></i><span>Number (0-9)</span></div>
                    <div id="adminReq-match" style="display: flex; align-items: center; gap: 0.5rem; margin: 0.5rem 0; color: #9CA3AF;"><i class="fas fa-circle"></i><span>Passwords match</span></div>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" style="flex: 1; padding: 0.75rem; background-color: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">Update Password</button>
                    <button type="button" onclick="closeChangePasswordModal()" style="flex: 1; padding: 0.75rem; background-color: #E5E7EB; color: #1F2937; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openChangePasswordModal(e) {
            if(e) e.preventDefault();
            document.getElementById('changePasswordModal').style.display = 'flex';
        }
        
        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
        }
        
        function checkAdminPasswordStrength() {
            const newPassword = document.getElementById('adminNewPassword').value;
            const confirmPassword = document.getElementById('adminConfirmPassword').value;
            
            const length = newPassword.length >= 6;
            const hasUpper = /[A-Z]/.test(newPassword);
            const hasLower = /[a-z]/.test(newPassword);
            const hasNumber = /[0-9]/.test(newPassword);
            const matches = newPassword === confirmPassword && newPassword.length > 0;
            
            updateAdminRequirement('adminReq-length', length);
            updateAdminRequirement('adminReq-upper', hasUpper);
            updateAdminRequirement('adminReq-lower', hasLower);
            updateAdminRequirement('adminReq-number', hasNumber);
            updateAdminRequirement('adminReq-match', matches);
            
            let strength = 0;
            if (length) strength++;
            if (hasUpper) strength++;
            if (hasLower) strength++;
            if (hasNumber) strength++;
            
            const strengthBar = document.getElementById('adminPasswordStrength');
            strengthBar.style.width = '0%';
            
            if (newPassword.length > 0) {
                if (strength <= 2) {
                    strengthBar.style.backgroundColor = '#EF4444';
                    strengthBar.style.width = '33%';
                } else if (strength === 3) {
                    strengthBar.style.backgroundColor = '#F59E0B';
                    strengthBar.style.width = '66%';
                } else {
                    strengthBar.style.backgroundColor = '#10B981';
                    strengthBar.style.width = '100%';
                }
            }
        }
        
        function updateAdminRequirement(id, met) {
            const element = document.getElementById(id);
            if (met) {
                element.style.color = '#10B981';
                element.querySelector('i').className = 'fas fa-check-circle';
            } else {
                element.style.color = '#9CA3AF';
                element.querySelector('i').className = 'fas fa-circle';
            }
        }

        document.getElementById('changePasswordModal').addEventListener('click', function(e) {
            if(e.target === this) closeChangePasswordModal();
        });
    </script>
</body>
</html>
