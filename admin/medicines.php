<?php
// Load security and configs
require_once '../config.php';
require_once '../auth.php';

// Enforce strict admin access
requireRole('admin');

$action = $_GET['action'] ?? '';
$error = '';
$success = '';

// HANDLE CRUD OPERATIONS (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD NEW MEDICINE LOGIC
  if ($action === 'create') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category'] ?? 'General');
    $subsidy_type = trim($_POST['subsidy_type'] ?? '');  // NEW
    $stock = intval($_POST['stock']);
    $min_stock = intval($_POST['min_stock'] ?? 10);
    $expiry_date = $_POST['expiry_date'];

    // VALIDATION: Ensure subsidy_type is provided
    if ($name && $expiry_date && !empty($subsidy_type)) {
        // Insert into Database with subsidy_type
        $stmt = $pdo->prepare("INSERT INTO medicines (name, category, subsidy_type, stock, min_stock, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $category, $subsidy_type, $stock, $min_stock, $expiry_date])) {
            $medicine_id = $pdo->lastInsertId();
            logTransaction($pdo, $medicine_id, 'created', $stock, $_SESSION['user_id']);
            $success = "Medicine added successfully.";
        } else {
            $error = "Failed to add medicine.";
        }
    } else {
        $error = "Name, Expiry Date, and Availability Type are required.";
    }
}
    } 
    // UPDATE EXISTING MEDICINE LOGIC
    elseif ($action === 'update') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $category = trim($_POST['category'] ?? 'General');
    $subsidy_type = trim($_POST['subsidy_type'] ?? '');  // NEW
    $new_stock = intval($_POST['stock']);
    $min_stock = intval($_POST['min_stock'] ?? 10);
    $expiry_date = $_POST['expiry_date'];

    if (empty($subsidy_type)) {
        $error = "Availability Type is required.";
    } else {
        $stmt = $pdo->prepare("SELECT stock FROM medicines WHERE id = ?");
        $stmt->execute([$id]);
        $old_med = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($old_med) {
            $old_stock = intval($old_med['stock']);
            $diff = $new_stock - $old_stock;

            // Update with subsidy_type
            $stmt = $pdo->prepare("UPDATE medicines SET name = ?, category = ?, subsidy_type = ?, stock = ?, min_stock = ?, expiry_date = ? WHERE id = ?");
            if ($stmt->execute([$name, $category, $subsidy_type, $new_stock, $min_stock, $expiry_date, $id])) {
                // Smart audit trail routing...
                if ($diff > 0) {
                    logTransaction($pdo, $id, 'addition', $diff, $_SESSION['user_id']);
                } elseif ($diff < 0) {
                    logTransaction($pdo, $id, 'reduction', abs($diff), $_SESSION['user_id']);
                } else {
                    logTransaction($pdo, $id, 'adjustment', 0, $_SESSION['user_id']);
                }
                $success = "Medicine updated successfully.";
            }
        }
    }
}
    // QUICK STOCK ADJUSTMENT
    elseif ($action === 'quick_stock') {
        $id = intval($_POST['id']);
        $change = intval($_POST['change']); // +1, -1, etc.
        
        $stmt = $pdo->prepare("SELECT stock FROM medicines WHERE id = ?");
        $stmt->execute([$id]);
        $old_med = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($old_med) {
            $new_stock = max(0, $old_med['stock'] + $change); // Prevent negative stock
            $diff = $new_stock - $old_med['stock'];
            
            if ($diff != 0) {
                $stmt = $pdo->prepare("UPDATE medicines SET stock = ? WHERE id = ?");
                if ($stmt->execute([$new_stock, $id])) {
                    $type = $diff > 0 ? 'addition' : 'reduction';
                    logTransaction($pdo, $id, $type, abs($diff), $_SESSION['user_id']);
                    $success = "Stock instantly updated.";
                }
            }
        }
    } 
    // DELETE MEDICINE LOGIC
    elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        // The foreign key constraint in our database uses ON DELETE CASCADE.
        // Therefore, deleting the medicine automatically drops its associated logs in the transactions table.
        $stmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = "Medicine deleted successfully.";
        }
    }

