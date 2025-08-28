<?php
// includes/functions.php
// Helper functions for the ecommerce demo

// require_once 'config/database.php';
require_once __DIR__ . '/../config/database.php';

// Sanitize input data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Format price
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

// Format date
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

// Format datetime
function formatDateTime($datetime) {
    return date('M j, Y g:i A', strtotime($datetime));
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

// Get user by ID
function getUserById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Get product by ID
function getProductById($productId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, s.shop_name, s.trader_id, u.full_name as trader_name, c.category_name 
        FROM products p 
        JOIN shops s ON p.shop_id = s.shop_id 
        JOIN users u ON s.trader_id = u.user_id 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.product_id = ? AND p.status = 'active'
    ");
    $stmt->execute([$productId]);
    return $stmt->fetch();
}

// Get all active products
function getActiveProducts($limit = null, $offset = 0) {
    global $pdo;
    $sql = "
        SELECT p.*, s.shop_name, s.trader_id, u.full_name as trader_name, c.category_name 
        FROM products p 
        JOIN shops s ON p.shop_id = s.shop_id 
        JOIN users u ON s.trader_id = u.user_id 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.status = 'active' AND s.status = 'active' AND u.status = 'active'
        ORDER BY p.created_at DESC
    ";
    
    if ($limit) {
        $sql .= " LIMIT $limit OFFSET $offset";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Search products
function searchProducts($query, $minPrice = null, $maxPrice = null, $minRating = null, $category = null) {
    global $pdo;
    
    $sql = "
        SELECT p.*, s.shop_name, s.trader_id, u.full_name as trader_name, c.category_name 
        FROM products p 
        JOIN shops s ON p.shop_id = s.shop_id 
        JOIN users u ON s.trader_id = u.user_id 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.status = 'active' AND s.status = 'active' AND u.status = 'active'
    ";
    
    $params = [];
    
    if ($query) {
        $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$query%";
        $params[] = "%$query%";
    }
    
    if ($minPrice) {
        $sql .= " AND p.price >= ?";
        $params[] = $minPrice;
    }
    
    if ($maxPrice) {
        $sql .= " AND p.price <= ?";
        $params[] = $maxPrice;
    }
    
    if ($minRating) {
        $sql .= " AND p.rating >= ?";
        $params[] = $minRating;
    }
    
    if ($category) {
        $sql .= " AND c.category_id = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY p.rating DESC, p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get categories
function getCategories() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY category_name");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get trader's products
function getTraderProducts($traderId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, s.shop_name, c.category_name 
        FROM products p 
        JOIN shops s ON p.shop_id = s.shop_id 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE s.trader_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$traderId]);
    return $stmt->fetchAll();
}

// Get trader's shop
function getTraderShop($traderId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM shops WHERE trader_id = ?");
    $stmt->execute([$traderId]);
    return $stmt->fetch();
}

// Get cart items for user
function getCartItems($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ci.*, p.product_name, p.price, p.image_path, p.stock_quantity, s.shop_name, s.shop_id,
               (ci.quantity * p.price) as subtotal
        FROM cart_items ci 
        JOIN products p ON ci.product_id = p.product_id 
        JOIN shops s ON p.shop_id = s.shop_id 
        WHERE ci.customer_id = ? AND p.status = 'active'
        ORDER BY ci.added_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// Get cart total
function getCartTotal($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT SUM(ci.quantity * p.price) as total 
        FROM cart_items ci 
        JOIN products p ON ci.product_id = p.product_id 
        WHERE ci.customer_id = ? AND p.status = 'active'
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// Get cart count
function getCartCount($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) as count 
        FROM cart_items 
        WHERE customer_id = ?
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

// Add to cart
function addToCart($userId, $productId, $quantity = 1) {
    global $pdo;
    
    // Check if item already in cart
    $stmt = $pdo->prepare("SELECT quantity FROM cart_items WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update quantity
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE customer_id = ? AND product_id = ?");
        return $stmt->execute([$quantity, $userId, $productId]);
    } else {
        // Insert new item
        $stmt = $pdo->prepare("INSERT INTO cart_items (customer_id, product_id, quantity) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $productId, $quantity]);
    }
}

// Update cart quantity
function updateCartQuantity($userId, $productId, $quantity) {
    global $pdo;
    if ($quantity <= 0) {
        return removeFromCart($userId, $productId);
    }
    
    $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE customer_id = ? AND product_id = ?");
    return $stmt->execute([$quantity, $userId, $productId]);
}

// Remove from cart
function removeFromCart($userId, $productId) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ? AND product_id = ?");
    return $stmt->execute([$userId, $productId]);
}

// Clear cart
function clearCart($userId) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ?");
    return $stmt->execute([$userId]);
}

// Get pending traders for admin approval
function getPendingTraders() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'trader' AND status = 'pending' ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Approve trader
function approveTrader($traderId) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND role = 'trader'");
    return $stmt->execute([$traderId]);
}

// Get violations
function getViolations($status = null) {
    global $pdo;
    $sql = "
        SELECT v.*, u1.username as reported_user, u2.username as reporter 
        FROM violations v 
        JOIN users u1 ON v.reported_user_id = u1.user_id 
        JOIN users u2 ON v.reporter_id = u2.user_id
    ";
    
    if ($status) {
        $sql .= " WHERE v.status = ?";
        $stmt = $pdo->prepare($sql . " ORDER BY v.created_at DESC");
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->prepare($sql . " ORDER BY v.created_at DESC");
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

// Add violation
function addViolation($reportedUserId, $reporterId, $violationType, $description) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Insert violation
        $stmt = $pdo->prepare("
            INSERT INTO violations (reported_user_id, reporter_id, violation_type, description) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$reportedUserId, $reporterId, $violationType, $description]);
        
        // Update user violation count
        $stmt = $pdo->prepare("UPDATE users SET violation_count = violation_count + 1 WHERE user_id = ?");
        $stmt->execute([$reportedUserId]);
        
        // Check if user should be disabled (3 violations = disable)
        $stmt = $pdo->prepare("SELECT violation_count FROM users WHERE user_id = ?");
        $stmt->execute([$reportedUserId]);
        $user = $stmt->fetch();
        
        if ($user['violation_count'] >= 3) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'disabled' WHERE user_id = ?");
            $stmt->execute([$reportedUserId]);
            
            // Update violation with action taken
            $stmt = $pdo->prepare("
                UPDATE violations 
                SET action_taken = 'account_disabled', status = 'resolved', resolved_at = NOW() 
                WHERE reported_user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$reportedUserId]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// Validate promo code
function validatePromoCode($code, $orderAmount) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM promo_codes 
        WHERE code = ? AND status = 'active' 
        AND (start_date IS NULL OR start_date <= CURDATE()) 
        AND (end_date IS NULL OR end_date >= CURDATE())
        AND (max_uses IS NULL OR current_uses < max_uses)
        AND min_order_amount <= ?
    ");
    $stmt->execute([$code, $orderAmount]);
    return $stmt->fetch();
}

// Apply promo code
function applyPromoCode($promoId) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE promo_codes SET current_uses = current_uses + 1 WHERE promo_id = ?");
    return $stmt->execute([$promoId]);
}

// Calculate discount
function calculateDiscount($promoCode, $amount) {
    if ($promoCode['discount_type'] === 'percentage') {
        return ($amount * $promoCode['discount_value']) / 100;
    } else {
        return min($promoCode['discount_value'], $amount);
    }
}

// Update product rating
function updateProductRating($productId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
        FROM reviews 
        WHERE product_id = ?
    ");
    $stmt->execute([$productId]);
    $result = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        UPDATE products 
        SET rating = ?, total_reviews = ? 
        WHERE product_id = ?
    ");
    $stmt->execute([
        round($result['avg_rating'], 2),
        $result['total_reviews'],
        $productId
    ]);
}

// Get sales report data
function getSalesReport($startDate = null, $endDate = null) {
    global $pdo;
    $sql = "
        SELECT 
            DATE(o.created_at) as date,
            COUNT(o.order_id) as total_orders,
            SUM(o.total_amount) as total_sales,
            AVG(o.total_amount) as avg_order_value
        FROM orders o 
        WHERE o.payment_status = 'completed'
    ";
    
    $params = [];
    if ($startDate) {
        $sql .= " AND DATE(o.created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $sql .= " AND DATE(o.created_at) <= ?";
        $params[] = $endDate;
    }
    
    $sql .= " GROUP BY DATE(o.created_at) ORDER BY date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Upload image
function uploadImage($file, $uploadDir = 'uploads/products/') {
    $targetDir = $uploadDir;
    $fileName = time() . '_' . basename($file["name"]);
    $targetFile = $targetDir . $fileName;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ['success' => false, 'message' => 'File is not an image.'];
    }
    
    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'File is too large. Max 5MB allowed.'];
    }
    
    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed.'];
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ['success' => true, 'filename' => $fileName, 'path' => $targetFile];
    } else {
        return ['success' => false, 'message' => 'Sorry, there was an error uploading your file.'];
    }
}

