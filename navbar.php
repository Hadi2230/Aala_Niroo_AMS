<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$username       = $_SESSION['username'] ?? 'کاربر';
$rawRole        = trim((string)($_SESSION['role'] ?? ''));
$roleLower      = mb_strtolower($rawRole, 'UTF-8');
$is_admin       = ($roleLower === 'ادمین' || $roleLower === 'admin' || $roleLower === 'administrator');
$is_logged_in   = !empty($_SESSION['user_id']);

$current        = basename($_SERVER['SCRIPT_NAME'] ?? '');
$active         = fn($files) => in_array($current, (array)$files, true) ? ' active' : '';

$surveyActive   = $active(['survey.php','survey_list.php','survey_admin.php','survey_customer_search.php','survey_response.php','survey_answer.php','survey_edit.php','survey_report.php']);
$assetsActive   = $active(['assets.php','profiles_list.php','customers.php','assignments.php','inventory.php']);
$warrantyActive = $active(['create_guaranty.php','print_warranty.php','print_guaranty.php']);
$reportsActive  = $active(['reports.php','survey_report.php','system_logs.php']);
$workflowActive = $active(['tickets.php','maintenance.php','notifications.php','messages.php','request_management.php','request_management_final.php','request_workflow_professional.php','request_tracking_final.php']);
$adminActive    = $active(['users.php','email_settings.php','system_logs.php']);
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root{
  --bg:#f8f9fa; --text:#111827;
  --nav-start:#2c3e50; --nav-end:#3498db; --nav-text:#ffffff; --icon-color:#ffffff;
  --tooltip-bg:#2c3e50; --search-bg:rgba(0,0,0,0.06); --search-placeholder:rgba(0,0,0,0.55);
  --dropdown-bg:#ffffff; --dropdown-text:#111827; --focus-ring:rgba(52,152,219,0.28);
  --workflow-primary:#8b5cf6; --workflow-secondary:#a78bfa;
}
html[data-theme="dark"]{
  --bg:#0b1220; --text:#e6eef8;
  --nav-start:#0f1724; --nav-end:#1b2a40; --nav-text:#e6eef8; --icon-color:#e6eef8;
  --tooltip-bg:#111827; --search-bg:rgba(255,255,255,0.04); --search-placeholder:rgba(255,255,255,0.68);
  --dropdown-bg:#0f1724; --dropdown-text:#e6eef8; --focus-ring:rgba(243,156,18,0.22);
}
html,body{background:var(--bg); color:var(--text); -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;}
.navbar-custom{background:linear-gradient(135deg,var(--nav-start),var(--nav-end)); padding:.54rem 1rem; box-shadow:0 2px 20px rgba(0,0,0,.12); transition:all .28s ease;}
.navbar-brand{font-weight:700; font-size:1rem; color:var(--nav-text)!important; display:flex; align-items:center;}
.navbar-nav{display:flex; gap:1.4rem; align-items:center; margin-left:2rem;}
.nav-item{opacity:0; transform:translateY(10px); transition:all .45s cubic-bezier(.2,.9,.2,1);}
.nav-item.show{opacity:1; transform:none;}
.nav-link{color:var(--nav-text)!important; font-size:.78rem; display:flex; flex-direction:column; align-items:center; gap:2px; padding:.25rem .35rem;}
.nav-link i{font-size:1.28rem; color:var(--icon-color); transition:transform .25s ease, text-shadow .25s;}
.nav-link:hover i{transform:scale(1.35); text-shadow:0 0 14px rgba(0,0,0,.15); color:var(--icon-color);}
.nav-link span{font-size:.72rem; color:var(--nav-text);}
.nav-link[data-bs-title]::after{
  content: attr(data-bs-title);
  position:absolute; bottom:-36px; left:50%; transform:translateX(-50%) scale(.96);
  background:var(--tooltip-bg); color:#fff; font-size:.68rem; padding:.28rem .6rem; border-radius:6px; white-space:nowrap;
  opacity:0; pointer-events:none; transition:opacity .28s, transform .28s; z-index:30;
}
.nav-link:hover[data-bs-title]::after{opacity:1; transform:translateX(-50%) scale(1);}
.dropdown-menu{background:var(--dropdown-bg); color:var(--dropdown-text); border:none; box-shadow:0 8px 26px rgba(0,0,0,.25); border-radius:8px;}
.dropdown-item{color:var(--dropdown-text); padding:.45rem .9rem; transition:background .18s;}
.dropdown-item:hover{background:rgba(0,0,0,0.06); color:var(--dropdown-text);}
.position-relative{position:relative;}
.search-input{background:var(--search-bg); border:none; border-radius:20px; color:var(--text); padding:.32rem .9rem .32rem 2rem; width:150px; transition:all .28s; font-size:.75rem;}
.search-input::placeholder{color:var(--search-placeholder);}
.search-input:focus{outline:none; box-shadow:0 0 0 4px var(--focus-ring); width:190px;}
.search-icon{position:absolute; right:10px; top:50%; transform:translateY(-50%); color:rgba(255,255,255,.65);}
.clock-chip{background:rgba(255,255,255,.06); color:var(--nav-text); border-radius:18px; padding:.2rem .5rem; font-size:.73rem;}
.theme-switcher,.lang-switcher{background:none; border:none; color:var(--nav-text); cursor:pointer; font-size:1rem; padding:.2rem;}
.notification-badge{position:absolute; top:-8px; right:-8px; background:var(--workflow-primary); color:white; border-radius:50%; width:18px; height:18px; font-size:.6rem; display:flex; align-items:center; justify-content:center; font-weight:bold;}
@media(max-width:991.98px){.navbar-nav{margin-left:0; gap:1rem;} .search-input{display:none;}}

