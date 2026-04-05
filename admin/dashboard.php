<?php
// Load dependencies
require_once '../config.php';
require_once '../auth.php';

// ENFORCE SECURITY: Check if user is logged in AND holds the 'admin' execution role.
requireRole('admin');

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

</body>
</html>
