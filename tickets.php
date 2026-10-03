<?php
session_start();
include 'db.php';
include 'mailer.php';

// Auth check
if(!isset($_SESSION['emp_id'])){
    header("Location: ticket_login.php"); exit;
}

$emp_id    = $_SESSION['emp_id'];
$emp_name  = $_SESSION['emp_name'];
$emp_dept  = $_SESSION['emp_dept'];
$emp_phone = $_SESSION['emp_phone'];

// Get employee email
$emp_data  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT email FROM emp_users WHERE id=$emp_id"));
$emp_email = $emp_data['email'] ?? '';

// ── SUBMIT TICKET
if(isset($_POST['submit_ticket'])){
    $category    = mysqli_real_escape_string($conn, $_POST['category']);
    $priority    = mysqli_real_escape_string($conn, $_POST['priority']);
    $subject     = mysqli_real_escape_string($conn, $_POST['subject']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $asset_tag   = mysqli_real_escape_string($conn, trim($_POST['asset_tag']));
    $name        = mysqli_real_escape_string($conn, $emp_name);
    $dept        = mysqli_real_escape_string($conn, $emp_dept);
    $phone       = mysqli_real_escape_string($conn, $emp_phone);
    $now         = date('Y-m-d H:i:s');

    $last      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT id FROM tickets ORDER BY id DESC LIMIT 1"));
    $next_id   = ($last ? $last['id'] : 0) + 1;
    $ticket_no = 'TKT-'.str_pad($next_id,4,'0',STR_PAD_LEFT);

    mysqli_query($conn,"INSERT INTO tickets
        (ticket_no,user_id,name,department,phone,asset_tag,category,priority,subject,description,status,created_at,updated_at)
        VALUES ('$ticket_no',$emp_id,'$name','$dept','$phone','$asset_tag','$category','$priority','$subject','$description','Open','$now','$now')");

    // Email to employee - ticket submitted
    if($emp_email){
        $body = mailTemplate(
            "Your Ticket Has Been Submitted! 🎫",
            "<p style='color:#e6edf3;font-size:15px;margin-bottom:16px;'>Namaste <strong>".htmlspecialchars($emp_name)."</strong>!</p>
            <p style='color:#8b949e;font-size:14px;margin-bottom:16px;'>Aapka IT complaint ticket successfully submit ho gaya hai. Hamare IT team jaldi se kaam shuru karegi.</p>
            <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                <tr><td style='padding:8px;color:#8b949e;'>Ticket No</td><td style='padding:8px;color:#e6edf3;font-weight:bold;font-family:monospace;'>$ticket_no</td></tr>
                <tr style='background:#161b22'><td style='padding:8px;color:#8b949e;'>Subject</td><td style='padding:8px;color:#e6edf3;'>".htmlspecialchars($_POST['subject'])."</td></tr>
                <tr><td style='padding:8px;color:#8b949e;'>Category</td><td style='padding:8px;color:#e6edf3;'>$category</td></tr>
                <tr style='background:#161b22'><td style='padding:8px;color:#8b949e;'>Priority</td><td style='padding:8px;color:#d29922;font-weight:bold;'>$priority</td></tr>
                <tr><td style='padding:8px;color:#8b949e;'>Status</td><td style='padding:8px;color:#ff7b72;font-weight:bold;'>Open</td></tr>
            </table>
            <p style='color:#8b949e;font-size:13px;margin-top:16px;'>Aap apne ticket ka status VCM IT Portal pe dekh sakte hain.</p>",
            '#1f6feb'
        );
        sendMail($emp_email, "Ticket Submit Ho Gaya — $ticket_no | VCM IT Helpdesk", $body);
    }

    header("Location: tickets.php?toast=submitted&tno=$ticket_no"); exit;
}

// ── SUBMIT FEEDBACK
if(isset($_POST['submit_feedback'])){
    $tid     = intval($_POST['ticket_id']);
    $rating  = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['feedback_comment']);
    $now     = date('Y-m-d H:i:s');

    // Check ticket belongs to this user and is Resolved
    $check = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM tickets WHERE id=$tid AND user_id=$emp_id AND status='Resolved'"));
    if($check && $rating >= 1 && $rating <= 5){
        mysqli_query($conn,"UPDATE tickets SET feedback_rating=$rating, feedback_comment='$comment', feedback_date='$now' WHERE id=$tid");
        header("Location: tickets.php?toast=feedback"); exit;
    }
}

// My tickets
$my_tickets  = mysqli_query($conn,"SELECT * FROM tickets WHERE user_id=$emp_id ORDER BY id DESC");
$my_total    = mysqli_num_rows($my_tickets);
$my_open     = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM tickets WHERE user_id=$emp_id AND status='Open'"));
$my_progress = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM tickets WHERE user_id=$emp_id AND status='In Progress'"));
$my_resolved = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM tickets WHERE user_id=$emp_id AND status='Resolved'"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IT Helpdesk — My Tickets</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0d1117;--surface:#161b22;--surface2:#21262d;
    --border:#30363d;--purple:#8b5cf6;--green:#238636;
    --danger:#da3633;--warn:#d29922;--text:#e6edf3;--muted:#8b949e;--radius:10px;
    --mono:'JetBrains Mono',monospace;--sans:'Space Grotesk',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--sans);background:var(--bg);color:var(--text);min-height:100vh;}

