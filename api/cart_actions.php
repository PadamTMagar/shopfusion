<?php
// api/cart_actions.php - Cart Management API
error_reporting(0); // Suppress PHP errors in API responses
ini_set('display_errors', 0);

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Clean any previous output
if (ob_get_level()) {
    ob_clean();
}
header('Content-Type: application/json');

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    // Start output buffering to catch any unexpected output
    ob_start();
    
    switch ($action) {
        case 'add':
            $response = addToCartAction();
            break;
            
        case 'update':
            $response = updateCartAction();
            break;
            
        case 'remove':
            $response = removeFromCartAction();
            break;
            
        case 'clear':
            $response = clearCartAction();
            break;
            
        case 'get_count':
            $response = getCartCountAction();
            break;
            
        case 'get_cart':
            $response = getCartAction();
            break;
            
        case 'apply_promo':
            $response = applyPromoCodeAction();
            break;
            
        case 'remove_promo':
            $response = removePromoCodeAction();
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
    }
    
    // Clear any unexpected output
    if (ob_get_level()) {
        ob_clean();
    }
    
} catch (Exception $e) {
    // Clear any output and return error
    if (ob_get_level()) {
        ob_clean();
    }
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
} catch (Error $e) {
    // Handle PHP 7+ Error objects
    if (ob_get_level()) {
        ob_clean();
    }
    $response = ['success' => false, 'message' => 'PHP error: ' . $e->getMessage()];
}

// Ensure clean JSON output
if (ob_get_level()) {
    ob_clean();
}
echo json_encode($response);

// Add item to cart
function addToCartAction() {
    global $pdo;
    
    $productId = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if (!$productId || $quantity < 1) {
        return ['success' => false, 'message' => 'Invalid product or quantity'];
    }
    
    // Verify product exists and is active
    $stmt = $pdo->prepare("
        SELECT p.*, s.shop_name 
        FROM products p 
        JOIN shops s ON p.shop_id = s.shop_id 
        JOIN users u ON s.trader_id = u.user_id
        WHERE p.product_id = ? AND p.status = 'active' 
        AND s.status = 'active' AND u.status = 'active'
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        return ['success' => false, 'message' => 'Product not found or unavailable'];
    }
    
    // Check stock
    if ($product['stock_quantity'] < $quantity) {
        return ['success' => false, 'message' => 'Insufficient stock available'];
    }
    
    if (isLoggedIn() && isCustomer()) {
        // Add to database cart for logged-in users
        $userId = getUserId();
        
        // Check if item already in cart
        $stmt = $pdo->prepare("SELECT quantity FROM cart_items WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $newQuantity = $existing['quantity'] + $quantity;
            
            // Check total stock
            if ($product['stock_quantity'] < $newQuantity) {
                return ['success' => false, 'message' => 'Not enough stock for requested quantity'];
            }
            
            $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE customer_id = ? AND product_id = ?");
            $stmt->execute([$newQuantity, $userId, $productId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cart_items (customer_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $productId, $quantity]);
        }
        
        $cartCount = getCartCount($userId);
    } else {
        // Add to session cart for guests
        if (!isset($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = [];
        }
        
        $currentQuantity = $_SESSION['guest_cart'][$productId] ?? 0;
        $newQuantity = $currentQuantity + $quantity;
        
        // Check total stock
        if ($product['stock_quantity'] < $newQuantity) {
            return ['success' => false, 'message' => 'Not enough stock for requested quantity'];
        }
        
        $_SESSION['guest_cart'][$productId] = $newQuantity;
        $cartCount = getGuestCartCount();
    }
    
    return [
        'success' => true,
        'message' => 'Product added to cart',
        'cart_count' => $cartCount,
        'product_name' => $product['product_name']
    ];
}

// Update cart item quantity
function updateCartAction() {
    global $pdo;
    
    $productId = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    
    if (!$productId) {
        return ['success' => false, 'message' => 'Invalid product'];
    }
    
    // If quantity is 0 or less, remove item
    if ($quantity <= 0) {
        return removeFromCartAction();
    }
    
    // Verify product and stock
    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE product_id = ? AND status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        return ['success' => false, 'message' => 'Product not found'];
    }
    
    if ($product['stock_quantity'] < $quantity) {
        return ['success' => false, 'message' => 'Insufficient stock available'];
    }
    
    if (isLoggedIn() && isCustomer()) {
        $userId = getUserId();
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$quantity, $userId, $productId]);
        
        $cartCount = getCartCount($userId);
        $cartTotal = getCartTotal($userId);
        $cartItems = getCartItems($userId);
    } else {
        if (isset($_SESSION['guest_cart'][$productId])) {
            $_SESSION['guest_cart'][$productId] = $quantity;
        }
        
        $cartCount = getGuestCartCount();
        $cartTotal = getGuestCartTotal();
        $cartItems = getGuestCartItems();
    }
    
    return [
        'success' => true,
        'message' => 'Cart updated',
        'cart_count' => $cartCount,
        'cart_total' => $cartTotal,
        'items' => $cartItems
    ];
}

