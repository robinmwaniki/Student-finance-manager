<?php
$currentPage = basename($_SERVER['PHP_SELF']);
// Carry current month through to export link
$sideMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $sideMonth)) $sideMonth = date('Y-m');
?>

<div class="sidebar" id="sidebar">

    <!-- Header -->
    <div class="sidebar-header">
        <h2>🎓 Finance Manager</h2>
        <button class="sidebar-close" id="sidebarClose">✕</button>
    </div>

    <!-- Profile card -->
    <?php if (isset($user_id)): ?>
    <?php
        $stmtSide = $conn->prepare("SELECT fullname, avatar FROM users WHERE id = ?");
        $stmtSide->bind_param("i", $user_id);
        $stmtSide->execute();
        $sideUser  = $stmtSide->get_result()->fetch_assoc();
        $nameParts = explode(' ', trim($sideUser['fullname']));
        $initials  = strtoupper(
            substr($nameParts[0], 0, 1) .
            (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : '')
        );
    ?>
    <a href="profile.php" class="sidebar-profile-link <?= $currentPage === 'profile.php' ? 'sidebar-profile-active' : '' ?>">
        <div class="sidebar-avatar">
            <?php if (!empty($sideUser['avatar'])): ?>
                <img src="../assets/avatars/<?= htmlspecialchars($sideUser['avatar']) ?>" alt="avatar">
            <?php else: ?>
                <div class="sidebar-initials"><?= $initials ?></div>
            <?php endif; ?>
        </div>
        <div class="sidebar-user-info">
            <span class="sidebar-name"><?= htmlspecialchars($sideUser['fullname']) ?></span>
            <span class="sidebar-role">Profile &amp; Settings →</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Nav links -->
    <ul>
        <li class="<?= $currentPage === 'dashboard.php'              ? 'active' : '' ?>">
            <a href="dashboard.php">📊 Dashboard</a>
        </li>
        <li class="<?= $currentPage === 'add_income.php'             ? 'active' : '' ?>">
            <a href="add_income.php">💰 Income</a>
        </li>
        <li class="<?= $currentPage === 'add_expense.php'            ? 'active' : '' ?>">
            <a href="add_expense.php">💸 Expenses</a>
        </li>
        <li class="<?= $currentPage === 'add_budget.php'             ? 'active' : '' ?>">
            <a href="add_budget.php">📅 Budget</a>
        </li>
        <li class="<?= $currentPage === 'add_savings.php'            ? 'active' : '' ?>">
            <a href="add_savings.php">🏦 Savings</a>
        </li>
        <li class="<?= $currentPage === 'savings_goals.php'          ? 'active' : '' ?>">
            <a href="savings_goals.php">🎯 Goals</a>
        </li>
        <li class="<?= $currentPage === 'recurring_transactions.php' ? 'active' : '' ?>">
            <a href="recurring_transactions.php">🔁 Recurring</a>
        </li>
        <li class="<?= $currentPage === 'reports.php'                ? 'active' : '' ?>">
            <a href="reports.php">📈 Reports</a>
        </li>
        <li class="<?= $currentPage === 'profile.php'                ? 'active' : '' ?>">
            <a href="profile.php">👤 Profile</a>
        </li>
    </ul>

    <!-- Export section -->
    <div class="sidebar-export-section">
        <span class="sidebar-export-label">Export</span>
        <a href="export_pdf.php?month=<?= $sideMonth ?>"
           class="sidebar-export-btn <?= $currentPage === 'export_pdf.php' ? 'active' : '' ?>">
            📄 Export PDF
        </a>
    </div>

    <!-- Logout -->
    <a href="logout.php" class="sidebar-logout">🚪 Logout</a>

</div>

<!-- Hamburger (mobile) -->
<button class="hamburger" id="hamburger">☰</button>

<script>
    const sidebar   = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    const closeBtn  = document.getElementById('sidebarClose');

    hamburger.addEventListener('click', () => sidebar.classList.add('open'));
    closeBtn.addEventListener('click',  () => sidebar.classList.remove('open'));

    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768
            && !sidebar.contains(e.target)
            && e.target !== hamburger) {
            sidebar.classList.remove('open');
        }
    });
</script>