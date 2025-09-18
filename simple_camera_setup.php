<?php
/**
 * simple_camera_setup.php - تنظیمات ساده دوربین‌های مداربسته
 */

echo "<h1>🎥 تنظیمات دوربین‌های مداربسته</h1>";
echo "<style>
    body { font-family: Tahoma; padding: 20px; direction: rtl; }
    .step { background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .code { background: #2d3748; color: #e2e8f0; padding: 10px; border-radius: 5px; font-family: monospace; }
</style>";

// اطلاعات دوربین‌های شما - این قسمت را تغییر دهید
$cameras = [
    'دوربین ورودی' => '192.168.1.100',
    'دوربین سالن تولید' => '192.168.1.101', 
    'دوربین انبار' => '192.168.1.102',
    'دوربین پارکینگ' => '192.168.1.103'
];

$username = 'admin';  // نام کاربری دوربین
$password = '123456'; // رمز عبور دوربین

echo "<div class='step'>";
echo "<h2>مرحله 1: اطلاعات دوربین‌های خود را وارد کنید</h2>";
echo "<p>در فایل <code>simple_camera_setup.php</code> خطوط زیر را تغییر دهید:</p>";
echo "<div class='code'>";
echo "\$cameras = [<br>";
echo "&nbsp;&nbsp;'دوربین ورودی' => '192.168.1.100',<br>";
echo "&nbsp;&nbsp;'دوربین سالن تولید' => '192.168.1.101',<br>";
echo "&nbsp;&nbsp;'دوربین انبار' => '192.168.1.102',<br>";
echo "&nbsp;&nbsp;'دوربین پارکینگ' => '192.168.1.103'<br>";
echo "];<br><br>";
echo "\$username = 'admin';  // نام کاربری دوربین<br>";
echo "\$password = '123456'; // رمز عبور دوربین";
echo "</div>";
echo "</div>";

echo "<div class='step'>";
echo "<h2>مرحله 2: آدرس‌های تولید شده</h2>";
echo "<p>آدرس‌های زیر را کپی کنید و در <code>visit_dashboard.php</code> جایگزین کنید:</p>";

$camera_id = 1;
foreach ($cameras as $name => $ip) {
    $rtsp_url = "rtsp://{$username}:{$password}@{$ip}:554/Streaming/Channels/101";
    $hls_url = "http://{$ip}:8080/hls/stream1.m3u8";
    $mjpeg_url = "http://{$ip}:8080/video.mjpg";
    
    echo "<h3>{$name} (camera{$camera_id}):</h3>";
    echo "<div class='code'>";
    echo "&lt;source src=\"{$rtsp_url}\" type=\"application/x-rtsp\"&gt;";
    echo "</div>";
    echo "<p><strong>آدرس‌های جایگزین:</strong></p>";
    echo "<div class='code'>";
    echo "HLS: {$hls_url}<br>";
    echo "MJPEG: {$mjpeg_url}";
    echo "</div>";
    echo "<hr>";
    
    $camera_id++;
}
echo "</div>";

echo "<div class='step'>";
echo "<h2>مرحله 3: جایگزینی در visit_dashboard.php</h2>";
echo "<p>در فایل <code>visit_dashboard.php</code> خطوط زیر را پیدا کنید و آدرس‌های بالا را جایگزین کنید:</p>";
echo "<div class='code'>";
echo "<!-- دوربین ورودی --><br>";
echo "&lt;source src=\"rtsp://192.168.1.100:554/stream1\" type=\"application/x-rtsp\"&gt;<br><br>";
echo "<!-- دوربین سالن تولید --><br>";
echo "&lt;source src=\"rtsp://192.168.1.101:554/stream1\" type=\"application/x-rtsp\"&gt;<br><br>";
echo "<!-- دوربین انبار --><br>";
echo "&lt;source src=\"rtsp://192.168.1.102:554/stream1\" type=\"application/x-rtsp\"&gt;<br><br>";
echo "<!-- دوربین پارکینگ --><br>";
echo "&lt;source src=\"rtsp://192.168.1.103:554/stream1\" type=\"application/x-rtsp\"&gt;";
echo "</div>";
echo "</div>";

echo "<div class='step'>";
echo "<h2>🔧 تنظیمات بر اساس نوع دوربین:</h2>";
echo "<h3>Hikvision:</h3>";
echo "<div class='code'>rtsp://admin:password@192.168.1.100:554/Streaming/Channels/101</div>";
echo "<h3>Dahua:</h3>";
echo "<div class='code'>rtsp://admin:password@192.168.1.100:554/cam/realmonitor?channel=1&subtype=0</div>";
echo "<h3>Axis:</h3>";
echo "<div class='code'>rtsp://192.168.1.100:554/axis-media/media.amp</div>";
echo "</div>";

echo "<div class='step'>";
echo "<h2>🚨 نکات مهم:</h2>";
echo "<ul>";
echo "<li>اطمینان حاصل کنید که دوربین‌ها در همان شبکه هستند</li>";
echo "<li>پورت 554 باید باز باشد</li>";
echo "<li>نام کاربری و رمز عبور صحیح باشد</li>";
echo "<li>دوربین‌ها از RTSP پشتیبانی کنند</li>";
echo "<li>اگر RTSP کار نکرد، از HLS یا MJPEG استفاده کنید</li>";
echo "</ul>";
echo "</div>";

echo "<div class='step'>";
echo "<h2>🔍 تست اتصال:</h2>";
echo "<p>برای تست اتصال دوربین‌ها:</p>";
echo "<div class='code'>";
echo "# تست RTSP<br>";
echo "ffplay rtsp://admin:password@192.168.1.100:554/Streaming/Channels/101<br><br>";
echo "# تست MJPEG<br>";
echo "curl -I http://192.168.1.100:8080/video.mjpg";
echo "</div>";
echo "</div>";

echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='visit_dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 بازگشت به داشبورد</a>";
echo "</div>";
?>