<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
<div class="login-container">
    <div class="overlay"></div>

    <form action="signup_process.php" method="POST" class="login-card">
        <div class="auth-logo">🎓</div>
        <h2>Create Account</h2>
        <p class="auth-subtitle">Start managing your finances today</p>

        <?php if (!empty($_SESSION['auth_error'])): ?>
            <div class="auth-error"><?= htmlspecialchars($_SESSION['auth_error']) ?></div>
            <?php unset($_SESSION['auth_error']); ?>
        <?php endif; ?>

        <div class="input-group">
            <span class="input-icon">👤</span>
            <input type="text" name="fullname" placeholder="Full Name" required autocomplete="name">
        </div>
        <div class="input-group">
            <span class="input-icon">✉️</span>
            <input type="email" name="email" placeholder="Email address" required autocomplete="email">
        </div>
        <div class="input-group">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password" placeholder="Password (min 6 chars)" required>
            <span class="toggle-password" onclick="togglePassword('password')">👁️</span>
        </div>
        <div class="strength-bar-wrap">
            <div class="strength-bar" id="strengthBar"></div>
        </div>
        <small id="strengthMessage" class="strength-msg"></small>

        <div class="input-group">
            <span class="input-icon">🔒</span>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
            <span class="toggle-password" onclick="togglePassword('confirm_password')">👁️</span>
        </div>
        <small id="matchMessage" class="strength-msg"></small>

        <button type="submit" name="signup" class="auth-btn">Create Account</button>
        <p class="auth-switch">Already have an account? <a href="login.php">Login</a></p>
    </form>
</div>
<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}
const password        = document.getElementById('password');
const strengthBar     = document.getElementById('strengthBar');
const strengthMessage = document.getElementById('strengthMessage');
const confirmPassword = document.getElementById('confirm_password');
const matchMessage    = document.getElementById('matchMessage');

password.addEventListener('input', function () {
    const val = password.value;
    let s = 0;
    if (val.length >= 6)            s++;
    if (val.match(/[A-Z]/))         s++;
    if (val.match(/[0-9]/))         s++;
    if (val.match(/[^A-Za-z0-9]/)) s++;
    const labels = ['','Weak','Fair','Good','Strong'];
    const colors = ['','#ef4444','#f59e0b','#3b82f6','#22c55e'];
    strengthBar.style.width      = (s * 25) + '%';
    strengthBar.style.background = colors[s];
    strengthMessage.textContent  = val.length > 0 ? 'Strength: ' + labels[s] : '';
    strengthMessage.style.color  = colors[s];
});
confirmPassword.addEventListener('input', function () {
    if (!confirmPassword.value) { matchMessage.textContent = ''; return; }
    if (password.value !== confirmPassword.value) {
        matchMessage.textContent = '❌ Passwords do not match';
        matchMessage.style.color = '#ef4444';
    } else {
        matchMessage.textContent = '✅ Passwords match';
        matchMessage.style.color = '#22c55e';
    }
});
</script>
</body>
</html>