<?php
// =============================================
// SIRF YE 2 LINES CHANGE KARO APNI DETAILS SE
$gmail_id   = "lavkushyt66@gmail.com";
$gmail_pass = "xliaypmfpiypbor"; // spaces hata do
// =============================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendMail($to, $subject, $body){
    global $gmail_id, $gmail_pass;

    // PHPMailer autoload
    $autoload = __DIR__.'/phpmailer/src/PHPMailer.php';
    if(!file_exists($autoload)){
        // Fallback: PHP mail() function
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: VCM IT Helpdesk <$gmail_id>\r\n";
        return mail($to, $subject, $body, $headers);
    }

    require $autoload;
    require __DIR__.'/phpmailer/src/Exception.php';
    require __DIR__.'/phpmailer/src/SMTP.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmail_id;
        $mail->Password   = $gmail_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($gmail_id, 'VCM IT Helpdesk');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error: ".$mail->ErrorInfo);
        return false;
    }
}

function mailTemplate($title, $content, $color='#1f6feb'){
    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0d1117;color:#e6edf3;border-radius:12px;overflow:hidden;'>
        <div style='background:$color;padding:24px;text-align:center;'>
            <h1 style='margin:0;font-size:22px;color:#fff;'>🎫 VCM IT Helpdesk</h1>
            <p style='margin:6px 0 0;color:#ffffffcc;font-size:14px;'>Vrindavan Chandrodaya Mandir</p>
        </div>
        <div style='padding:28px;'>
            <h2 style='font-size:18px;margin:0 0 16px;color:#e6edf3;'>$title</h2>
            $content
        </div>
        <div style='background:#161b22;padding:14px;text-align:center;font-size:12px;color:#8b949e;border-top:1px solid #30363d;'>
            © ".date('Y')." VCM IT Department &nbsp;·&nbsp; This is an automated email, please do not reply
        </div>
    </div>";
}
?>
