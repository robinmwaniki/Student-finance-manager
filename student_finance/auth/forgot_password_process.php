<?php
session_start();
include 'config.php';

if (!isset($_POST['email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = trim($_POST['email']);

$stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['auth_success'] = "If that email exists, a reset link has been generated.";
    header("Location: forgot_password.php");
    exit();
}

$user   = $result->fetch_assoc();
$token  = bin2hex(random_bytes(50));
$expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

$update = $conn->prepare("UPDATE users SET reset_token=?, token_expiry=? WHERE email=?");
$update->bind_param("sss", $token, $expiry, $email);
$update->execute();

$resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') .
             '://' . $_SERVER['HTTP_HOST'] .
             dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Link - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
<div class="auth-container">
    <div class="overlay"></div>
    <div class="auth-card">
        <div class="auth-logo">📧</div>
        <h2>Reset Link Ready</h2>
        <p class="auth-sub">
            Hi <strong style="color:#a5b4fc;"><?= htmlspecialchars($user['fullname']) ?></strong>,
            click below to reset your password.
            This link expires in <strong>1 hour</strong>.
        </p>

        <a href="<?= htmlspecialchars($resetLink) ?>" class="reset-link-btn">
            🔐 Reset My Password
        </a>

        <p style="margin-top:20px;"><a href="login.php">← Back to Login</a></p>
    </div>
</div>
</body>
</html>