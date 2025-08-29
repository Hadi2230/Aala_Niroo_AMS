diff --git a/login.php b/login.php
--- a/login.php
+++ b/login.php
@@ -1,65 +1,73 @@
-<?php
-session_start();
-include 'config.php';
-
-if ($_SERVER['REQUEST_METHOD'] == 'POST') {
-    $username = $_POST['username'];
-    $password = $_POST['password'];
-
-    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
-    $stmt->execute([$username]);
-    $user = $stmt->fetch();
-
-    if ($user && password_verify($password, $user['password'])) {
-        $_SESSION['user_id'] = $user['id'];
-        $_SESSION['username'] = $user['username'];
-        $_SESSION['role'] = $user['role'];
-        header('Location: dashboard.php');
-        exit();
-    } else {
-        $error = "نام کاربری یا رمز عبور اشتباه است!";
-    }
-}
-?>
-
-<!DOCTYPE html>
-<html dir="rtl" lang="fa">
-<head>
-    <meta charset="UTF-8">
-    <meta name="viewport" content="width=device-width, initial-scale=1.0">
-    <title>ورود به سامانه - اعلا نیرو</title>
-    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
-    <style>
-        body {
-            background-color: #f8f9fa;
-        }
-        .login-container {
-            max-width: 400px;
-            margin: 100px auto;
-            padding: 20px;
-            background: white;
-            border-radius: 10px;
-            box-shadow: 0 0 10px rgba(0,0,0,0.1);
-        }
-    </style>
-</head>
-<body>
-    <div class="login-container">
-        <h2 class="text-center mb-4">ورود به سامانه ثبت تجهیزات شرکت اعلا نیرو </h2>
-        <?php if (isset($error)): ?>
-            <div class="alert alert-danger"><?php echo $error; ?></div>
-        <?php endif; ?>
-        <form method="POST">
-            <div class="mb-3">
-                <label for="username" class="form-label">نام کاربری:</label>
-                <input type="text" class="form-control" id="username" name="username" required>
-            </div>
-            <div class="mb-3">
-                <label for="password" class="form-label">رمز عبور:</label>
-                <input type="password" class="form-control" id="password" name="password" required>
-            </div>
-            <button type="submit" class="btn btn-primary w-100">ورود</button>
-        </form>
-    </div>
-</body>
-</html>
+<?php
+session_start();
+include 'config.php';
+
+if ($_SERVER['REQUEST_METHOD'] == 'POST') {
+    $username = $_POST['username'];
+    $password = $_POST['password'];
+
+    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
+    $stmt->execute([$username]);
+    $user = $stmt->fetch();
+
+    if ($user && password_verify($password, $user['password'])) {
+        $_SESSION['user_id'] = $user['id'];
+        $_SESSION['username'] = $user['username'];
+        $_SESSION['role'] = $user['role'];
+        // ثبت زمان آخرین ورود و لاگ سیستم
+        try {
+            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
+            logAction($pdo, 'LOGIN', 'کاربر وارد سیستم شد');
+        } catch (Throwable $e) {
+            error_log('Login update error: ' . $e->getMessage());
+        }
+        header('Location: dashboard.php');
+        exit();
+    } else {
+        $error = "نام کاربری یا رمز عبور اشتباه است!";
+    }
+}
+?>
+
+<!DOCTYPE html>
+<html dir="rtl" lang="fa">
+<head>
+    <meta charset="UTF-8">
+    <meta name="viewport" content="width=device-width, initial-scale=1.0">
+    <title>ورود به سامانه - اعلا نیرو</title>
+    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
+    <style>
+        body {
+            background-color: #f8f9fa;
+        }
+        .login-container {
+            max-width: 400px;
+            margin: 100px auto;
+            padding: 20px;
+            background: white;
+            border-radius: 10px;
+            box-shadow: 0 0 10px rgba(0,0,0,0.1);
+        }
+    </style>
+</head>
+<body>
+    <div class="login-container">
+        <h2 class="text-center mb-4">ورود به سامانه ثبت تجهیزات شرکت اعلا نیرو </h2>
+        <?php if (isset($error)): ?>
+            <div class="alert alert-danger"><?php echo $error; ?></div>
+        <?php endif; ?>
+        <form method="POST">
+            <div class="mb-3">
+                <label for="username" class="form-label">نام کاربری:</label>
+                <input type="text" class="form-control" id="username" name="username" required>
+            </div>
+            <div class="mb-3">
+                <label for="password" class="form-label">رمز عبور:</label>
+                <input type="password" class="form-control" id="password" name="password" required>
+            </div>
+            <button type="submit" class="btn btn-primary w-100">ورود</button>
+        </form>
+    </div>
+</body>
+</html>
+