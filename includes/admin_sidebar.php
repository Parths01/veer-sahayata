<nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="../admin/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="../admin/users.php">
                    <i class="fas fa-users"></i>
                    User Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : ''; ?>" href="../admin/documents.php">
                    <i class="fas fa-file-alt"></i>
                    Document Approval
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'verifications.php' ? 'active' : ''; ?>" href="../admin/verifications.php">
                    <i class="fas fa-shield-alt"></i>
                    Verification Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pension_management.php' ? 'active' : ''; ?>" href="../admin/pension_management.php">
                    <i class="fas fa-money-check"></i>
                    Pension Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'loan_management.php' ? 'active' : ''; ?>" href="../admin/loan_management.php">
                    <i class="fas fa-chart-line"></i>
                    Loan Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'schemes.php' ? 'active' : ''; ?>" href="../admin/schemes.php">
                    <i class="fas fa-clipboard-list"></i>
                    Schemes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'colleges.php' ? 'active' : ''; ?>" href="../admin/colleges.php">
                    <i class="fas fa-graduation-cap"></i>
                    Colleges
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'hospitals.php' ? 'active' : ''; ?>" href="../admin/hospitals.php">
                    <i class="fas fa-hospital"></i>
                    Hospitals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'news.php' ? 'active' : ''; ?>" href="../admin/news.php">
                    <i class="fas fa-newspaper"></i>
                    News Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="../admin/reports.php">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>System</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="../admin/settings.php">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''; ?>" href="../admin/backup.php">
                    <i class="fas fa-database"></i>
                    Backup
                </a>
            </li>
        </ul>
    </div>
</nav>
