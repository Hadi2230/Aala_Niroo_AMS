# 📹 راهنمای کامل تنظیم دوربین‌های مداربسته در visit_dashboard.php

## 🎯 **آنچه اضافه شده:**

### ✅ **ویژگی‌های پیاده‌سازی شده:**
- **4 دوربین لایو** (ورودی، سالن تولید، انبار، پارکینگ)
- **کنترل‌های کامل** (شروع، توقف، بروزرسانی)
- **نمایش وضعیت** (آنلاین/آفلاین/در حال بارگذاری)
- **عکس‌برداری** از دوربین‌ها
- **حالت تمام صفحه**
- **رابط کاربری حرفه‌ای** با Video.js

## 🔧 **تنظیمات مورد نیاز:**

### 1️⃣ **تنظیم آدرس‌های دوربین‌ها:**

در فایل `visit_dashboard.php`، آدرس‌های RTSP را تغییر دهید:

```html
<!-- دوربین ورودی -->
<source src="rtsp://192.168.1.100:554/stream1" type="application/x-rtsp">

<!-- دوربین سالن تولید -->
<source src="rtsp://192.168.1.101:554/stream1" type="application/x-rtsp">

<!-- دوربین انبار -->
<source src="rtsp://192.168.1.102:554/stream1" type="application/x-rtsp">

<!-- دوربین پارکینگ -->
<source src="rtsp://192.168.1.103:554/stream1" type="application/x-rtsp">
```

### 2️⃣ **فرمت‌های پشتیبانی شده:**

#### **RTSP (پیشنهادی)**
```
rtsp://username:password@ip:port/stream_path
rtsp://192.168.1.100:554/stream1
rtsp://admin:123456@192.168.1.100:554/live/ch00_0
```

#### **HLS (سازگار با همه مرورگرها)**
```
http://192.168.1.100:8080/hls/stream1.m3u8
```

#### **WebRTC (بهترین کیفیت)**
```javascript
// نیاز به سرور WebRTC
const stream = await navigator.mediaDevices.getUserMedia({
    video: { width: 1280, height: 720 }
});
```

### 3️⃣ **تنظیمات سرور:**

#### **برای RTSP:**
```bash
# نصب FFmpeg
sudo apt-get install ffmpeg

# تبدیل RTSP به HLS
ffmpeg -i rtsp://192.168.1.100:554/stream1 \
       -c:v libx264 -c:a aac \
       -f hls -hls_time 2 -hls_list_size 3 \
       -hls_flags delete_segments \
       /var/www/html/hls/stream1.m3u8
```

#### **برای Nginx:**
```nginx
# /etc/nginx/sites-available/cameras
server {
    listen 8080;
    location /hls/ {
        add_header Cache-Control no-cache;
        add_header Access-Control-Allow-Origin *;
        root /var/www/html;
    }
}
```

### 4️⃣ **تنظیمات دوربین‌های مختلف:**

#### **Hikvision:**
```
rtsp://admin:password@192.168.1.100:554/Streaming/Channels/101
rtsp://admin:password@192.168.1.100:554/Streaming/Channels/102
```

#### **Dahua:**
```
rtsp://admin:password@192.168.1.100:554/cam/realmonitor?channel=1&subtype=0
rtsp://admin:password@192.168.1.100:554/cam/realmonitor?channel=1&subtype=1
```

#### **Axis:**
```
rtsp://192.168.1.100:554/axis-media/media.amp
rtsp://192.168.1.100:554/mjpg/video.mjpg
```

## 🛠️ **راه‌حل‌های جایگزین:**

### 1️⃣ **استفاده از WebRTC:**
```javascript
// اضافه کردن به visit_dashboard.php
async function startWebRTCCamera(cameraId, streamUrl) {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                frameRate: { ideal: 30 }
            }
        });
        
        const video = document.getElementById(cameraId);
        video.srcObject = stream;
        video.play();
        
    } catch (error) {
        console.error('خطا در WebRTC:', error);
    }
}
```

### 2️⃣ **استفاده از MJPEG:**
```html
<!-- برای دوربین‌های MJPEG -->
<img src="http://192.168.1.100:8080/video.mjpg" 
     width="100%" height="250" 
     style="border-radius: 8px;">
```

### 3️⃣ **استفاده از HLS:**
```html
<!-- برای HLS streams -->
<video id="camera1" class="video-js vjs-default-skin" 
       controls preload="auto" width="100%" height="250">
    <source src="http://192.168.1.100:8080/hls/stream1.m3u8" 
            type="application/x-mpegURL">
</video>
```

## 🔒 **امنیت و دسترسی:**

