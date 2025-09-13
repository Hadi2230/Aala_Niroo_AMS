<?php
session_start();
include 'config.php';

// تولید CSRF Token
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// شمارش تلاش‌ها و قفل موقت
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lock_time'])) $_SESSION['lock_time'] = 0;

$error = '';
$success = '';
$locked = false;
$lock_duration = 60; // ثانیه

if (time() - $_SESSION['lock_time'] < $lock_duration && $_SESSION['login_attempts'] >= 5) {
	$locked = true;
	$remaining = $lock_duration - (time() - $_SESSION['lock_time']);
	$error = "تعداد تلاش ورود بیش از حد مجاز است. لطفاً $remaining ثانیه دیگر تلاش کنید.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
	$username   = trim((string)($_POST['username'] ?? ''));
	$password   = (string)($_POST['password'] ?? '');
	$csrf_token = (string)($_POST['csrf_token'] ?? '');

	if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
		$error = 'درخواست نامعتبر.';
	} elseif ($username === '' || $password === '') {
		$error = 'لطفاً نام کاربری و رمز عبور را وارد کنید.';
	} else {
		$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
		$stmt->execute([$username]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($user && password_verify($password, $user['password'])) {
			$_SESSION['user_id']  = (int)$user['id'];
			$_SESSION['username'] = (string)$user['username'];
			$roleRaw = trim((string)$user['role']);
			$_SESSION['role'] = ($roleRaw === 'ادمین' || strcasecmp($roleRaw,'admin')===0 || strcasecmp($roleRaw,'administrator')===0) ? 'ادمین' : $roleRaw;

			$_SESSION['login_attempts'] = 0;
			$_SESSION['lock_time'] = 0;

			try { $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]); } catch (Throwable $e) { error_log('Login update error: ' . $e->getMessage()); }

			// لاگ‌گیری ورود موفق
			logAction($pdo, 'LOGIN_SUCCESS', "ورود موفق کاربر: $username", 'info', 'auth', [
				'username' => $username,
				'user_id' => $user['id'],
				'role' => $_SESSION['role']
			]);

			$success = 'ورود موفقیت‌آمیز بود! در حال انتقال...';
			echo "<script>setTimeout(function(){ window.location.href='dashboard.php'; }, 1200);</script>";
		} else {
			$_SESSION['login_attempts']++;
			
			// لاگ‌گیری ورود ناموفق
			logAction($pdo, 'LOGIN_FAILED', "تلاش ورود ناموفق برای کاربر: $username", 'warning', 'auth', [
				'username' => $username,
				'attempts' => $_SESSION['login_attempts'],
				'reason' => $user ? 'invalid_password' : 'user_not_found'
			]);
			
			if ($_SESSION['login_attempts'] >= 5) {
				$_SESSION['lock_time'] = time();
				
				// لاگ‌گیری قفل اکانت
				logAction($pdo, 'ACCOUNT_LOCKED', "قفل اکانت به دلیل تلاش‌های مکرر: $username", 'critical', 'auth', [
					'username' => $username,
					'attempts' => $_SESSION['login_attempts']
				]);
				$locked = true;
				$error = "تعداد تلاش ورود بیش از حد مجاز است. لطفاً $lock_duration ثانیه دیگر تلاش کنید.";
			} else {
				$error = 'نام کاربری یا رمز عبور اشتباه است!';
			}
		}
	}
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ورود به سامانه - اعلا نیرو</title>

	<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

	<style>
	:root {
		--primary-color:#2c3e50;
		--secondary-color:#3498db;
		--accent-color:#e74c3c;
		--success-color:#28a745;
		--gradient-start:#2c3e50;
		--gradient-end:#3498db;
	}
	body {
		margin:0; padding:0;
		font-family:'Vazirmatn',sans-serif;
		background: linear-gradient(135deg,var(--gradient-start),var(--gradient-end));
		min-height:100vh;
		display:flex; flex-direction:column; justify-content:center; align-items:center;
		position:relative;
		transition: background 0.3s ease;
	}
	body.dark-mode { background:#1e1e2f; }
	body::before {
		content:""; position:absolute; width:100%; height:100%;
		background-image: radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 20%),
						  radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 20%),
						  radial-gradient(circle at 40% 40%, rgba(255,255,255,0.05) 0%, transparent 20%);
		z-index:0;
	}
	.login-container {
		width:95%; max-width:480px;
		background: rgba(255,255,255,0.95);
		backdrop-filter: blur(12px);
		border-radius:20px; padding:30px;
		box-shadow:0 15px 35px rgba(0,0,0,0.25);
		position:relative; z-index:1;
		animation: fadeIn 0.5s ease-out;
		transition: transform 0.3s ease, box-shadow 0.3s ease;
	}
	body.dark-mode .login-container { background: rgba(20,20,35,0.95); color:#eee; }
	.login-container:hover { transform:translateY(-5px); box-shadow:0 20px 40px rgba(0,0,0,0.4); }
	.logo { text-align:center; margin-bottom:20px; }
	.logo i { font-size:3rem; color: var(--primary-color); background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:10px; }
	.logo h2 { margin:0; font-size:1.6rem; font-weight:700; }
	.logo p { margin-top:5px; font-size:0.9rem; color:#666; }
	.input-icon { position:relative; }
	.input-icon i { position:absolute; left:15px; top:50%; transform:translateY(-50%); color:#6c757d; z-index:5; }
	.input-icon .form-control { padding-left:45px; border-radius:10px; border:2px solid #e1e5eb; padding:16px 20px; transition:0.3s; }
	.input-icon .form-control:focus { border-color: var(--secondary-color); box-shadow:0 0 0 0.25rem rgba(52,152,219,0.25); }
	.password-toggle { 
		position:absolute; 
		right:15px; 
		top:50%; 
		transform:translateY(-50%); 
		color:#6c757d; 
		cursor:pointer; 
		z-index:6; 
		transition: color 0.3s ease;
		padding: 5px;
	}
	.password-toggle:hover { color: var(--secondary-color); }
	.btn-login { background: linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); border:none; color:#fff; padding:12px; border-radius:10px; width:100%; font-weight:600; margin-top:10px; position:relative; overflow:hidden; transition: all 0.3s; }
	.btn-login:disabled { opacity:0.6; cursor:not-allowed; }
	.btn-login .spinner { position:absolute; left:50%; top:50%; transform:translate(-50%, -50%); display:none; }
	.btn-login.loading .spinner { display:inline-block; }
	.alert { border-radius:10px; padding:12px 16px; margin-bottom:20px; }
	.alert-success { background-color: rgba(40,167,69,0.1); color: var(--success-color); border:1px solid var(--success-color);}
	.footer-links { text-align:center; margin-top:15px; font-size:0.85rem; }
	.footer-links a { color: var(--secondary-color); text-decoration:none; transition:0.3s; }
	.footer-links a:hover { color: var(--primary-color); text-decoration:underline; }
	@keyframes fadeIn { from {opacity:0; transform:translateY(20px);} to {opacity:1; transform:translateY(0);} }
	@keyframes shake { 0%,100%{transform:translateX(0);} 20%,60%{transform:translateX(-10px);} 40%,80%{transform:translateX(10px);} }
	.login-container.shake { animation: shake 0.5s; }
	@media(max-width:576px){.login-container{width:95%;padding:20px;}}

	.theme-switch{position:absolute;top:20px;left:20px;cursor:pointer; z-index:10; color:white; font-size:1.3rem;}

	/* پشتیبانی و پیام حقوقی */
	.phone-chip{
		display:inline-flex;align-items:center;gap:.5rem;
		background:rgba(52,152,219,.12);border:1px solid rgba(52,152,219,.35);
		color:#1f2937;border-radius:999px;padding:.45rem .8rem;font-weight:600;
	}
	.phone-chip .badge{background:#3498db}
	.copy-btn,.call-btn{border:none;border-radius:8px;padding:.35rem .6rem;cursor:pointer}
	.copy-btn{background:#f3f4f6}
	.call-btn{background:#d1fae5;color:#065f46}
	.copy-toast{
		position:fixed;left:50%;bottom:24px;transform:translateX(-50%);
		background:rgba(17,24,39,.95);color:#fff;border-radius:10px;padding:.6rem .9rem;
		box-shadow:0 8px 24px rgba(0,0,0,.25);display:none;z-index:1056
	}
	.legal{
		text-align:center;margin-top:10px;font-size:.86rem;color:#6b7280; z-index:1;
	}
	</style>
</head>
<body>

<div class="theme-switch" onclick="toggleTheme()"><i class="fas fa-moon"></i></div>

<div class="login-container <?php echo $error ? 'shake' : ''; ?>">
	<div class="logo">
		<i class="fas fa-bolt"></i>
		<h2>سیستم مدیریت اعلا نیرو</h2>
		<p>لطفاً برای ادامه وارد شوید</p>
	</div>

	<?php if ($error): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<?php if ($success): ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			<?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<form method="POST" id="loginForm" novalidate>
		<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
		<div class="input-icon mb-3">
			<i class="fas fa-user"></i>
			<input type="text" name="username" class="form-control" placeholder="نام کاربری" required <?php echo $locked ? 'disabled' : ''; ?>>
		</div>
		<div class="input-icon mb-3">
			<i class="fas fa-lock"></i>
			<input type="password" name="password" id="passwordInput" class="form-control" placeholder="رمز عبور" required <?php echo $locked ? 'disabled' : ''; ?>>
			<i class="fas fa-eye password-toggle" id="passwordToggle" onclick="togglePassword()"></i>
		</div>
		<button type="submit" class="btn btn-login" <?php echo $locked ? 'disabled' : ''; ?>>
			<span class="btn-text"><i class="fas fa-sign-in-alt me-2"></i>ورود به سیستم</span>
			<span class="spinner"><i class="fas fa-spinner fa-spin"></i></span>
		</button>
	</form>

	<div class="footer-links">
		<a href="#"><i class="fas fa-question-circle me-1"></i>راهنما</a> •
		<a href="#" data-bs-toggle="modal" data-bs-target="#supportModal">
			<i class="fas fa-headset me-1"></i>پشتیبانی
		</a>
	</div>
</div>

<!-- پیام حقوقی شیک، درست زیر کادر ورود -->
<div class="legal">
	© کلیه حقوق این نرم‌افزار متعلق به مؤلف و توسعه‌دهنده آن است؛ هرگونه تکثیر، انتشار یا بهره‌برداری تجاری بدون مجوز کتبی، ممنوع و قابل پیگرد است.
</div>

<!-- Support Modal -->
<div class="modal fade" id="supportModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content" style="border:none;border-radius:16px;overflow:hidden">
			<div style="background:linear-gradient(135deg,#2c3e50,#3498db);color:#fff" class="p-3">
				<h5 class="m-0"><i class="fas fa-headset me-2"></i>ارتباط با توسعه دهنده سیستم</h5>
				<small class="opacity-75">از صبوری شما سپاسگزارم</small>
			</div>
			<div class="modal-body">
				<p class="mb-3">
					برای پشتیبانی، لطفاً با <strong>توسعه‌دهنده سیستم</strong> تماس حاصل فرمایید.
				</p>
				<div class="d-flex align-items-center justify-content-between">
					<div class="phone-chip">
						<i class="fas fa-phone"></i>
						<span>۰۹۱۲۲۵۴۸۹۰۰</span>
						<span class="badge">Support</span>
					</div>
					<div class="d-flex gap-2">
						<button type="button" class="copy-btn" onclick="copyPhone()">
							<i class="fas fa-copy me-1"></i>کپی
						</button>
						<a class="call-btn text-decoration-none d-inline-flex align-items-center"
						   href="tel:09122548900">
							<i class="fas fa-phone-alt me-1"></i>تماس
						</a>
					</div>
				</div>
				<hr>
				<div class="small text-muted">
					ساعات پاسخ‌گویی: همه‌روزه ۹ تا ۱۵ — درخواست‌های خارج از ساعت اداری در اولین فرصت رسیدگی می‌شوند.
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
			</div>
		</div>
	</div>
</div>

<!-- Toast copy -->
<div id="copyToast" class="copy-toast">
	<i class="fas fa-check-circle me-1 text-success"></i> شماره پشتیبانی کپی شد
</div>

<script>
function togglePassword() {
	const passwordInput = document.getElementById('passwordInput');
	const passwordToggle = document.getElementById('passwordToggle');
	
	if (passwordInput.type === 'password') {
		passwordInput.type = 'text';
		passwordToggle.classList.remove('fa-eye');
		passwordToggle.classList.add('fa-eye-slash');
	} else {
		passwordInput.type = 'password';
		passwordToggle.classList.remove('fa-eye-slash');
		passwordToggle.classList.add('fa-eye');
	}
}

function toggleTheme(){
	const body=document.body,icon=document.querySelector('.theme-switch i');
	if(body.classList.contains('dark-mode')){
		body.classList.remove('dark-mode');
		icon && icon.classList.replace('fa-sun','fa-moon');
		document.cookie='theme=light; path=/; max-age=31536000';
	}else{
		body.classList.add('dark-mode');
		icon && icon.classList.replace('fa-moon','fa-sun');
		document.cookie='theme=dark; path=/; max-age=31536000';
	}
}

document.addEventListener('DOMContentLoaded',()=>{
	const savedTheme=document.cookie.match('(^|;)\\s*theme\\s*=\\s*([^;]+)')?.pop()||'';
	const icon=document.querySelector('.theme-switch i');
	if(savedTheme==='dark'){
		document.body.classList.add('dark-mode');
		icon && icon.classList.replace('fa-moon','fa-sun');
	}

	const container=document.querySelector('.login-container');
	if(container && container.classList.contains('shake')){
		container.addEventListener('animationend',()=>{ container.classList.remove('shake'); });
	}

	const form=document.getElementById('loginForm');
	if (form) {
		form.addEventListener('submit', function(){
			const btn=form.querySelector('.btn-login');
			btn && btn.classList.add('loading');
		});
	}
});

function copyPhone(){
	const num='09122548900';
	if(navigator.clipboard && window.isSecureContext){
		navigator.clipboard.writeText(num).then(showCopied);
	}else{
		const ta=document.createElement('textarea');
		ta.value=num; ta.style.position='fixed'; ta.style.opacity='0';
		document.body.appendChild(ta); ta.select();
		try{ document.execCommand('copy'); }catch(e){}
		document.body.removeChild(ta); showCopied();
	}
}
function showCopied(){
	const t=document.getElementById('copyToast');
	if(!t) return;
	t.style.display='block';
	setTimeout(()=>{ t.style.display='none'; }, 1400);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>