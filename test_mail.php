<?php
// Test file - mail check karne ke liye
// Ye file run karo: localhost/asset/test_mail.php

$gmail_id   = "lavkushyt66@gmail.com";
$gmail_pass = "hfxkjyspderssuyq"; // apna app password yahan

$test_to = "lavkushyt66@gmail.com"; // jis par mail bhejni hai

// Step 1 - PHPMailer check
echo "<h2>Step 1: PHPMailer Check</h2>";
$path = __DIR__.'/phpmailer/src/PHPMailer.php';
if(file_exists($path)){
    echo "✅ PHPMailer found at: $path<br>";
} else {
    echo "❌ PHPMailer NOT found!<br>";
    echo "Ye folder check karo: C:\\xampp\\htdocs\\asset\\phpmailer\\src\\<br>";
    
    // Check other possible locations
    $paths = [
        __DIR__.'/phpMailer/src/PHPMailer.php',
        __DIR__.'/phpmailer-6.8.1/src/PHPMailer.php',
        __DIR__.'/PHPMailer-6.8.1/src/PHPMailer.php',
    ];
    foreach($paths as $p){
        if(file_exists($p)) echo "✅ Found at: $p<br>";
    }
    die("<br><b>PHPMailer sahi jagah nahi hai!</b>");
}

// Step 2 - Load PHPMailer
echo "<h2>Step 2: PHPMailer Load</h2>";
require $path;
require __DIR__.'/phpmailer/src/Exception.php';
require __DIR__.'/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

echo "✅ PHPMailer loaded<br>";

// Step 3 - Send test mail
echo "<h2>Step 3: Mail Bhej Raha Hoon...</h2>";
$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER; // Full debug output
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmail_id;
    $mail->Password   = $gmail_pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($gmail_id, 'VCM IT Helpdesk');
    $mail->addAddress($test_to);
    $mail->isHTML(true);
    $mail->Subject = 'VCM IT Helpdesk - Test Mail';
    $mail->Body    = '<h2>Test Mail</h2><p>Agar ye mail aa gayi toh sab kuch sahi kaam kar raha hai!</p>';

    $mail->send();
    echo "<br><h2 style='color:green'>✅ MAIL SUCCESSFULLY SEND HO GAYI!</h2>";
    echo "Check karo: $test_to";
} catch (Exception $e) {
    echo "<br><h2 style='color:red'>❌ MAIL SEND NAHI HUI!</h2>";
    echo "<b>Error:</b> " . $mail->ErrorInfo;
    echo "<br><br><b>Possible Fixes:</b><br>";
    echo "1. App Password check karo — spaces nahi hone chahiye<br>";
    echo "2. Gmail mein 2FA ON hai?<br>";
    echo "3. App Password sahi generate kiya?<br>";
}
?>