### 1️⃣ **تنظیمات امنیتی:**
```php
// اضافه کردن به visit_dashboard.php
$camera_access = [
    'camera1' => hasPermission('camera.entrance'),
    'camera2' => hasPermission('camera.production'),
    'camera3' => hasPermission('camera.warehouse'),
    'camera4' => hasPermission('camera.parking')
];
```

### 2️⃣ **محدود کردن دسترسی:**
```php
// بررسی دسترسی قبل از نمایش
if (!hasPermission('camera.view')) {
    echo '<div class="alert alert-warning">شما مجوز مشاهده دوربین‌ها را ندارید</div>';
    return;
}
```

## 📱 **پشتیبانی موبایل:**

### 1️⃣ **تنظیمات ریسپانسیو:**
```css
@media (max-width: 768px) {
    .camera-card {
        margin-bottom: 15px;
    }
    
    .camera-video {
        height: 200px;
    }
    
    .camera-controls .btn {
        width: 100%;
        margin-bottom: 10px;
    }
}
```

### 2️⃣ **بهینه‌سازی برای موبایل:**
```javascript
// تشخیص موبایل
if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
    // تنظیمات خاص موبایل
    configureCameraSettings('camera1', {
        quality: 720,
        playbackRate: 1,
        volume: 0
    });
}
```

## 🐛 **عیب‌یابی:**

### 1️⃣ **مشکلات رایج:**
- **دوربین لود نمی‌شود**: بررسی آدرس RTSP و اتصال شبکه
- **کیفیت پایین**: تنظیم کیفیت دوربین و پهنای باند
- **تأخیر زیاد**: استفاده از HLS به جای RTSP
- **عدم پشتیبانی مرورگر**: استفاده از Video.js

### 2️⃣ **تست اتصال:**
```bash
# تست RTSP
ffplay rtsp://192.168.1.100:554/stream1

# تست HLS
ffplay http://192.168.1.100:8080/hls/stream1.m3u8

# تست MJPEG
curl -I http://192.168.1.100:8080/video.mjpg
```

### 3️⃣ **لاگ‌گیری:**
```javascript
// اضافه کردن به JavaScript
function logCameraErrors() {
    Object.keys(cameraPlayers).forEach(cameraId => {
        if (cameraPlayers[cameraId]) {
            cameraPlayers[cameraId].on('error', function(error) {
                console.error(`خطا در دوربین ${cameraId}:`, error);
                // ارسال به سرور
                fetch('log_camera_error.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        camera: cameraId,
                        error: error.message,
                        timestamp: new Date().toISOString()
                    })
                });
            });
        }
    });
}
```

## 📊 **مانیتورینگ و آمار:**

### 1️⃣ **آمار دوربین‌ها:**
```javascript
// دریافت آمار
function getCameraStatistics() {
    return {
        total_cameras: Object.keys(cameraPlayers).length,
        online_cameras: Object.values(cameraStatus).filter(s => s === 'online').length,
        offline_cameras: Object.values(cameraStatus).filter(s => s === 'offline').length,
        uptime: Date.now() - startTime
    };
}
```

### 2️⃣ **گزارش‌گیری:**
```php
// ایجاد جدول آمار دوربین‌ها
CREATE TABLE camera_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camera_id VARCHAR(50),
    status ENUM('online', 'offline', 'error'),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_message TEXT
);
```

## 🚀 **بهینه‌سازی عملکرد:**

### 1️⃣ **تنظیمات Video.js:**
```javascript
const playerOptions = {
    fluid: true,
    responsive: true,
    controls: true,
    preload: 'metadata', // به جای 'auto'
    autoplay: false,
    muted: true,
    playbackRates: [0.5, 1, 1.25, 1.5, 2],
    html5: {
        vhs: {
            overrideNative: true
        }
    }
};
```

### 2️⃣ **Lazy Loading:**
```javascript
// بارگذاری دوربین‌ها فقط هنگام نیاز
function loadCameraOnDemand(cameraId) {
    if (!cameraPlayers[cameraId]) {
        initializeCamera(cameraId);
    }
}
```

---

## ✅ **خلاصه:**

سیستم دوربین‌های مداربسته کاملاً پیاده‌سازی شده و شامل:
- **4 دوربین لایو** با کنترل‌های کامل
- **پشتیبانی از RTSP, HLS, WebRTC**
- **رابط کاربری حرفه‌ای** با Video.js
- **امنیت و دسترسی** قابل تنظیم
- **پشتیبانی موبایل** کامل
- **عیب‌یابی و مانیتورینگ** پیشرفته

**فقط آدرس‌های دوربین‌های خود را تغییر دهید و سیستم آماده است! 🎉**