// Log activity (simple logging)
function logActivity($userId, $action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $action, $details]);
    } catch (Exception $e) {
        // Fail silently for logging
    }
}

// Guest cart functions
function getGuestCartItems() {
    global $pdo;
    
    if (!isset($_SESSION['guest_cart']) || empty($_SESSION['guest_cart'])) {
        return [];
    }
    
    $productIds = array_keys($_SESSION['guest_cart']);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT p.*, s.shop_name, s.shop_id, c.category_name
        FROM products p 
        JOIN shops s ON p.shop_id = s.shop_id 
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.product_id IN ($placeholders) AND p.status = 'active'
    ");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();
    
    $cartItems = [];
    foreach ($products as $product) {
        $quantity = $_SESSION['guest_cart'][$product['product_id']];
        $cartItems[] = [
            'product_id' => $product['product_id'],
            'product_name' => $product['product_name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'subtotal' => $product['price'] * $quantity,
            'image_path' => $product['image_path'],
            'stock_quantity' => $product['stock_quantity'],
            'shop_name' => $product['shop_name'],
            'shop_id' => $product['shop_id']
        ];
    }
    
    return $cartItems;
}

function getGuestCartTotal() {
    $items = getGuestCartItems();
    $total = 0;
    
    foreach ($items as $item) {
        $total += $item['subtotal'];
    }
    
    return $total;
}

// Helper function to get the correct image path
function getImagePath($imagePath, $baseDir = '') {
    if (empty($imagePath)) {
        return $baseDir . 'uploads/products/placeholder.jpg';
    }
    
    // Try the full path first
    if (file_exists($imagePath)) {
        return $imagePath;
    }
    
    // Try with base directory
    $fullPath = $baseDir . $imagePath;
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    
    // Try with just the filename in uploads/products
    $filename = basename($imagePath);
    $uploadPath = $baseDir . 'uploads/products/' . $filename;
    if (file_exists($uploadPath)) {
        return $uploadPath;
    }
    
    // If the specified image doesn't exist, try to find any available image
    // This helps when the database has incorrect image paths
    $uploadDir = $baseDir . 'uploads/products/';
    if (is_dir($uploadDir)) {
        $files = glob($uploadDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        if (!empty($files)) {
            // Use a deterministic but varied selection based on the imagePath hash
            $hash = crc32($imagePath);
            $selectedFile = $files[$hash % count($files)];
            return $selectedFile;
        }
    }
    
    // Fallback to placeholder
    return $baseDir . 'uploads/products/placeholder.jpg';
}

?>