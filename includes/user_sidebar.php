<nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="../user/dashboard.php">
                    <i class="fas fa-home"></i>
                    Home
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pension.php' ? 'active' : ''; ?>" href="../user/pension.php">
                    <i class="fas fa-money-bill-wave"></i>
                    Pension Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'loans.php' ? 'active' : ''; ?>" href="../user/loans.php">
                    <i class="fas fa-chart-line"></i>
                    Loan Tracker
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'schemes.php' ? 'active' : ''; ?>" href="../user/schemes.php">
                    <i class="fas fa-clipboard-list"></i>
                    Schemes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'colleges.php' ? 'active' : ''; ?>" href="../user/colleges.php">
                    <i class="fas fa-graduation-cap"></i>
                    Colleges
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'health.php' ? 'active' : ''; ?>" href="../user/health.php">
                    <i class="fas fa-heartbeat"></i>
                    Health
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="../user/profile.php">
                    <i class="fas fa-user"></i>
                    Profile
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Support</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'verification.php' ? 'active' : ''; ?>" href="../user/verification.php">
                    <i class="fas fa-check-circle"></i>
                    Document Verification
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'live_verification.php' ? 'active' : ''; ?>" href="../user/live_verification.php">
                    <i class="fas fa-user-check"></i>
                    Live Verification
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'help.php' ? 'active' : ''; ?>" href="../user/help.php">
                    <i class="fas fa-question-circle"></i>
                    Help & Support
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-globe"></i>
                    Public Site
                </a>
            </li>
        </ul>
    </div>
</nav>
