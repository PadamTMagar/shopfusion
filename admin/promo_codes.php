<?php
// admin/promo_codes.php - Promo Codes Management
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

$error = '';
$success = '';

// Handle promo code actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'create_promo':
                    $code = strtoupper(sanitize($_POST['code']));
                    $discountType = sanitize($_POST['discount_type']);
                    $discountValue = floatval($_POST['discount_value']);
                    $minOrderAmount = floatval($_POST['min_order_amount'] ?? 0);
                    $maxUses = intval($_POST['max_uses'] ?? 0);
                    $validFrom = $_POST['valid_from'];
                    $validUntil = $_POST['valid_until'];
                    $description = sanitize($_POST['description'] ?? '');
                    
                    // Validate inputs
                    if (empty($code) || $discountValue <= 0) {
                        throw new Exception('Invalid promo code data');
                    }
                    
                    if ($discountType === 'percentage' && $discountValue > 100) {
                        throw new Exception('Percentage discount cannot exceed 100%');
                    }
                    
                    // Check if code already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM promo_codes WHERE code = ?");
                    $stmt->execute([$code]);
                    if ($stmt->fetch()[0] > 0) {
                        throw new Exception('Promo code already exists');
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO promo_codes (code, discount_type, discount_value, min_order_amount, max_uses, valid_from, valid_until, description) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$code, $discountType, $discountValue, $minOrderAmount, $maxUses, $validFrom, $validUntil, $description]);
                    
                    $success = "Promo code created successfully!";
                    logActivity(getUserId(), 'promo_code_created', "Created promo code: $code");
                    break;
                    
                case 'update_promo':
                    $promoId = intval($_POST['promo_id']);
                    $discountType = sanitize($_POST['discount_type']);
                    $discountValue = floatval($_POST['discount_value']);
                    $minOrderAmount = floatval($_POST['min_order_amount'] ?? 0);
                    $maxUses = intval($_POST['max_uses'] ?? 0);
                    $validFrom = $_POST['valid_from'];
                    $validUntil = $_POST['valid_until'];
                    $description = sanitize($_POST['description'] ?? '');
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    
                    if ($discountValue <= 0) {
                        throw new Exception('Invalid discount value');
                    }
                    
                    if ($discountType === 'percentage' && $discountValue > 100) {
                        throw new Exception('Percentage discount cannot exceed 100%');
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE promo_codes 
                        SET discount_type = ?, discount_value = ?, min_order_amount = ?, max_uses = ?, 
                            valid_from = ?, valid_until = ?, description = ?, is_active = ?
                        WHERE promo_id = ?
                    ");
                    $stmt->execute([$discountType, $discountValue, $minOrderAmount, $maxUses, $validFrom, $validUntil, $description, $isActive, $promoId]);
                    
                    $success = "Promo code updated successfully!";
                    logActivity(getUserId(), 'promo_code_updated', "Updated promo code ID: $promoId");
                    break;
                    
                case 'delete_promo':
                    $promoId = intval($_POST['promo_id']);
                    
                    $stmt = $pdo->prepare("DELETE FROM promo_codes WHERE promo_id = ?");
                    $stmt->execute([$promoId]);
                    
                    $success = "Promo code deleted successfully!";
                    logActivity(getUserId(), 'promo_code_deleted', "Deleted promo code ID: $promoId");
                    break;
                    
                case 'toggle_status':
                    $promoId = intval($_POST['promo_id']);
                    
                    $stmt = $pdo->prepare("UPDATE promo_codes SET is_active = !is_active WHERE promo_id = ?");
                    $stmt->execute([$promoId]);
                    
                    $success = "Promo code status updated successfully!";
                    logActivity(getUserId(), 'promo_code_toggled', "Toggled promo code ID: $promoId");
                    break;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
        
        // Set flash message and redirect
        if ($success) {
            setFlashMessage('success', $success);
        }
        if ($error) {
            setFlashMessage('error', $error);
        }
        
        header('Location: promo_codes.php');
        exit();
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query conditions
$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    if ($statusFilter === 'active') {
        $whereConditions[] = "is_active = 1 AND valid_until >= NOW()";
    } elseif ($statusFilter === 'inactive') {
        $whereConditions[] = "is_active = 0";
    } elseif ($statusFilter === 'expired') {
        $whereConditions[] = "valid_until < NOW()";
    }
}