// Remove item from cart
function removeFromCartAction() {
    global $pdo;
    
    $productId = intval($_POST['product_id'] ?? 0);
    
    if (!$productId) {
        return ['success' => false, 'message' => 'Invalid product'];
    }
    
    if (isLoggedIn() && isCustomer()) {
        $userId = getUserId();
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        
        $cartCount = getCartCount($userId);
        $cartTotal = getCartTotal($userId);
        $cartItems = getCartItems($userId);
    } else {
        if (isset($_SESSION['guest_cart'][$productId])) {
            unset($_SESSION['guest_cart'][$productId]);
        }
        
        $cartCount = getGuestCartCount();
        $cartTotal = getGuestCartTotal();
        $cartItems = getGuestCartItems();
    }
    
    return [
        'success' => true,
        'message' => 'Item removed from cart',
        'cart_count' => $cartCount,
        'cart_total' => $cartTotal,
        'items' => $cartItems
    ];
}

// Clear entire cart
function clearCartAction() {
    global $pdo;
    
    if (isLoggedIn() && isCustomer()) {
        $userId = getUserId();
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ?");
        $stmt->execute([$userId]);
    } else {
        $_SESSION['guest_cart'] = [];
    }
    
    return [
        'success' => true,
        'message' => 'Cart cleared',
        'cart_count' => 0,
        'cart_total' => 0,
        'items' => []
    ];
}

// Get cart count
function getCartCountAction() {
    if (isLoggedIn() && isCustomer()) {
        $cartCount = getCartCount(getUserId());
    } else {
        $cartCount = getGuestCartCount();
    }
    
    return [
        'success' => true,
        'cart_count' => $cartCount
    ];
}

// Get full cart data
function getCartAction() {
    global $pdo;
    
    if (isLoggedIn() && isCustomer()) {
        $userId = getUserId();
        $items = getCartItems($userId);
        $total = getCartTotal($userId);
        $count = getCartCount($userId);
    } else {
        $items = getGuestCartItems();
        $total = getGuestCartTotal();
        $count = getGuestCartCount();
    }
    
    return [
        'success' => true,
        'items' => $items,
        'cart_items' => $items, // Keep for backward compatibility
        'cart_total' => $total,
        'cart_count' => $count
    ];
}

// Apply promo code
function applyPromoCodeAction() {
    global $pdo;
    
    $promoCode = sanitize($_POST['promo_code'] ?? '');
    
    if (!$promoCode) {
        return ['success' => false, 'message' => 'Please enter a promo code'];
    }
    
    // Get cart total
    if (isLoggedIn() && isCustomer()) {
        $cartTotal = getCartTotal(getUserId());
    } else {
        $cartTotal = getGuestCartTotal();
    }
    
    if ($cartTotal <= 0) {
        return ['success' => false, 'message' => 'Your cart is empty'];
    }
    
    // Validate promo code
    $promo = validatePromoCode($promoCode, $cartTotal);
    
    if (!$promo) {
        return ['success' => false, 'message' => 'Invalid or expired promo code'];
    }
    
    // Calculate discount
    $discount = calculateDiscount($promo, $cartTotal);
    $newTotal = max(0, $cartTotal - $discount);
    
    // Store in session
    $_SESSION['applied_promo'] = [
        'code' => $promoCode,
        'discount' => $discount,
        'promo_id' => $promo['promo_id']
    ];
    
    return [
        'success' => true,
        'message' => 'Promo code applied successfully',
        'discount_amount' => $discount,
        'new_total' => $newTotal,
        'promo_code' => $promoCode
    ];
}

// Remove promo code
function removePromoCodeAction() {
    unset($_SESSION['applied_promo']);
    
    if (isLoggedIn() && isCustomer()) {
        $cartTotal = getCartTotal(getUserId());
    } else {
        $cartTotal = getGuestCartTotal();
    }
    
    return [
        'success' => true,
        'message' => 'Promo code removed',
        'cart_total' => $cartTotal
    ];
}
?>
