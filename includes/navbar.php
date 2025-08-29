<?php
// includes/navbar.php - جزء ناوبری مشترک
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
<style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --dark-bg: #1a1a1a;
            --dark-text: #ffffff;
            --dark-border: #333;
        }
        
        .dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text);
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover,
        .nav-link.active {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.18) !important;
            transform: translateY(-2px);
        }
        
        .user-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin-left: 1rem;
        }
        
        .clock {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-family: 'Courier New', monospace;
            margin-left: 1rem;
        }
        
        .theme-switch {
            cursor: pointer;
            padding: 0.5rem;
            margin-left: 1rem;
        }
</style>
<div class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <span style="font-weight:700; letter-spacing:.5px;">اعلا نیرو</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto gap-1">
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-home"></i> داشبورد
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'assets.php' ? 'active' : ''; ?>" href="assets.php">
                            <i class="fas fa-server"></i> مدیریت دارایی‌ها
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                            <i class="fas fa-users"></i> مدیریت مشتریان
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'assignments.php' ? 'active' : ''; ?>" href="assignments.php">
                            <i class="fas fa-link"></i> انتساب دستگاه
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                            <i class="fas fa-chart-bar"></i> گزارشات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                            <i class="fas fa-user-cog"></i> مدیریت کاربران
                        </a>
                    </li>
                    <?php if (($_SESSION['role'] ?? '') === 'ادمین'): ?>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>" href="logs.php">
                            <i class="fas fa-list"></i> لاگ‌ها
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'errors.php' ? 'active' : ''; ?>" href="errors.php">
                            <i class="fas fa-bug"></i> خطاها
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="d-flex align-items-center">
                    <div class="clock" id="liveClock">
                        <i class="fas fa-clock"></i>
                        <span id="clockTime">00:00:00</span>
                    </div>
                    
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        <span><?php echo $_SESSION['username'] ?? 'کاربر'; ?></span>
                    </div>
                    
                    <div class="theme-switch" onclick="toggleTheme()">
                        <i class="fas fa-moon"></i>
                    </div>
                    
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> خروج
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <script>
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fa-IR');
            const el = document.getElementById('clockTime');
            if (el) el.textContent = timeString;
        }
        setInterval(updateClock, 1000);
        updateClock();
        
        function toggleTheme() {
            const body = document.body;
            const isDark = body.classList.contains('dark-mode');
            if (isDark) {
                body.classList.remove('dark-mode');
                document.cookie = "theme=light; path=/; max-age=31536000";
            } else {
                body.classList.add('dark-mode');
                document.cookie = "theme=dark; path=/; max-age=31536000";
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = (document.cookie.match('(^|;)\\s*theme\\s*=\\s*([^;]+)')||[])[2];
            if (savedTheme === 'dark') document.body.classList.add('dark-mode');
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jalaali-js@1.2.7/dist/jalaali.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.0.6/dist/persian-date.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.js"></script>
    <script src="assets/js/jalali-setup.js"></script>
</div>