if ($searchQuery) {
    $whereConditions[] = "(code LIKE ? OR description LIKE ?)";
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    // Get promo codes
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(o.order_id) as usage_count,
               SUM(o.discount_amount) as total_discount_given
        FROM promo_codes p
        LEFT JOIN orders o ON p.code = o.promo_code
        $whereClause
        GROUP BY p.promo_id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $promoCodes = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_codes,
            SUM(CASE WHEN is_active = 1 AND valid_until >= NOW() THEN 1 ELSE 0 END) as active_codes,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_codes,
            SUM(CASE WHEN valid_until < NOW() THEN 1 ELSE 0 END) as expired_codes
        FROM promo_codes
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Failed to load promo codes: " . $e->getMessage();
    $promoCodes = [];
    $stats = ['total_codes' => 0, 'active_codes' => 0, 'inactive_codes' => 0, 'expired_codes' => 0];
}

// Check for flash messages
if ($flashSuccess = getFlashMessage('success')) {
    $success = $flashSuccess;
}
if ($flashError = getFlashMessage('error')) {
    $error = $flashError;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promo Codes - Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-layout">
    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <h2>Shopfusion</h2>
                <span>Admin Panel</span>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="traders.php">
                    <i class="fas fa-store"></i> Traders
                </a>
            </li>
            <li>
                <a href="products.php">
                    <i class="fas fa-box"></i> Products
                </a>
            </li>
            <li>
                <a href="users.php">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <li>
                <a href="orders.php">
                    <i class="fas fa-shopping-bag"></i> Orders
                </a>
            </li>
            <li class="active">
                <a href="promo_codes.php">
                    <i class="fas fa-tags"></i> Promo Codes
                </a>
            </li>
            <li>
                <a href="violations.php">
                    <i class="fas fa-exclamation-triangle"></i> Violations
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Back to Store
            </a>
            <a href="../auth/logout.php" class="btn-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="admin-main">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-tags"></i> Promo Codes</h1>
                <p>Create and manage promotional discount codes</p>
            </div>
            <div class="topbar-right">
                <button class="btn-primary" onclick="showCreateModal()">
                    <i class="fas fa-plus"></i> Create Promo Code
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Error/Success Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_codes']); ?></h3>
                        <p>Total Codes</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['active_codes']); ?></h3>
                        <p>Active Codes</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['expired_codes']); ?></h3>
                        <p>Expired Codes</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon inactive">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['inactive_codes']); ?></h3>
                        <p>Inactive Codes</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Status:</label>
                        <select name="status">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Search:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Code or description...">
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    
                    <a href="promo_codes.php" class="btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </form>
            </div>

            <!-- Promo Codes Table -->
            <div class="table-section">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Promo Codes (<?php echo count($promoCodes); ?> found)</h2>
                </div>
                
                <?php if (empty($promoCodes)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h3>No Promo Codes Found</h3>
                        <p>No promo codes match your current filters.</p>
                        <button class="btn-primary" onclick="showCreateModal()">
                            <i class="fas fa-plus"></i> Create First Promo Code
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Discount</th>
                                    <th>Usage</th>
                                    <th>Valid Period</th>
                                    <th>Status</th>
                                    <th>Total Savings</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promoCodes as $promo): ?>
                                    <tr>
                                        <td>
                                            <div class="promo-code">
                                                <strong class="code"><?php echo htmlspecialchars($promo['code']); ?></strong>
                                                <?php if ($promo['description']): ?>
                                                    <small><?php echo htmlspecialchars($promo['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="discount-info">
                                                <span class="discount-value">
                                                    <?php if ($promo['discount_type'] === 'percentage'): ?>
                                                        <?php echo $promo['discount_value']; ?>%
                                                    <?php else: ?>
                                                        <?php echo formatPrice($promo['discount_value']); ?>
                                                    <?php endif; ?>
                                                </span>
                                                <?php if ($promo['min_order_amount'] > 0): ?>
                                                    <small>Min: <?php echo formatPrice($promo['min_order_amount']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="usage-info">
                                                <span class="usage-count"><?php echo $promo['usage_count']; ?> used</span>
                                                <?php if ($promo['max_uses'] > 0): ?>
                                                    <small>Max: <?php echo $promo['max_uses']; ?></small>
                                                    <div class="usage-bar">
                                                        <div class="usage-progress" style="width: <?php echo min(100, ($promo['usage_count'] / $promo['max_uses']) * 100); ?>%"></div>
                                                    </div>
                                                <?php else: ?>
                                                    <small>Unlimited</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="validity-info">
                                                <small>From: <?php echo formatDate($promo['valid_from']); ?></small>
                                                <small>Until: <?php echo formatDate($promo['valid_until']); ?></small>
                                                <?php
                                                $now = time();
                                                $validUntil = strtotime($promo['valid_until']);
                                                $daysLeft = ceil(($validUntil - $now) / (24 * 60 * 60));
                                                ?>
                                                <?php if ($daysLeft > 0): ?>
                                                    <span class="days-left"><?php echo $daysLeft; ?> days left</span>
                                                <?php else: ?>
                                                    <span class="expired">Expired</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $isExpired = strtotime($promo['valid_until']) < time();
                                            $statusClass = $isExpired ? 'expired' : ($promo['is_active'] ? 'active' : 'inactive');
                                            $statusText = $isExpired ? 'Expired' : ($promo['is_active'] ? 'Active' : 'Inactive');
                                            ?>
                                            <span class="status-badge status-<?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="savings"><?php echo formatPrice($promo['total_discount_given'] ?? 0); ?></strong>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action primary" title="Edit Promo Code" 
                                                        onclick="showEditModal(<?php echo htmlspecialchars(json_encode($promo)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if (!$isExpired): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle this promo code status?')">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="promo_id" value="<?php echo $promo['promo_id']; ?>">
                                                        <button type="submit" class="btn-action <?php echo $promo['is_active'] ? 'warning' : 'success'; ?>" 
                                                                title="<?php echo $promo['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                            <i class="fas fa-<?php echo $promo['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this promo code?')">
                                                    <input type="hidden" name="action" value="delete_promo">
                                                    <input type="hidden" name="promo_id" value="<?php echo $promo['promo_id']; ?>">
                                                    <button type="submit" class="btn-action danger" title="Delete Promo Code">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Create Promo Code Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Create Promo Code</h3>
                <span class="close" onclick="closeModal('createModal')">&times;</span>
            </div>
            
            <form method="POST" id="createForm">
                <input type="hidden" name="action" value="create_promo">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="code">Promo Code: *</label>
                            <input type="text" name="code" id="code" required maxlength="20" 
                                   placeholder="e.g., SAVE20" style="text-transform: uppercase;">
                        </div>
                        
                        <div class="form-group">
                            <label for="discount_type">Discount Type: *</label>
                            <select name="discount_type" id="discount_type" required onchange="updateDiscountLabel()">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="discount_value">Discount Value: *</label>
                            <input type="number" name="discount_value" id="discount_value" required min="0.01" step="0.01">
                            <small id="discount_help">Enter percentage (1-100) or fixed amount</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="min_order_amount">Minimum Order Amount:</label>
                            <input type="number" name="min_order_amount" id="min_order_amount" min="0" step="0.01" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="max_uses">Maximum Uses:</label>
                            <input type="number" name="max_uses" id="max_uses" min="0" value="0">
                            <small>0 = Unlimited uses</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="valid_from">Valid From: *</label>
                            <input type="datetime-local" name="valid_from" id="valid_from" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="valid_until">Valid Until: *</label>
                            <input type="datetime-local" name="valid_until" id="valid_until" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Description:</label>
                            <textarea name="description" id="description" rows="3" 
                                      placeholder="Optional description for internal use..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> Create Promo Code
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Promo Code Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Promo Code</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_promo">
                <input type="hidden" name="promo_id" id="edit_promo_id">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_code">Promo Code:</label>
                            <input type="text" id="edit_code" readonly class="readonly-field">
                            <small>Code cannot be changed after creation</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_discount_type">Discount Type: *</label>
                            <select name="discount_type" id="edit_discount_type" required onchange="updateEditDiscountLabel()">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_discount_value">Discount Value: *</label>
                            <input type="number" name="discount_value" id="edit_discount_value" required min="0.01" step="0.01">
                            <small id="edit_discount_help">Enter percentage (1-100) or fixed amount</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_min_order_amount">Minimum Order Amount:</label>
                            <input type="number" name="min_order_amount" id="edit_min_order_amount" min="0" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_max_uses">Maximum Uses:</label>
                            <input type="number" name="max_uses" id="edit_max_uses" min="0">
                            <small>0 = Unlimited uses</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_valid_from">Valid From: *</label>
                            <input type="datetime-local" name="valid_from" id="edit_valid_from" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_valid_until">Valid Until: *</label>
                            <input type="datetime-local" name="valid_until" id="edit_valid_until" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                                Active
                            </label>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="edit_description">Description:</label>
                            <textarea name="description" id="edit_description" rows="3" 
                                      placeholder="Optional description for internal use..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Promo Code
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
    
    <script>
        // Modal functions
        function showCreateModal() {
            // Set default dates
            const now = new Date();
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            const nextMonth = new Date(now);
            nextMonth.setMonth(nextMonth.getMonth() + 1);
            
            document.getElementById('valid_from').value = now.toISOString().slice(0, 16);
            document.getElementById('valid_until').value = nextMonth.toISOString().slice(0, 16);
            
            document.getElementById('createModal').style.display = 'block';
        }
        
        function showEditModal(promo) {
            document.getElementById('edit_promo_id').value = promo.promo_id;
            document.getElementById('edit_code').value = promo.code;
            document.getElementById('edit_discount_type').value = promo.discount_type;
            document.getElementById('edit_discount_value').value = promo.discount_value;
            document.getElementById('edit_min_order_amount').value = promo.min_order_amount;
            document.getElementById('edit_max_uses').value = promo.max_uses;
            
            // Convert dates to datetime-local format
            const validFrom = new Date(promo.valid_from).toISOString().slice(0, 16);
            const validUntil = new Date(promo.valid_until).toISOString().slice(0, 16);
            document.getElementById('edit_valid_from').value = validFrom;
            document.getElementById('edit_valid_until').value = validUntil;
            
            document.getElementById('edit_is_active').checked = promo.is_active == 1;
            document.getElementById('edit_description').value = promo.description || '';
            
            updateEditDiscountLabel();
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'createModal') {
                document.getElementById('createForm').reset();
            } else if (modalId === 'editModal') {
                document.getElementById('editForm').reset();
            }
        }
        
        function updateDiscountLabel() {
            const type = document.getElementById('discount_type').value;
            const help = document.getElementById('discount_help');
            if (type === 'percentage') {
                help.textContent = 'Enter percentage (1-100)';
            } else {
                help.textContent = 'Enter fixed dollar amount';
            }
        }
        
        function updateEditDiscountLabel() {
            const type = document.getElementById('edit_discount_type').value;
            const help = document.getElementById('edit_discount_help');
            if (type === 'percentage') {
                help.textContent = 'Enter percentage (1-100)';
            } else {
                help.textContent = 'Enter fixed dollar amount';
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['createModal', 'editModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }
        
        // Auto uppercase promo code
        document.getElementById('code').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>

    <style>
        /* Promo Codes Styles */
        .stat-icon.active {
            background: #d4edda;
            color: #155724;
        }
        
        .stat-icon.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .stat-icon.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .promo-code {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .promo-code .code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.9rem;
        }
        
        .promo-code small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .discount-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .discount-value {
            font-weight: 600;
            color: #28a745;
        }
        
        .discount-info small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .usage-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .usage-count {
            font-weight: 500;
        }
        
        .usage-bar {
            width: 100%;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .usage-progress {
            height: 100%;
            background: #007bff;
            transition: width 0.3s ease;
        }
        
        .validity-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.8rem;
        }
        
        .validity-info small {
            color: #666;
        }
        
        .days-left {
            color: #28a745;
            font-weight: 500;
        }
        
        .expired {
            color: #dc3545;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-badge.status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.status-expired {
            background: #6c757d;
            color: white;
        }
        
        .savings {
            color: #28a745;
            font-size: 1.1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        
        .btn-action.primary {
            background: #007bff;
            color: white;
        }
        
        .btn-action.primary:hover {
            background: #0056b3;
        }
        
        .btn-action.success {
            background: #28a745;
            color: white;
        }
        
        .btn-action.success:hover {
            background: #218838;
        }
        
        .btn-action.warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-action.warning:hover {
            background: #e0a800;
        }
        
        .btn-action.danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-action.danger:hover {
            background: #c82333;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 2% auto;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-large {
            max-width: 800px;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .readonly-field {
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .form-group small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
