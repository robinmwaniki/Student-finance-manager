<?php
session_start();

// Initialize variables safely
$success_msg = $_SESSION['success_message'] ?? null;
$error_msg = $_SESSION['error_message'] ?? null;

// Clear session messages so they appear only once
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Finance Manager</title>
    <link rel="stylesheet" href="assets/css/landing.css">
    <?php if ($success_msg): ?>
    <div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

    
</head>
<body>

<div class="landing">
    <div class="overlay"></div>

    <div class="content">
        <h1>🎓 Student Finance Manager</h1>
        <p>Track your income, expenses, budgets, and savings — all in one place.</p>

        <div class="buttons">
            <a href="auth/login.php" class="btn btn-primary">Login</a>
            <a href="auth/signup.php" class="btn btn-secondary">Sign Up</a>
        </div>
    </div>
</div>

</body>
</html>
