<?php
session_start();

// No cache headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
include 'db.php';

// Already logged in
if(isset($_SESSION['emp_id']) || isset($_SESSION['user_id'])){
    if(isset($_SESSION['user_id'])){
        header("Location: admin_tickets.php"); 
    } else {
        header("Location: tickets.php");
    }
    exit;
}

$error   = '';
$success = '';
$tab     = $_GET['tab'] ?? 'login';

// EMPLOYEE REGISTER
if(isset($_POST['register'])){
    $name       = mysqli_real_escape_string($conn, trim($_POST['name']));
    $phone      = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $emp_id     = mysqli_real_escape_string($conn, trim($_POST['emp_id']));
    $password   = MD5($_POST['password']);

    $check = mysqli_query($conn, "SELECT id FROM emp_users WHERE phone='$phone'");
    if(mysqli_num_rows($check) > 0){
        $error = "This phone number is already registered!";
        $tab = 'register';
    } else {
        $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
        mysqli_query($conn, "INSERT INTO emp_users (name,phone,email,department,emp_id,password,created_at)
            VALUES ('$name','$phone','$email','$department','$emp_id','$password',NOW())");

        // Welcome Email
        if(!empty($_POST['email'])){
            include_once 'mailer.php';
            $welcome_body = mailTemplate(
                "Welcome to VCM IT Portal! 🎉",
                "<p style='color:#e6edf3;font-size:15px;margin-bottom:16px;'>Namaste <strong>".htmlspecialchars($_POST['name'])."</strong>!</p>
                <p style='color:#8b949e;font-size:14px;margin-bottom:20px;'>Aapka VCM IT Helpdesk portal mein registration successful ho gaya hai.</p>
                <div style='background:#23863618;border:1px solid #23863640;border-radius:8px;padding:16px;margin-bottom:16px;'>
                    <p style='color:#3fb950;font-weight:bold;margin:0 0 10px;'>✅ Aapki Details:</p>
                    <p style='color:#e6edf3;margin:4px 0;'>👤 Naam: ".htmlspecialchars($_POST['name'])."</p>
                    <p style='color:#e6edf3;margin:4px 0;'>🏢 Department: ".htmlspecialchars($_POST['department'])."</p>
                    <p style='color:#e6edf3;margin:4px 0;'>🪪 Employee ID: ".htmlspecialchars($_POST['emp_id'])."</p>
                    <p style='color:#e6edf3;margin:4px 0;'>📱 Phone: ".htmlspecialchars($_POST['phone'])."</p>
                </div>
                <p style='color:#8b949e;font-size:14px;margin-bottom:16px;'>Ab aap VCM IT Portal pe login karke IT complaints submit kar sakte hain.</p>
                <div style='text-align:center;margin-top:20px;'>
                    <a href='http://localhost/asset/ticket_login.php' style='background:#8b5cf6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px;display:inline-block;'>🎫 Portal Par Jao</a>
                </div>",
                '#8b5cf6'
            );
            sendMail($_POST['email'], "Welcome to VCM IT Portal! 🎉", $welcome_body);
        }

        $success = "Registration successful! A welcome email has been sent. Please login.";
        $tab = 'login';
    }
}

// EMPLOYEE LOGIN
if(isset($_POST['emp_login'])){
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $password = MD5($_POST['password']);

    $result = mysqli_query($conn, "SELECT * FROM emp_users WHERE phone='$phone' AND password='$password'");
    if(mysqli_num_rows($result) == 1){
        $emp = mysqli_fetch_assoc($result);
        $_SESSION['emp_id']    = $emp['id'];
        $_SESSION['emp_name']  = $emp['name'];
        $_SESSION['emp_dept']  = $emp['department'];
        $_SESSION['emp_phone'] = $emp['phone'];
        header("Location: tickets.php"); exit;
    } else {
        $error = "Invalid phone number or password!";
        $tab = 'login';
    }
}

// ADMIN LOGIN
if(isset($_POST['admin_login'])){
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = MD5($_POST['password']);

    $result = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' AND password='$password'");
    if(mysqli_num_rows($result) == 1){
        $row = mysqli_fetch_assoc($result);
        if(in_array($row['role'], ['super_admin','admin','staff'])){
            $_SESSION['user_id']  = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['name']     = $row['name'];
            $_SESSION['role']     = $row['role'];
            header("Location: admin_tickets.php"); exit;
        } else {
            $error = "You do not have admin access!";
            $tab = 'admin';
        }
    } else {
        $error = "Invalid username or password!";
        $tab = 'admin';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IT Helpdesk — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0d1117;--surface:#161b22;--surface2:#21262d;
    --border:#30363d;--purple:#8b5cf6;--green:#238636;
    --accent:#1f6feb;--warn:#d29922;
    --danger:#da3633;--text:#e6edf3;--muted:#8b949e;--radius:10px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{
    font-family:'Space Grotesk',sans-serif;
    background:var(--bg);color:var(--text);min-height:100vh;
    display:grid;place-items:center;padding:20px;
    background-image:
        radial-gradient(ellipse at 25% 25%, #8b5cf60a 0%, transparent 50%),
        radial-gradient(ellipse at 75% 75%, #1f6feb08 0%, transparent 50%);
}
.box{
    background:var(--surface);border:1px solid var(--border);
    border-radius:16px;width:100%;max-width:460px;
    box-shadow:0 24px 64px #0009;
    animation:fadeUp .5s ease;overflow:hidden;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

/* HEADER */
.box-header{padding:28px 32px 0;text-align:center;}
.logo{
    width:60px;height:60px;
    background:linear-gradient(135deg,var(--purple),#6366f1);
    border-radius:16px;display:inline-grid;place-items:center;
    font-size:28px;margin-bottom:14px;
    box-shadow:0 8px 24px #8b5cf633;
}
.box-header h1{font-size:20px;font-weight:700;margin-bottom:4px;}
.box-header p{font-size:13px;color:var(--muted);margin-bottom:24px;}

/* TABS */
.tabs{display:grid;grid-template-columns:1fr 1fr 1fr;border-bottom:1px solid var(--border);}
.tab-btn{
    padding:12px 8px;text-align:center;font-size:13px;font-weight:600;
    cursor:pointer;border:none;background:transparent;color:var(--muted);
    transition:all .2s;border-bottom:2px solid transparent;
    font-family:'Space Grotesk',sans-serif;white-space:nowrap;
}
.tab-btn.active.emp  {color:var(--purple);border-bottom-color:var(--purple);}
.tab-btn.active.admin{color:var(--warn);border-bottom-color:var(--warn);}
.tab-btn.active.reg  {color:var(--green);border-bottom-color:var(--green);}
.tab-btn:hover:not(.active){color:var(--text);}

/* FORM CONTENT */
.tab-content{display:none;padding:24px 32px 32px;}
.tab-content.active{display:block;animation:fadeIn .3s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ROLE BANNER */
.role-banner{
    border-radius:8px;padding:10px 14px;
    font-size:13px;font-weight:500;margin-bottom:18px;
    display:flex;align-items:center;gap:8px;
}
.role-banner.emp  {background:#8b5cf618;border:1px solid #8b5cf640;color:#c4b5fd;}
.role-banner.admin{background:#d2992218;border:1px solid #d2992240;color:#d29922;}
.role-banner.reg  {background:#23863618;border:1px solid #23863640;color:#3fb950;}

/* ALERT */
.alert{
    padding:11px 14px;border-radius:7px;font-size:13px;
    font-weight:500;margin-bottom:16px;display:flex;align-items:center;gap:8px;
}
.alert.error  {background:#da363318;border:1px solid #da363340;color:#ff7b72;animation:shake .4s ease;}
.alert.success{background:#23863618;border:1px solid #23863640;color:#3fb950;}
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}40%{transform:translateX(6px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}

/* FIELDS */
.field{margin-bottom:14px;}
.field label{display:block;font-size:12px;font-weight:500;color:var(--muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;}
.field input,
.field select{
    width:100%;padding:11px 14px;
    background:var(--surface2);border:1px solid var(--border);
    border-radius:8px;color:var(--text);
    font-family:'Space Grotesk',sans-serif;font-size:14px;
    transition:border-color .2s,box-shadow .2s;
}
.field input:focus,.field select:focus{outline:none;}
.field select option{background:var(--surface2);}

/* Focus colors per tab */
.emp-form   .field input:focus,
.emp-form   .field select:focus{border-color:var(--purple);box-shadow:0 0 0 3px #8b5cf622;}
.admin-form .field input:focus{border-color:var(--warn);box-shadow:0 0 0 3px #d2992222;}
.reg-form   .field input:focus,
.reg-form   .field select:focus{border-color:var(--green);box-shadow:0 0 0 3px #23863622;}

/* BUTTONS */
.btn{width:100%;padding:13px;border:none;border-radius:8px;font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:600;cursor:pointer;transition:all .2s;margin-top:4px;}
.btn-purple{background:var(--purple);color:#fff;}
.btn-purple:hover{background:#7c3aed;transform:translateY(-1px);box-shadow:0 8px 24px #8b5cf644;}
.btn-warn{background:var(--warn);color:#000;}
.btn-warn:hover{background:#e0a820;transform:translateY(-1px);box-shadow:0 8px 24px #d2992244;}
.btn-green{background:var(--green);color:#fff;}
.btn-green:hover{background:#2ea043;transform:translateY(-1px);box-shadow:0 8px 24px #23863644;}
.btn-ghost{background:var(--surface2);border:1px solid var(--border);color:var(--text);}
.btn-ghost:hover{border-color:var(--muted);}

.divider{text-align:center;font-size:12px;color:var(--muted);margin:14px 0;position:relative;}
.divider::before,.divider::after{content:'';position:absolute;top:50%;width:44%;height:1px;background:var(--border);}
.divider::before{left:0;}.divider::after{right:0;}

.back-link{display:block;text-align:center;margin-top:14px;font-size:13px;color:var(--muted);text-decoration:none;transition:color .2s;}
.back-link:hover{color:var(--text);}
</style>
</head>
<body>
<div class="box">

    <!-- HEADER -->
    <div class="box-header">
        <div class="logo">🎫</div>
        <h1>IT Helpdesk</h1>
        <p>Login or create a new account</p>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <button class="tab-btn emp  <?php echo $tab=='login' ?'active':''; ?>" onclick="switchTab('login')">👤 Employee</button>
        <button class="tab-btn admin <?php echo $tab=='admin' ?'active':''; ?>" onclick="switchTab('admin')">🛡 IT Admin</button>
        <button class="tab-btn reg  <?php echo $tab=='register'?'active':''; ?>" onclick="switchTab('register')">✨ Register</button>
    </div>

    <!-- EMPLOYEE LOGIN -->
    <div class="tab-content emp-form <?php echo $tab=='login'?'active':''; ?>" id="tab-login">
        <div class="role-banner emp">👤 Employee Login — Submit tickets or track their status</div>
        <?php if($error && $tab=='login'): ?>
        <div class="alert error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if($success): ?>
        <div class="alert success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="field">
                <label>📱 Phone Number</label>
                <input type="tel" name="phone" placeholder="e.g. 9876543210" required maxlength="10" autofocus>
            </div>
            <div class="field">
                <label>🔒 Password</label>
                <input type="password" name="password" placeholder="Your password" required>
            </div>
            <button type="submit" name="emp_login" class="btn btn-purple">→ Login</button>
        </form>
        <div class="divider">ya</div>
        <button class="btn btn-ghost" onclick="switchTab('register')">✨ Create New Account</button>
        <a href="home.php" class="back-link">← Back to Home</a>
    </div>

    <!-- ADMIN LOGIN -->
    <div class="tab-content admin-form <?php echo $tab=='admin'?'active':''; ?>" id="tab-admin">
        <div class="role-banner admin">🛡 IT Admin — Manage and resolve tickets</div>
        <?php if($error && $tab=='admin'): ?>
        <div class="alert error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="field">
                <label>👤 Username</label>
                <input type="text" name="username" placeholder="Admin username" required>
            </div>
            <div class="field">
                <label>🔒 Password</label>
                <input type="password" name="password" placeholder="Admin password" required>
            </div>
            <button type="submit" name="admin_login" class="btn btn-warn">🛡 Admin Login</button>
        </form>
        <a href="home.php" class="back-link">← Back to Home</a>
    </div>

    <!-- REGISTER -->
    <div class="tab-content reg-form <?php echo $tab=='register'?'active':''; ?>" id="tab-register">
        <div class="role-banner reg">✨ First time? Create your account</div>
        <?php if($error && $tab=='register'): ?>
        <div class="alert error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="field">
                <label>👤 Full Name</label>
                <input type="text" name="name" placeholder="e.g. Rahul Sharma" required>
            </div>
            <div class="field">
                <label>📱 Phone Number</label>
                <input type="tel" name="phone" placeholder="e.g. 9876543210" required maxlength="10">
            </div>
            <div class="field">
                <label>🏢 Department</label>
                <select name="department" required>
                    <option value="">Select Department</option>
                    <?php foreach(['IT','HR','Finance','Admin','Operations','Marketing','Sales'] as $d): ?>
                    <option><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>📧 Email ID</label>
                <input type="email" name="email" placeholder="name@company.com" required>
            </div>
            <div class="field">
                <label>🪪 Employee ID</label>
                <input type="text" name="emp_id" placeholder="e.g. EMP-101" required>
            </div>
            <div class="field">
                <label>🔒 Password Banao</label>
                <input type="password" name="password" placeholder="Minimum 4 characters" required minlength="4">
            </div>
            <button type="submit" name="register" class="btn btn-green">✨ Register</button>
        </form>
        <div class="divider">ya</div>
        <button class="btn btn-ghost" onclick="switchTab('login')">🔑 Login</button>
        <a href="home.php" class="back-link">← Back to Home</a>
    </div>

</div>

<script>
function switchTab(tab){
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-'+tab).classList.add('active');
    const map = {login:0, admin:1, register:2};
    document.querySelectorAll('.tab-btn')[map[tab]].classList.add('active');
}
</script>
<!-- HELPLINE BAR -->
<div style="position:fixed;bottom:0;left:0;right:0;background:var(--surface);border-top:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;justify-content:center;gap:24px;flex-wrap:wrap;z-index:100;">
    <span style="font-size:12px;color:var(--muted);">Need help?</span>
    <a href="tel:7037090999" style="display:flex;align-items:center;gap:6px;color:#3fb950;text-decoration:none;font-size:13px;font-weight:600;">
        📞 +91 70370 90999
    </a>
    <span style="color:var(--border);">|</span>
    <a href="mailto:ithelpdesk@vcm.org.in" style="display:flex;align-items:center;gap:6px;color:#79c0ff;text-decoration:none;font-size:13px;font-weight:600;">
        ✉️ ithelpdesk@vcm.org.in
    </a>
</div>
<div style="height:50px;"></div>
</body>
</html>
