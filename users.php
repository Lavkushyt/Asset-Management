<?php
include 'auth.php';
include 'db.php';
checkRole(['super_admin']); // Sirf super admin access kar sakta hai

$toast = '';

// ADD USER
if(isset($_POST['add_user'])){
    $uname  = mysqli_real_escape_string($conn, trim($_POST['username']));
    $name   = mysqli_real_escape_string($conn, trim($_POST['name']));
    $role   = mysqli_real_escape_string($conn, $_POST['role']);
    $pass   = MD5($_POST['password']);

    $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$uname'");
    if(mysqli_num_rows($check) > 0){
        $toast = "error:Username '$uname' already exists!";
    } else {
        $uemail = mysqli_real_escape_string($conn, trim($_POST['email']));
        mysqli_query($conn, "INSERT INTO users (username, password, name, role, email) VALUES ('$uname','$pass','$name','$role','$uemail')");
        header("Location: users.php?toast=added"); exit;
    }
}

// DELETE USER
if(isset($_GET['delete'])){
    $did = intval($_GET['delete']);
    if($did != $_SESSION['user_id']){
        mysqli_query($conn, "DELETE FROM users WHERE id=$did");
        header("Location: users.php?toast=deleted"); exit;
    } else {
        $toast = "error:You cannot delete your own account!";
    }
}

// RESET PASSWORD
if(isset($_POST['reset_pass'])){
    $rid      = intval($_POST['user_id']);
    $newpass  = MD5($_POST['new_password']);
    mysqli_query($conn, "UPDATE users SET password='$newpass' WHERE id=$rid");
    header("Location: users.php?toast=reset"); exit;
}

// CHANGE ROLE
if(isset($_POST['change_role'])){
    $cid  = intval($_POST['user_id']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    if($cid != $_SESSION['user_id']){
        mysqli_query($conn, "UPDATE users SET role='$role' WHERE id=$cid");
        header("Location: users.php?toast=role"); exit;
    } else {
        $toast = "error:You cannot change your own role!";
    }
}

$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id ASC");
$total = mysqli_num_rows($users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management — IT Asset Manager</title>
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

/* NAV */
nav{
    background:var(--surface);border-bottom:1px solid var(--border);
    padding:0 24px;display:flex;align-items:center;
    justify-content:space-between;height:60px;
    position:sticky;top:0;z-index:100;
}
.nav-brand{display:flex;align-items:center;gap:10px;}
.nav-logo{width:32px;height:32px;background:var(--purple);border-radius:8px;display:grid;place-items:center;font-size:16px;}
.nav-brand span{font-weight:700;font-size:15px;}
.nav-back{
    display:inline-flex;align-items:center;gap:6px;color:var(--muted);
    text-decoration:none;font-size:13px;border:1px solid var(--border);
    padding:6px 14px;border-radius:7px;transition:all .2s;
}
.nav-back:hover{color:var(--text);border-color:var(--text);}

/* PAGE */
.page{max-width:1100px;margin:0 auto;padding:24px 20px;}

/* TOAST */
.toast-wrap{position:fixed;top:70px;right:20px;z-index:999;display:flex;flex-direction:column;gap:8px;}
.toast{
    background:var(--surface);border:1px solid var(--border);
    padding:13px 20px;border-radius:var(--radius);font-size:14px;
    min-width:250px;box-shadow:0 8px 32px #0007;
    animation:slideIn .3s ease, fadeOut .4s ease 3.6s forwards;
    border-left:4px solid var(--green);
}
.toast.error{border-left-color:var(--danger);}
.toast.warn {border-left-color:var(--warn);}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes fadeOut{to{opacity:0;transform:translateX(120%)}}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;}
.stat-card{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);padding:20px;
    animation:fadeUp .4s ease both;transition:transform .2s;
}
.stat-card:hover{transform:translateY(-3px);}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.stat-num{font-size:32px;font-weight:700;font-family:var(--mono);}
.stat-label{font-size:12px;color:var(--muted);margin-top:4px;}