nav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100;}
.nav-brand{display:flex;align-items:center;gap:10px;}
.nav-logo{width:32px;height:32px;background:var(--purple);border-radius:8px;display:grid;place-items:center;font-size:16px;}
.nav-brand span{font-weight:700;font-size:15px;}
.nav-right{display:flex;align-items:center;gap:10px;}
.nav-user{font-size:13px;font-weight:600;}
.dept-badge{background:var(--surface2);border:1px solid var(--border);padding:3px 10px;border-radius:20px;font-size:12px;color:var(--muted);}
.nav-link{color:var(--danger);text-decoration:none;font-size:13px;border:1px solid var(--border);padding:5px 12px;border-radius:6px;transition:all .2s;}
.nav-link:hover{background:var(--danger);color:#fff;}

.page{max-width:900px;margin:0 auto;padding:24px 20px;}

/* TOAST */
.toast-wrap{position:fixed;top:70px;right:20px;z-index:999;}
.toast{background:var(--surface);border:1px solid var(--border);padding:13px 20px;border-radius:var(--radius);font-size:14px;min-width:260px;box-shadow:0 8px 32px #0007;animation:slideIn .3s ease,fadeOut .4s ease 4.6s forwards;border-left:4px solid var(--green);}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes fadeOut{to{opacity:0;transform:translateX(120%)}}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;text-align:center;animation:fadeUp .4s ease both;transition:transform .2s;}
.stat-card:hover{transform:translateY(-3px);}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.stat-num{font-size:28px;font-weight:700;font-family:var(--mono);}
.stat-label{font-size:12px;color:var(--muted);margin-top:5px;}

/* CARD */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:20px;overflow:hidden;}
.card-header{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none;}
.card-header h3{font-size:15px;font-weight:600;display:flex;align-items:center;gap:8px;}
.collapse-icon{transition:transform .3s;color:var(--muted);}
.collapsed .collapse-icon{transform:rotate(-90deg);}
.collapsible-body{overflow:hidden;transition:max-height .4s ease,opacity .3s ease;max-height:2000px;opacity:1;}
.collapsed .collapsible-body{max-height:0;opacity:0;}
.card-body{padding:20px;}

/* FORM */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group.full{grid-column:1/-1;}
.form-group label{font-size:12px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}
.form-group input,.form-group select,.form-group textarea{background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:7px;font-family:var(--sans);font-size:14px;transition:border-color .2s;width:100%;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--purple);box-shadow:0 0 0 3px #8b5cf622;}
.form-group select option{background:var(--surface2);}
.form-group textarea{resize:vertical;min-height:100px;}
.btn-submit{width:100%;padding:13px;border:none;border-radius:8px;background:var(--purple);color:#fff;font-family:var(--sans);font-size:15px;font-weight:600;cursor:pointer;transition:all .2s;margin-top:8px;}
.btn-submit:hover{background:#7c3aed;transform:translateY(-1px);}

/* PRIORITY STATUS */
.priority{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
.priority.Low    {background:#8b949e18;color:#8b949e;border:1px solid #8b949e40;}
.priority.Medium {background:#1f6feb18;color:#79c0ff;border:1px solid #1f6feb40;}
.priority.High   {background:#d2992218;color:#d29922;border:1px solid #d2992240;}
.priority.Urgent {background:#da363318;color:#ff7b72;border:1px solid #da363340;}
.status{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
.status::before{content:'';width:6px;height:6px;border-radius:50%;}
.status.Open{background:#da363318;color:#ff7b72;border:1px solid #da363340;}
.status.Open::before{background:#ff7b72;animation:pulse 2s infinite;}
.status.In-Progress{background:#d2992218;color:#d29922;border:1px solid #d2992240;}
.status.In-Progress::before{background:#d29922;}
.status.Resolved{background:#23863618;color:#3fb950;border:1px solid #23863640;}
.status.Resolved::before{background:#3fb950;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 3px #ff7b7233}50%{box-shadow:0 0 0 6px #ff7b7211}}

/* TICKET CARDS */
.ticket-list{display:flex;flex-direction:column;gap:12px;padding:16px;}
.ticket-card{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;transition:transform .2s,border-color .2s;animation:fadeUp .3s ease both;}
.ticket-card:hover{transform:translateX(4px);}
.ticket-card.resolved-card{border-left:3px solid #3fb950;}
.ticket-card.open-card{border-left:3px solid #ff7b72;}
.ticket-card.progress-card{border-left:3px solid var(--warn);}
.ticket-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px;}
.ticket-no{font-family:var(--mono);font-size:12px;color:var(--purple);font-weight:600;}
.ticket-badges{display:flex;gap:6px;flex-wrap:wrap;}
.ticket-subject{font-size:15px;font-weight:600;margin-bottom:6px;}
.ticket-meta{font-size:12px;color:var(--muted);display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px;}

/* ASSIGNED INFO */
.assigned-info{
    background:var(--surface);border:1px solid #8b5cf640;
    border-radius:7px;padding:10px 14px;margin-bottom:10px;
    display:flex;align-items:center;gap:10px;
}
.assigned-info .icon{font-size:20px;}
.assigned-info .text{font-size:13px;}
.assigned-info .text strong{color:#a78bfa;}
.assigned-info .text span{color:var(--muted);font-size:12px;}

/* COMMENTS */
.comments-box{margin-top:10px;padding-top:10px;border-top:1px solid var(--border);}
.comments-box h4{font-size:12px;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px;}
.comment{background:var(--surface);border:1px solid var(--border);border-radius:7px;padding:10px 12px;margin-bottom:6px;}
.comment-header{display:flex;justify-content:space-between;margin-bottom:4px;}
.comment-author{font-size:12px;font-weight:600;color:var(--purple);}
.comment-date{font-size:11px;color:var(--muted);}
.comment-text{font-size:13px;line-height:1.5;}

/* FEEDBACK BOX */
.feedback-box{
    background:linear-gradient(135deg,#23863618,#1f6feb12);
    border:1px solid #23863640;border-radius:var(--radius);
    padding:16px;margin-top:12px;
}
.feedback-box h4{font-size:14px;font-weight:700;color:#3fb950;margin-bottom:12px;display:flex;align-items:center;gap:6px;}
.star-rating{display:flex;gap:6px;margin-bottom:12px;flex-direction:row-reverse;justify-content:flex-end;}
.star-rating input{display:none;}
.star-rating label{font-size:28px;cursor:pointer;color:var(--border);transition:color .15s;}
.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label{color:#f59e0b;}
.feedback-textarea{width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:10px;border-radius:7px;font-family:var(--sans);font-size:13px;resize:vertical;min-height:70px;margin-bottom:10px;}
.feedback-textarea:focus{outline:none;border-color:var(--green);}
.feedback-btn{width:100%;padding:10px;background:var(--green);color:#fff;border:none;border-radius:7px;font-family:var(--sans);font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;}
.feedback-btn:hover{background:#2ea043;transform:translateY(-1px);}

/* FEEDBACK GIVEN */
.feedback-given{background:var(--surface);border:1px solid #23863640;border-radius:7px;padding:12px;margin-top:10px;}
.feedback-given h4{font-size:13px;color:#3fb950;margin-bottom:6px;}
.stars-display{font-size:20px;margin-bottom:4px;}
.feedback-given p{font-size:13px;color:var(--muted);}

/* EMPTY */
.empty{text-align:center;padding:50px 20px;color:var(--muted);}
.empty .icon{font-size:44px;margin-bottom:12px;}

@media(max-width:600px){
    .stats{grid-template-columns:repeat(2,1fr);}
    .form-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<nav>
    <div class="nav-brand">
        <div class="nav-logo">🎫</div>
        <span>IT Helpdesk</span>
    </div>
    <div class="nav-right">
        <span class="nav-user">👤 <?php echo htmlspecialchars($emp_name); ?></span>
        <span class="dept-badge"><?php echo htmlspecialchars($emp_dept); ?></span>
        <a href="ticket_logout.php" class="nav-link">Logout</a>
    </div>
</nav>

<div class="toast-wrap" id="toastWrap"></div>

<div class="page">

    <!-- STATS -->
    <div class="stats">
        <div class="stat-card" style="border-top:3px solid var(--purple);animation-delay:.05s">
            <div style="font-size:22px;margin-bottom:6px;">🎫</div>
            <div class="stat-num" style="color:var(--purple);" data-target="<?php echo $my_total; ?>">0</div>
            <div class="stat-label">Total Tickets</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--danger);animation-delay:.1s">
            <div style="font-size:22px;margin-bottom:6px;">🔴</div>
            <div class="stat-num" style="color:#ff7b72;" data-target="<?php echo $my_open; ?>">0</div>
            <div class="stat-label">Open</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--warn);animation-delay:.15s">
            <div style="font-size:22px;margin-bottom:6px;">🟡</div>
            <div class="stat-num" style="color:var(--warn);" data-target="<?php echo $my_progress; ?>">0</div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--green);animation-delay:.2s">
            <div style="font-size:22px;margin-bottom:6px;">✅</div>
            <div class="stat-num" style="color:#3fb950;" data-target="<?php echo $my_resolved; ?>">0</div>
            <div class="stat-label">Resolved</div>
        </div>
    </div>

    <!-- NEW TICKET FORM -->
    <div class="card" id="newTicketCard">
        <div class="card-header" onclick="toggleCard('newTicketCard')">
            <h3>➕ New Ticket Submit</h3>
            <span class="collapse-icon">▾</span>
        </div>
        <div class="collapsible-body">
        <div class="card-body">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>📂 Category *</label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <option>Hardware</option>
                            <option>Software</option>
                            <option>Network</option>
                            <option>Printer</option>
                            <option>Email</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>⚡ Priority *</label>
                        <select name="priority" required>
                            <option value="">Select Priority</option>
                            <option value="Low">🟢 Low</option>
                            <option value="Medium">🔵 Medium</option>
                            <option value="High">🟡 High</option>
                            <option value="Urgent">🔴 Urgent</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>🏷 Asset Tag * <span style="font-size:11px;color:var(--muted);">(e.g. VCMT/VRN/LT/13)</span></label>
                        <input type="text" name="asset_tag" placeholder="Enter asset tag of the device having issue..." required>
                    </div>
                    <div class="form-group full">
                        <label>📝 Subject *</label>
                        <input type="text" name="subject" placeholder="Brief description of the issue..." required maxlength="200">
                    </div>
                    <div class="form-group full">
                        <label>📋 Problem Detail *</label>
                        <textarea name="description" placeholder="Describe the issue in detail..." required></textarea>
                    </div>
                </div>
                <button type="submit" name="submit_ticket" class="btn-submit">🎫 Ticket Submit</button>
            </form>
        </div>
        </div>
    </div>

    <!-- MY TICKETS -->
    <div class="card">
        <div class="card-header" style="cursor:default;">
            <h3>
                📋 My Tickets
                <span style="background:var(--surface2);border:1px solid var(--border);color:var(--muted);font-size:12px;padding:2px 10px;border-radius:20px;font-family:var(--mono);font-weight:400;"><?php echo $my_total; ?></span>
            </h3>
        </div>
        <div class="ticket-list">
        <?php if($my_total > 0):
            $i=0;
            while($t = mysqli_fetch_assoc($my_tickets)):
                $sc  = str_replace(' ','-',$t['status']);
                $cls = $t['status']=='Resolved'?'resolved-card':($t['status']=='In Progress'?'progress-card':'open-card');
                $i++;
                $comms     = mysqli_query($conn,"SELECT * FROM ticket_comments WHERE ticket_id={$t['id']} ORDER BY created_at ASC");
                $comm_count= mysqli_num_rows($comms);
        ?>
        <div class="ticket-card <?php echo $cls; ?>" style="animation-delay:<?php echo $i*0.05; ?>s">
            <div class="ticket-top">
                <span class="ticket-no"><?php echo $t['ticket_no']; ?></span>
                <div class="ticket-badges">
                    <span class="priority <?php echo $t['priority']; ?>"><?php echo $t['priority']; ?></span>
                    <span class="status <?php echo $sc; ?>"><?php echo $t['status']; ?></span>
                </div>
            </div>
            <div class="ticket-subject"><?php echo htmlspecialchars($t['subject']); ?></div>
            <div class="ticket-meta">
                <?php if($t['asset_tag']): ?>
                <span style="color:var(--accent2);font-family:var(--mono);font-weight:600;">🏷 <?php echo htmlspecialchars($t['asset_tag']); ?></span>
                <?php endif; ?>
                <span>📂 <?php echo $t['category']; ?></span>
                <span>🕐 <?php echo date('d M Y, h:i A',strtotime($t['created_at'])); ?></span>
                <?php if($comm_count > 0): ?>
                <span>💬 <?php echo $comm_count; ?> comment<?php echo $comm_count>1?'s':''; ?></span>
                <?php endif; ?>
            </div>

            <!-- ASSIGNED INFO -->
            <?php if($t['assigned_to']): ?>
            <div class="assigned-info">
                <div class="icon">👨‍💻</div>
                <div class="text">
                    <strong><?php echo htmlspecialchars($t['assigned_to']); ?></strong>
                    <br><span>is working on this ticket</span>
                </div>
                <?php if($t['status']=='Resolved'): ?>
                <span style="margin-left:auto;background:#23863618;border:1px solid #23863640;color:#3fb950;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;">✅ Resolved</span>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:7px;padding:10px 14px;margin-bottom:10px;font-size:13px;color:var(--muted);">
                ⏳ Awaiting assignment from IT team...
            </div>
            <?php endif; ?>

            <!-- COMMENTS -->
            <?php if($comm_count > 0): ?>
            <div class="comments-box">
                <h4>💬 IT Team Comments</h4>
                <?php while($c = mysqli_fetch_assoc($comms)): ?>
                <div class="comment">
                    <div class="comment-header">
                        <span class="comment-author">👨‍💻 <?php echo htmlspecialchars($c['commenter_name']); ?></span>
                        <span class="comment-date"><?php echo date('d M, h:i A',strtotime($c['created_at'])); ?></span>
                    </div>
                    <div class="comment-text"><?php echo htmlspecialchars($c['comment']); ?></div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <!-- FEEDBACK -->
            <?php if($t['status'] == 'Resolved'): ?>
                <?php if(!$t['feedback_rating']): ?>
                <div class="feedback-box">
                    <h4>⭐ Share Your Feedback</h4>
                    <form method="POST">
                        <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                        <div class="star-rating">
                            <input type="radio" name="rating" id="s5_<?php echo $t['id']; ?>" value="5">
                            <label for="s5_<?php echo $t['id']; ?>">★</label>
                            <input type="radio" name="rating" id="s4_<?php echo $t['id']; ?>" value="4">
                            <label for="s4_<?php echo $t['id']; ?>">★</label>
                            <input type="radio" name="rating" id="s3_<?php echo $t['id']; ?>" value="3">
                            <label for="s3_<?php echo $t['id']; ?>">★</label>
                            <input type="radio" name="rating" id="s2_<?php echo $t['id']; ?>" value="2">
                            <label for="s2_<?php echo $t['id']; ?>">★</label>
                            <input type="radio" name="rating" id="s1_<?php echo $t['id']; ?>" value="1">
                            <label for="s1_<?php echo $t['id']; ?>">★</label>
                        </div>
                        <textarea class="feedback-textarea" name="feedback_comment" placeholder="How was your experience? (optional)"></textarea>
                        <button type="submit" name="submit_feedback" class="feedback-btn">⭐ Feedback Submit</button>
                    </form>
                </div>
                <?php else: ?>
                <div class="feedback-given">
                    <h4>✅ Your Feedback</h4>
                    <div class="stars-display">
                        <?php for($s=1;$s<=5;$s++) echo $s<=$t['feedback_rating']?'⭐':'☆'; ?>
                        <span style="font-size:13px;color:var(--muted);margin-left:6px;"><?php echo $t['feedback_rating']; ?>/5</span>
                    </div>
                    <?php if($t['feedback_comment']): ?>
                    <p>"<?php echo htmlspecialchars($t['feedback_comment']); ?>"</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
        <?php endwhile;
        else: ?>
        <div class="empty">
            <div class="icon">🎫</div>
            <p>No tickets yet.<br>Submit one above if you have an IT issue!</p>
        </div>
        <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.querySelectorAll('.stat-num[data-target]').forEach(el => {
    const target = +el.dataset.target;
    let current = 0;
    const step = Math.max(1,Math.ceil(target/30));
    const timer = setInterval(() => {
        current = Math.min(current+step,target);
        el.textContent = current;
        if(current>=target) clearInterval(timer);
    },40);
});

const toastMessages = {
    'submitted': '🎫 Ticket submitted successfully! A confirmation email has been sent.',
    'feedback':  '⭐ Thank you for your feedback!',
};
const urlParams = new URLSearchParams(location.search);
const toastKey  = urlParams.get('toast');
const tno       = urlParams.get('tno');
if(toastKey && toastMessages[toastKey]){
    showToast(toastMessages[toastKey] + (tno?' ('+tno+')':''));
    history.replaceState({},'', location.pathname);
}
function showToast(msg){
    const wrap=document.getElementById('toastWrap');
    const t=document.createElement('div');
    t.className='toast';
    t.textContent=msg;
    wrap.appendChild(t);
    setTimeout(()=>t.remove(),5000);
}
function toggleCard(id){
    document.getElementById(id).classList.toggle('collapsed');
}
</script>
</body>
</html>
