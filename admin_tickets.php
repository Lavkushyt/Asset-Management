<?php
session_start();
include 'db.php';

// Auth check
if(!isset($_SESSION['user_id'])){
    header("Location: ticket_login.php?tab=admin"); exit;
}

$my_id    = $_SESSION['user_id'];
$my_name  = $_SESSION['name'];
$my_role  = $_SESSION['role'];

if(!in_array($my_role, ['super_admin','admin','staff'])){
    header("Location: ticket_login.php?tab=admin"); exit;
}

$is_super = $my_role == 'super_admin';
$is_admin = $my_role == 'admin';
$is_staff = $my_role == 'staff';

// ── DELETE (sirf super_admin)
if(isset($_GET['delete']) && $is_super){
    $did = intval($_GET['delete']);
    mysqli_query($conn,"DELETE FROM ticket_comments WHERE ticket_id=$did");
    mysqli_query($conn,"DELETE FROM tickets WHERE id=$did");
    header("Location: admin_tickets.php?toast=deleted"); exit;
}

// ── STATUS UPDATE
if(isset($_POST['update_status'])){
    include 'mailer.php';
    $tid    = intval($_POST['ticket_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $now    = date('Y-m-d H:i:s');

    // Assign karo (super_admin ya admin)
    if(($is_super || $is_admin) && isset($_POST['assign_id'])){
        $assign_id   = intval($_POST['assign_id']);
        $assign_name = '';
        if($assign_id > 0){
            $ar = mysqli_fetch_assoc(mysqli_query($conn,"SELECT name FROM users WHERE id=$assign_id"));
            $assign_name = $ar ? mysqli_real_escape_string($conn,$ar['name']) : '';
        }
        mysqli_query($conn,"UPDATE tickets SET status='$status', assigned_to='$assign_name', assigned_to_id=$assign_id, updated_at='$now' WHERE id=$tid");
    } else {
        mysqli_query($conn,"UPDATE tickets SET status='$status', updated_at='$now' WHERE id=$tid");
    }

    // Resolved hone par user ko email bhejo
    if($status == 'Resolved'){
        $ticket = mysqli_fetch_assoc(mysqli_query($conn,"SELECT t.*, e.email as emp_email FROM tickets t LEFT JOIN emp_users e ON e.id=t.user_id WHERE t.id=$tid"));
        if($ticket && !empty($ticket['emp_email'])){
            $resolved_body = mailTemplate(
                "Aapka Ticket Resolve Ho Gaya! ✅",
                "<p style='color:#e6edf3;font-size:15px;margin-bottom:16px;'>Namaste <strong>".htmlspecialchars($ticket['name'])."</strong>!</p>
                <p style='color:#8b949e;font-size:14px;margin-bottom:16px;'>Aapka IT complaint ticket successfully resolve kar diya gaya hai.</p>
                <div style='background:#23863618;border:1px solid #23863640;border-radius:8px;padding:16px;margin-bottom:16px;'>
                    <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                        <tr><td style='padding:6px;color:#8b949e;'>Ticket No</td><td style='padding:6px;color:#e6edf3;font-weight:bold;font-family:monospace;'>".htmlspecialchars($ticket['ticket_no'])."</td></tr>
                        <tr><td style='padding:6px;color:#8b949e;'>Subject</td><td style='padding:6px;color:#e6edf3;'>".htmlspecialchars($ticket['subject'])."</td></tr>
                        <tr><td style='padding:6px;color:#8b949e;'>Resolved By</td><td style='padding:6px;color:#a78bfa;font-weight:bold;'>".htmlspecialchars($ticket['assigned_to'])."</td></tr>
                        <tr><td style='padding:6px;color:#8b949e;'>Resolve Time</td><td style='padding:6px;color:#3fb950;'>$now</td></tr>
                    </table>
                </div>
                <p style='color:#8b949e;font-size:14px;margin-bottom:16px;'>Kripya apna feedback zaroor dijiye — isse hamare IT team ko behtar karne mein madad milegi.</p>
                <div style='text-align:center;'>
                    <a href='http://localhost/asset/tickets.php' style='background:#238636;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;'>⭐ Feedback Do</a>
                </div>",
                '#238636'
            );
            sendMail($ticket['emp_email'], "Ticket Resolve Ho Gaya — ".$ticket['ticket_no']." | VCM IT Helpdesk", $resolved_body);
        }
    }

    header("Location: admin_tickets.php?toast=updated&open=$tid"); exit;
}

// ── ADD COMMENT
if(isset($_POST['add_comment'])){
    $tid     = intval($_POST['ticket_id']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $cname   = mysqli_real_escape_string($conn, $my_name);
    $now     = date('Y-m-d H:i:s');
    mysqli_query($conn,"INSERT INTO ticket_comments (ticket_id,user_id,commenter_name,comment,created_at) VALUES ($tid,$my_id,'$cname','$comment','$now')");
    header("Location: admin_tickets.php?toast=commented&open=$tid"); exit;
}

// ── FILTERS
$filter_status   = $_GET['status']   ?? '';
$filter_priority = $_GET['priority'] ?? '';
$filter_dept     = $_GET['dept']     ?? '';
$search          = $_GET['search']   ?? '';
$filter_mine     = $_GET['mine']     ?? ($is_staff ? '1' : '0');

$where = "WHERE 1=1";
// Staff sirf apne assigned tickets dekhe
if($is_staff){
    $where .= " AND assigned_to_id=$my_id";
} elseif($filter_mine == '1'){
    $where .= " AND assigned_to_id=$my_id";
}
if($filter_status)   $where .= " AND status='".mysqli_real_escape_string($conn,$filter_status)."'";
if($filter_priority) $where .= " AND priority='".mysqli_real_escape_string($conn,$filter_priority)."'";
if($filter_dept)     $where .= " AND department='".mysqli_real_escape_string($conn,$filter_dept)."'";
if($search){
    $s = mysqli_real_escape_string($conn,$search);
    $where .= " AND (ticket_no LIKE '%$s%' OR subject LIKE '%$s%' OR name LIKE '%$s%')";
}

$tickets = mysqli_query($conn,"SELECT * FROM tickets $where ORDER BY
    CASE priority WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END,
    id DESC");
$total = mysqli_num_rows($tickets);

// ── STATS
if($is_staff){
    $sw = "WHERE assigned_to_id=$my_id";
} else {
    $sw = "WHERE 1=1";
}
$t_open     = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM tickets $sw AND status='Open'"));
$t_progress = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM tickets $sw AND status='In Progress'"));
$t_resolved = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM tickets $sw AND status='Resolved'"));
$t_urgent   = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM tickets $sw AND priority='Urgent' AND status!='Resolved'"));
$t_mine     = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM tickets WHERE assigned_to_id=$my_id AND status!='Resolved'"));

// ── OVERDUE (24 ghante se zyada Open)
$deadline = date('Y-m-d H:i:s', strtotime('-24 hours'));
$od_where = "WHERE status='Open' AND created_at < '$deadline'";
if($is_staff) $od_where .= " AND assigned_to_id=$my_id";
$overdue_tickets = mysqli_query($conn,"SELECT * FROM tickets $od_where ORDER BY created_at ASC");
$overdue_count   = mysqli_num_rows($overdue_tickets);

// ── STAFF LIST for assign dropdown (admin + staff)
$staff_res = mysqli_query($conn,"SELECT id,name,role FROM users WHERE role IN ('admin','staff','super_admin') ORDER BY role,name");

// ── Pre-load all data for JS
$all_tickets  = [];
$all_comments = [];
$tq = mysqli_query($conn,"SELECT * FROM tickets ORDER BY id DESC");
while($tr = mysqli_fetch_assoc($tq)) $all_tickets[$tr['id']] = $tr;
$cq = mysqli_query($conn,"SELECT * FROM ticket_comments ORDER BY created_at ASC");
while($cr = mysqli_fetch_assoc($cq)) $all_comments[$cr['ticket_id']][] = $cr;

$open_id = isset($_GET['open']) ? intval($_GET['open']) : 0;

// Staff list for JS
$staff_list_js = [];
mysqli_data_seek($staff_res,0);
while($sr = mysqli_fetch_assoc($staff_res)) $staff_list_js[] = $sr;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IT Helpdesk — Admin Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0d1117;--surface:#161b22;--surface2:#21262d;
    --border:#30363d;--accent:#1f6feb;--green:#238636;
    --danger:#da3633;--warn:#d29922;--purple:#8b5cf6;
    --text:#e6edf3;--muted:#8b949e;--radius:10px;
    --mono:'JetBrains Mono',monospace;--sans:'Space Grotesk',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--sans);background:var(--bg);color:var(--text);min-height:100vh;}
nav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100;}
.nav-brand{display:flex;align-items:center;gap:10px;}
.nav-logo{width:32px;height:32px;background:var(--warn);border-radius:8px;display:grid;place-items:center;font-size:16px;}
.nav-brand span{font-weight:700;font-size:15px;}
.nav-right{display:flex;align-items:center;gap:8px;}
.nav-user{font-size:13px;font-weight:600;}
.role-badge{padding:3px 10px;border-radius:20px;font-size:12px;font-family:var(--mono);}
.role-badge.super_admin{background:#d2992218;border:1px solid #d2992240;color:var(--warn);}
.role-badge.admin{background:#1f6feb18;border:1px solid #1f6feb40;color:var(--accent);}
.role-badge.staff{background:#8b5cf618;border:1px solid #8b5cf640;color:#a78bfa;}
.nav-link{color:var(--muted);text-decoration:none;font-size:13px;border:1px solid var(--border);padding:5px 12px;border-radius:6px;transition:all .2s;}
.nav-link:hover{color:var(--text);border-color:var(--text);}
.nav-logout{color:var(--danger);}
.nav-logout:hover{background:var(--danger);color:#fff;border-color:var(--danger);}

.page{max-width:1400px;margin:0 auto;padding:20px;}

/* TOAST */
.toast-wrap{position:fixed;top:70px;right:20px;z-index:999;}
.toast{background:var(--surface);border:1px solid var(--border);padding:13px 20px;border-radius:var(--radius);font-size:14px;min-width:250px;box-shadow:0 8px 32px #0007;animation:slideIn .3s ease,fadeOut .4s ease 3.6s forwards;border-left:4px solid var(--green);}
.toast.error{border-left-color:var(--danger);}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes fadeOut{to{opacity:0;transform:translateX(120%)}}

/* OVERDUE ALERT */
.overdue-alert{background:#da363318;border:1px solid #da363340;border-radius:var(--radius);padding:14px 20px;margin-bottom:16px;animation:fadeUp .4s ease;}
.overdue-alert .title{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
.overdue-alert .title h3{font-size:15px;font-weight:700;color:#ff7b72;}
.overdue-chips{display:flex;gap:8px;flex-wrap:wrap;}
.overdue-chip{background:#da363330;border:1px solid #da363360;color:#ff7b72;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--mono);transition:all .2s;}
.overdue-chip:hover{background:#da363350;}

/* WELCOME */
.welcome-banner{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.welcome-banner h2{font-size:16px;font-weight:700;}
.welcome-banner p{font-size:13px;color:var(--muted);margin-top:2px;}
.mine-toggle{display:flex;gap:8px;}
.toggle-btn{padding:7px 14px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;border:1px solid var(--border);color:var(--muted);background:transparent;font-family:var(--sans);}
.toggle-btn.active{background:var(--accent);color:#fff;border-color:var(--accent);}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:16px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;text-align:center;animation:fadeUp .4s ease both;transition:transform .2s;}
.stat-card:hover{transform:translateY(-3px);}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.stat-num{font-size:28px;font-weight:700;font-family:var(--mono);}
.stat-label{font-size:11px;color:var(--muted);margin-top:4px;}

/* FILTER */
.filter-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:12px 16px;margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;flex:1;min-width:130px;}
.fg label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}
.fg input,.fg select{background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-family:var(--sans);font-size:12px;}
.fg input:focus,.fg select:focus{outline:none;border-color:var(--accent);}
.fg select option{background:var(--surface2);}
.btn{padding:7px 14px;border:none;border-radius:6px;font-family:var(--sans);font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:4px;text-decoration:none;white-space:nowrap;}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{background:#388bfd;}
.btn-clear{background:var(--surface2);border:1px solid var(--border);color:var(--text);}

/* LAYOUT */
.layout{display:grid;grid-template-columns:1fr 400px;gap:14px;align-items:start;}
@media(max-width:1000px){.layout{grid-template-columns:1fr;}}

/* TICKET LIST */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.card-header{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.card-header h3{font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px;}
.count-badge{background:var(--surface2);border:1px solid var(--border);color:var(--muted);font-size:11px;padding:2px 8px;border-radius:20px;font-family:var(--mono);}
.ticket-list{max-height:65vh;overflow-y:auto;}
.ticket-item{padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .15s,border-left .15s;border-left:3px solid transparent;animation:rowIn .3s ease both;}
@keyframes rowIn{from{opacity:0;transform:translateX(-6px)}to{opacity:1;transform:translateX(0)}}
.ticket-item:hover{background:var(--surface2);}
.ticket-item.active{background:var(--surface2);border-left-color:var(--accent);}
.ticket-item.urgent-row{border-left-color:var(--danger);}
.ti-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;}
.ti-no{font-family:var(--mono);font-size:11px;color:var(--accent);}
.ti-badges{display:flex;gap:4px;flex-wrap:wrap;align-items:center;}
.ti-subject{font-size:13px;font-weight:600;margin-bottom:4px;}
.ti-meta{font-size:11px;color:var(--muted);display:flex;gap:8px;flex-wrap:wrap;}
.assigned-tag{color:#a78bfa;font-weight:600;}
.unassigned-tag{color:var(--danger);}

/* BADGES */
.priority{display:inline-flex;align-items:center;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:600;}
.priority.Low    {background:#8b949e18;color:#8b949e;border:1px solid #8b949e40;}
.priority.Medium {background:#1f6feb18;color:#79c0ff;border:1px solid #1f6feb40;}
.priority.High   {background:#d2992218;color:#d29922;border:1px solid #d2992240;}
.priority.Urgent {background:#da363318;color:#ff7b72;border:1px solid #da363340;animation:urgentPulse 2s infinite;}
@keyframes urgentPulse{0%,100%{box-shadow:0 0 0 0 #da363330}50%{box-shadow:0 0 0 5px #da363308}}
.status{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:600;}
.status::before{content:'';width:5px;height:5px;border-radius:50%;}
.status.Open{background:#da363318;color:#ff7b72;border:1px solid #da363340;}
.status.Open::before{background:#ff7b72;animation:sp 2s infinite;}
.status.In-Progress{background:#d2992218;color:#d29922;border:1px solid #d2992240;}
.status.In-Progress::before{background:#d29922;}
.status.Resolved{background:#23863618;color:#3fb950;border:1px solid #23863640;}
.status.Resolved::before{background:#3fb950;}
@keyframes sp{0%,100%{box-shadow:0 0 0 2px #ff7b7233}50%{box-shadow:0 0 0 5px #ff7b7211}}

/* DETAIL PANEL */
.detail-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);position:sticky;top:76px;}
.dp-header{padding:14px 16px;border-bottom:1px solid var(--border);}
.dp-header h3{font-size:14px;font-weight:600;}
.dp-body{padding:16px;max-height:80vh;overflow-y:auto;}
.dp-empty{text-align:center;padding:40px 20px;color:var(--muted);}
.dp-empty .icon{font-size:36px;margin-bottom:10px;}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;}
.info-item{display:flex;flex-direction:column;gap:2px;}
.info-item span{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;}
.info-item strong{font-size:13px;}
.dp-subject{font-size:15px;font-weight:700;margin-bottom:8px;line-height:1.3;}
.dp-desc{background:var(--surface2);border:1px solid var(--border);border-radius:7px;padding:10px 12px;font-size:13px;line-height:1.6;margin-bottom:14px;white-space:pre-wrap;}

/* UPDATE FORM */
.update-form{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:12px;margin-bottom:12px;}
.update-form h4{font-size:12px;font-weight:600;margin-bottom:8px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}
.uf-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px;}
.uf-grid select,.uf-grid input{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:7px 9px;border-radius:6px;font-family:var(--sans);font-size:12px;width:100%;}
.uf-grid select:focus,.uf-grid input:focus{outline:none;border-color:var(--accent);}
.uf-grid select option{background:var(--surface2);}
.ubtn{width:100%;padding:8px;background:var(--accent);color:#fff;border:none;border-radius:6px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;}
.ubtn:hover{background:#388bfd;}
.del-btn{width:100%;padding:7px;background:transparent;color:var(--danger);border:1px solid var(--danger);border-radius:6px;font-family:var(--sans);font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;margin-top:6px;}
.del-btn:hover{background:var(--danger);color:#fff;}

/* COMMENTS */
.comments-section h4{font-size:12px;font-weight:600;margin-bottom:8px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}
.comment{background:var(--surface2);border:1px solid var(--border);border-radius:7px;padding:9px 11px;margin-bottom:6px;}
.comment-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
.comment-author{font-size:12px;font-weight:600;color:var(--accent);}
.comment-date{font-size:10px;color:var(--muted);}
.comment-text{font-size:13px;line-height:1.5;}
.comment-form textarea{width:100%;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px;font-family:var(--sans);font-size:13px;resize:vertical;min-height:65px;margin-top:8px;}
.comment-form textarea:focus{outline:none;border-color:var(--purple);}
.cbtn{width:100%;padding:8px;background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:6px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;margin-top:5px;}
.cbtn:hover{border-color:var(--purple);color:var(--purple);}

@media(max-width:600px){
    .stats{grid-template-columns:repeat(2,1fr);}
    .info-grid{grid-template-columns:1fr;}
    .uf-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<nav>
    <div class="nav-brand">
        <div class="nav-logo">🛡</div>
        <span>IT Helpdesk</span>
    </div>
    <div class="nav-right">
        <span class="nav-user"><?php echo htmlspecialchars($my_name); ?></span>
        <span class="role-badge <?php echo $my_role; ?>"><?php echo $my_role; ?></span>
        <a href="home.php" class="nav-link">🏠 Home</a>
        <?php if($is_super): ?>
        <a href="index.php" class="nav-link">📦 Assets</a>
        <?php endif; ?>
        <a href="ticket_logout.php" class="nav-link nav-logout">Logout</a>
    </div>
</nav>

<div class="toast-wrap" id="toastWrap"></div>

<div class="page">

<!-- OVERDUE ALERT -->
<?php if($overdue_count > 0): ?>
<div class="overdue-alert">
    <div class="title">
        <span style="font-size:22px;">🚨</span>
        <h3><?php echo $overdue_count; ?> ticket<?php echo $overdue_count>1?'s':''; ?> not started within 24 hours!</h3>
    </div>
    <div class="overdue-chips">
        <?php
        mysqli_data_seek($overdue_tickets,0);
        while($ot = mysqli_fetch_assoc($overdue_tickets)):
            $hrs = round((time()-strtotime($ot['created_at']))/3600);
        ?>
        <span class="overdue-chip" onclick="loadTicket(<?php echo $ot['id']; ?>)">
            <?php echo $ot['ticket_no']; ?> — <?php echo $hrs; ?> ghante
        </span>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<!-- WELCOME -->
<div class="welcome-banner">
    <div>
        <h2>
            <?php
            $icons = ['super_admin'=>'👑','admin'=>'🛡','staff'=>'👨‍💻'];
            echo ($icons[$my_role]??'👤').' '.htmlspecialchars($my_name);
            ?>
        </h2>
        <p>
            <?php if($is_super): ?>View and manage all tickets — including delete
            <?php elseif($is_admin): ?>Assign tickets to staff — delete not allowed
            <?php else: ?>Your assigned tickets — work and update them<?php endif; ?>
        </p>
    </div>
    <?php if(!$is_staff): ?>
    <div class="mine-toggle">
        <a href="?mine=0" class="toggle-btn <?php echo $filter_mine=='0'?'active':''; ?>">🌐 All Tickets</a>
        <a href="?mine=1" class="toggle-btn <?php echo $filter_mine=='1'?'active':''; ?>">👤 My Assigned</a>
    </div>
    <?php endif; ?>
</div>

<!-- STATS -->
<div class="stats">
    <div class="stat-card" style="border-top:3px solid var(--danger);animation-delay:.05s">
        <div style="font-size:20px;margin-bottom:5px;">🔴</div>
        <div class="stat-num" style="color:#ff7b72;" data-target="<?php echo $t_open; ?>">0</div>
        <div class="stat-label">Open</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--warn);animation-delay:.1s">
        <div style="font-size:20px;margin-bottom:5px;">🟡</div>
        <div class="stat-num" style="color:var(--warn);" data-target="<?php echo $t_progress; ?>">0</div>
        <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--green);animation-delay:.15s">
        <div style="font-size:20px;margin-bottom:5px;">✅</div>
        <div class="stat-num" style="color:#3fb950;" data-target="<?php echo $t_resolved; ?>">0</div>
        <div class="stat-label">Resolved</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--danger);animation-delay:.2s">
        <div style="font-size:20px;margin-bottom:5px;">🚨</div>
        <div class="stat-num" style="color:#ff7b72;" data-target="<?php echo $t_urgent; ?>">0</div>
        <div class="stat-label">Urgent</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--purple);animation-delay:.25s">
        <div style="font-size:20px;margin-bottom:5px;">👤</div>
        <div class="stat-num" style="color:#a78bfa;" data-target="<?php echo $t_mine; ?>">0</div>
        <div class="stat-label">My Assigned</div>
    </div>
</div>

<!-- FILTER -->
<form method="GET">
    <?php if($is_staff): ?><input type="hidden" name="mine" value="1"><?php else: ?>
    <input type="hidden" name="mine" value="<?php echo $filter_mine; ?>"><?php endif; ?>
    <div class="filter-bar">
        <div class="fg" style="flex:2;">
            <label>🔍 Search</label>
            <input type="text" name="search" placeholder="Ticket no, subject, naam..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="fg">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                <option <?php echo $filter_status=='Open'?'selected':''; ?> value="Open">🔴 Open</option>
                <option <?php echo $filter_status=='In Progress'?'selected':''; ?> value="In Progress">🟡 In Progress</option>
                <option <?php echo $filter_status=='Resolved'?'selected':''; ?> value="Resolved">✅ Resolved</option>
            </select>
        </div>
        <div class="fg">
            <label>Priority</label>
            <select name="priority">
                <option value="">All</option>
                <option <?php echo $filter_priority=='Urgent'?'selected':''; ?> value="Urgent">🔴 Urgent</option>
                <option <?php echo $filter_priority=='High'?'selected':''; ?> value="High">🟡 High</option>
                <option <?php echo $filter_priority=='Medium'?'selected':''; ?> value="Medium">🔵 Medium</option>
                <option <?php echo $filter_priority=='Low'?'selected':''; ?> value="Low">⚪ Low</option>
            </select>
        </div>
        <div class="fg">
            <label>Department</label>
            <select name="dept">
                <option value="">All</option>
                <?php foreach(['IT','HR','Finance','Admin','Operations','Marketing','Sales'] as $d): ?>
                <option <?php echo $filter_dept==$d?'selected':''; ?>><?php echo $d; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if($filter_status||$filter_priority||$filter_dept||$search): ?>
        <a href="admin_tickets.php?mine=<?php echo $filter_mine; ?>" class="btn btn-clear">✕</a>
        <?php endif; ?>
    </div>
</form>

<!-- MAIN LAYOUT -->
<div class="layout">

    <!-- TICKET LIST -->
    <div class="card">
        <div class="card-header">
            <h3>
                🎫 <?php echo $is_staff?'My Tickets':($filter_mine=='1'?'My Assigned':'All Tickets'); ?>
                <span class="count-badge"><?php echo $total; ?></span>
            </h3>
        </div>
        <div class="ticket-list">
        <?php if($total > 0):
            $i=0;
            while($t = mysqli_fetch_assoc($tickets)):
                $sc       = str_replace(' ','-',$t['status']);
                $is_urg   = $t['priority']=='Urgent' && $t['status']!='Resolved';
                $is_mine  = $t['assigned_to_id'] == $my_id;
                $is_act   = $open_id == $t['id'];
                $i++;
        ?>
        <div class="ticket-item <?php echo ($is_urg ? 'urgent-row ' : '').($is_act ? 'active' : ''); ?>"
             onclick="loadTicket(<?php echo $t['id']; ?>)"
             style="animation-delay:<?php echo $i*0.03; ?>s"
             id="ti-<?php echo $t['id']; ?>">
            <div class="ti-top">
                <span class="ti-no"><?php echo $t['ticket_no']; ?></span>
                <div class="ti-badges">
                    <span class="priority <?php echo $t['priority']; ?>"><?php echo $t['priority']; ?></span>
                    <span class="status <?php echo $sc; ?>"><?php echo $t['status']; ?></span>
                </div>
            </div>
            <div class="ti-subject"><?php echo htmlspecialchars($t['subject']); ?></div>
            <div class="ti-meta">
                <span>👤 <?php echo htmlspecialchars($t['name']); ?></span>
                <span>🏢 <?php echo $t['department']; ?></span>
                <span>🕐 <?php echo date('d M, h:i A',strtotime($t['created_at'])); ?></span>
                <?php if($t['assigned_to']): ?>
                <span class="assigned-tag">👨‍💻 <?php echo htmlspecialchars($t['assigned_to']); ?></span>
                <?php else: ?>
                <span class="unassigned-tag">⚠ Unassigned</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile;
        else: ?>
        <div style="text-align:center;padding:40px;color:var(--muted);">
            <div style="font-size:36px;margin-bottom:10px;"><?php echo $is_staff?'😊':'🎉'; ?></div>
            <p><?php echo $is_staff?'No tickets assigned to you yet!':'No tickets found!'; ?></p>
        </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- DETAIL PANEL -->
    <div class="detail-panel">
        <div class="dp-header"><h3 id="dp-title">📋 Ticket Detail</h3></div>
        <div class="dp-body" id="dp-body">
            <div class="dp-empty">
                <div class="icon">👈</div>
                <p>Select a ticket from the left</p>
            </div>
        </div>
    </div>

</div>
</div>

<script>
const tickets    = <?php echo json_encode($all_tickets); ?>;
const comments   = <?php echo json_encode($all_comments); ?>;
const staffList  = <?php echo json_encode($staff_list_js); ?>;
const myRole     = <?php echo json_encode($my_role); ?>;
const isSuperAdmin = <?php echo $is_super?'true':'false'; ?>;
const isAdmin      = <?php echo $is_admin?'true':'false'; ?>;
const isStaff      = <?php echo $is_staff?'true':'false'; ?>;

// Counter animation
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

// Toast
const toastMsgs = {'updated':'✅ Ticket updated successfully!','commented':'💬 Comment added successfully!','deleted':'🗑 Ticket deleted successfully!'};
const urlP = new URLSearchParams(location.search);
const tk   = urlP.get('toast');
if(tk && toastMsgs[tk]) showToast(toastMsgs[tk], tk==='deleted'?'error':'');
function showToast(msg,type=''){
    const wrap=document.getElementById('toastWrap');
    const t=document.createElement('div');
    t.className='toast '+(type==='error'?'error':'');
    t.textContent=msg;
    wrap.appendChild(t);
    setTimeout(()=>t.remove(),4000);
}

function loadTicket(id){
    document.querySelectorAll('.ticket-item').forEach(el=>el.classList.remove('active'));
    const item=document.getElementById('ti-'+id);
    if(item) item.classList.add('active');

    const t=tickets[id];
    if(!t) return;
    const sc=t.status.replace(' ','-');
    const ticketComments=comments[id]||[];

    // Assign dropdown — sirf super_admin aur admin ke liye
    let assignHtml = '';
    if(!isStaff){
        let opts = '<option value="0">-- Unassigned --</option>';
        staffList.forEach(s => {
            const roleLabel = s.role==='super_admin'?'👑':s.role==='admin'?'🛡':'👨‍💻';
            opts += `<option value="${s.id}" ${t.assigned_to_id==s.id?'selected':''}>${roleLabel} ${escHtml(s.name)}</option>`;
        });
        assignHtml = `<select name="assign_id">${opts}</select>`;
    }

    // Delete button sirf super_admin ke liye
    const delBtn = isSuperAdmin
        ? `<a href="admin_tickets.php?delete=${t.id}" class="del-btn" onclick="return confirm('Are you sure you want to permanently delete this ticket?')">🗑 Delete Ticket</a>`
        : '';

    let commentsHtml = ticketComments.length > 0
        ? ticketComments.map(c=>`
            <div class="comment">
                <div class="comment-top">
                    <span class="comment-author">💬 ${escHtml(c.commenter_name)}</span>
                    <span class="comment-date">${fmtDate(c.created_at)}</span>
                </div>
                <div class="comment-text">${escHtml(c.comment)}</div>
            </div>`).join('')
        : '<p style="color:var(--muted);font-size:13px;margin-bottom:8px;">No comments yet.</p>';

    document.getElementById('dp-title').textContent = t.ticket_no;
    document.getElementById('dp-body').innerHTML = `
        <div class="dp-subject">${escHtml(t.subject)}</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
            <span class="priority ${t.priority}">${t.priority}</span>
            <span class="status ${sc}">${t.status}</span>
            <span style="background:var(--surface2);border:1px solid var(--border);color:var(--muted);font-size:11px;padding:2px 7px;border-radius:5px;font-family:var(--mono);">${t.category}</span>
        </div>
        <div class="info-grid">
            <div class="info-item"><span>Employee</span><strong>${escHtml(t.name)}</strong></div>
            <div class="info-item"><span>Department</span><strong>${t.department}</strong></div>
            <div class="info-item"><span>Phone</span><strong>${t.phone||'—'}</strong></div>
            <div class="info-item"><span>Asset Tag</span>
                <strong style="color:#79c0ff;font-family:var(--mono);">
                    ${t.asset_tag ? '🏷 '+escHtml(t.asset_tag) : '—'}
                </strong>
            </div>
            <div class="info-item"><span>Submit Date</span><strong>${fmtDate(t.created_at)}</strong></div>
            <div class="info-item"><span>Assigned To</span>
                <strong style="color:${t.assigned_to?'#a78bfa':'#ff7b72'}">
                    ${t.assigned_to ? '👨‍💻 '+escHtml(t.assigned_to) : '⚠ Unassigned'}
                </strong>
            </div>
            <div class="info-item"><span>Last Update</span><strong>${fmtDate(t.updated_at)}</strong></div>
        </div>
        ${t.asset_tag ? '<div style="margin-bottom:14px;"><a href=\"asset_complaints.php?tag='+encodeURIComponent(t.asset_tag)+'\" style=\"display:inline-flex;align-items:center;gap:6px;background:#1f6feb18;border:1px solid #1f6feb40;color:#79c0ff;padding:6px 14px;border-radius:7px;text-decoration:none;font-size:13px;font-weight:600;\" target=\"_blank\">📊 View Asset Complaint History</a></div>' : ''}
        <div style="font-size:11px;color:var(--muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px;">Problem</div>
        <div class="dp-desc">${escHtml(t.description)}</div>

        <div class="update-form">
            <h4>⚙ Update</h4>
            <form method="POST">
                <input type="hidden" name="ticket_id" value="${t.id}">
                <div class="uf-grid">
                    <select name="status">
                        <option value="Open"        ${t.status=='Open'       ?'selected':''}>🔴 Open</option>
                        <option value="In Progress" ${t.status=='In Progress'?'selected':''}>🟡 In Progress</option>
                        <option value="Resolved"    ${t.status=='Resolved'   ?'selected':''}>✅ Resolved</option>
                    </select>
                    ${assignHtml}
                </div>
                <button type="submit" name="update_status" class="ubtn">💾 Update</button>
            </form>
            ${delBtn}
        </div>

        <div class="comments-section">
            <h4>💬 Comments (${ticketComments.length})</h4>
            ${commentsHtml}
            <div class="comment-form">
                <form method="POST">
                    <input type="hidden" name="ticket_id" value="${t.id}">
                    <textarea name="comment" placeholder="Write an update or comment..." required></textarea>
                    <button type="submit" name="add_comment" class="cbtn">💬 Add Comment</button>
                </form>
            </div>
        </div>
    `;
}

function escHtml(str){
    if(!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDate(str){
    if(!str) return '—';
    const d=new Date(str);
    return d.toLocaleDateString('en-IN',{day:'2-digit',month:'short'})+', '+
           d.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
}

<?php if($open_id): ?>
window.addEventListener('load',()=>loadTicket(<?php echo $open_id; ?>));
<?php endif; ?>
</script>
</body>
</html>
