<?php
/**
 * camera_config_example.php - نمونه تنظیمات دوربین‌های مداربسته
 * این فایل را کپی کنید و تنظیمات دوربین‌های خود را وارد کنید
 */

// تنظیمات دوربین‌های شما
$camera_configs = [
    'camera1' => [
        'name' => 'دوربین ورودی کارخانه',
        'ip' => '192.168.1.100',
        'port' => '554',
        'username' => 'admin',
        'password' => '123456',
        'stream_path' => '/Streaming/Channels/101', // برای Hikvision
        'type' => 'hikvision' // hikvision, dahua, axis, generic
    ],
    'camera2' => [
        'name' => 'دوربین سالن تولید',
        'ip' => '192.168.1.101',
        'port' => '554',
        'username' => 'admin',
        'password' => '123456',
        'stream_path' => '/Streaming/Channels/101',
        'type' => 'hikvision'
    ],
    'camera3' => [
        'name' => 'دوربین انبار',
        'ip' => '192.168.1.102',
        'port' => '554',
        'username' => 'admin',
        'password' => '123456',
        'stream_path' => '/Streaming/Channels/101',
        'type' => 'hikvision'
    ],
    'camera4' => [
        'name' => 'دوربین پارکینگ',
        'ip' => '192.168.1.103',
        'port' => '554',
        'username' => 'admin',
        'password' => '123456',
        'stream_path' => '/Streaming/Channels/101',
        'type' => 'hikvision'
    ]
];

// تابع تولید آدرس RTSP
function generateRTSPUrl($config) {
    $ip = $config['ip'];
    $port = $config['port'];
    $username = $config['username'];
    $password = $config['password'];
    $stream_path = $config['stream_path'];
    
    return "rtsp://{$username}:{$password}@{$ip}:{$port}{$stream_path}";
}

// تابع تولید آدرس HLS (برای مرورگرهای قدیمی)
function generateHLSUrl($config) {
    $ip = $config['ip'];
    return "http://{$ip}:8080/hls/stream1.m3u8";
}

// تابع تولید آدرس MJPEG (برای دوربین‌های قدیمی)
function generateMJPEGUrl($config) {
    $ip = $config['ip'];
    return "http://{$ip}:8080/video.mjpg";
}

// تولید آدرس‌های نهایی
$camera_urls = [];
foreach ($camera_configs as $camera_id => $config) {
    $camera_urls[$camera_id] = [
        'rtsp' => generateRTSPUrl($config),
        'hls' => generateHLSUrl($config),
        'mjpeg' => generateMJPEGUrl($config),
        'name' => $config['name']
    ];
}

// نمایش آدرس‌های تولید شده
echo "<h2>آدرس‌های تولید شده برای دوربین‌های شما:</h2>";
foreach ($camera_urls as $camera_id => $urls) {
    echo "<h3>{$urls['name']} ({$camera_id}):</h3>";
    echo "<p><strong>RTSP:</strong> <code>{$urls['rtsp']}</code></p>";
    echo "<p><strong>HLS:</strong> <code>{$urls['hls']}</code></p>";
    echo "<p><strong>MJPEG:</strong> <code>{$urls['mjpeg']}</code></p>";
    echo "<hr>";
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>تنظیمات دوربین‌های مداربسته</title>
    <style>
        body { font-family: Tahoma; padding: 20px; }
        .config-section { background: #f8f9fa; padding: 20px; margin: 10px 0; border-radius: 8px; }
        .code-block { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; font-family: monospace; }
        .step { background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px; border-right: 4px solid #2196f3; }
    </style>
</head>
<body>
    <h1>🎥 راهنمای تنظیم دوربین‌های مداربسته</h1>
    
    <div class="step">
        <h3>مرحله 1: اطلاعات دوربین‌های خود را وارد کنید</h3>
        <p>در فایل <code>camera_config_example.php</code> اطلاعات زیر را تغییر دهید:</p>
        <ul>
            <li><strong>IP Address:</strong> آدرس IP دوربین (مثل 192.168.1.100)</li>
            <li><strong>Port:</strong> پورت دوربین (معمولاً 554)</li>
            <li><strong>Username:</strong> نام کاربری (معمولاً admin)</li>
            <li><strong>Password:</strong> رمز عبور دوربین</li>
            <li><strong>Stream Path:</strong> مسیر استریم (بسته به نوع دوربین)</li>
        </ul>
    </div>

    <div class="step">
        <h3>مرحله 2: آدرس‌های تولید شده را کپی کنید</h3>
        <p>بعد از اجرای این فایل، آدرس‌های RTSP تولید شده را کپی کنید.</p>
    </div>

    <div class="step">
        <h3>مرحله 3: آدرس‌ها را در visit_dashboard.php جایگزین کنید</h3>
        <p>در فایل <code>visit_dashboard.php</code> خطوط زیر را پیدا کنید و آدرس‌های جدید را جایگزین کنید:</p>
        
        <div class="code-block">
            <!-- دوربین ورودی -->
            <source src="rtsp://192.168.1.100:554/stream1" type="application/x-rtsp">
            
            <!-- دوربین سالن تولید -->
            <source src="rtsp://192.168.1.101:554/stream1" type="application/x-rtsp">
            
            <!-- دوربین انبار -->
            <source src="rtsp://192.168.1.102:554/stream1" type="application/x-rtsp">
            
            <!-- دوربین پارکینگ -->
            <source src="rtsp://192.168.1.103:554/stream1" type="application/x-rtsp">
        </div>
    </div>

    <div class="config-section">
        <h3>🔧 تنظیمات مختلف بر اساس نوع دوربین:</h3>
        
        <h4>Hikvision:</h4>
        <div class="code-block">
            rtsp://admin:password@192.168.1.100:554/Streaming/Channels/101
            rtsp://admin:password@192.168.1.100:554/Streaming/Channels/102
        </div>

        <h4>Dahua:</h4>
        <div class="code-block">
            rtsp://admin:password@192.168.1.100:554/cam/realmonitor?channel=1&subtype=0
            rtsp://admin:password@192.168.1.100:554/cam/realmonitor?channel=1&subtype=1
        </div>

        <h4>Axis:</h4>
        <div class="code-block">
            rtsp://192.168.1.100:554/axis-media/media.amp
            http://192.168.1.100:8080/video.mjpg
        </div>
    </div>

    <div class="config-section">
        <h3>🚨 نکات مهم:</h3>
        <ul>
            <li>اطمینان حاصل کنید که دوربین‌ها در همان شبکه هستند</li>
            <li>پورت 554 باید باز باشد</li>
            <li>نام کاربری و رمز عبور صحیح باشد</li>
            <li>دوربین‌ها از RTSP پشتیبانی کنند</li>
        </ul>
    </div>

    <div class="config-section">
        <h3>🔍 تست اتصال:</h3>
        <p>برای تست اتصال دوربین‌ها، از دستورات زیر استفاده کنید:</p>
        <div class="code-block">
            # تست RTSP
            ffplay rtsp://admin:password@192.168.1.100:554/Streaming/Channels/101
            
            # تست MJPEG
            curl -I http://192.168.1.100:8080/video.mjpg
        </div>
    </div>
</body>
</html>