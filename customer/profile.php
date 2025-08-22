<?php
// customer/profile.php - Customer Profile Management
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require customer access
requireLogin();
if (!isCustomer()) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Get current user data
$currentUser = getCurrentUser();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$currentUser['user_id']]);
$userData = $stmt->fetch();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $fullName = sanitize($_POST['full_name']);
                $email = sanitize($_POST['email']);
                $phone = sanitize($_POST['phone']);
                $address = sanitize($_POST['address']);
                
                if ($fullName && $email) {
                    // Check if email is already taken by another user
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                    $stmt->execute([$email, $currentUser['user_id']]);
                    
                    if ($stmt->fetch()) {
                        $error = "Email is already registered to another account.";
                    } else {
                        try {
                            $stmt = $pdo->prepare("
                                UPDATE users 
                                SET full_name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() 
                                WHERE user_id = ?
                            ");
                            
                            if ($stmt->execute([$fullName, $email, $phone, $address, $currentUser['user_id']])) {
                                // Update session data
                                $_SESSION['full_name'] = $fullName;
                                $_SESSION['email'] = $email;
                                
                                $success = "Profile updated successfully!";
                                
                                // Refresh user data
                                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                                $stmt->execute([$currentUser['user_id']]);
                                $userData = $stmt->fetch();
                            } else {
                                $error = "Failed to update profile.";
                            }
                        } catch (PDOException $e) {
                            $error = "Database error: " . $e->getMessage();
                        }
                    }
                } else {
                    $error = "Full name and email are required.";
                }
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'];
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'];
                
                if ($currentPassword && $newPassword && $confirmPassword) {
                    if ($newPassword !== $confirmPassword) {
                        $error = "New passwords do not match.";
                    } elseif (strlen($newPassword) < 6) {
                        $error = "New password must be at least 6 characters long.";
                    } elseif (!verifyPassword($currentPassword, $userData['password'])) {
                        $error = "Current password is incorrect.";
                    } else {
                        try {
                            $hashedPassword = hashPassword($newPassword);
                            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
                            
                            if ($stmt->execute([$hashedPassword, $currentUser['user_id']])) {
                                $success = "Password changed successfully!";
                            } else {
                                $error = "Failed to change password.";
                            }
                        } catch (PDOException $e) {
                            $error = "Database error: " . $e->getMessage();
                        }
                    }
                } else {
                    $error = "All password fields are required.";
                }
                break;
        }
    }
}

try {
    // Get user statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE customer_id = ? AND payment_status = 'completed'");
    $stmt->execute([$currentUser['user_id']]);
    $totalOrders = $stmt->fetch()['total_orders'];
    
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total_spent FROM orders WHERE customer_id = ? AND payment_status = 'completed'");
    $stmt->execute([$currentUser['user_id']]);
    $totalSpent = $stmt->fetch()['total_spent'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_reviews FROM reviews WHERE customer_id = ?");
    $stmt->execute([$currentUser['user_id']]);
    $totalReviews = $stmt->fetch()['total_reviews'];
    
    // Recent activity
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.created_at, o.total_amount, o.order_status, 
               COUNT(oi.item_id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.customer_id = ?
        GROUP BY o.order_id, o.created_at, o.total_amount, o.order_status
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$currentUser['user_id']]);
    $recentOrders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Failed to load profile data: " . $e->getMessage();
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
    <title>My Profile - ShopFusion</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="customer-body">
    <?php include '../includes/header.php'; ?>

    <!-- Main Content -->
    <div class="customer-container">
        <!-- Customer Sidebar -->
        <nav class="customer-sidebar">
            <div class="sidebar-header">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h3><?php echo htmlspecialchars($userData['full_name']); ?></h3>
                <p class="user-email"><?php echo htmlspecialchars($userData['email']); ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <li class="active">
                    <a href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li>
                    <a href="order.php">
                        <i class="fas fa-shopping-bag"></i> My Orders
                        <?php if ($totalOrders > 0): ?>
                            <span class="badge"><?php echo $totalOrders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="points.php">
                        <i class="fas fa-star"></i> Loyalty Points
                        <span class="badge points-badge"><?php echo $userData['loyalty_points']; ?></span>
                    </a>
                </li>
                <li>
                    <a href="../index.php">
                        <i class="fas fa-shopping-cart"></i> Continue Shopping
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="../auth/logout.php" class="btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Profile Content -->
        <main class="customer-main">
            <div class="page-header">
                <h1><i class="fas fa-user"></i> My Profile</h1>
                <p>Manage your account settings and personal information</p>
            </div>

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

            <!-- Account Overview -->
            <div class="profile-grid">
                <!-- Account Stats -->
                <div class="profile-card stats-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Account Overview</h3>
                    </div>
                    <div class="card-content">
                        <div class="stats-grid-small">
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                                <div class="stat-details">
                                    <h4><?php echo number_format($totalOrders); ?></h4>
                                    <p>Total Orders</p>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="stat-details">
                                    <h4><?php echo formatPrice($totalSpent); ?></h4>
                                    <p>Total Spent</p>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-details">
                                    <h4><?php echo number_format($userData['loyalty_points']); ?></h4>
                                    <p>Loyalty Points</p>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-comment"></i>
                                </div>
                                <div class="stat-details">
                                    <h4><?php echo number_format($totalReviews); ?></h4>
                                    <p>Reviews Given</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="membership-info">
                            <p><strong>Member since:</strong> <?php echo formatDate($userData['created_at']); ?></p>
                            <p><strong>Account Status:</strong> 
                                <span class="status-badge status-<?php echo $userData['status']; ?>">
                                    <?php echo ucfirst($userData['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Profile Information -->
                <div class="profile-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                    </div>
                    <div class="card-content">
                        <form method="POST" class="profile-form">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($userData['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($userData['username']); ?>" readonly>
                                <small class="form-help">Username cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($userData['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="profile-card">
                    <div class="card-header">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                    </div>
                    <div class="card-content">
                        <form method="POST" class="password-form">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" 
                                       minlength="6" required>
                                <small class="form-help">Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       minlength="6" required>
                            </div>
                            
                            <button type="submit" class="btn-warning">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="profile-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Recent Activity</h3>
                        <a href="orders.php" class="view-all-link">View All Orders</a>
                    </div>
                    <div class="card-content">
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-bag"></i>
                                <p>No orders yet</p>
                                <a href="../index.php" class="btn-primary">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($recentOrders as $order): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-shopping-bag"></i>
                                        </div>
                                        <div class="activity-details">
                                            <h4>Order #<?php echo $order['order_id']; ?></h4>
                                            <p>
                                                <?php echo $order['item_count']; ?> item<?php echo $order['item_count'] != 1 ? 's' : ''; ?> • 
                                                <?php echo formatPrice($order['total_amount']); ?>
                                            </p>
                                            <span class="order-status status-<?php echo $order['order_status']; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </div>
                                        <div class="activity-date">
                                            <?php echo formatDate($order['created_at']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../js/main.js"></script>
    <script>
        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Form validation feedback
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>