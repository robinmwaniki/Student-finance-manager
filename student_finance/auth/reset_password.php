<?php
session_start();
include 'config.php';

if (!isset($_GET['token'])) {
    $_SESSION['auth_error'] = "Invalid password reset link.";
    header("Location: forgot_password.php");
    exit();
}

$token        = $_GET['token'];
$current_time = date('Y-m-d H:i:s');

$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token=? AND token_expiry>=?");
$stmt->bind_param("ss", $token, $current_time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['auth_error'] = "This reset link has expired or is invalid. Please request a new one.";
    header("Location: forgot_password.php");
    exit();
}

$userId  = $result->fetch_assoc()['id'];
$error   = '';
$success = false;

if (isset($_POST['reset'])) {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, token_expiry=NULL WHERE id=?");
        $update->bind_param("si", $hashed, $userId);
        if ($update->execute()) {
            $success = true;
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
<div class="auth-container">
    <div class="overlay"></div>

    <?php if ($success): ?>
        <div class="auth-card" style="text-align:center;">
            <div class="auth-logo">✅</div>
            <h2>Password Updated!</h2>
            <p class="auth-sub">Your password has been changed. You can now log in.</p>
            <a href="login.php" class="reset-link-btn">→ Go to Login</a>
        </div>
    <?php else: ?>
        <form method="POST" action="reset_password.php?token=<?= htmlspecialchars($token) ?>" class="auth-card">
            <div class="auth-logo">🔐</div>
            <h2>Set New Password</h2>
            <p class="auth-sub">Choose a strong password with at least 6 characters.</p>

            <?php if ($error): ?>
                <div class="auth-msg error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="password-wrapper">
                <input type="password" id="newPassword" name="password" placeholder="New Password" required>
                <span class="toggle-password" onclick="togglePw('newPassword')">👁️</span>
            </div>

            <div class="password-wrapper">
                <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm Password" required>
                <span class="toggle-password" onclick="togglePw('confirmPassword')">👁️</span>
            </div>

            <small id="matchMsg" style="display:block;margin-bottom:14px;font-size:13px;"></small>

            <button type="submit" name="reset">Update Password</button>
            <p><a href="login.php">← Back to Login</a></p>
        </form>

        <script>
        function togglePw(id) {
            const i = document.getElementById(id);
            i.type = i.type === 'password' ? 'text' : 'password';
        }
        const np = document.getElementById('newPassword');
        const cp = document.getElementById('confirmPassword');
        const mg = document.getElementById('matchMsg');
        function checkMatch() {
            if (!cp.value) { mg.textContent = ''; return; }
            if (np.value !== cp.value) { mg.textContent = '❌ Passwords do not match'; mg.style.color = '#f87171'; }
            else { mg.textContent = '✅ Passwords match'; mg.style.color = '#4ade80'; }
        }
        np.addEventListener('input', checkMatch);
        cp.addEventListener('input', checkMatch);
        </script>
    <?php endif; ?>

</div>
</body>
</html>