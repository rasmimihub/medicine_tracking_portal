<?php
// Load dependencies and enforce Supervisor role
require_once '../config.php';
require_once '../auth.php';
requireRole('supervisor');

// 1. STATS: Fetch total missing stock
$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE stock = 0");
$out_of_stock = $stmt->fetchColumn();

// 2. STATS: Fetch total transactions executed exactly today (uses MySQL CURDATE function)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE DATE(timestamp) = CURDATE()");
$today_transactions = $stmt->fetchColumn();

// 3. STATS: Fetch medicines whose expiry date has passed relative to today's database date
$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE expiry_date <= CURDATE()");
$expired_meds = $stmt->fetchColumn();

// 4. RECENT LOGS: 
// Fetch the latest 10 transactions. We use LEFT JOIN to pull in read-friendly names 
// for the medicines and users rather than just raw numeric IDs. Default to NULL if deleted.
$stmt = $pdo->query("
    SELECT t.*, m.name as medicine_name, u.username as user_name 
    FROM transactions t
    LEFT JOIN medicines m ON t.medicine_id = m.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.timestamp DESC
    LIMIT 10
");
$recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard</title>
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            <a href="dashboard.php" class="sidebar-link active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="logs.php" class="sidebar-link">
                <i class="fas fa-clipboard-list"></i>
                <span>Audit Logs</span>
            </a>
            <a href="../logout.php" class="sidebar-link" style="margin-top: 2rem; color: var(--danger);">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
        <div class="p-4" style="border-top: 1px solid var(--border-color);">
            <div class="flex items-center gap-2">
                <div style="width: 2rem; height: 2rem; border-radius: 50%; background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.875rem;">S</div>
                <div>
                    <p class="text-sm font-medium" style="margin: 0; line-height: 1.2;">Supervisor User</p>
                    <p class="text-xs text-muted" style="margin: 0;">Auditor</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="mb-6">
            <h1 class="text-2xl font-semibold">Supervisor Overview</h1>
        </header>

        <div class="stats-grid">
            <!-- Card 1 -->
            <div class="card stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-details">
                    <h3>Today's Transactions</h3>
                    <p><?php echo $today_transactions; ?></p>
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
                <div class="stat-icon secondary" style="background-color: #F97316;">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-details">
                    <h3>Expired Medicines</h3>
                    <p><?php echo $expired_meds; ?></p>
                </div>
            </div>
        </div>

        <!-- Recent Logs Section -->
        <div class="card mt-8">
            <div class="p-4 flex justify-between items-center" style="border-bottom: 1px solid var(--border-color); background-color: var(--surface-hover);">
                <h2 class="text-lg font-medium" style="margin: 0;">Recent Audit Logs</h2>
                <a href="logs.php" style="font-size: 0.875rem; font-weight: 500;">View All &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Medicine</th>
                            <th>QTY Changed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_logs as $log): ?>
                            <?php
                                $typeBadgeStyle = "padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; ";
                                if ($log['type'] === 'addition') $typeBadgeStyle .= 'background-color: #ECFDF5; color: #065F46; border: 1px solid #34D399;';
                                else if ($log['type'] === 'reduction') $typeBadgeStyle .= 'background-color: #FEF2F2; color: #991B1B; border: 1px solid #F87171;';
                                else if ($log['type'] === 'created') $typeBadgeStyle .= 'background-color: #EFF6FF; color: #1E40AF; border: 1px solid #60A5FA;';
                                else $typeBadgeStyle .= 'background-color: #F3F4F6; color: #1F2937; border: 1px solid #D1D5DB;';
                            ?>
                        <tr>
                            <td class="text-muted"><?php echo date('M d, H:i', strtotime($log['timestamp'])); ?></td>
                            <td class="font-medium"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                            <td>
                                <span style="<?php echo $typeBadgeStyle; ?>">
                                    <?php echo ucfirst($log['type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['medicine_name'] ?? 'Unknown/Deleted'); ?></td>
                            <td class="font-bold">
                                <?php echo $log['quantity'] != 0 ? $log['quantity'] : '-'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent_logs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">No recent transactions found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>
