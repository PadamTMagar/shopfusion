<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

// If user is admin, send them to admin dashboard instead
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: ../admin/index.php");
    exit();
}

// If not logged in at all, send back to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Pending Approval</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/theme-refresh.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="auth-page pending-page">

    <div class="auth-container">
        <div class="auth-card" style="max-width: 520px; text-align: center;">
            <div class="auth-header">
                <div class="logo" style="justify-content: center;">
                    <a href="../index.php" aria-label="ShopFusion homepage">
                        <img src="../images/logo.png" alt="ShopFusion logo" class="logo-image">
                    </a>
                </div>
                <h2><i class="fas fa-hourglass-half"></i> Account Pending</h2>
                <p>Your trader account is waiting for admin approval.</p>
            </div>

            <div class="demo-accounts" style="text-align: left;">
                <h4>What happens next</h4>
                <p>Your account is currently pending verification by the admin. Once approved, you will be able to sign in and manage your trader dashboard.</p>
                <p style="margin-top: 0.85rem;">Please check back later or contact support if approval is taking longer than expected.</p>
            </div>

            <div class="auth-footer pending-actions" style="margin-top: 1.5rem;">
                <a href="logout.php" class="btn-primary">Logout</a>
            </div>
        <h2 style="color:#333; margin-bottom:15px;">⏳ Account Pending</h2>
        <p style="color:#666; font-size:16px; line-height:1.5;">
            Your account is currently <b>pending verification</b> by the admin.<br><br>
            Once approved, you’ll be able to log in as a <b>Trader</b>.
        </p>
        <p style="margin-top:20px; font-size:14px; color:#999;">
            Please check back later or contact support if this takes too long.
        </p>
        <a href="logout.php" style="display:inline-block; margin-top:25px; padding:10px 20px; background:#ff4d4d; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;">Logout</a>
        </div>
    </div>

</body>
</html>
