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
    <title>Account Pending Approval</title>
</head>
<body style="margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f4f9; display:flex; justify-content:center; align-items:center; height:100vh;">

    <div style="background:#fff; padding:40px; border-radius:12px; box-shadow:0 4px 8px rgba(0,0,0,0.1); text-align:center; max-width:450px;">
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

</body>
</html>
