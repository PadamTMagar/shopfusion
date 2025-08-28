<?php
// trader/invoice.php - Generate Invoice for Trader's Orders
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require trader access
requireTrader();

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$orderId) {
    setFlashMessage('error', 'Invalid order ID');
    header('Location: orders.php');
    exit();
}

// Get trader's shop
$traderShop = getTraderShop($_SESSION['user_id']);
if (!$traderShop) {
    setFlashMessage('error', 'No shop assigned to your account');
    header('Location: orders.php');
    exit();
}

try {
    // Get order details (only items from this trader's shop)
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.order_id = ? AND oi.shop_id = ? AND o.payment_status = 'completed'
        GROUP BY o.order_id
    ");
    $stmt->execute([$orderId, $traderShop['shop_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        setFlashMessage('error', 'Order not found or access denied');
        header('Location: orders.php');
        exit();
    }
    
    // Get order items for this trader only
    $stmt = $pdo->prepare("
        SELECT oi.*, p.product_name, p.image_path
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ? AND oi.shop_id = ?
        ORDER BY p.product_name
    ");
    $stmt->execute([$orderId, $traderShop['shop_id']]);
    $orderItems = $stmt->fetchAll();
    
    // Calculate trader's portion of the order
    $traderSubtotal = 0;
    foreach ($orderItems as $item) {
        $traderSubtotal += $item['subtotal'];
    }
    
    // Calculate proportional tax and fees
    $taxRate = 0.08; // 8% tax
    $traderTax = $traderSubtotal * $taxRate;
    $traderTotal = $traderSubtotal + $traderTax;
    
    // Platform commission (5% of trader's sales)
    $platformCommission = $traderSubtotal * 0.05;
    $traderEarnings = $traderSubtotal - $platformCommission;
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Failed to load invoice data');
    header('Location: orders.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $orderId; ?> - <?php echo htmlspecialchars($traderShop['shop_name']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="invoice-layout">
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="invoice-logo">
                <h1>SHOPFUSION</h1>
                <p>E-commerce Platform</p>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <div class="invoice-details">
                    <p><strong>Invoice #:</strong> INV-<?php echo $orderId; ?>-<?php echo $traderShop['shop_id']; ?></p>
                    <p><strong>Order #:</strong> <?php echo $orderId; ?></p>
                    <p><strong>Date:</strong> <?php echo formatDate($order['created_at']); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Parties Information -->
        <div class="invoice-parties">
            <div class="party-info">
                <h3>From (Seller):</h3>
                <div class="party-details">
                    <strong><?php echo htmlspecialchars($traderShop['shop_name']); ?></strong><br>
                    <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span><br>
                    <span><?php echo htmlspecialchars($_SESSION['email']); ?></span><br>
                    <small>Trader ID: <?php echo $_SESSION['user_id']; ?></small>
                </div>
            </div>
            
            <div class="party-info">
                <h3>To (Customer):</h3>
                <div class="party-details">
                    <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                    <span><?php echo htmlspecialchars($order['customer_email']); ?></span><br>
                    <?php if ($order['customer_phone']): ?>
                        <span><?php echo htmlspecialchars($order['customer_phone']); ?></span><br>
                    <?php endif; ?>
                    <small>Customer ID: <?php echo $order['customer_id']; ?></small>
                </div>
            </div>
            
            <div class="party-info">
                <h3>Ship To:</h3>
                <div class="party-details">
                    <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                    <address><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></address>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="invoice-items">
            <h3>Items Sold</h3>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $index => $item): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div class="product-info">
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                    <small>Product ID: <?php echo $item['product_id']; ?></small>
                                </div>
                            </td>
                            <td><?php echo number_format($item['quantity']); ?></td>
                            <td><?php echo formatPrice($item['unit_price']); ?></td>
                            <td><?php echo formatPrice($item['subtotal']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="invoice-totals">
            <div class="totals-section">
                <div class="totals-row">
                    <span>Subtotal:</span>
                    <span><?php echo formatPrice($traderSubtotal); ?></span>
                </div>
                <div class="totals-row">
                    <span>Tax (8%):</span>
                    <span><?php echo formatPrice($traderTax); ?></span>
                </div>
                <div class="totals-row total">
                    <span><strong>Total Amount:</strong></span>
                    <span><strong><?php echo formatPrice($traderTotal); ?></strong></span>
                </div>
            </div>
        </div>

        <!-- Payment & Commission Info -->
        <div class="invoice-payment">
            <div class="payment-section">
                <h3>Payment Information</h3>
                <div class="payment-details">
                    <div class="payment-row">
                        <span>Payment Method:</span>
                        <span>
                            <?php if ($order['payment_method'] === 'paypal'): ?>
                                <i class="fab fa-paypal"></i> PayPal
                            <?php else: ?>
                                <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="payment-row">
                        <span>Payment Status:</span>
                        <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    </div>
                    <?php if ($order['paypal_transaction_id']): ?>
                        <div class="payment-row">
                            <span>Transaction ID:</span>
                            <span class="transaction-id"><?php echo htmlspecialchars($order['paypal_transaction_id']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="commission-section">
                <h3>Commission Breakdown</h3>
                <div class="commission-details">
                    <div class="commission-row">
                        <span>Gross Sales:</span>
                        <span><?php echo formatPrice($traderSubtotal); ?></span>
                    </div>
                    <div class="commission-row">
                        <span>Platform Commission (5%):</span>
                        <span class="commission">-<?php echo formatPrice($platformCommission); ?></span>
                    </div>
                    <div class="commission-row earnings">
                        <span><strong>Your Earnings:</strong></span>
                        <span><strong><?php echo formatPrice($traderEarnings); ?></strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            <div class="footer-info">
                <div class="footer-section">
                    <h4>Terms & Conditions</h4>
                    <ul>
                        <li>Payment is processed automatically upon order completion</li>
                        <li>Platform commission is deducted from gross sales</li>
                        <li>Earnings will be transferred to your account within 3-5 business days</li>
                        <li>For any disputes, please contact admin support</li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Information</h4>
                    <p>
                        <strong>ShopFusion Support</strong><br>
                        Email: support@shopfusion.com<br>
                        Phone: 1-800-SHOPFUSION<br>
                        Website: www.shopfusion.com
                    </p>
                </div>
            </div>
            
            <div class="invoice-actions">
                <button onclick="window.print()" class="btn-primary">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
                <button onclick="downloadPDF()" class="btn-secondary">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <a href="orders.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            </div>
        </div>
    </div>

    <script>
        // Print functionality
        function downloadPDF() {
            // In a real implementation, you would use a library like jsPDF or send to server
            alert('PDF download functionality would be implemented here');
        }
        
        // Print styles
        window.addEventListener('beforeprint', function() {
            document.body.classList.add('printing');
        });
        
        window.addEventListener('afterprint', function() {
            document.body.classList.remove('printing');
        });
    </script>

    <style>
        /* Invoice Styles */
        .invoice-layout {
            background: #f5f5f5;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .invoice-logo h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .invoice-logo p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .invoice-info {
            text-align: right;
        }
        
        .invoice-info h2 {
            margin: 0 0 1rem 0;
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .invoice-details p {
            margin: 0.25rem 0;
            opacity: 0.9;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-badge.status-completed {
            background: rgba(255,255,255,0.2);
            color: #d4edda;
        }
        
        .invoice-parties {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
            border-bottom: 1px solid #eee;
        }
        
        .party-info h3 {
            color: #333;
            margin: 0 0 1rem 0;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .party-details {
            color: #666;
            line-height: 1.6;
        }
        
        .party-details strong {
            color: #333;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .party-details small {
            color: #999;
            font-size: 0.8rem;
        }
        
        .invoice-items {
            padding: 2rem;
            border-bottom: 1px solid #eee;
        }
        
        .invoice-items h3 {
            color: #333;
            margin: 0 0 1.5rem 0;
            font-size: 1.2rem;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .invoice-table th,
        .invoice-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .invoice-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }
        
        .invoice-table td {
            color: #666;
        }
        
        .product-info strong {
            color: #333;
            display: block;
        }
        
        .product-info small {
            color: #999;
            font-size: 0.8rem;
        }
        
        .invoice-totals {
            padding: 2rem;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        
        .totals-section {
            max-width: 300px;
            margin-left: auto;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #ddd;
        }
        
        .totals-row.total {
            border-top: 2px solid #007bff;
            border-bottom: none;
            padding-top: 1rem;
            margin-top: 1rem;
            font-size: 1.1rem;
        }
        
        .invoice-payment {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
            border-bottom: 1px solid #eee;
        }
        
        .payment-section h3,
        .commission-section h3 {
            color: #333;
            margin: 0 0 1rem 0;
            font-size: 1rem;
        }
        
        .payment-row,
        .commission-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .commission-row.earnings {
            border-top: 2px solid #28a745;
            border-bottom: none;
            padding-top: 1rem;
            margin-top: 1rem;
            color: #28a745;
        }
        
        .commission {
            color: #dc3545;
        }
        
        .transaction-id {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
        }
        
        .invoice-footer {
            padding: 2rem;
        }
        
        .footer-info {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-section h4 {
            color: #333;
            margin: 0 0 1rem 0;
            font-size: 1rem;
        }
        
        .footer-section ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-section li {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            padding-left: 1rem;
            position: relative;
        }
        
        .footer-section li::before {
            content: '•';
            color: #007bff;
            position: absolute;
            left: 0;
        }
        
        .invoice-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-primary,
        .btn-secondary,
        .btn-outline {
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-outline {
            background: white;
            color: #007bff;
            border: 2px solid #007bff;
        }
        
        .btn-outline:hover {
            background: #007bff;
            color: white;
        }
        
        /* Print Styles */
        @media print {
            .invoice-layout {
                background: white;
                padding: 0;
            }
            
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .invoice-actions {
                display: none;
            }
            
            .invoice-header {
                background: #007bff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        @media (max-width: 768px) {
            .invoice-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .invoice-parties {
                grid-template-columns: 1fr;
            }
            
            .invoice-payment {
                grid-template-columns: 1fr;
            }
            
            .footer-info {
                grid-template-columns: 1fr;
            }
            
            .invoice-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
