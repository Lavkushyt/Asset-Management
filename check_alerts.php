<?php
include 'db.php';
include 'mailer.php';

// 24 ghante purane Open tickets jo abhi tak In Progress nahi hue
$deadline = date('Y-m-d H:i:s', strtotime('-24 hours'));

$overdue = mysqli_query($conn, "
    SELECT t.*, u.email as staff_email 
    FROM tickets t
    LEFT JOIN users u ON u.name = t.assigned_to
    WHERE t.status = 'Open' 
    AND t.created_at < '$deadline'
    AND (t.alert_sent IS NULL OR t.alert_sent = 0)
");

if(mysqli_num_rows($overdue) > 0){

    // Super admin emails
    $admins = mysqli_query($conn, "SELECT name, email FROM users WHERE role='super_admin' AND email != ''");
    $admin_emails = [];
    while($a = mysqli_fetch_assoc($admins)) $admin_emails[] = $a;

    while($t = mysqli_fetch_assoc($overdue)){

        $ticket_no  = $t['ticket_no'];
        $subject_t  = htmlspecialchars($t['subject']);
        $emp_name   = htmlspecialchars($t['name']);
        $dept       = $t['department'];
        $priority   = $t['priority'];
        $assigned   = $t['assigned_to'] ?: 'Unassigned';
        $created    = date('d M Y, h:i A', strtotime($t['created_at']));
        $hours_ago  = round((time() - strtotime($t['created_at'])) / 3600);

        // Email content
        $email_content = "
        <div style='background:#da363318;border:1px solid #da363340;border-radius:8px;padding:16px;margin-bottom:16px;'>
            <p style='color:#ff7b72;font-weight:bold;margin:0 0 4px;'>⚠ $hours_ago ghante ho gaye — kaam shuru nahi hua!</p>
        </div>
        <table style='width:100%;border-collapse:collapse;font-size:14px;'>
            <tr><td style='padding:8px;color:#8b949e;width:40%'>Ticket No</td><td style='padding:8px;color:#e6edf3;font-weight:bold;font-family:monospace;'>$ticket_no</td></tr>
            <tr style='background:#161b22'><td style='padding:8px;color:#8b949e;'>Subject</td><td style='padding:8px;color:#e6edf3;'>$subject_t</td></tr>
            <tr><td style='padding:8px;color:#8b949e;'>Employee</td><td style='padding:8px;color:#e6edf3;'>$emp_name</td></tr>
            <tr style='background:#161b22'><td style='padding:8px;color:#8b949e;'>Department</td><td style='padding:8px;color:#e6edf3;'>$dept</td></tr>
            <tr><td style='padding:8px;color:#8b949e;'>Priority</td><td style='padding:8px;color:#ff7b72;font-weight:bold;'>$priority</td></tr>
            <tr style='background:#161b22'><td style='padding:8px;color:#8b949e;'>Assigned To</td><td style='padding:8px;color:#a78bfa;font-weight:bold;'>$assigned</td></tr>
            <tr><td style='padding:8px;color:#8b949e;'>Submit Time</td><td style='padding:8px;color:#e6edf3;'>$created</td></tr>
        </table>
        <div style='margin-top:20px;text-align:center;'>
            <a href='http://localhost/asset/admin_tickets.php' 
               style='background:#1f6feb;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;'>
               🔗 View Ticket
            </a>
        </div>";

        $email_subject = "⚠ Alert: $ticket_no not started within 24 hours!";
        $email_body    = mailTemplate("Ticket Alert — $ticket_no", $email_content, '#da3633');

        // Staff ko email
        if(!empty($t['staff_email'])){
            sendMail($t['staff_email'], $email_subject, $email_body);
        }

        // Super admins ko email
        foreach($admin_emails as $admin){
            sendMail($admin['email'], $email_subject, $email_body);
        }

        // Mark alert sent
        mysqli_query($conn, "UPDATE tickets SET alert_sent=1 WHERE id={$t['id']}");
    }
}
?>