/* Mega Menu (Generic) */
.mega-menu{
  position:absolute; top:100%; left:0; min-width:560px; background:var(--dropdown-bg); color:var(--dropdown-text);
  border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.15); padding:20px; z-index:1000; display:none; margin-top:10px;
}
.mega-menu.show{display:block;}
.menu-grid{display:grid; grid-template-columns:repeat(2, 1fr); gap:14px;}
.menu-card{background:#f8f9fa; border-radius:8px; padding:14px; transition:all .28s ease; border:1px solid #e9ecef; text-align:center; text-decoration:none;}
.menu-card:hover{background:#e9ecef; transform:translateY(-3px); box-shadow:0 5px 15px rgba(0,0,0,0.1);}
.menu-card i{font-size:22px; margin-bottom:8px; color:#3498db;}
.menu-card h6{margin:0; font-weight:700; color:#2c3e50; font-size:.92rem;}
.menu-card p{font-size:.75rem; color:#6c757d; margin:6px 0 0;}
/* NOTE: workflow submenu now uses the same visual style as other menu-cards (consistent colors) */
.menu-card.workflow{
  background:var(--dropdown-bg);
  color:var(--dropdown-text);
  border-radius:8px;
  padding:14px;
  transition:all .28s ease;
  border:1px solid #e9ecef;
  text-align:center;
  text-decoration:none;
}
.menu-card.workflow:hover{background:#e9ecef; transform:translateY(-3px); box-shadow:0 5px 15px rgba(0,0,0,0.1);}
.menu-card.workflow i{font-size:22px; margin-bottom:8px; color:#3498db;}
.menu-card.workflow h6{margin:0; font-weight:700; color:#2c3e50; font-size:.92rem;}
.menu-card.workflow p{font-size:.75rem; color:#6c757d; margin:6px 0 0;}
html[data-theme="dark"] .menu-card{background:#1a2235; border-color:#2d3748;}
html[data-theme="dark"] .menu-card:hover{background:#222b3f;}
html[data-theme="dark"] .menu-card h6{color:#e6eef8;}
html[data-theme="dark"] .menu-card p{color:#a0aec0;}
/* ensure workflow cards match dark-mode cards as well */
html[data-theme="dark"] .menu-card.workflow{background:#1a2235; border-color:#2d3748;}
html[data-theme="dark"] .menu-card.workflow:hover{background:#222b3f;}
html[data-theme="dark"] .menu-card.workflow h6{color:#e6eef8;}
html[data-theme="dark"] .menu-card.workflow p{color:#a0aec0;}
</style>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top" role="navigation" aria-label="Main navigation">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php"><i class="fas fa-bolt me-2"></i><span class="lang" data-fa="اعلا نیرو" data-en="Aala Niroo"></span></a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link<?php echo $active('dashboard.php'); ?>" href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span class="lang" data-fa="داشبورد" data-en="Dashboard"></span></a></li>

        <!-- Assets Mega -->
        <li class="nav-item position-relative">
          <a class="nav-link<?php echo $assetsActive; ?>" href="#" id="assetsMenuTrigger">
            <i class="fas fa-server"></i><span class="lang" data-fa="دارایی‌ها" data-en="Assets"></span>
          </a>
          <div class="mega-menu" id="assetsMegaMenu" aria-labelledby="assetsMenuTrigger">
            <div class="menu-grid">
              <a href="assets.php" class="menu-card">
                <i class="fas fa-plus-circle"></i>
                <h6 class="lang" data-fa="مدیریت دارایی‌ها" data-en="Manage Assets"></h6>
                <p class="lang" data-fa="ثبت و مدیریت دستگاه‌ها" data-en="Create and manage assets"></p>
              </a>
              <a href="profiles_list.php" class="menu-card">
                <i class="fas fa-id-card"></i>
                <h6 class="lang" data-fa="پروفایل دستگاه‌ها" data-en="Device Profiles"></h6>
                <p class="lang" data-fa="مشاهده و چاپ پروفایل" data-en="View and print profiles"></p>
              </a>
              <a href="assignments.php" class="menu-card">
                <i class="fas fa-link"></i>
                <h6 class="lang" data-fa="مدیریت انتساب‌ها" data-en="Assignments"></h6>
                <p class="lang" data-fa="انتساب دستگاه به مشتری" data-en="Assign devices to customers"></p>
              </a>
              <a href="customers.php" class="menu-card">
                <i class="fas fa-users"></i>
                <h6 class="lang" data-fa="مدیریت مشتریان" data-en="Customers"></h6>
                <p class="lang" data-fa="افزودن و ویرایش مشتری" data-en="Add and edit customers"></p>
              </a>
              <?php if ($is_admin): ?>
              <a href="inventory.php" class="menu-card">
                <i class="fas fa-warehouse"></i>
                <h6 class="lang" data-fa="مدیریت انبار" data-en="Inventory"></h6>
                <p class="lang" data-fa="نمایش/افزایش/کاهش موجودی" data-en="View & adjust stock"></p>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </li>

        <!-- Workflow Mega (includes Factory Visit Management) -->
        <li class="nav-item position-relative">
          <a class="nav-link<?php echo $workflowActive; ?>" href="#" id="workflowMenuTrigger">
            <i class="fas fa-project-diagram"></i><span class="lang" data-fa="گردش کار" data-en="Workflow"></span>
            <span class="notification-badge" id="workflowNotificationBadge" style="display:none;">0</span>
          </a>
          <div class="mega-menu" id="workflowMegaMenu" aria-labelledby="workflowMenuTrigger">
            <div class="menu-grid">
              <a href="visit_dashboard.php" class="menu-card workflow">
                <i class="fas fa-building"></i>
                <h6 class="lang" data-fa="داشبورد بازدید کارخانه" data-en="Factory Visit Dashboard"></h6>
                <p class="lang" data-fa="مدیریت بازدیدها و آمار" data-en="Visit management and statistics"></p>
              </a>
              <a href="visit_management.php" class="menu-card workflow">
                <i class="fas fa-calendar-plus"></i>
                <h6 class="lang" data-fa="مدیریت بازدیدها" data-en="Visit Management"></h6>
                <p class="lang" data-fa="ثبت و مدیریت درخواست‌ها" data-en="Create and manage requests"></p>
              </a>
              <a href="request_management_final.php" class="menu-card workflow">
                <i class="fas fa-shopping-cart"></i>
                <h6 class="lang" data-fa="درخواست کالا/خدمات" data-en="Request Management"></h6>
                <p class="lang" data-fa="ایجاد درخواست جدید" data-en="Create new request"></p>
              </a>
              <a href="request_workflow_professional.php" class="menu-card workflow">
                <i class="fas fa-cogs"></i>
                <h6 class="lang" data-fa="سیستم حرفه‌ای درخواست‌ها" data-en="Professional Request System"></h6>
                <p class="lang" data-fa="مدیریت و پیگیری پیشرفته" data-en="Advanced management and tracking"></p>
              </a>
              <a href="tickets.php" class="menu-card workflow">
                <i class="fas fa-ticket-alt"></i>
                <h6 class="lang" data-fa="مدیریت تیکت‌ها" data-en="Ticket Management"></h6>
                <p class="lang" data-fa="ایجاد و پیگیری تیکت‌ها" data-en="Create and track tickets"></p>
              </a>
              <a href="maintenance.php" class="menu-card workflow">
                <i class="fas fa-tools"></i>
                <h6 class="lang" data-fa="تعمیرات دوره‌ای" data-en="Maintenance"></h6>
                <p class="lang" data-fa="برنامه‌ریزی و مدیریت تعمیرات" data-en="Schedule and manage maintenance"></p>
              </a>
              <a href="notifications.php" class="menu-card workflow">
                <i class="fas fa-bell"></i>
                <h6 class="lang" data-fa="اعلان‌ها" data-en="Notifications"></h6>
                <p class="lang" data-fa="مشاهده و مدیریت اعلان‌ها" data-en="View and manage notifications"></p>
              </a>
              <a href="messages.php" class="menu-card workflow">
                <i class="fas fa-envelope"></i>
                <h6 class="lang" data-fa="پیام‌های داخلی" data-en="Internal Messages"></h6>
                <p class="lang" data-fa="ارتباط بین کارمندان" data-en="Staff communication"></p>
              </a>
            </div>
          </div>
        </li>

        <!-- Warranty Mega -->
        <li class="nav-item position-relative">
          <a class="nav-link<?php echo $warrantyActive; ?>" href="#" id="warrantyMenuTrigger">
            <i class="fas fa-file-contract"></i><span class="lang" data-fa="گارانتی" data-en="Warranty"></span>
          </a>
          <div class="mega-menu" id="warrantyMegaMenu" aria-labelledby="warrantyMenuTrigger">
            <div class="menu-grid">
              <a href="create_guaranty.php#issue" class="menu-card">
                <i class="fas fa-stamp"></i>
                <h6 class="lang" data-fa="صدور گارانتی" data-en="Issue Warranty"></h6>
                <p class="lang" data-fa="صدور و چاپ کارت گارانتی" data-en="Issue and print warranty card"></p>
              </a>
              <a href="create_guaranty.php#report" class="menu-card">
                <i class="fas fa-list-check"></i>
                <h6 class="lang" data-fa="گزارش گارانتی" data-en="Warranty Report"></h6>
                <p class="lang" data-fa="مشاهده، ویرایش، حذف" data-en="View, edit, delete"></p>
              </a>
            </div>
          </div>
        </li>

        <!-- Reports Mega -->
        <li class="nav-item position-relative">
          <a class="nav-link<?php echo $reportsActive; ?>" href="#" id="reportsMenuTrigger">
            <i class="fas fa-chart-bar"></i><span class="lang" data-fa="گزارشات" data-en="Reports"></span>
          </a>
          <div class="mega-menu" id="reportsMegaMenu" aria-labelledby="reportsMenuTrigger">
            <div class="menu-grid">
              <a href="reports.php" class="menu-card">
                <i class="fas fa-table"></i>
                <h6 class="lang" data-fa="گزارشات سیستم" data-en="System Reports"></h6>
                <p class="lang" data-fa="خلاصه‌های مدیریتی" data-en="Operational summaries"></p>
              </a>
              <?php if ($is_admin): ?>
              <a href="survey_report.php" class="menu-card">
                <i class="fas fa-chart-pie"></i>
                <h6 class="lang" data-fa="گزارش نظرسنجی" data-en="Survey Reports"></h6>
                <p class="lang" data-fa="فیلتر، نمودار، خروجی" data-en="Filters, charts, export"></p>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </li>

        <!-- Survey Mega -->
        <li class="nav-item position-relative">
          <a class="nav-link<?php echo $surveyActive; ?>" href="#" id="surveyMenuTrigger">
            <i class="fas fa-poll"></i><span class="lang" data-fa="نظرسنجی" data-en="Survey"></span>
          </a>
          <div class="mega-menu" id="surveyMegaMenu" aria-labelledby="surveyMenuTrigger">
            <div class="menu-grid">
              <a href="survey.php" class="menu-card">
                <i class="fas fa-play-circle"></i>
                <h6 class="lang" data-fa="شروع نظرسنجی" data-en="Start Survey"></h6>
                <p class="lang" data-fa="شروع یک نظرسنجی جدید" data-en="Start a new survey"></p>
              </a>
              <a href="survey_customer_search.php" class="menu-card">
                <i class="fas fa-search"></i>
                <h6 class="lang" data-fa="جستجوی مشتری" data-en="Customer Search"></h6>
                <p class="lang" data-fa="برای آغاز پاسخ‌دهی" data-en="Find customer to start"></p>
              </a>
              <a href="survey_list.php" class="menu-card">
                <i class="fas fa-history"></i>
                <h6 class="lang" data-fa="تاریخچه نظرسنجی" data-en="Survey History"></h6>
                <p class="lang" data-fa="مشاهده ثبت‌ها" data-en="View submissions"></p>
              </a>
              <?php if($is_admin): ?>
              <a href="survey_admin.php" class="menu-card">
                <i class="fas fa-cog"></i>
                <h6 class="lang" data-fa="مدیریت نظرسنجی" data-en="Survey Management"></h6>
                <p class="lang" data-fa="مدیریت فرم و سوالات" data-en="Manage forms and questions"></p>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </li>

        <?php if($is_admin): ?>
          <!-- Admin Mega Menu -->
          <li class="nav-item position-relative">
            <a class="nav-link<?php echo $adminActive; ?>" href="#" id="adminMenuTrigger">
              <i class="fas fa-user-shield"></i><span class="lang" data-fa="مدیریت" data-en="Admin"></span>
            </a>
            <div class="mega-menu" id="adminMegaMenu" aria-labelledby="adminMenuTrigger">
              <div class="menu-grid">
                <a href="users.php" class="menu-card">
                  <i class="fas fa-user-cog"></i>
                  <h6 class="lang" data-fa="مدیریت کاربران" data-en="User Management"></h6>
                  <p class="lang" data-fa="ایجاد و مدیریت کاربران" data-en="Create and manage users"></p>
                </a>
                <a href="email_settings.php" class="menu-card">
                  <i class="fas fa-envelope-open"></i>
                  <h6 class="lang" data-fa="تنظیمات ایمیل" data-en="Email Settings"></h6>
                  <p class="lang" data-fa="تنظیم SMTP و قالب‌ها" data-en="Configure SMTP and templates"></p>
                </a>
                <a href="system_logs.php" class="menu-card">
                  <i class="fas fa-clipboard-list"></i>
                  <h6 class="lang" data-fa="لاگ سیستم" data-en="System Logs"></h6>
                  <p class="lang" data-fa="مشاهده و تحلیل لاگ‌ها" data-en="View and analyze logs"></p>
                </a>
              </div>
            </div>
          </li>
        <?php endif; ?>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <div class="position-relative d-none d-lg-block">
          <i class="fas fa-search search-icon" aria-hidden="true"></i>
          <input type="text" class="search-input" placeholder="جستجو..." aria-label="Search">
        </div>
        <span class="clock-chip d-none d-lg-inline" id="liveClock" aria-live="polite"></span>
        <button class="theme-switcher d-none d-lg-inline" id="themeSwitcher" aria-pressed="false" title="Toggle theme"><i class="fas fa-moon" aria-hidden="true"></i></button>
        <button class="lang-switcher d-none d-lg-inline" id="langSwitcher" aria-pressed="false" title="Toggle language"><i class="fas fa-language" aria-hidden="true"></i></button>
        <a href="#" class="d-none d-lg-flex align-items-center" style="color:var(--nav-text);"><i class="fas fa-user me-1" aria-hidden="true"></i><?php echo htmlspecialchars($username); ?></a>
        <?php if($is_logged_in): ?>
          <a class="btn btn-sm btn-outline-light d-none d-lg-inline-flex" href="logout.php" title="خروج"><i class="fas fa-sign-out-alt"></i></a>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-light d-none d-lg-inline-flex" href="login.php" title="ورود"><i class="fas fa-sign-in-alt"></i></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const clock = document.getElementById('liveClock');
  if(clock){ const update = ()=> clock.textContent = new Date().toLocaleTimeString('fa-IR',{hour12:false}); update(); setInterval(update,1000); }

  const themeBtn = document.getElementById('themeSwitcher');
  const themeIcon = themeBtn && themeBtn.querySelector('i');
  const applyTheme = (t)=>{
    if(t === 'dark'){
      document.documentElement.setAttribute('data-theme','dark');
      if(themeIcon){ themeIcon.classList.replace('fa-moon','fa-sun'); }
      themeBtn && themeBtn.setAttribute('aria-pressed','true');
    } else {
      document.documentElement.removeAttribute('data-theme');
      if(themeIcon){ themeIcon.classList.replace('fa-sun','fa-moon'); }
      themeBtn && themeBtn.setAttribute('aria-pressed','false');
    }
  };
  let theme = localStorage.getItem('theme') || 'light';
  applyTheme(theme);
  themeBtn && themeBtn.addEventListener('click', ()=>{ theme = (theme === 'light' ? 'dark' : 'light'); localStorage.setItem('theme', theme); applyTheme(theme); });

  const langBtn = document.getElementById('langSwitcher');
  const applyLang = (l) => {
    document.querySelectorAll('.lang').forEach(el=>{
      const txt = el.getAttribute('data-' + l) || '';
      el.textContent = txt;
    });
    langBtn && langBtn.setAttribute('aria-pressed', l === 'en' ? 'true' : 'false');
  };
  let lang = localStorage.getItem('lang') || 'fa';
  applyLang(lang);
  langBtn && langBtn.addEventListener('click', ()=>{ lang = (lang === 'fa' ? 'en' : 'fa'); localStorage.setItem('lang', lang); applyLang(lang); });

  document.querySelectorAll('.nav-item').forEach((el,i)=> setTimeout(()=> el.classList.add('show'), i * 70));

  function setupMega(triggerId, menuId){
    const trigger = document.getElementById(triggerId);
    const menu    = document.getElementById(menuId);
    if(!trigger || !menu) return;
    trigger.addEventListener('click', function(e){
      e.preventDefault();
      // بستن سایر مگامنوها
      document.querySelectorAll('.mega-menu').forEach(m=>{ if(m !== menu) m.classList.remove('show'); });
      menu.classList.toggle('show');
    });
  }
  setupMega('workflowMenuTrigger','workflowMegaMenu');
  setupMega('assetsMenuTrigger','assetsMegaMenu');
  setupMega('warrantyMenuTrigger','warrantyMegaMenu');
  setupMega('reportsMenuTrigger','reportsMegaMenu');
  setupMega('surveyMenuTrigger','surveyMegaMenu');
  setupMega('adminMenuTrigger','adminMegaMenu');

  document.addEventListener('click', function(e){
    const anyOpen = document.querySelector('.mega-menu.show');
    if(anyOpen){
      const isInside = e.target.closest('.mega-menu') || e.target.closest('.nav-item.position-relative');
      if(!isInside) anyOpen.classList.remove('show');
    }
  });

  // به‌روزرسانی تعداد اعلان‌های workflow
  function updateWorkflowNotifications() {
    fetch('get_notifications_count.php')
    .then(response => response.json())
    .then(data => {
      const badge = document.getElementById('workflowNotificationBadge');
      if (badge) {
        if (data.count > 0) {
          badge.textContent = data.count;
          badge.style.display = 'flex';
        } else {
          badge.style.display = 'none';
        }
      }
    })
    .catch(error => console.error('Error updating notifications:', error));
  }

  // به‌روزرسانی هر 30 ثانیه
  setInterval(updateWorkflowNotifications, 30000);
  
  // به‌روزرسانی اولیه
  updateWorkflowNotifications();
});
</script>