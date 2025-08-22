<?php
// auth/login.php
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                if ($user['status'] === 'disabled') {
                    $error = 'Your account has been disabled. Please contact administrator.';
                } elseif ($user['role'] === 'trader' && $user['status'] === 'pending') {
                    loginUser($user);
                    header('Location: pending.php');
                    exit();
                } else {
                    loginUser($user);
                    
                    if (isCustomer() && isset($_SESSION['merge_cart'])) {
                        mergeGuestCart(getUserId());
                        unset($_SESSION['merge_cart']);
                    }
                    
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
                    }
                    
                    if (isset($_SESSION['redirect_after_login'])) {
                        $redirect = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        header("Location: $redirect");
                    } else {
                        redirectByRole();
                    }
                    exit();
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}

function mergeGuestCart($userId) {
    global $pdo;
    
    if (!isset($_SESSION['guest_cart']) || empty($_SESSION['guest_cart'])) {
        return;
    }
    
    foreach ($_SESSION['guest_cart'] as $productId => $quantity) {
        addToCart($userId, $productId, $quantity);
    }
    
    clearGuestCart();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopfusion - Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
       
        .auth-card {
            border-top: 6px solid #ffa14a;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        .auth-header h1 a {
            color: #0f375e;
            text-decoration: none;
        }
        .btn-primary {
            background-color: #0f375e;
            border: none;
        }
        .btn-primary:hover {
            background-color: #ffa14a;
            color: #0f375e;
        }
        a {
            color: #ffa14a;
        }
        a:hover {
            text-decoration: underline;
        }
        .demo-btn:hover {
            background: #ffa14a;
            color: #fff;
            border-color: #ffa14a;
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <h1><a href="../index.php">Shopfusion</a></h1>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to continue shopping with <strong>Shopfusion</strong></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($message = getFlashMessage('success')): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($message = getFlashMessage('info')): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($email); ?>"
                               placeholder="Enter your email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required
                               placeholder="Enter your password">
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-primary btn-full">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>

            <div class="auth-divider">
                <span>or</span>
            </div>

            <div class="demo-accounts">
                <h4>Accounts (Click to auto-fill)</h4>
                <div class="demo-buttons">
                    <button type="button" class="demo-btn" onclick="fillDemo('admin@shopfusion.com', 'password')">
                        <i class="fas fa-user-shield"></i>
                        Admin 
                    </button>
                    <button type="button" class="demo-btn" onclick="fillDemo('trader1@shopfusion.com', 'password')">
                        <i class="fas fa-store"></i>
                        Trader 
                    </button>
                    <button type="button" class="demo-btn" onclick="fillDemo('customer1@shopfusion.com', 'password')">
                        <i class="fas fa-user"></i>
                        Customer 
                    </button>
                </div>
            </div>

            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Sign up here</a></p>
                <p><a href="../index.php">← Back to Homepage</a></p>
            </div>
        </div>
    </div>

    <script>
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

        function fillDemo(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }

        document.getElementById('email').focus();
    </script>
</body>
</html>
