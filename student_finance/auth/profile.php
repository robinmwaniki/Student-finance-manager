<?php
session_start();
include 'config.php';
include 'sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$flashMsg  = '';
$flashType = '';

/* =========================
   FETCH CURRENT USER
========================= */
$stmtUser = $conn->prepare("SELECT fullname, email, avatar FROM users WHERE id=?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

/* =========================
   UPLOAD AVATAR
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $flashMsg  = 'Please select an image to upload.';
        $flashType = 'error';
    } else {
        $file     = $_FILES['avatar'];
        $maxSize  = 2 * 1024 * 1024;
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($file['size'] > $maxSize) {
            $flashMsg  = 'Image must be under 2MB.';
            $flashType = 'error';
        } elseif (!in_array($mimeType, $allowed)) {
            $flashMsg  = 'Only JPG, PNG, GIF and WEBP images are allowed.';
            $flashType = 'error';
        } else {
            $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename  = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $uploadDir = '../assets/avatars/';

            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Delete old avatar
            if (!empty($user['avatar']) && file_exists($uploadDir . $user['avatar'])) {
                unlink($uploadDir . $user['avatar']);
            }

            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
                $stmt->bind_param("si", $filename, $user_id);
                $stmt->execute();
                $_SESSION['flash'] = ['msg' => 'Profile photo updated!', 'type' => 'success'];
                header("Location: profile.php");
                exit();
            } else {
                $flashMsg  = 'Upload failed. Please check the avatars folder exists.';
                $flashType = 'error';
            }
        }
    }
}

/* =========================
   REMOVE AVATAR
========================= */
if (isset($_GET['remove_avatar'])) {
    if (!empty($user['avatar']) && file_exists('../assets/avatars/' . $user['avatar'])) {
        unlink('../assets/avatars/' . $user['avatar']);
    }
    $stmt = $conn->prepare("UPDATE users SET avatar=NULL WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $_SESSION['flash'] = ['msg' => 'Profile photo removed.', 'type' => 'success'];
    header("Location: profile.php");
    exit();
}

/* =========================
   UPDATE NAME
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'update_name') {
    $newName = trim($_POST['fullname']);
    if (empty($newName)) {
        $flashMsg  = 'Name cannot be empty.';
        $flashType = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE users SET fullname=? WHERE id=?");
        $stmt->bind_param("si", $newName, $user_id);
        $stmt->execute();
        $_SESSION['fullname'] = $newName;
        $_SESSION['flash'] = ['msg' => 'Name updated successfully!', 'type' => 'success'];
        header("Location: profile.php");
        exit();
    }
}

/* =========================
   CHANGE PASSWORD
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPass = $_POST['current_password'];
    $newPass     = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];

    $stmtPass = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmtPass->bind_param("i", $user_id);
    $stmtPass->execute();
    $passRow = $stmtPass->get_result()->fetch_assoc();

    if (!password_verify($currentPass, $passRow['password'])) {
        $flashMsg  = 'Current password is incorrect.';
        $flashType = 'error';
    } elseif (strlen($newPass) < 6) {
        $flashMsg  = 'New password must be at least 6 characters.';
        $flashType = 'error';
    } elseif ($newPass !== $confirmPass) {
        $flashMsg  = 'New passwords do not match.';
        $flashType = 'error';
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute();
        $_SESSION['flash'] = ['msg' => 'Password changed successfully!', 'type' => 'success'];
        header("Location: profile.php");
        exit();
    }
}

/* =========================
   DELETE ACCOUNT
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmtCheck = $conn->prepare("SELECT password, email FROM users WHERE id=?");
    $stmtCheck->bind_param("i", $user_id);
    $stmtCheck->execute();
    $checkRow = $stmtCheck->get_result()->fetch_assoc();

    if ($checkRow['email'] !== $email || !password_verify($password, $checkRow['password'])) {
        $flashMsg  = 'Incorrect email or password. Account not deleted.';
        $flashType = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        session_destroy();
        header("Location: login.php");
        exit();
    }
}

/* =========================
   FLASH MESSAGE
========================= */
if (empty($flashMsg) && isset($_SESSION['flash'])) {
    $flashMsg  = $_SESSION['flash']['msg'];
    $flashType = $_SESSION['flash']['type'];
    unset($_SESSION['flash']);
}

/* =========================
   AVATAR & INITIALS
========================= */
// Re-fetch user after possible updates
$stmtUser = $conn->prepare("SELECT fullname, email, avatar FROM users WHERE id=?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$avatarUrl = !empty($user['avatar'])
    ? '../assets/avatars/' . htmlspecialchars($user['avatar'])
    : null;

$nameParts = explode(' ', $user['fullname']);
$initials  = strtoupper(substr($nameParts[0],0,1) . (isset($nameParts[1]) ? substr($nameParts[1],0,1) : ''));

/* =========================
   STATS
========================= */
$currentMonth = date('Y-m');
$stmtInc = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM income WHERE user_id=? AND income_month=?");
$stmtInc->bind_param("is", $user_id, $currentMonth);
$stmtInc->execute();
$thisIncome = (float)$stmtInc->get_result()->fetch_assoc()['t'];

$stmtExp = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE user_id=? AND expense_month=?");
$stmtExp->bind_param("is", $user_id, $currentMonth);
$stmtExp->execute();
$thisExpenses = (float)$stmtExp->get_result()->fetch_assoc()['t'];

$stmtCounts = $conn->prepare(
    "SELECT
        (SELECT COUNT(*) FROM income   WHERE user_id=?) AS inc,
        (SELECT COUNT(*) FROM expenses WHERE user_id=?) AS exp,
        (SELECT COUNT(*) FROM savings  WHERE user_id=?) AS sav"
);
$stmtCounts->bind_param("iii", $user_id, $user_id, $user_id);
$stmtCounts->execute();
$counts = $stmtCounts->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile & Settings - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="main-content">
<div class="page-wrapper">

    <div class="page-header">
        <h2>👤 Profile & Settings</h2>
    </div>

    <?php if ($flashMsg): ?>
        <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <div class="profile-layout">

        <!-- ====== LEFT: AVATAR CARD ====== -->
        <div class="profile-avatar-card">

            <!-- Avatar -->
            <div class="avatar-wrapper">
                <?php if ($avatarUrl): ?>
                    <img src="<?= $avatarUrl ?>" alt="Profile Photo" class="avatar-img">
                <?php else: ?>
                    <div class="avatar-initials"><?= $initials ?></div>
                <?php endif; ?>
                <label for="avatarInput" class="avatar-overlay" title="Change photo">📷</label>
            </div>

            <h3 class="profile-name"><?= htmlspecialchars($user['fullname']) ?></h3>
            <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>

            <!-- Hidden upload form — auto submits on file select -->
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_avatar">
                <input type="file" id="avatarInput" name="avatar"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       style="display:none;"
                       onchange="this.form.submit()">
            </form>

            <?php if ($avatarUrl): ?>
                <a href="?remove_avatar=1"
                   onclick="return confirm('Remove profile photo?')"
                   class="btn-remove-avatar">🗑 Remove Photo</a>
            <?php endif; ?>

            <p class="avatar-hint">JPG, PNG, GIF or WEBP · Max 2MB<br>Click photo to change</p>

            <!-- Stats -->
            <div class="profile-stats">
                <div class="profile-stat">
                    <span class="stat-num"><?= (int)$counts['inc'] ?></span>
                    <span class="stat-lbl">Income</span>
                </div>
                <div class="profile-stat">
                    <span class="stat-num"><?= (int)$counts['exp'] ?></span>
                    <span class="stat-lbl">Expenses</span>
                </div>
                <div class="profile-stat">
                    <span class="stat-num"><?= (int)$counts['sav'] ?></span>
                    <span class="stat-lbl">Savings</span>
                </div>
            </div>

            <!-- This month mini summary -->
            <div class="profile-month-mini">
                <p class="mini-month-label">📅 <?= date('F Y') ?></p>
                <div class="month-stat income-stat">
                    <span>💰 Income</span>
                    <strong>KES <?= number_format($thisIncome, 2) ?></strong>
                </div>
                <div class="month-stat expense-stat">
                    <span>💸 Expenses</span>
                    <strong>KES <?= number_format($thisExpenses, 2) ?></strong>
                </div>
                <?php $bal = $thisIncome - $thisExpenses; ?>
                <div class="month-stat balance-stat">
                    <span>💼 Balance</span>
                    <strong class="<?= $bal >= 0 ? 'text-success' : 'text-danger' ?>">
                        KES <?= number_format($bal, 2) ?>
                    </strong>
                </div>
            </div>

        </div>

        <!-- ====== RIGHT: SETTINGS CARDS ====== -->
        <div class="profile-info-col">

            <!-- UPDATE NAME -->
            <div class="settings-card">
                <h3>👤 Update Name</h3>
                <p class="desc">Change how your name appears across the app.</p>
                <form method="POST" class="finance-form">
                    <input type="hidden" name="action" value="update_name">
                    <div class="form-row">
                        <input type="text" name="fullname" placeholder="Full Name" required
                               value="<?= htmlspecialchars($user['fullname']) ?>">
                        <button type="submit">💾 Save Name</button>
                    </div>
                </form>
            </div>

            <!-- CHANGE PASSWORD -->
            <div class="settings-card" style="margin-top:20px;">
                <h3>🔒 Change Password</h3>
                <p class="desc">Use a strong password with at least 6 characters.</p>
                <form method="POST" class="finance-form">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-col">
                        <input type="password" name="current_password" placeholder="Current Password" required>
                        <input type="password" name="new_password"     placeholder="New Password (min 6 chars)" required>
                        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                        <button type="submit" style="align-self:flex-start;">🔐 Update Password</button>
                    </div>
                </form>
            </div>

            <!-- QUICK LINKS -->
            <div class="settings-card" style="margin-top:20px;">
                <h3>🔗 Quick Links</h3>
                <p class="desc">Jump to key pages.</p>
                <div class="profile-links">
                    <a href="savings_goals.php"          class="profile-link">🎯 Savings Goals</a>
                    <a href="recurring_transactions.php" class="profile-link">🔁 Recurring Transactions</a>
                    <a href="reports.php"                class="profile-link">📈 Annual Report</a>
                </div>
            </div>

            <!-- DELETE ACCOUNT -->
            <div class="settings-card danger-zone" style="margin-top:20px;">
                <h3>⚠️ Delete Account</h3>
                <p class="desc">This is <strong>permanent and cannot be undone</strong>. All your data will be erased.</p>
                <form method="POST" class="finance-form"
                      onsubmit="return confirm('Are you absolutely sure? This cannot be undone.')">
                    <input type="hidden" name="action" value="delete_account">
                    <div class="form-col">
                        <input type="email"    name="email"    placeholder="Confirm your email"    required>
                        <input type="password" name="password" placeholder="Confirm your password" required>
                        <button type="submit" class="btn-danger" style="align-self:flex-start;">
                            🗑 Permanently Delete Account
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</div>
</div>
</body>
</html>