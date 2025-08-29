<?php
// جهت حفظ سازگاری قدیمی، فایل جدید از includes/navbar.php بارگذاری شود
include __DIR__ . '/includes/navbar.php';
return;
?>
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
            color: white !important;
            background-color: rgba(255, 255, 255, 0.1);
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
            <!-- لوگو و نام شرکت -->
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-bolt"></i>
                اعلا نیرو
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- منوی اصلی -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-home"></i> داشبورد
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'assets.php' ? 'active' : ''; ?>" href="assets.php">
                            <i class="fas fa-server"></i> مدیریت دارایی‌ها
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                            <i class="fas fa-users"></i> مدیریت مشتریان
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'assignments.php' ? 'active' : ''; ?>" href="assignments.php">
                            <i class="fas fa-link"></i> انتساب دستگاه
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                            <i class="fas fa-chart-bar"></i> گزارشات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>" href="users.php">
                            <i class="fas fa-user-cog"></i> مدیریت کاربران
                        </a>
                    </li>
                </ul>
                
                <!-- اطلاعات کاربر و ساعت -->
                <div class="d-flex align-items-center">
                    <!-- ساعت -->
                    <div class="clock" id="liveClock">
                        <i class="fas fa-clock"></i>
                        <span id="clockTime">00:00:00</span>
                    </div>
                    
                    <!-- اطلاعات کاربر -->
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        <span><?php echo $_SESSION['username'] ?? 'کاربر'; ?></span>
                    </div>
                    
                    <!-- تغییر تم -->
                    <div class="theme-switch" onclick="toggleTheme()">
                        <i class="fas fa-moon"></i>
                    </div>
                    
                    <!-- خروج -->
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> خروج
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        // نمایش ساعت زنده
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fa-IR');
            document.getElementById('clockTime').textContent = timeString;
        }
        
        setInterval(updateClock, 1000);
        updateClock();
        
        // تغییر تم
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
        
        // بررسی تم ذخیره شده
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = getCookie('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
            }
        });
        
        // تابع خواندن cookie
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
        }
    </script>
</div>