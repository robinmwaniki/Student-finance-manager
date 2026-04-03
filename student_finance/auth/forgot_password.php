<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
<div class="auth-container">
    <div class="overlay"></div>

    <form action="forgot_password_process.php" method="POST" class="auth-card">
        <div class="auth-logo">🔑</div>
        <h2>Forgot Password</h2>
        <p class="auth-sub">Enter your email and we'll generate a reset link.</p>

        <?php if (!empty($_SESSION['auth_error'])): ?>
            <div class="auth-msg error"><?= htmlspecialchars($_SESSION['auth_error']) ?></div>
            <?php unset($_SESSION['auth_error']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['auth_success'])): ?>
            <div class="auth-msg success"><?= htmlspecialchars($_SESSION['auth_success']) ?></div>
            <?php unset($_SESSION['auth_success']); ?>
        <?php endif; ?>

        <input type="email" name="email" placeholder="Enter your email address" required autocomplete="email">

        <button type="submit">Send Reset Link</button>

        <p><a href="login.php">← Back to Login</a></p>
    </form>
</div>
</body>
</html>