<?php
// customer/points.php - Customer Loyalty Points
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

try {
    // Points earning history (from completed orders)
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.created_at, o.total_amount, o.points_earned, 
               'order' as transaction_type, o.order_status
        FROM orders o
        WHERE o.customer_id = ? AND o.points_earned > 0 AND o.payment_status = 'completed'
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$currentUser['user_id']]);
    $pointsHistory = $stmt->fetchAll();
    
    // Points usage history (from orders where points were used)
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.created_at, o.total_amount, o.points_used,
               'redemption' as transaction_type, o.order_status
        FROM orders o
        WHERE o.customer_id = ? AND o.points_used > 0 AND o.payment_status = 'completed'
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$currentUser['user_id']]);
    $pointsUsage = $stmt->fetchAll();
    
    // Combine and sort all transactions
    $allTransactions = array_merge($pointsHistory, $pointsUsage);
    usort($allTransactions, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Calculate points statistics
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN points_earned > 0 THEN points_earned ELSE 0 END) as total_earned,
            SUM(CASE WHEN points_used > 0 THEN points_used ELSE 0 END) as total_used,
            COUNT(CASE WHEN points_earned > 0 THEN 1 END) as orders_with_points,
            COUNT(CASE WHEN points_used > 0 THEN 1 END) as redemption_count
        FROM orders 
        WHERE customer_id = ? AND payment_status = 'completed'
    ");
    $stmt->execute([$currentUser['user_id']]);
    $pointsStats = $stmt->fetch();
    
    // Calculate points value (assuming 1 point = $0.01)
    $pointsValue = $userData['loyalty_points'] * 0.01;
    
    // Get next milestone (every 1000 points gets a bonus)
    $currentTier = floor($userData['loyalty_points'] / 1000);
    $nextMilestone = ($currentTier + 1) * 1000;
    $pointsToNext = $nextMilestone - $userData['loyalty_points'];
    
    // Points tiers
    $tiers = [
        ['name' => 'Bronze', 'min' => 0, 'bonus' => 0, 'color' => '#CD7F32'],
        ['name' => 'Silver', 'min' => 1000, 'bonus' => 5, 'color' => '#C0C0C0'],
        ['name' => 'Gold', 'min' => 3000, 'bonus' => 10, 'color' => '#FFD700'],
        ['name' => 'Platinum', 'min' => 5000, 'bonus' => 15, 'color' => '#E5E4E2'],
        ['name' => 'Diamond', 'min' => 10000, 'bonus' => 25, 'color' => '#B9F2FF']
    ];
    
    // Find current tier
    $currentTierInfo = $tiers[0];
    foreach ($tiers as $tier) {
        if ($userData['loyalty_points'] >= $tier['min']) {
            $currentTierInfo = $tier;
        }
    }
    
    // Find next tier
    $nextTierInfo = null;
    foreach ($tiers as $tier) {
        if ($userData['loyalty_points'] < $tier['min']) {
            $nextTierInfo = $tier;
            break;
        }
    }
    
} catch (PDOException $e) {
    $error = "Failed to load points data: " . $e->getMessage();
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
    <title>Loyalty Points - ShopFusion</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/customer.css">
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
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li>
                    <a href="order.php">
                        <i class="fas fa-shopping-bag"></i> My Orders
                        <?php if (isset($pointsStats['orders_with_points'])): ?>
                            <span class="badge"><?php echo $pointsStats['orders_with_points']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="active">
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

        <!-- Points Content -->
        <main class="customer-main">
            <div class="page-header">
                <h1><i class="fas fa-star"></i> Loyalty Points</h1>
                <p>Earn points with every purchase and redeem them for discounts</p>
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

            <!-- Points Overview -->
            <div class="points-overview">
                <div class="points-balance-card">
                    <div class="balance-main">
                        <div class="balance-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="balance-info">
                            <h2><?php echo number_format($userData['loyalty_points']); ?></h2>
                            <p>Available Points</p>
                            <span class="points-value">Worth <?php echo formatPrice($pointsValue); ?></span>
                        </div>
                    </div>
                    
                    <div class="tier-info">
                        <div class="current-tier" style="color: <?php echo $currentTierInfo['color']; ?>">
                            <i class="fas fa-crown"></i>
                            <span><?php echo $currentTierInfo['name']; ?> Member</span>
                            <small><?php echo $currentTierInfo['bonus']; ?>% bonus points</small>
                        </div>
                        
                        <?php if ($nextTierInfo): ?>
                            <div class="next-tier">
                                <p>
                                    <strong><?php echo number_format($pointsToNext); ?> points</strong> 
                                    to reach <strong style="color: <?php echo $nextTierInfo['color']; ?>">
                                    <?php echo $nextTierInfo['name']; ?></strong>
                                </p>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo (($userData['loyalty_points'] - $currentTierInfo['min']) / ($nextTierInfo['min'] - $currentTierInfo['min'])) * 100; ?>%"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="max-tier">
                                <i class="fas fa-trophy"></i>
                                <span>Maximum tier achieved!</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Points Statistics -->
                <div class="points-stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($pointsStats['total_earned'] ?? 0); ?></h3>
                            <p>Points Earned</p>
                            <small><?php echo $pointsStats['orders_with_points'] ?? 0; ?> orders</small>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-minus-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($pointsStats['total_used'] ?? 0); ?></h3>
                            <p>Points Redeemed</p>
                            <small><?php echo $pointsStats['redemption_count'] ?? 0; ?> redemptions</small>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $currentTierInfo['bonus']; ?>%</h3>
                            <p>Bonus Rate</p>
                            <small><?php echo $currentTierInfo['name']; ?> tier</small>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatPrice($pointsValue); ?></h3>
                            <p>Points Value</p>
                            <small>Available to spend</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- How Points Work -->
            <div class="points-info-section">
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-question-circle"></i> How Points Work</h3>
                    </div>
                    <div class="card-content">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Earn Points</h4>
                                    <p>Earn 1 point for every $1 spent on completed orders</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Tier Bonuses</h4>
                                    <p>Higher tiers earn bonus points on every purchase</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Redeem Points</h4>
                                    <p>100 points = $1.00 discount at checkout</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="info-content">
                                    <h4>No Expiry</h4>
                                    <p>Your points never expire as long as your account is active</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Membership Tiers -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> Membership Tiers</h3>
                    </div>
                    <div class="card-content">
                        <div class="tiers-list">
                            <?php foreach ($tiers as $tier): ?>
                                <div class="tier-item <?php echo $tier['name'] === $currentTierInfo['name'] ? 'current' : ''; ?>">
                                    <div class="tier-icon" style="color: <?php echo $tier['color']; ?>">
                                        <i class="fas fa-crown"></i>
                                    </div>
                                    <div class="tier-details">
                                        <h4 style="color: <?php echo $tier['color']; ?>"><?php echo $tier['name']; ?></h4>
                                        <p><?php echo number_format($tier['min']); ?>+ points</p>
                                        <span class="tier-bonus"><?php echo $tier['bonus']; ?>% bonus points</span>
                                    </div>
                                    <?php if ($tier['name'] === $currentTierInfo['name']): ?>
                                        <div class="current-badge">
                                            <i class="fas fa-check"></i> Current
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Points History -->
            <div class="points-history-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Points History</h2>
                </div>

                <?php if (empty($allTransactions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        <h3>No points transactions yet</h3>
                        <p>Start shopping to earn your first loyalty points!</p>
                        <a href="../index.php" class="btn-primary">
                            <i class="fas fa-shopping-cart"></i> Start Shopping
                        </a>
                    </div>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach (array_slice($allTransactions, 0, 20) as $transaction): ?>
                            <div class="history-item <?php echo $transaction['transaction_type']; ?>">
                                <div class="history-icon">
                                    <?php if ($transaction['transaction_type'] === 'order'): ?>
                                        <i class="fas fa-plus-circle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-minus-circle"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="history-details">
                                    <div class="history-main">
                                        <h4>
                                            <?php if ($transaction['transaction_type'] === 'order'): ?>
                                                Points Earned - Order #<?php echo $transaction['order_id']; ?>
                                            <?php else: ?>
                                                Points Redeemed - Order #<?php echo $transaction['order_id']; ?>
                                            <?php endif; ?>
                                        </h4>
                                        <p>Order total: <?php echo formatPrice($transaction['total_amount']); ?></p>
                                    </div>
                                    
                                    <div class="history-meta">
                                        <span class="history-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo formatDateTime($transaction['created_at']); ?>
                                        </span>
                                        <span class="order-status status-<?php echo $transaction['order_status']; ?>">
                                            <?php echo ucfirst($transaction['order_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="history-points">
                                    <?php if ($transaction['transaction_type'] === 'order'): ?>
                                        <span class="points-earned">+<?php echo $transaction['points_earned']; ?></span>
                                    <?php else: ?>
                                        <span class="points-used">-<?php echo $transaction['points_used']; ?></span>
                                    <?php endif; ?>
                                    <small>points</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($allTransactions) > 20): ?>
                            <div class="show-more">
                                <button class="btn-secondary" onclick="showMoreHistory()">
                                    <i class="fas fa-plus"></i> Show More History
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions-section">
                <div class="section-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                
                <div class="actions-grid">
                    <a href="../index.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="action-content">
                            <h3>Earn More Points</h3>
                            <p>Shop now and earn points on every purchase</p>
                        </div>
                        <div class="action-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </a>
                    
                    <a href="order.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="action-content">
                            <h3>View Orders</h3>
                            <p>Check your order history and points earned</p>
                        </div>
                        <div class="action-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </a>
                    
                    <a href="../shop/checkout.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div class="action-content">
                            <h3>Redeem Points</h3>
                            <p>Use your points for discounts at checkout</p>
                        </div>
                        <div class="action-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </a>
                    
                    <div class="action-card info-card">
                        <div class="action-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="action-content">
                            <h3>Points Calculator</h3>
                            <p>Your <?php echo number_format($userData['loyalty_points']); ?> points = <?php echo formatPrice($pointsValue); ?> discount</p>
                        </div>
                        <div class="action-value">
                            <?php echo formatPrice($pointsValue); ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden history items for "Show More" functionality -->
    <div id="hiddenHistory" style="display: none;">
        <?php if (count($allTransactions) > 20): ?>
            <?php foreach (array_slice($allTransactions, 20) as $transaction): ?>
                <div class="history-item <?php echo $transaction['transaction_type']; ?>" style="display: none;">
                    <div class="history-icon">
                        <?php if ($transaction['transaction_type'] === 'order'): ?>
                            <i class="fas fa-plus-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-minus-circle"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="history-details">
                        <div class="history-main">
                            <h4>
                                <?php if ($transaction['transaction_type'] === 'order'): ?>
                                    Points Earned - Order #<?php echo $transaction['order_id']; ?>
                                <?php else: ?>
                                    Points Redeemed - Order #<?php echo $transaction['order_id']; ?>
                                <?php endif; ?>
                            </h4>
                            <p>Order total: <?php echo formatPrice($transaction['total_amount']); ?></p>
                        </div>
                        
                        <div class="history-meta">
                            <span class="history-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo formatDateTime($transaction['created_at']); ?>
                            </span>
                            <span class="order-status status-<?php echo $transaction['order_status']; ?>">
                                <?php echo ucfirst($transaction['order_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="history-points">
                        <?php if ($transaction['transaction_type'] === 'order'): ?>
                            <span class="points-earned">+<?php echo $transaction['points_earned']; ?></span>
                        <?php else: ?>
                            <span class="points-used">-<?php echo $transaction['points_used']; ?></span>
                        <?php endif; ?>
                        <small>points</small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../js/main.js"></script>
    <script>
        // Show more history functionality
        function showMoreHistory() {
            const hiddenItems = document.querySelectorAll('#hiddenHistory .history-item');
            const historyList = document.querySelector('.history-list');
            const showMoreBtn = document.querySelector('.show-more');
            
            hiddenItems.forEach(item => {
                const clonedItem = item.cloneNode(true);
                clonedItem.style.display = 'flex';
                clonedItem.style.opacity = '0';
                historyList.appendChild(clonedItem);
                
                // Animate in
                setTimeout(() => {
                    clonedItem.style.opacity = '1';
                }, 100);
            });
            
            // Hide the show more button
            showMoreBtn.style.display = 'none';
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Animate stats on page load
        document.addEventListener('DOMContentLoaded', function() {
            const statNumbers = document.querySelectorAll('.stat-content h3, .balance-info h2');
            
            statNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/,/g, ''));
                if (!isNaN(finalValue) && finalValue > 0) {
                    animateCounter(stat, finalValue);
                }
            });
        });

        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 50; // 50 steps
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current).toLocaleString();
            }, 30);
        }

        // Progress bar animation
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.progress-fill');
            if (progressBar) {
                const targetWidth = progressBar.style.width;
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.transition = 'width 1s ease-in-out';
                    progressBar.style.width = targetWidth;
                }, 500);
            }
        });

        // Tier highlight animation
        document.addEventListener('DOMContentLoaded', function() {
            const currentTier = document.querySelector('.tier-item.current');
            if (currentTier) {
                setTimeout(() => {
                    currentTier.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        currentTier.style.transform = 'scale(1)';
                    }, 200);
                }, 1000);
            }
        });
    </script>
</body>
</html>