// FETCH ALL MEDICINES FOR DISPLAY: Orders chronologically by newest first
$stmt = $pdo->query("SELECT * FROM medicines ORDER BY id DESC");
$medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// EDIT MODE PRE-FILL TRIGGER: 
// If an admin clicks the "Edit" button matching a URL parameter (e.g. ?action=edit&id=5)
// This block fetches that exact medicine's existing data so the HTML form below is pre-filled.
$edit_med = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ?");
    $stmt->execute([$id]);
    $edit_med = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Medicines - Admin Portal</title>
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        @media (min-width: 1024px) {
            .grid-layout {
                grid-template-columns: 350px 1fr;
            }
        }
    </style>
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
            <a href="dashboard.php" class="sidebar-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="medicines.php" class="sidebar-link active">
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
        <header class="mb-6">
            <h1 class="text-2xl font-semibold">Medicines Directory</h1>
        </header>

        <div>
            <?php if($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="grid-layout">
                <!-- Form Area -->
                <div>
                    <div class="card p-6" style="position: sticky; top: 2rem;">
                        <h2 class="text-lg font-semibold mb-4" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                            <?php echo $edit_med ? 'Edit Medicine' : 'Add New Medicine'; ?>
                        </h2>
                        
                        <form method="POST" action="medicines.php">
                            <?php if($edit_med): ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?php echo $edit_med['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="action" value="create">
                            <?php endif; ?>

                            <div class="form-group">
                                <label class="form-label">Medicine Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo $edit_med ? htmlspecialchars($edit_med['name']) : ''; ?>" required>
                            </div>


                            <div class="form-group">
                                <label class="form-label">Medicine Category</label>
                                <?php $cat = $edit_med ? $edit_med['category'] : 'General'; ?>
                                <select name="category" class="form-control" required>
                                    <option value="General" <?php echo $cat === 'General' ? 'selected' : ''; ?>>General</option>
                                    <option value="Antibiotics" <?php echo $cat === 'Antibiotics' ? 'selected' : ''; ?>>Antibiotics</option>
                                    <option value="Pain Relief" <?php echo $cat === 'Pain Relief' ? 'selected' : ''; ?>>Pain Relief</option>
                                    <option value="Vitamins" <?php echo $cat === 'Vitamins' ? 'selected' : ''; ?>>Vitamins</option>
                                    <option value="Vaccines" <?php echo $cat === 'Vaccines' ? 'selected' : ''; ?>>Vaccines</option>
                                </select>
                            </div>

                                <!-- NEW: Add Subsidy Type Field -->
                                <div class="form-group">
                                    <label class="form-label">Availability Type <span style="color: var(--danger);">*</span></label>
                                    <?php $subsidy = $edit_med ? $edit_med['subsidy_type'] : ''; ?>
                                    <select name="subsidy_type" class="form-control" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="Free" <?php echo $subsidy === 'Free' ? 'selected' : ''; ?>>Free</option>
                                        <option value="Subsidized" <?php echo $subsidy === 'Subsidized' ? 'selected' : ''; ?>>Subsidized</option>
                                    </select>
                                </div>

                            <div class="form-group">
                                <label class="form-label">Current Stock</label>
                                <input type="number" name="stock" min="0" class="form-control" value="<?php echo $edit_med ? $edit_med['stock'] : '0'; ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Min Reorder Level</label>
                                <input type="number" name="min_stock" min="0" class="form-control" value="<?php echo $edit_med ? $edit_med['min_stock'] : '10'; ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" name="expiry_date" min="<?php echo date('Y-m-d'); ?>" class="form-control" value="<?php echo $edit_med ? $edit_med['expiry_date'] : ''; ?>" required>
                            </div>

                            <div class="mt-4 flex items-center justify-between" style="gap: 1rem;">
                                <?php if($edit_med): ?>
                                    <a href="medicines.php" class="text-sm text-muted">Cancel</a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary" style="flex:1;">
                                    <?php echo $edit_med ? 'Update Medicine' : 'Add Medicine'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Table Area -->
                <div>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                       <th class="sortable" data-sort="id" style="cursor: pointer;"><i class="fas fa-sort mr-1"></i> ID</th>
                                        <th class="sortable" data-sort="name" style="cursor: pointer;"><i class="fas fa-sort mr-1"></i> Name</th>
                                        <th class="sortable" data-sort="category" style="cursor: pointer;"><i class="fas fa-sort mr-1"></i> Category</th>
                                        <th class="sortable" data-sort="subsidy_type" style="cursor: pointer;"><i class="fas fa-sort mr-1"></i> Type</th>
                                        <th class="sortable" data-sort="stock" style="cursor: pointer;"><i class="fas fa-sort mr-1"></i> Stock</th>
                                        <th class="sortable" data-sort="expiry" style="cursor: pointer;"><i class="fas fa-sort mr-1"></i> Expiry</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($medicines as $med): ?>
                                    <tr>
                                         <td class="text-muted">#<?php echo $med['id']; ?></td>
                                        <td class="font-medium text-main"><?php echo htmlspecialchars($med['name']); ?></td>
                                        <td><span style="font-size: 0.8rem; background: var(--surface-hover); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-color);">
                                            <?php echo htmlspecialchars($med['category']); ?>
                                        </span></td>
                                        <!-- NEW: Subsidy Type Column -->
                                        <td>
                                            <span style="font-size: 0.8rem; background: <?php echo $med['subsidy_type'] === 'Free' ? '#ECFDF5' : ($med['subsidy_type'] === 'Subsidized' ? '#FEF3C7' : '#F0F0F0'); ?>; color: <?php echo $med['subsidy_type'] === 'Free' ? '#065F46' : ($med['subsidy_type'] === 'Subsidized' ? '#92400E' : '#666'); ?>; padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid <?php echo $med['subsidy_type'] === 'Free' ? '#34D399' : ($med['subsidy_type'] === 'Subsidized' ? '#FBBF24' : '#ddd'); ?>;">
                                                <?php echo htmlspecialchars($med['subsidy_type'] ?? 'Not Set'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="medicines.php" style="display: flex; align-items: center; gap: 0.5rem;">
                                                <input type="hidden" name="action" value="quick_stock">
                                                <input type="hidden" name="id" value="<?php echo $med['id']; ?>">
                                                
                                                <button type="submit" name="change" value="-1" class="btn" style="padding: 0 0.4rem; height: 24px; font-size: 0.8rem; background: var(--surface-hover); border: 1px solid var(--border-color); color: var(--text-main);">-</button>
                                                
                                                <?php 
                                                // Determine styles dynamically inline here to ensure styling portability
                                                $stockBadgeStyle = "padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; min-width: 35px; text-align: center; ";
                                                $min_stock = $med['min_stock'];
                                                if ($med['stock'] > $min_stock * 2) $stockBadgeStyle .= "background-color: #ECFDF5; color: #065F46; border: 1px solid #34D399;";
                                                else if ($med['stock'] > $min_stock) $stockBadgeStyle .= "background-color: #FEF3C7; color: #92400E; border: 1px solid #FBBF24;";
                                                else $stockBadgeStyle .= "background-color: #FEF2F2; color: #991B1B; border: 1px solid #F87171;";
                                                ?>
                                                <span style="<?php echo $stockBadgeStyle; ?>" title="Min allowed: <?php echo $min_stock; ?>">
                                                    <?php echo $med['stock']; ?>
                                                </span>

                                                <button type="submit" name="change" value="1" class="btn" style="padding: 0 0.4rem; height: 24px; font-size: 0.8rem; background: var(--surface-hover); border: 1px solid var(--border-color); color: var(--text-main);">+</button>
                                            </form>
                                        </td>
                                        <td class="text-muted"><?php echo date('M d, Y', strtotime($med['expiry_date'])); ?></td>
                                        <td style="text-align: right;">
                                            <a href="?action=edit&id=<?php echo $med['id']; ?>" class="text-primary mr-3" title="Edit" style="margin-right: 0.75rem;"><i class="fas fa-edit"></i></a>
                                            <form method="POST" action="medicines.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $med['id']; ?>">
                                                <button type="submit" class="text-danger" style="background:none; border:none; cursor:pointer;" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;

            const comparer = (idx, asc) => (a, b) => ((v1, v2) => 
                v1 !== '' && v2 !== '' && !isNaN(v1) && !isNaN(v2) ? v1 - v2 : v1.toString().localeCompare(v2)
                )(getCellValue(asc ? a : b, idx).replace(/[^a-zA-Z0-9.\-]/g, ''), getCellValue(asc ? b : a, idx).replace(/[^a-zA-Z0-9.\-]/g, ''));

            document.querySelectorAll('th.sortable').forEach(th => th.addEventListener('click', (() => {
                const table = th.closest('table').querySelector('tbody');
                Array.from(table.querySelectorAll('tr'))
                    .sort(comparer(Array.from(th.parentNode.children).indexOf(th), this.asc = !this.asc))
                    .forEach(tr => table.appendChild(tr));
            })));
        });
    </script>
</body>
</html>