/* CARD */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:20px;overflow:hidden;}
.card-header{
    padding:16px 20px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    cursor:pointer;user-select:none;
}
.card-header h3{font-size:15px;font-weight:600;display:flex;align-items:center;gap:8px;}
.collapse-icon{transition:transform .3s;color:var(--muted);}
.collapsed .collapse-icon{transform:rotate(-90deg);}
.collapsible-body{overflow:hidden;transition:max-height .4s ease,opacity .3s ease;max-height:1000px;opacity:1;}
.collapsed .collapsible-body{max-height:0;opacity:0;}
.card-body{padding:20px;}

/* FORM */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group label{font-size:12px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}
.form-group input,
.form-group select{
    background:var(--surface2);border:1px solid var(--border);
    color:var(--text);padding:10px 14px;border-radius:7px;
    font-family:var(--sans);font-size:14px;transition:border-color .2s;
}
.form-group input:focus,
.form-group select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px #1f6feb22;}
.form-group select option{background:var(--surface2);}
.btn{
    padding:10px 20px;border:none;border-radius:7px;
    font-family:var(--sans);font-size:13px;font-weight:600;
    cursor:pointer;transition:all .2s;display:inline-flex;
    align-items:center;gap:6px;text-decoration:none;
}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{background:#388bfd;transform:translateY(-1px);}
.btn-success{background:var(--green);color:#fff;}
.btn-success:hover{background:#2ea043;transform:translateY(-1px);}
.btn-full{width:100%;justify-content:center;padding:12px;margin-top:8px;font-size:14px;}

/* ALERT */
.alert{padding:12px 16px;border-radius:7px;margin-bottom:16px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:8px;}
.alert.error{background:#da363318;border:1px solid #da363340;color:#ff7b72;}

/* TABLE */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{
    background:var(--surface2);color:var(--muted);padding:11px 16px;
    text-align:left;font-size:11px;text-transform:uppercase;
    letter-spacing:.6px;border-bottom:1px solid var(--border);font-weight:600;
}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s;animation:rowIn .3s ease both;}
@keyframes rowIn{from{opacity:0;transform:translateX(-6px)}to{opacity:1;transform:translateX(0)}}
tbody tr:hover{background:var(--surface2);}
tbody td{padding:12px 16px;vertical-align:middle;}

/* BADGES */
.role-badge{
    display:inline-flex;align-items:center;padding:3px 10px;
    border-radius:20px;font-size:12px;font-weight:600;font-family:var(--mono);
}
.role-badge.super_admin{background:#d2992222;border:1px solid #d2992255;color:var(--warn);}
.role-badge.admin{background:#1f6feb22;border:1px solid #1f6feb55;color:var(--accent);}
.role-badge.user{background:#8b949e22;border:1px solid #8b949e55;color:var(--muted);}
.you-badge{
    background:#8b5cf622;border:1px solid #8b5cf655;color:#a78bfa;
    font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;margin-left:6px;
}

/* INLINE FORMS */
.inline-form{display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
.inline-form input,
.inline-form select{
    background:var(--surface2);border:1px solid var(--border);
    color:var(--text);padding:5px 10px;border-radius:6px;
    font-family:var(--sans);font-size:12px;transition:border-color .2s;
}
.inline-form input:focus,
.inline-form select:focus{outline:none;border-color:var(--accent);}
.act-btn{
    padding:5px 10px;border-radius:5px;font-size:11px;font-weight:600;
    text-decoration:none;transition:all .15s;border:1px solid transparent;
    cursor:pointer;background:transparent;font-family:var(--sans);white-space:nowrap;
}
.act-btn.primary{color:var(--accent);border-color:var(--accent);}
.act-btn.primary:hover{background:var(--accent);color:#fff;}
.act-btn.success{color:var(--green);border-color:var(--green);}
.act-btn.success:hover{background:var(--green);color:#fff;}
.act-btn.danger{color:var(--danger);border-color:var(--danger);}
.act-btn.danger:hover{background:var(--danger);color:#fff;}
.act-btn.warn{color:var(--warn);border-color:var(--warn);}
.act-btn.warn:hover{background:var(--warn);color:#000;}

/* MODAL */
.modal-overlay{
    display:none;position:fixed;inset:0;background:#000a;
    z-index:200;align-items:center;justify-content:center;
}
.modal-overlay.active{display:flex;}
.modal{
    background:var(--surface);border:1px solid var(--border);
    border-radius:12px;padding:28px;width:100%;max-width:400px;
    box-shadow:0 24px 64px #0009;animation:fadeUp .3s ease;
}
.modal h3{font-size:16px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
.modal-close{
    position:absolute;top:16px;right:16px;
    background:transparent;border:none;color:var(--muted);
    font-size:20px;cursor:pointer;
}
.modal-actions{display:flex;gap:8px;margin-top:16px;justify-content:flex-end;}

@media(max-width:768px){.form-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<nav>
    <div class="nav-brand">
        <div class="nav-logo">👥</div>
        <span>User Management</span>
    </div>
    <a href="index.php" class="nav-back">← Dashboard</a>
</nav>

<!-- TOAST -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-overlay" id="resetModal">
    <div class="modal" style="position:relative;">
        <button class="modal-close" onclick="closeModal('resetModal')">✕</button>
        <h3>🔑 Reset Password</h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="reset_user_id">
            <div class="form-group" style="margin-bottom:14px;">
                <label>New Password</label>
                <input type="password" name="new_password" id="new_password_input"
                       placeholder="Enter new password..." required minlength="4">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal('resetModal')" class="btn" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);">Cancel</button>
                <button type="submit" name="reset_pass" class="btn btn-primary">🔑 Reset</button>
            </div>
        </form>
    </div>
</div>

<div class="page">

<?php
// Alert from $toast variable
if($toast):
    $is_error = str_starts_with($toast, 'error:');
    $clean = $is_error ? substr($toast, 6) : $toast;
?>
<div class="alert error">⚠ <?php echo htmlspecialchars($clean); ?></div>
<?php endif; ?>

<!-- STATS -->
<?php
$total_super = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM users WHERE role='super_admin'"));
$total_admin = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM users WHERE role='admin'"));
$total_user  = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM users WHERE role='user'"));
?>
<div class="stats">
    <div class="stat-card" style="border-top:3px solid var(--accent);">
        <div style="font-size:24px;margin-bottom:8px;">👥</div>
        <div class="stat-num" style="color:var(--accent);"><?php echo $total; ?></div>
        <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--warn);">
        <div style="font-size:24px;margin-bottom:8px;">👑</div>
        <div class="stat-num" style="color:var(--warn);"><?php echo $total_super; ?></div>
        <div class="stat-label">Super Admins</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--accent);">
        <div style="font-size:24px;margin-bottom:8px;">🛡</div>
        <div class="stat-num" style="color:var(--accent);"><?php echo $total_admin; ?></div>
        <div class="stat-label">Admins</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--muted);">
        <div style="font-size:24px;margin-bottom:8px;">👤</div>
        <div class="stat-num" style="color:var(--muted);"><?php echo $total_user; ?></div>
        <div class="stat-label">Users</div>
    </div>
</div>

<!-- ADD USER FORM -->
<div class="card" id="addCard">
    <div class="card-header" onclick="toggleCard('addCard')">
        <h3>➕ Create New User</h3>
        <span class="collapse-icon">▾</span>
    </div>
    <div class="collapsible-body">
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" placeholder="e.g. Rahul Sharma" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" placeholder="e.g. rahul123" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Email ID *</label>
                    <input type="email" name="email" placeholder="name@company.com" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" placeholder="Minimum 4 characters" required minlength="4" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="super_admin">👑 Super Admin</option>
                        <option value="admin">🛡 Admin</option>
                        <option value="staff">👨‍💻 IT Staff</option>
                        <option value="user">👤 User</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_user" class="btn btn-success btn-full">➕ Create User</button>
        </form>
    </div>
    </div>
</div>

<!-- USERS TABLE -->
<div class="card">
    <div class="card-header" style="cursor:default;">
        <h3>👥 All Users <span style="background:var(--surface2);border:1px solid var(--border);color:var(--muted);font-size:12px;padding:2px 10px;border-radius:20px;font-family:var(--mono);font-weight:400;"><?php echo $total; ?></span></h3>
    </div>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Role Change</th>
                <th>Password Reset</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
        <?php
        mysqli_data_seek($users, 0);
        $i = 1;
        while($u = mysqli_fetch_assoc($users)):
            $is_me = $u['id'] == $_SESSION['user_id'];
        ?>
        <tr style="animation-delay:<?php echo $i * 0.05; ?>s">
            <td style="color:var(--muted);font-family:var(--mono);font-size:12px;"><?php echo $i++; ?></td>
            <td>
                <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                <?php if($is_me): ?><span class="you-badge">You</span><?php endif; ?>
            </td>
            <td style="font-family:var(--mono);font-size:13px;color:var(--accent);">
                @<?php echo htmlspecialchars($u['username']); ?>
            </td>
            <td>
                <span class="role-badge <?php echo $u['role']; ?>">
                    <?php
                    $icons = ['super_admin'=>'👑','admin'=>'🛡','staff'=>'👨‍💻','user'=>'👤'];
                    echo ($icons[$u['role']] ?? '👤').' '.htmlspecialchars($u['role']);
                    ?>
                </span>
            </td>
            <td>
                <?php if(!$is_me): ?>
                <form method="POST" class="inline-form">
                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                    <select name="role">
                        <option value="super_admin" <?php echo $u['role']=='super_admin'?'selected':''; ?>>👑 Super Admin</option>
                        <option value="admin"       <?php echo $u['role']=='admin'      ?'selected':''; ?>>🛡 Admin</option>
                        <option value="staff"       <?php echo $u['role']=='staff'      ?'selected':''; ?>>👨‍💻 IT Staff</option>
                        <option value="user"        <?php echo $u['role']=='user'       ?'selected':''; ?>>👤 User</option>
                    </select>
                    <button type="submit" name="change_role" class="act-btn primary">Update</button>
                </form>
                <?php else: ?>
                <span style="color:var(--muted);font-size:12px;">—</span>
                <?php endif; ?>
            </td>
            <td>
                <button class="act-btn warn"
                    onclick="openResetModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')">
                    🔑 Reset
                </button>
            </td>
            <td>
                <?php if(!$is_me): ?>
                <a href="users.php?delete=<?php echo $u['id']; ?>"
                   class="act-btn danger"
                   onclick="return confirm('<?php echo htmlspecialchars($u['name']); ?> — confirm delete?')">
                   🗑 Delete
                </a>
                <?php else: ?>
                <span style="color:var(--muted);font-size:12px;">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

</div><!-- /page -->

<script>
// Toast system
const toastMessages = {
    'added':   '✅ New user created successfully!',
    'deleted': '🗑 User deleted successfully.',
    'reset':   '🔑 Password reset successfully!',
    'role':    '🛡 Role updated successfully!',
};
const urlParams = new URLSearchParams(location.search);
const toastKey  = urlParams.get('toast');
if(toastKey && toastMessages[toastKey]){
    showToast(toastMessages[toastKey], toastKey === 'deleted' ? 'error' : 'success');
    history.replaceState({}, '', location.pathname);
}
function showToast(message, type='success'){
    const wrap = document.getElementById('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast ' + (type === 'error' ? 'error' : '');
    t.textContent = message;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4200);
}

// Collapsible
function toggleCard(id){
    document.getElementById(id).classList.toggle('collapsed');
}

// Reset Modal
function openResetModal(userId, username){
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('new_password_input').value = '';
    document.getElementById('resetModal').classList.add('active');
}
function closeModal(id){
    document.getElementById(id).classList.remove('active');
}
// Close on overlay click
document.getElementById('resetModal').addEventListener('click', function(e){
    if(e.target === this) closeModal('resetModal');
});
</script>
</body>
</html>
