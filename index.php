<?php
// Load configs
require_once 'config.php';
require_once 'auth.php';

// ============ PAGINATION LOGIC ============
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total count of non-expired medicines
$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE expiry_date >= CURDATE()");
$total_medicines = $stmt->fetchColumn();
$total_pages = ceil($total_medicines / $items_per_page);

// Ensure current page doesn't exceed total pages
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}

// FETCH PUBLIC MEDICINES WITH PAGINATION:
// Get the current page medicines that are NOT expired
$stmt = $pdo->prepare("SELECT * FROM medicines WHERE expiry_date >= CURDATE() ORDER BY name ASC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all medicines for client-side filtering (optional: for small datasets)
$stmt = $pdo->query("SELECT * FROM medicines WHERE expiry_date >= CURDATE() ORDER BY name ASC");
$all_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Tracking Portal</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Specific overrides for index page */
        .hero-section {
            background-color: var(--primary);
            background-image: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 4rem 1rem 8rem 1rem;
            text-align: center;
            color: white;
        }
        
        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.9;
        }
        
        .main-container {
            margin-top: -4rem;
            padding-bottom: 3rem;
        }
        
        .search-bar-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .search-input-wrapper {
            position: relative;
            flex: 1;
            min-width: 250px;
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        
        .filter-select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background-color: var(--surface);
            font-size: 1rem;
            cursor: pointer;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.25rem;
        }
        
        .badge-success {
            background-color: #ECFDF5;
            color: #065F46;
            border: 1px solid #34D399;
        }
        
        .badge-danger {
            background-color: #FEF2F2;
            color: #991B1B;
            border: 1px solid #F87171;
        }
        
        .medicine-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: var(--text-muted);
        }
        
        .empty-icon {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 1rem;
        }

        /* ============ PAGINATION STYLES ============ */
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

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .table thead {
                display: none;
            }
            .table tbody tr {
                display: flex;
                flex-direction: column;
                padding: 1rem;
                border-bottom: 1px solid var(--border-color);
            }
            .table td {
                padding: 0.5rem 0;
                border: none;
            }
            .mobile-status {
                display: block;
                margin-top: 0.5rem;
                font-size: 0.875rem;
            }
            .desktop-status {
                display: none;
            }
        }
        @media (min-width: 641px) {
            .mobile-status {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-brand">
            <i class="fas fa-pills"></i>
            <span>Medicine Tracking Portal</span>
        </div>
        <div class="nav-links">
            <?php if(isLoggedIn()): ?>
                <?php if(hasRole('admin')): ?>
                    <a href="admin/dashboard.php" class="nav-link">Admin Dashboard</a>
                <?php else: ?>
                    <a href="supervisor/dashboard.php" class="nav-link">Supervisor Dashboard</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="admin_login.php" class="btn btn-primary">Admin Login</a>
                <a href="supervisor_login.php" class="btn btn-outline">Supervisor Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <h1 class="hero-title">Check Medicine Availability</h1>
        <p class="hero-subtitle">
            Real-time inventory of medicines available at our hospital. Search and filter to find what you need quickly.
        </p>
    </div>

    <!-- Main Content -->
    <main class="container main-container">
        <div class="card">
            <!-- Search & Filter Bar -->
            <div class="search-bar-container">
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search medicines by name...">
                </div>
                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <div>
                        <label for="categoryFilter" class="font-medium" style="margin-right:0.5rem">Category:</label>
                        <select id="categoryFilter" class="filter-select">
                            <option value="all">All Categories</option>
                            <option value="General">General</option>
                            <option value="Antibiotics">Antibiotics</option>
                            <option value="Pain Relief">Pain Relief</option>
                            <option value="Vitamins">Vitamins</option>
                            <option value="Vaccines">Vaccines</option>
                        </select>
                    </div>
                    <div>
                        <label for="stockFilter" class="font-medium" style="margin-right:0.5rem">Show:</label>
                        <select id="stockFilter" class="filter-select">
                            <option value="all">All Medicines</option>
                            <option value="in_stock">In Stock Only</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Page Info -->
            <div class="page-info">
                Showing <?php echo ($medicines ? $offset + 1 : 0); ?> to <?php echo min($offset + $items_per_page, $total_medicines); ?> of <?php echo $total_medicines; ?> medicines
            </div>

            <!-- Table section -->
            <div class="table-responsive">
                <table class="table" id="medicineTable">
                    <thead>
                        <tr>
                            <th>Medicine Name</th>
                            <th class="desktop-status">Stock Status</th>
                        </tr>
                    </thead>
                    <tbody id="medicineList">
                        <?php if(empty($medicines)): ?>
                        <tr>
                            <td colspan="2" class="empty-state">
                                <i class="fas fa-box-open empty-icon"></i>
                                <p>No medicines currently registered in the database.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($medicines as $med): ?>
                                <?php 
                                    $inStock = $med['stock'] > 0;
                                    $badgeClass = $inStock ? 'badge-success' : 'badge-danger';
                                    $iconClass = $inStock ? 'fa-check-circle' : 'fa-times-circle';
                                    $statusText = $inStock ? "In Stock ({$med['stock']} units)" : "Out of Stock";
                                    $stockDataValue = $inStock ? "in_stock" : "out_of_stock";
                                ?>
                            <tr class="medicine-item" data-stock="<?php echo $stockDataValue; ?>">
                                <td>
                                    <div class="flex items-center">
                                        <div class="medicine-icon-box">
                                            <i class="fas fa-capsules"></i>
                                        </div>
                                        <div>
                                        <div class="font-bold medicine-name text-2xl" style="font-size:1rem;color:var(--text-main);"><?php echo htmlspecialchars($med['name']); ?></div>
                                        <div style="margin-top: 0.25rem;">
                                            <span class="medicine-category" style="font-size: 0.75rem; background: var(--surface-hover); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid var(--border-color);">
                                                <?php echo htmlspecialchars($med['category']); ?>
                                            </span>
                                            <!-- Show Subsidy Type -->
                                            <?php if($med['subsidy_type']): ?>
                                                <span style="font-size: 0.75rem; background: <?php echo $med['subsidy_type'] === 'Free' ? '#ECFDF5' : '#FEF3C7'; ?>; color: <?php echo $med['subsidy_type'] === 'Free' ? '#065F46' : '#92400E'; ?>; padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid <?php echo $med['subsidy_type'] === 'Free' ? '#34D399' : '#FBBF24'; ?> margin-left: 0.5rem;">
                                                    <?php echo htmlspecialchars($med['subsidy_type']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                            <!-- Mobile Status -->
                                            <div class="mobile-status">
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <i class="fas <?php echo $iconClass; ?>"></i>
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="desktop-status" style="vertical-align: middle;">
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <i class="fas <?php echo $iconClass; ?>"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- No Results State -->
                <div id="noResults" class="empty-state hidden">
                    <i class="fas fa-search empty-icon"></i>
                    <p>No medicines matched your search criteria.</p>
                </div>
            </div>

            <!-- ============ PAGINATION BUTTONS ============ -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <!-- First & Previous -->
                <?php if($current_page > 1): ?>
                    <a href="index.php?page=1" title="First Page">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="index.php?page=<?php echo $current_page - 1; ?>" title="Previous Page">
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
                            <a href="index.php?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                        <?php endif;
                    endfor;
                    
                    if($end_page < $total_pages): ?>
                        <span>...</span>
                    <?php endif; ?>

                <!-- Next & Last -->
                <?php if($current_page < $total_pages): ?>
                    <a href="index.php?page=<?php echo $current_page + 1; ?>" title="Next Page">
                        Next <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="index.php?page=<?php echo $total_pages; ?>" title="Last Page">
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
            const searchInput = document.getElementById('searchInput');
            const stockFilter = document.getElementById('stockFilter');
            const categoryFilter = document.getElementById('categoryFilter');
            const items = document.querySelectorAll('.medicine-item');
            const noResults = document.getElementById('noResults');

            const filterItems = () => {
                const query = searchInput.value.toLowerCase().trim();
                const filterVal = stockFilter.value;
                const catVal = categoryFilter.value;
                let visibleCount = 0;

                items.forEach(item => {
                    const name = item.querySelector('.medicine-name').textContent.toLowerCase();
                    const category = item.querySelector('.medicine-category').textContent;
                    const stock = item.getAttribute('data-stock');
                    
                    const matchesSearch = name.includes(query);
                    const matchesStock = filterVal === 'all' || stock === filterVal;
                    const matchesCategory = catVal === 'all' || category === catVal;

                    if (matchesSearch && matchesStock && matchesCategory) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (visibleCount === 0 && items.length > 0) {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
            };

            searchInput.addEventListener('input', filterItems);
            stockFilter.addEventListener('change', filterItems);
            categoryFilter.addEventListener('change', filterItems);
        });
    </script>
</body>
</html>