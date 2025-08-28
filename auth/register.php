<?php
// auth/register.php
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}

$error = '';
$success = '';
$formData = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'phone' => '',
    'address' => '',
    'role' => isset($_GET['type']) && $_GET['type'] === 'trader' ? 'trader' : 'customer'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $formData['username'] = sanitize($_POST['username']);
    $formData['email'] = sanitize($_POST['email']);
    $formData['full_name'] = sanitize($_POST['full_name']);
    $formData['phone'] = sanitize($_POST['phone']);
    $formData['address'] = sanitize($_POST['address']);
    $formData['role'] = sanitize($_POST['role']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $shopName = sanitize($_POST['shop_name'] ?? '');
    $shopDescription = sanitize($_POST['shop_description'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($formData['username'])) {
        $errors[] = 'Username is required.';
    } elseif (strlen($formData['username']) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $formData['username'])) {
        $errors[] = 'Username can only contain letters, numbers, and underscores.';
    }
    
    if (empty($formData['email'])) {
        $errors[] = 'Email is required.';
    } elseif (!isValidEmail($formData['email'])) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($formData['full_name'])) {
        $errors[] = 'Full name is required.';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    
    if ($formData['role'] === 'trader') {
        // if (empty($shopName)) {
        //     $errors[] = 'Shop name is required for traders.';
        // }
        // if (empty($formData['phone'])) {
        //     $errors[] = 'Phone number is required for traders.';
        // }
        // if (empty($formData['address'])) {
        //     $errors[] = 'Address is required for traders.';
        // }
    }
    
    // Check for existing username/email
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$formData['username'], $formData['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email already exists.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error. Please try again.';
        }
    }
    
    // If no errors, create account
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert user
            $hashedPassword = hashPassword($password);
            $status = ($formData['role'] === 'trader') ? 'pending' : 'active';
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, full_name, phone, address, role, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $formData['username'],
                $formData['email'],
                $hashedPassword,
                $formData['full_name'],
                $formData['phone'],
                $formData['address'],
                $formData['role'],
                $status
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // If trader, create shop
            if ($formData['role'] === 'trader') {
                $stmt = $pdo->prepare("
                    INSERT INTO shops (trader_id, shop_name, description) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$userId, $shopName, $shopDescription]);
            }
            
            $pdo->commit();
            
            if ($formData['role'] === 'trader') {
                setFlashMessage('success', 'Registration successful! Your trader account is pending approval. You will be notified once approved.');
            } else {
                setFlashMessage('success', 'Registration successful! You can now login to your account.');
            }
            
            header('Location: login.php');
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Shopfusion</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/customer.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card register-card">
            <div class="auth-header">
                <div class="logo">
                    <h1><a href="../index.php">Shopfusion</a></h1>
                </div>
                <h2>Create Your Account</h2>
                <p>Join our community of buyers and sellers!</p>
            </div>

            <!-- Role Selection -->
            <div class="role-selection">
                <button type="button" class="role-btn <?php echo $formData['role'] === 'customer' ? 'active' : ''; ?>" 
                        onclick="selectRole('customer')">
                    <i class="fas fa-user"></i>
                    <span>Customer</span>
                    <small>Shop from multiple stores</small>
                </button>
                <button type="button" class="role-btn <?php echo $formData['role'] === 'trader' ? 'active' : ''; ?>" 
                        onclick="selectRole('trader')">
                    <i class="fas fa-store"></i>
                    <span>Trader</span>
                    <small>Sell your products</small>
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form" id="registerForm">
                <input type="hidden" name="role" id="roleInput" value="<?php echo $formData['role']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-icon">
                            <!-- <i class="fas fa-user"></i> -->
                            <input type="text" id="username" name="username" required
                                   value="<?php echo htmlspecialchars($formData['username']); ?>"
                                   placeholder="Choose a username">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-icon">
                            <!-- <i class="fas fa-envelope"></i> -->
                            <input type="email" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($formData['email']); ?>"
                                   placeholder="Enter your email">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <div class="input-icon">
                        <!-- <i class="fas fa-id-card"></i> -->
                        <input type="text" id="full_name" name="full_name" required
                               value="<?php echo htmlspecialchars($formData['full_name']); ?>"
                               placeholder="Enter your full name">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-icon">
                            <!-- <i class="fas fa-lock"></i> -->
                            <input type="password" id="password" name="password" required
                                   placeholder="Create a password">
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <!-- <i class="fas fa-eye"></i> -->
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-icon">
                            <!-- <i class="fas fa-lock"></i> -->
                            <input type="password" id="confirm_password" name="confirm_password" required
                                   placeholder="Confirm your password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <!-- <i class="fas fa-eye"></i> -->
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Trader-specific fields -->
                <div id="traderFields" class="trader-fields" style="<?php echo $formData['role'] === 'trader' ? 'display: block;' : 'display: none;'; ?>">
                    <div class="form-group">
                        <label for="shop_name">Shop Name</label>
                        <div class="input-icon">
                            <i class="fas fa-store"></i>
                            <input type="text" id="shop_name" name="shop_name"
                                   value="<?php echo htmlspecialchars($shopName ?? ''); ?>"
                                   placeholder="Enter your shop name">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="shop_description">Shop Description</label>
                        <textarea id="shop_description" name="shop_description" 
                                  placeholder="Describe your shop and products"><?php echo htmlspecialchars($shopDescription ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <div class="input-icon">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($formData['phone']); ?>"
                                       placeholder="Enter phone number">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <div class="input-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" id="address" name="address"
                                       value="<?php echo htmlspecialchars($formData['address']); ?>"
                                       placeholder="Enter your address">
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Trader accounts require admin approval before you can start selling.
                    </div>
                </div>

                <!-- Customer optional fields -->
                <div id="customerFields" class="customer-fields" style="<?php echo $formData['role'] === 'customer' ? 'display: block;' : 'display: none;'; ?>">
                    <div class="form-row">
                        <!-- <div class="form-group">
                            <label for="phone_customer">Phone Number (Optional)</label>
                            <div class="input-icon">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone_customer" name="phone"
                                       value="<?php echo htmlspecialchars($formData['phone']); ?>"
                                       placeholder="Enter phone number">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address_customer">Address (Optional)</label>
                            <div class="input-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" id="address_customer" name="address"
                                       value="<?php echo htmlspecialchars($formData['address']); ?>"
                                       placeholder="Enter your address">
                            </div>
                        </div> -->
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-container">
                        <input type="checkbox" required>
                        <span class="checkmark"></span>
                        I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn-primary btn-full">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
                <p><a href="../index.php">← Back to Homepage</a></p>
            </div>
        </div>
    </div>

    <script>
        function selectRole(role) {
            // Update visual state
            document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
            event.target.closest('.role-btn').classList.add('active');
            
            // Update hidden input
            document.getElementById('roleInput').value = role;
            
            // Show/hide relevant fields
            const traderFields = document.getElementById('traderFields');
            const customerFields = document.getElementById('customerFields');
            
            if (role === 'trader') {
                traderFields.style.display = 'block';
                customerFields.style.display = 'none';
                // Make trader fields required
                document.getElementById('shop_name').required = true;
                document.getElementById('phone').required = true;
                document.getElementById('address').required = true;
            } else {
                traderFields.style.display = 'none';
                customerFields.style.display = 'block';
                // Make trader fields optional
                document.getElementById('shop_name').required = false;
                document.getElementById('phone').required = false;
                document.getElementById('address').required = false;
            }
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = field.nextElementSibling;
            const icon = toggle.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            // You can add visual feedback here
        });

        // Auto-focus on first input
        document.getElementById('username').focus();
    </script>
</body>
</html>