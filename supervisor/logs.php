<?php
// Load dependencies and enforce Supervisor role
require_once '../config.php';
require_once '../auth.php';
requireRole('supervisor');

// ============ PAGINATION LOGIC ============
$items_per_page = 15;  // Changed from 50 to 15 for better readability
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions");
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $items_per_page);

// Ensure current page doesn't exceed total pages
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}

// FETCH TRANSACTIONS WITH PAGINATION
$stmt = $pdo->prepare("
    SELECT t.*, m.name as medicine_name, u.username as user_name 
    FROM transactions t
    LEFT JOIN medicines m ON t.medicine_id = m.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.timestamp DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
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
    <style>
        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-main);
            transition: all 0.2s ease;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .pagination a:hover {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination span.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
            font-weight: 600;
        }
        
        .pagination span.disabled {
            color: var(--text-muted);
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .page-info {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 1rem;
            font-size: 0.9rem;
            padding: 1rem;
            background-color: var(--surface-hover);
            border-bottom: 1px solid var(--border-color);
        }

        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .logs-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .pagination {
                justify-content: flex-start;
            }
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
                <div style="width: 2rem; height: 2rem; border-radius: 50%; background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                    S
                </div>
                <div>
                    <p class="text-sm font-medium" style="margin: 0; line-height: 1.2;">Supervisor User</p>
                    <p class="text-xs text-muted" style="margin: 0;">Auditor</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="logs-header">
            <div>
                <h1 class="text-2xl font-semibold">Complete Audit Logs</h1>
                <p class="text-sm text-muted" style="margin-top: 0.5rem;">Total Transactions: <strong><?php echo $total_logs; ?></strong></p>
            </div>
            <button onclick="window.print()" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.5rem; background-color: white;">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
        </div>

        <div class="card">
            <!-- Search Bar -->
            <div class="p-4" style="border-bottom: 1px solid var(--border-color); background-color: var(--surface-hover);">
                <input type="text" id="logSearch" placeholder="Search logs by medicine or user..." class="form-control" style="max-width: 500px;">
            </div>

            <!-- Page Info -->
            <div class="page-info">
                Showing <?php echo ($logs ? $offset + 1 : 0); ?> to <?php echo min($offset + $items_per_page, $total_logs); ?> of <?php echo $total_logs; ?> transactions
            </div>

            <!-- Table -->
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
                        <?php if(empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                <i class="fas fa-inbox" style="font-size: 2rem; color: var(--border-color); margin-bottom: 1rem; display: block;"></i>
                                <p style="color: var(--text-muted);">No audit logs available.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($logs as $log): ?>
                                <?php
                                    $typeBadgeStyle = "padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; ";
                                    if ($log['type'] === 'addition') $typeBadgeStyle .= 'background-color: #ECFDF5; color: #065F46; border: 1px solid #34D399;';
                                    else if ($log['type'] === 'reduction') $typeBadgeStyle .= 'background-color: #FEF2F2; color: #991B1B; border: 1px solid #F87171;';
                                    else if ($log['type'] === 'created') $typeBadgeStyle .= 'background-color: #EFF6FF; color: #1E40AF; border: 1px solid #60A5FA;';
                                    else if ($log['type'] === 'deleted') $typeBadgeStyle .= 'background-color: #F3F4F6; color: #991B1B; border: 1px solid #D1D5DB;';
                                    else $typeBadgeStyle .= 'background-color: #F3F4F6; color: #1F2937; border: 1px solid #D1D5DB;';
                                ?>
                            <tr class="log-row">
                                <td class="text-muted" style="font-weight: 600;">#<?php echo $log['id']; ?></td>
                                <td class="text-muted" style="font-size: 0.9rem;">
                                    <div><?php echo date('M d, Y', strtotime($log['timestamp'])); ?></div>
                                    <div style="font-size: 0.85rem; color: #9CA3AF;"><?php echo date('H:i:s', strtotime($log['timestamp'])); ?></div>
                                </td>
                                <td class="font-medium log-user">
                                    <i class="fas fa-user-circle" style="margin-right: 0.5rem; color: var(--primary);"></i>
                                    <?php echo htmlspecialchars($log['user_name'] ?? 'System/Deleted'); ?>
                                </td>
                                <td>
                                    <span style="<?php echo $typeBadgeStyle; ?>">
                                        <i class="fas fa-<?php 
                                            if($log['type'] === 'addition') echo 'plus';
                                            elseif($log['type'] === 'reduction') echo 'minus';
                                            elseif($log['type'] === 'created') echo 'plus-circle';
                                            elseif($log['type'] === 'deleted') echo 'trash';
                                            else echo 'edit';
                                        ?>"></i> <?php echo ucfirst($log['type']); ?>
                                    </span>
                                </td>
                                <td class="log-medicine">
                                    <i class="fas fa-pills" style="margin-right: 0.5rem; color: var(--secondary);"></i>
                                    <?php echo htmlspecialchars($log['medicine_name'] ?? 'Unknown/Deleted (ID: '.$log['medicine_id'].')'); ?>
                                </td>
                                <td class="font-bold">
                                    <?php 
                                        if ($log['type'] == 'reduction') echo "<span style='color: var(--danger);'><i class='fas fa-arrow-down'></i> {$log['quantity']}</span>";
                                        else if ($log['type'] == 'addition' || $log['type'] == 'created') echo "<span style='color: var(--secondary);'><i class='fas fa-arrow-up'></i> {$log['quantity']}</span>";
                                        else echo "<span>{$log['quantity']}</span>";
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ============ ENHANCED PAGINATION BUTTONS ============ -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <!-- First & Previous -->
                <?php if($current_page > 1): ?>
                    <a href="logs.php?page=1" title="First Page">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="logs.php?page=<?php echo $current_page - 1; ?>" title="Previous Page">
                        <i class="fas fa-angle-left"></i> Previous
                    </a>
                <?php else: ?>
                    <span class="disabled" title="First Page">
                        <i class="fas fa-angle-double-left"></i>
                    </span>
                    <span class="disabled" title="Previous Page">
                        <i class="fas fa-angle-left"></i> Previous
                    </span>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php 
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if($start_page > 1): ?>
                        <span>...</span>
                    <?php endif;
                    
                    for($p = $start_page; $p <= $end_page; $p++): 
                        if($p == $current_page): ?>
                            <span class="active"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a href="logs.php?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                        <?php endif;
                    endfor;
                    
                    if($end_page < $total_pages): ?>
                        <span>...</span>
                    <?php endif; ?>

                <!-- Next & Last -->
                <?php if($current_page < $total_pages): ?>
                    <a href="logs.php?page=<?php echo $current_page + 1; ?>" title="Next Page">
                        Next <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="logs.php?page=<?php echo $total_pages; ?>" title="Last Page">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled" title="Next Page">
                        Next <i class="fas fa-angle-right"></i>
                    </span>
                    <span class="disabled" title="Last Page">
                        <i class="fas fa-angle-double-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const logSearch = document.getElementById('logSearch');
            const logRows = document.querySelectorAll('.log-row');

            logSearch.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
                let visibleCount = 0;
                
                logRows.forEach(row => {
                    const user = row.querySelector('.log-user').textContent.toLowerCase();
                    const medicine = row.querySelector('.log-medicine').textContent.toLowerCase();
                    
                    if (user.includes(query) || medicine.includes(query)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Show no results message
                const noResultsRow = document.querySelector('.no-results');
                if (visibleCount === 0 && logRows.length > 0) {
                    if (!noResultsRow) {
                        const table = document.querySelector('#logTable tbody');
                        const row = table.insertRow();
                        row.className = 'no-results';
                        row.innerHTML = '<td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);"><i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i><p>No logs matched your search criteria.</p></td>';
                    }
                } else if (noResultsRow) {
                    noResultsRow.remove();
                }
            });
        });
    </script>
</body>
</html>