<?php
/**
 * Simple autoloader for PHPMailer
 */

// PHPMailer classes
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// Create namespace alias for easier usage
class_alias('PHPMailer\PHPMailer\PHPMailer', 'PHPMailer');
class_alias('PHPMailer\PHPMailer\SMTP', 'SMTP');
class_alias('PHPMailer\PHPMailer\Exception', 'PHPMailerException');
?>