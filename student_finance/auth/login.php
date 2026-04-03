<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
<div class="login-container">
    <div class="overlay"></div>

    <form action="login_process.php" method="POST" class="login-card">
        <div class="auth-logo">🎓</div>
        <h2>Welcome Back</h2>
        <p class="auth-subtitle">Sign in to your Finance Manager</p>

        <?php if (!empty($_SESSION['auth_error'])): ?>
            <div class="auth-error"><?= htmlspecialchars($_SESSION['auth_error']) ?></div>
            <?php unset($_SESSION['auth_error']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['auth_success'])): ?>
            <div class="auth-success"><?= htmlspecialchars($_SESSION['auth_success']) ?></div>
            <?php unset($_SESSION['auth_success']); ?>
        <?php endif; ?>

        <div class="input-group">
            <span class="input-icon">✉️</span>
            <input type="email" name="email" placeholder="Email address" required autocomplete="email">
        </div>

        <div class="input-group">
            <span class="input-icon">🔒</span>
            <input type="password" id="loginPassword" name="password" placeholder="Password" required autocomplete="current-password">
            <span class="toggle-password" id="toggleLogin">👁️</span>
        </div>

        <div class="auth-options">
            <label class="remember-me">
                <input type="checkbox" name="remember"> Remember me
            </label>
            <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
        </div>

        <button type="submit" name="login" class="auth-btn">Login</button>
        <p class="auth-switch">Don't have an account? <a href="signup.php">Sign Up</a></p>
    </form>
</div>
<script>
document.getElementById('toggleLogin').addEventListener('click', function () {
    const input = document.getElementById('loginPassword');
    input.type = input.type === 'password' ? 'text' : 'password';
});
</script>
</body>
</html>