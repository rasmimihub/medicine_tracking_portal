<?php
// Load dependencies and enforce Supervisor role
require_once '../config.php';
require_once '../auth.php';
requireRole('supervisor');

// FETCH ALL TRANSACTIONS (AUDIT LOG):
// This massive query joins the 'transactions' table with 'medicines' and 'users'
// to reconstruct the complete historical narrative. Even if a user or medicine is deleted,
// the main transaction row survives (the LEFT JOIN will simply return NULL/empty for the name).
// PAGINATION LOGIC:
$limit = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get total for calculations
$total_stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
$total_logs = $total_stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

$stmt = $pdo->prepare("
    SELECT t.*, m.name as medicine_name, u.username as user_name 
    FROM transactions t
    LEFT JOIN medicines m ON t.medicine_id = m.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.timestamp DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Supervisor Portal</title>
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
            <a href="dashboard.php" class="sidebar-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="logs.php" class="sidebar-link active">
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
        <header class="mb-6 flex justify-between items-center" style="display: flex; justify-content: space-between; align-items: center;">
            <h1 class="text-2xl font-semibold">Complete Audit Logs</h1>
            <button onclick="window.print()" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.5rem; background-color: white;">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
        </header>

        <div class="card">
            <div class="p-4" style="border-bottom: 1px solid var(--border-color); background-color: var(--surface-hover);">
                <input type="text" id="logSearch" placeholder="Search logs by medicine or user..." class="form-control" style="max-width: 400px;">
            </div>
            <div class="table-responsive">
                <table class="table" id="logTable">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Medicine</th>
                            <th>QTY Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                            <?php
                                $typeBadgeStyle = "padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; ";
                                if ($log['type'] === 'addition') $typeBadgeStyle .= 'background-color: #ECFDF5; color: #065F46; border: 1px solid #34D399;';
                                else if ($log['type'] === 'reduction') $typeBadgeStyle .= 'background-color: #FEF2F2; color: #991B1B; border: 1px solid #F87171;';
                                else if ($log['type'] === 'created') $typeBadgeStyle .= 'background-color: #EFF6FF; color: #1E40AF; border: 1px solid #60A5FA;';
                                else $typeBadgeStyle .= 'background-color: #F3F4F6; color: #1F2937; border: 1px solid #D1D5DB;';
                            ?>
                        <tr class="log-row">
                            <td class="text-muted">#<?php echo $log['id']; ?></td>
                            <td class="text-muted"><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                            <td class="font-medium log-user"><?php echo htmlspecialchars($log['user_name'] ?? 'System/Deleted'); ?></td>
                            <td>
                                <span style="<?php echo $typeBadgeStyle; ?>">
                                    <?php echo ucfirst($log['type']); ?>
                                </span>
                            </td>
                            <td class="log-medicine"><?php echo htmlspecialchars($log['medicine_name'] ?? 'Unknown/Deleted (ID: '.$log['medicine_id'].')'); ?></td>
                            <td class="font-bold">
                                <?php 
                                    if ($log['type'] == 'reduction') echo "<span style='color: var(--danger);'>-{$log['quantity']}</span>";
                                    else if ($log['type'] == 'addition' || $log['type'] == 'created') echo "<span style='color: var(--secondary);'>+{$log['quantity']}</span>";
                                    else echo $log['quantity'];
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <div class="p-4" style="border-top: 1px solid var(--border-color); background-color: var(--surface); display: flex; align-items: center; justify-content: space-between;">
                <div class="text-sm text-muted">
                    Showing <?php echo $total_logs > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_logs); ?> of <?php echo $total_logs; ?> logs
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.875rem;">Previous</a>
                    <?php else: ?>
                        <button class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.875rem; opacity: 0.5; cursor: not-allowed;" disabled>Previous</button>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.875rem;">Next</a>
                    <?php else: ?>
                        <button class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.875rem; opacity: 0.5; cursor: not-allowed;" disabled>Next</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const logSearch = document.getElementById('logSearch');
            const logRows = document.querySelectorAll('.log-row');

            logSearch.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
                
                logRows.forEach(row => {
                    const user = row.querySelector('.log-user').textContent.toLowerCase();
                    const medicine = row.querySelector('.log-medicine').textContent.toLowerCase();
                    
                    if (user.includes(query) || medicine.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
