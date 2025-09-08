<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fa fa-cogs"></i> اعلا نیرو
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fa fa-home"></i> داشبورد
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="assets.php">
                        <i class="fa fa-box"></i> دارایی‌ها
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="customers.php">
                        <i class="fa fa-users"></i> مشتریان
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fa fa-user"></i> کاربران
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="assignments.php">
                        <i class="fa fa-link"></i> انتساب‌ها
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tickets.php">
                        <i class="fa fa-ticket-alt"></i> تیکت‌ها
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="maintenance.php">
                        <i class="fa fa-tools"></i> تعمیرات دوره‌ای
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <!-- اعلان‌ها -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fa fa-bell"></i>
                        <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="notificationBadge">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                        <li><h6 class="dropdown-header">اعلان‌های جدید</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="markAllAsRead()">علامت‌گذاری همه به عنوان خوانده شده</a></li>
                    </ul>
                </li>
                
                <!-- کاربر -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fa fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'کاربر'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fa fa-user-edit"></i> پروفایل</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fa fa-cog"></i> تنظیمات</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out-alt"></i> خروج</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
// به‌روزرسانی تعداد اعلان‌ها
function updateNotificationCount() {
    fetch('get_notifications_count.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.textContent = data.count;
            if (data.count > 0) {
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// به‌روزرسانی هر 30 ثانیه
setInterval(updateNotificationCount, 30000);

// به‌روزرسانی اولیه
document.addEventListener('DOMContentLoaded', updateNotificationCount);
</script>