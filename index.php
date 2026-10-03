<?php
include 'auth.php';
include 'db.php';

// DELETE
if(isset($_GET['delete']) && $_SESSION['role'] == 'super_admin'){
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM assets WHERE id=$id");
    header("Location: index.php?toast=deleted");
    exit;
}

$msg = "";
$toast = $_GET['toast'] ?? '';
$search = $_GET['search'] ?? '';

// RETURN
if(isset($_GET['return'])){
    $id = intval($_GET['return']);
    $asset = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM assets WHERE id=$id"));
    if($asset){
        $asset_Tag = mysqli_real_escape_string($conn, $asset['asset_Tag']);
        $employee  = mysqli_real_escape_string($conn, $asset['employee']);
        $return_date = date('Y-m-d H:i:s');
        mysqli_query($conn, "UPDATE assets SET status='Returned', return_date='$return_date', employee='' WHERE id=$id");
        mysqli_query($conn, "INSERT INTO asset_history (asset_tag, employee, action, action_date) VALUES ('$asset_Tag','$employee','Returned','$return_date')");
    }
    header("Location: index.php?toast=returned");
    exit;
}

// RE-ISSUE
if(isset($_POST['reissue_submit'])){
    $issue_tag    = mysqli_real_escape_string($conn, $_POST['issue_tag']);
    $new_employee = mysqli_real_escape_string($conn, $_POST['new_employee']);
    $new_issue_date = date('Y-m-d');
    mysqli_query($conn, "UPDATE assets SET employee='$new_employee', issue_date='$new_issue_date', status='Issued', return_date=NULL WHERE asset_Tag='$issue_tag'");
    mysqli_query($conn, "INSERT INTO asset_history (asset_tag, employee, action, action_date) VALUES ('$issue_tag','$new_employee','Issued','$new_issue_date')");
    header("Location: index.php?toast=reissued");
    exit;
}

// ADD / UPDATE
if(isset($_POST['add'])){
    $asset_Tag    = mysqli_real_escape_string($conn, $_POST['asset_Tag']);
    $type         = mysqli_real_escape_string($conn, $_POST['type']);
    $model        = mysqli_real_escape_string($conn, $_POST['model']);
    $Config       = mysqli_real_escape_string($conn, $_POST['Config']);
    $serial_no    = mysqli_real_escape_string($conn, $_POST['serial']);
    $employee     = mysqli_real_escape_string($conn, $_POST['emp']);
    $issue_date   = date('Y-m-d H:i:s');
    $purchase_date= empty($_POST['purchase_date']) ? 'NULL' : "'".mysqli_real_escape_string($conn,$_POST['purchase_date'])."'";
    $department   = mysqli_real_escape_string($conn, $_POST['department']);
    $location     = mysqli_real_escape_string($conn, $_POST['location']);
    $phone        = mysqli_real_escape_string($conn, $_POST['phone']);
    $email        = mysqli_real_escape_string($conn, $_POST['email']);
    $vendor       = mysqli_real_escape_string($conn, $_POST['vendor']);
    $item_price   = empty($_POST['item_price']) ? 'NULL' : floatval($_POST['item_price']);

    $check = mysqli_query($conn,"SELECT * FROM assets WHERE asset_Tag='$asset_Tag'");
    if(mysqli_num_rows($check) > 0){
        $old = mysqli_fetch_assoc($check);
        if($old['status'] == 'Returned'){
            mysqli_query($conn,"UPDATE assets SET type='$type',model='$model',Config='$Config',serial_no='$serial_no',employee='$employee',issue_date='$issue_date',purchase_date=$purchase_date,department='$department',location='$location',phone='$phone',email='$email',vendor='$vendor',item_price=$item_price,status='Issued' WHERE asset_Tag='$asset_Tag'");
            header("Location: index.php?toast=added"); exit;
        } else {
            $msg = "error:Asset '{$old['asset_Tag']}' is currently Issued to {$old['employee']}!";
        }
    } else {
        $sql = "INSERT INTO assets (asset_Tag,type,model,Config,serial_no,employee,issue_date,status,purchase_date,department,location,phone,email,vendor,item_price) VALUES ('$asset_Tag','$type','$model','$Config','$serial_no','$employee','$issue_date','Issued',$purchase_date,'$department','$location','$phone','$email','$vendor',$item_price)";
        if(mysqli_query($conn,$sql)){
            mysqli_query($conn,"INSERT INTO asset_history (asset_tag,employee,action,action_date) VALUES ('$asset_Tag','$employee','Issued','$issue_date')");
            header("Location: index.php?toast=added"); exit;
        } else {
            $msg = "error:DB Error: ".mysqli_error($conn);
        }
    }
}

if(isset($_POST['update'])){
    $uid           = intval($_POST['uid']);
    $asset_Tag     = mysqli_real_escape_string($conn, $_POST['asset_Tag']);
    $type          = mysqli_real_escape_string($conn, $_POST['type']);
    $model         = mysqli_real_escape_string($conn, $_POST['model']);
    $Config        = mysqli_real_escape_string($conn, $_POST['Config']);
    $serial        = mysqli_real_escape_string($conn, $_POST['serial']);
    $emp           = mysqli_real_escape_string($conn, $_POST['emp']);
    $department    = mysqli_real_escape_string($conn, $_POST['department']);
    $location      = mysqli_real_escape_string($conn, $_POST['location']);
    $phone         = mysqli_real_escape_string($conn, $_POST['phone']);
    $email         = mysqli_real_escape_string($conn, $_POST['email']);
    $vendor        = mysqli_real_escape_string($conn, $_POST['vendor']);
    $item_price    = empty($_POST['item_price']) ? 'NULL' : floatval($_POST['item_price']);
    $purchase_date = empty($_POST['purchase_date']) ? 'NULL' : "'" . mysqli_real_escape_string($conn, $_POST['purchase_date']) . "'";

    mysqli_query($conn, "UPDATE assets SET
        asset_Tag='$asset_Tag',
        type='$type',
        model='$model',
        Config='$Config',
        serial_no='$serial',
        employee='$emp',
        department='$department',
        location='$location',
        phone='$phone',
        email='$email',
        vendor='$vendor',
        item_price=$item_price,
        purchase_date=$purchase_date
        WHERE id=$uid");
    header("Location: index.php?toast=updated"); exit;
}

// Stats
$total     = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM assets"));
$issued    = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM assets WHERE status='Issued'"));
$available = mysqli_num_rows(mysqli_query($conn,"SELECT id FROM assets WHERE status='Returned' OR status='Available'"));
$depts     = mysqli_num_rows(mysqli_query($conn,"SELECT DISTINCT department FROM assets WHERE department!=''"));

// Har type ka dynamic card data
$type_result = mysqli_query($conn,"SELECT type, COUNT(*) as total, SUM(CASE WHEN status='Issued' THEN 1 ELSE 0 END) as issued FROM assets WHERE type != '' GROUP BY type ORDER BY total DESC");

// Edit data
$edit_data = [];
if(isset($_GET['edit'])){
    $eid = intval($_GET['edit']);
    $edit_data = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM assets WHERE id=$eid")) ?? [];
}

// Assets list
if($search != ''){
    $s = mysqli_real_escape_string($conn, $search);
    $result = mysqli_query($conn,"SELECT * FROM assets WHERE asset_Tag LIKE '%$s%' OR employee LIKE '%$s%' OR model LIKE '%$s%' OR type LIKE '%$s%' OR department LIKE '%$s%' ORDER BY id DESC");
} else {
    $result = mysqli_query($conn,"SELECT * FROM assets ORDER BY id DESC LIMIT 50");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IT Asset Manager — VCM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
    --bg:        #080c10;
    --surface:   #0d1117;
    --surface2:  #161b22;
    --surface3:  #1c2128;
    --border:    #21262d;
    --border2:   #30363d;
    --accent:    #238636;
    --accent2:   #1f6feb;
    --danger:    #da3633;
    --warn:      #d29922;
    --purple:    #8b5cf6;
    --text:      #cdd9e5;
    --text-muted:#768390;
    --radius:    12px;
    --radius-sm: 8px;
    --mono:      'JetBrains Mono', monospace;
    --sans:      'Space Grotesk', sans-serif;
    --shadow:    0 8px 32px rgba(0,0,0,0.4);
    --shadow-lg: 0 16px 48px rgba(0,0,0,0.6);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--sans);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}

/* NAV */
nav {
    background: rgba(13,17,23,0.95);
    border-bottom: 1px solid var(--border);
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
    position: sticky; top: 0; z-index: 100;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}
.nav-brand { display: flex; align-items: center; gap: 12px; }
.nav-brand .logo {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--accent2), var(--purple));
    border-radius: 10px; display: grid; place-items: center;
    font-size: 18px; box-shadow: 0 4px 12px rgba(31,111,235,0.3);
}
.nav-brand span { font-weight: 700; font-size: 16px; letter-spacing: -.4px; color: var(--text); }
.nav-right { display: flex; align-items: center; gap: 10px; font-size: 14px; }
.role-badge {
    background: var(--surface2); border: 1px solid var(--border2);
    padding: 4px 12px; border-radius: 20px; font-size: 11px;
    font-family: var(--mono); color: var(--text-muted);
    letter-spacing: .3px;
}
.role-badge.super { border-color: var(--warn); color: var(--warn); background: rgba(210,153,34,0.1); }
.role-badge.admin { border-color: var(--accent2); color: var(--accent2); background: rgba(31,111,235,0.1); }
.nav-user { font-weight: 600; color: var(--text); }
.nav-link {
    color: var(--danger); text-decoration: none; font-size: 13px;
    border: 1px solid rgba(218,54,51,0.4); padding: 6px 14px;
    border-radius: var(--radius-sm); transition: all .2s; font-weight: 500;
}
.nav-link:hover { background: var(--danger); color: #fff; border-color: var(--danger); }

/* LAYOUT */
.page { max-width: 1440px; margin: 0 auto; padding: 28px 24px; }

/* TOAST */
.toast-wrap {
    position: fixed; top: 70px; right: 20px; z-index: 999;
    display: flex; flex-direction: column; gap: 8px;
}
.toast {
    background: var(--surface); border: 1px solid var(--border);
    padding: 14px 20px; border-radius: var(--radius);
    font-size: 14px; min-width: 260px; box-shadow: 0 8px 32px #0007;
    display: flex; align-items: center; gap: 10px;
    animation: slideIn .3s ease, fadeOut .4s ease 3.6s forwards;
    border-left: 4px solid var(--accent);
}
.toast.error { border-left-color: var(--danger); }
.toast.warn  { border-left-color: var(--warn); }
@keyframes slideIn { from { transform: translateX(120%); opacity:0; } to { transform: translateX(0); opacity:1; } }
@keyframes fadeOut { to { opacity:0; transform: translateX(120%); } }

/* STATS GRID */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px; margin-bottom: 28px;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px;
    position: relative; overflow: hidden;
    cursor: default;
    transition: transform .25s, box-shadow .25s, border-color .25s;
    animation: fadeUp .5s ease both;
}
.stat-card:hover { 
    transform: translateY(-4px); 
    box-shadow: var(--shadow-lg);
    border-color: var(--border2);
}
a.stat-card {
    display: block;
    text-decoration: none;
    color: var(--text);
}
a.stat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 48px rgba(0,0,0,0.5);
}
a.stat-card .click-hint {
    font-size: 11px;
    opacity: 0;
    transition: opacity .2s;
    margin-top: 6px;
}
a.stat-card:hover .click-hint {
    opacity: 1;
}
.stat-card::before {
    content: ''; position: absolute; top:-40px; right:-40px;
    width: 120px; height: 120px; border-radius: 50%;
    opacity: .08;
}
.stat-card.blue::before  { background: var(--accent2); }
.stat-card.green::before { background: var(--accent); }
.stat-card.orange::before{ background: var(--warn); }
.stat-card.purple::before{ background: #8b5cf6; }
.stat-icon {
    width: 40px; height: 40px; border-radius: 8px;
    display: grid; place-items: center; font-size: 20px;
    margin-bottom: 14px;
}
.stat-icon.blue   { background: #1f6feb22; }
.stat-icon.green  { background: #23863622; }
.stat-icon.orange { background: #d2992222; }
.stat-icon.purple { background: #8b5cf622; }
.stat-num {
    font-size: 36px; font-weight: 700; line-height: 1;
    counter-reset: num;
    font-family: var(--mono);
}
.stat-num.blue   { color: var(--accent2); }
.stat-num.green  { color: var(--accent); }
.stat-num.orange { color: var(--warn); }
.stat-num.purple { color: #a78bfa; }
.stat-label { color: var(--text-muted); font-size: 13px; margin-top: 6px; }
@keyframes fadeUp {
    from { opacity:0; transform: translateY(20px); }
    to   { opacity:1; transform: translateY(0); }
}
.stat-card:nth-child(1){ animation-delay:.05s }
.stat-card:nth-child(2){ animation-delay:.1s  }
.stat-card:nth-child(3){ animation-delay:.15s }
.stat-card:nth-child(4){ animation-delay:.2s  }

/* SECTION CARD */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.2);
    transition: box-shadow .2s;
}
.card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    cursor: pointer; user-select: none;
}
.card-header h3 { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.card-body { padding: 20px; }
.collapse-icon { transition: transform .3s; color: var(--text-muted); }
.collapsed .collapse-icon { transform: rotate(-90deg); }
.collapsible-body { overflow: hidden; transition: max-height .4s ease, opacity .3s ease; max-height: 2000px; opacity:1; }
.collapsed .collapsible-body { max-height: 0; opacity:0; }

/* FORM */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 13px; font-weight: 500; color: var(--text-muted); }
.form-group input,
.form-group select {
    background: var(--surface2); border: 1px solid var(--border);
    color: var(--text); padding: 10px 14px; border-radius: 7px;
    font-family: var(--sans); font-size: 14px;
    transition: border-color .2s, box-shadow .2s;
    width: 100%;
}
.form-group input:focus,
.form-group select:focus {
    outline: none; border-color: var(--accent2);
    box-shadow: 0 0 0 3px #1f6feb22;
}
.form-group select option { background: var(--surface2); }
.btn {
    padding: 11px 20px; border: none; border-radius: 7px;
    font-family: var(--sans); font-size: 14px; font-weight: 600;
    cursor: pointer; transition: all .2s; display: inline-flex;
    align-items: center; gap: 6px;
}
.btn-primary { background: var(--accent2); color: #fff; }
.btn-primary:hover { background: #388bfd; transform: translateY(-1px); box-shadow: 0 6px 20px #1f6feb44; }
.btn-success { background: var(--accent); color: #fff; }
.btn-success:hover { background: #2ea043; transform: translateY(-1px); }
.btn-danger  { background: var(--danger); color: #fff; }
.btn-full { width: 100%; justify-content: center; padding: 13px; margin-top: 8px; font-size: 15px; }

/* SEARCH */
.search-row {
    display: flex; gap: 10px; margin-bottom: 16px;
    align-items: center;
}
.search-row input {
    flex: 1; background: var(--surface2); border: 1px solid var(--border);
    color: var(--text); padding: 10px 16px 10px 40px; border-radius: 7px;
    font-family: var(--sans); font-size: 14px; transition: border-color .2s;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%238b949e' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.44 1.407a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: 14px center;
}
.search-row input:focus { outline: none; border-color: var(--accent2); }
.search-row .btn { white-space: nowrap; }

/* TABLE */
.table-wrap { 
    overflow-x: auto;
    overflow-y: auto;
    max-height: 500px;
    border-radius: 0 0 var(--radius) var(--radius);
}
.table-wrap thead th {
    position: sticky;
    top: 0;
    z-index: 10;
}
table {
    width: 100%; border-collapse: collapse; font-size: 13.5px;
}
thead th {
    background: var(--surface2); color: var(--text-muted);
    padding: 11px 14px; text-align: left; font-weight: 600;
    font-size: 12px; text-transform: uppercase; letter-spacing: .5px;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
    animation: rowIn .3s ease both;
}
@keyframes rowIn {
    from { opacity:0; transform: translateX(-8px); }
    to   { opacity:1; transform: translateX(0); }
}
tbody tr:hover { background: var(--surface2); }
tbody td {
    padding: 12px 16px;
    vertical-align: middle;
    color: var(--text);
    font-size: 13.5px;
    border-bottom: 1px solid var(--border);
}
.tag {
    font-family: var(--mono); font-size: 12px;
    background: var(--surface2); border: 1px solid var(--border);
    padding: 3px 8px; border-radius: 5px; color: var(--accent2);
}
.badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;
}
.badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
.badge.issued  { background: #23863618; color: #3fb950; border: 1px solid #23863640; }
.badge.issued::before  { background: #3fb950; box-shadow: 0 0 0 3px #3fb95033; animation: pulse 2s infinite; }
.badge.returned{ background: #8b949e18; color: #8b949e; border: 1px solid #8b949e40; }
.badge.returned::before{ background: #8b949e; }
@keyframes pulse { 0%,100%{ box-shadow: 0 0 0 3px #3fb95033; } 50%{ box-shadow: 0 0 0 6px #3fb95011; } }

.action-links { display: flex; gap: 6px; flex-wrap: wrap; }
.act-btn {
    padding: 4px 10px; border-radius: 5px; font-size: 12px; font-weight: 600;
    text-decoration: none; transition: all .15s; border: 1px solid transparent;
    cursor: pointer; background: transparent; font-family: var(--sans);
}
.act-btn.edit    { color: var(--accent2); border-color: var(--accent2); }
.act-btn.edit:hover{ background: var(--accent2); color: #fff; }
.act-btn.del     { color: var(--danger); border-color: var(--danger); }
.act-btn.del:hover{ background: var(--danger); color: #fff; }
.act-btn.ret     { color: var(--warn); border-color: var(--warn); }
.act-btn.ret:hover{ background: var(--warn); color: #000; }
.act-btn.hist    { color: var(--text-muted); border-color: var(--border); }
.act-btn.hist:hover{ background: var(--surface2); color: var(--text); }

/* TOP ACTIONS ROW */
.top-actions {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
}
.export-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: var(--surface2);
    border: 1px solid var(--border); border-radius: 7px;
    color: var(--text); text-decoration: none; font-size: 13px; font-weight: 600;
    transition: all .2s;
}
.export-btn:hover { border-color: var(--accent); color: var(--accent); }

/* EMPTY STATE */
.empty {
    text-align: center; padding: 50px 20px;
    color: var(--text-muted);
}
.empty .icon { font-size: 48px; margin-bottom: 12px; }
.empty p { font-size: 15px; }

/* RE-ISSUE FIND */
.find-result {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 20px; margin-top: 16px;
    animation: fadeUp .3s ease;
}
.info-row { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 16px; }
.info-item { display: flex; flex-direction: column; gap: 3px; }
.info-item span { font-size: 12px; color: var(--text-muted); }
.info-item strong { font-family: var(--mono); font-size: 14px; }

/* ALERT */
.alert {
    padding: 12px 16px; border-radius: 7px; margin-bottom: 16px;
    font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px;
}
.alert.error { background: #da363318; border: 1px solid #da363340; color: #ff7b72; }
.alert.success { background: #23863618; border: 1px solid #23863640; color: #3fb950; }

/* COUNT BADGE */
.count-badge {
    background: var(--surface2); border: 1px solid var(--border);
    color: var(--text-muted); font-size: 12px; padding: 2px 8px;
    border-radius: 20px; font-family: var(--mono);
}

@media (max-width: 768px) {
    .form-grid { grid-template-columns: 1fr; }
    .top-actions { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="nav-brand">
        <div class="logo">📦</div>
        <span>IT Asset Manager</span>
    </div>
    <div class="nav-right">
        <span class="nav-user"><?= htmlspecialchars($_SESSION['name']) ?></span>
        <span class="role-badge <?= $_SESSION['role'] == 'super_admin' ? 'super' : ($_SESSION['role'] == 'admin' ? 'admin' : '') ?>">
            <?= htmlspecialchars($_SESSION['role']) ?>
        </span>
        <a href="all_assets.php" class="export-btn" style="border-color:#8b5cf6;color:#a78bfa;" title="All Assets">📋 All Assets</a>
        <a href="store.php" class="export-btn" style="border-color:#0891b2;color:#22d3ee;" title="IT Store">🏪 IT Store</a>
        <a href="ink_tracker.php" class="export-btn" style="border-color:#7c3aed;color:#a78bfa;" title="Ink Tracker">🖨 Ink Tracker</a>
        <?php if(in_array($_SESSION['role'],['super_admin','admin'])): ?>
        <a href="import.php" class="export-btn" style="border-color:#238636;color:#3fb950;" title="Import CSV">📥 Import</a>
        <?php endif; ?>
        <?php if($_SESSION['role'] == 'super_admin'): ?>
        <a href="users.php" class="export-btn" style="border-color:#d29922;color:#d29922;" title="User Management">👥 Users</a>
        <?php endif; ?>
        <a href="export.php" class="export-btn" title="Export CSV">⬇ Export</a>
        <a href="logout.php" class="nav-link">Logout</a>

    </div>
</nav>

<!-- TOAST -->
<div class="toast-wrap" id="toastWrap"></div>

<div class="page">

<?php if($msg): 
    $is_error = str_starts_with($msg, 'error:');
    $clean = $is_error ? substr($msg, 6) : $msg;
?>
<div class="alert <?= $is_error ? 'error' : 'success' ?>">
    <?= $is_error ? '⚠' : '✓' ?> <?= htmlspecialchars($clean) ?>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats">
    <a href="all_assets.php" class="stat-card blue" style="text-decoration:none;">
        <div class="stat-icon blue">📊</div>
        <div class="stat-num blue" data-target="<?= $total ?>">0</div>
        <div class="stat-label">Total Assets</div>
    </a>
    <a href="all_assets.php?status=Issued" class="stat-card green" style="text-decoration:none;">
        <div class="stat-icon green">✅</div>
        <div class="stat-num green" data-target="<?= $issued ?>">0</div>
        <div class="stat-label">Currently Issued</div>
    </a>
    <a href="all_assets.php?status=Returned" class="stat-card orange" style="text-decoration:none;">
        <div class="stat-icon orange">🔓</div>
        <div class="stat-num orange" data-target="<?= $available ?>">0</div>
        <div class="stat-label">Available</div>
    </a>
    <a href="all_assets.php" class="stat-card purple" style="text-decoration:none;">
        <div class="stat-icon purple">🏢</div>
        <div class="stat-num purple" data-target="<?= $depts ?>">0</div>
        <div class="stat-label">Departments</div>
    </a>

    <!-- Har Type ka Dynamic Card -->
    <?php 
    $type_colors = ['#0ea5e9','#8b5cf6','#f59e0b','#10b981','#ef4444','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16'];
    $ci = 0;
    while($tr = mysqli_fetch_assoc($type_result)):
        $avail_count = $tr['total'] - $tr['issued'];
        $color = $type_colors[$ci % count($type_colors)];
        $ci++;
        $type_url = urlencode($tr['type']);
    ?>
    <a href="all_assets.php?type=<?= $type_url ?>" class="stat-card" 
       style="border-top: 3px solid <?= $color ?>; animation-delay: <?= 0.2 + ($ci * 0.05) ?>s; text-decoration:none; cursor:pointer;"
       title="Click to view — <?= htmlspecialchars($tr['type']) ?> assets">
        <div class="stat-icon" style="background:<?= $color ?>22; font-size:18px;">💻</div>
        <div class="stat-num" style="color:<?= $color ?>; font-size:32px;" data-target="<?= $tr['total'] ?>">0</div>
        <div class="stat-label"><?= htmlspecialchars($tr['type']) ?></div>
        <div style="margin-top:8px; font-size:11px; color:var(--text-muted); display:flex; gap:8px; justify-content:center;">
            <span style="color:#3fb950;">↑<?= $tr['issued'] ?> issued</span>
            <span style="color:var(--text-muted);">↓<?= $avail_count ?> free</span>
        </div>
        <div style="margin-top:8px; font-size:11px; color:<?= $color ?>; opacity:0.7;">
            Dekho →
        </div>
    </a>
    <?php endwhile; ?>
</div>

<!-- ISSUE FORM -->
<div class="card" id="issueCard">
    <div class="card-header" onclick="toggleCard('issueCard')">
        <h3>➕ <?= !empty($edit_data) ? 'Edit Asset' : 'Issue New Asset' ?></h3>
        <span class="collapse-icon">▾</span>
    </div>
    <div class="collapsible-body">
    <div class="card-body">
        <form method="POST" id="issueForm">
            <?php if(!empty($edit_data)): ?>
                <input type="hidden" name="uid" value="<?= $edit_data['id'] ?>">
            <?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Asset Tag *</label>
                    <input type="text" name="asset_Tag" placeholder="e.g. LAP-001" required
                           value="<?= htmlspecialchars($edit_data['asset_Tag'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Type *</label>
                    <select name="type" required>
                        <option value="">Select Type</option>
                        <?php foreach(['Laptop','Desktop','Monitor','Printer','Mouse','Keyboard','Router','Switch','UPS','Other'] as $t):
                            $sel = ($edit_data['type'] ?? '') == $t ? 'selected' : ''; ?>
                            <option <?= $sel ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" placeholder="e.g. Dell Latitude 5520"
                           value="<?= htmlspecialchars($edit_data['model'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Config</label>
                    <input type="text" name="Config" placeholder="e.g. i5/8GB/256SSD"
                           value="<?= htmlspecialchars($edit_data['Config'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Serial No.</label>
                    <input type="text" name="serial" placeholder="Serial number"
                           value="<?= htmlspecialchars($edit_data['serial_no'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Employee Name *</label>
                    <input type="text" name="emp" placeholder="Full name" required
                           value="<?= htmlspecialchars($edit_data['employee'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">Select Department</option>
                        <?php foreach(['IT','HR','Finance','Admin','Operations','Marketing','Sales'] as $d):
                            $sel = ($edit_data['department'] ?? '') == $d ? 'selected' : ''; ?>
                            <option <?= $sel ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <select name="location">
                        <option value="">Select Location</option>
                        <?php foreach(['Delhi','Mumbai','Vrindavan'] as $l):
                            $sel = ($edit_data['location'] ?? '') == $l ? 'selected' : ''; ?>
                            <option <?= $sel ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" placeholder="9876543210"
                           value="<?= htmlspecialchars($edit_data['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="name@company.com"
                           value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Vendor</label>
                    <input type="text" name="vendor" placeholder="e.g. Amazon, Dell Store"
                           value="<?= htmlspecialchars($edit_data['vendor'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Item Price (₹)</label>
                    <input type="number" step="0.01" name="item_price" placeholder="55000.00"
                           value="<?= htmlspecialchars($edit_data['item_price'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date"
                           value="<?= htmlspecialchars($edit_data['purchase_date'] ?? '') ?>">
                </div>
            </div>
            <?php if(!empty($edit_data)): ?>
                <button type="submit" name="update" class="btn btn-primary btn-full">💾 Update Asset</button>
            <?php else: ?>
                <button type="submit" name="add" class="btn btn-success btn-full">➕ Issue Asset</button>
            <?php endif; ?>
        </form>
    </div>
    </div>
</div>

<!-- RE-ISSUE -->
<div class="card" id="reissueCard">
    <div class="card-header" onclick="toggleCard('reissueCard')">
        <h3>🔄 Re-Issue Asset</h3>
        <span class="collapse-icon">▾</span>
    </div>
    <div class="collapsible-body">
    <div class="card-body">
        <form method="POST" style="display:flex; gap:10px;">
            <div class="form-group" style="flex:1; margin:0;">
                <input type="text" name="tag" placeholder="Enter Asset Tag..." required>
            </div>
            <button type="submit" name="find" class="btn btn-primary" style="margin-top:0;">🔍 Find</button>
        </form>
        <?php if(isset($_POST['find'])):
            $t = mysqli_real_escape_string($conn, trim($_POST['tag']));
            $q = mysqli_query($conn,"SELECT * FROM assets WHERE TRIM(asset_Tag)='$t'");
            if(mysqli_num_rows($q) > 0):
                $r = mysqli_fetch_assoc($q); ?>
                <div class="find-result">
                    <div class="info-row">
                        <div class="info-item"><span>Asset Tag</span><strong><?= htmlspecialchars($r['asset_Tag']) ?></strong></div>
                        <div class="info-item"><span>Type</span><strong><?= htmlspecialchars($r['type']) ?></strong></div>
                        <div class="info-item"><span>Model</span><strong><?= htmlspecialchars($r['model']) ?></strong></div>
                        <div class="info-item"><span>Config</span><strong><?= htmlspecialchars($r['Config']) ?></strong></div>
                        <div class="info-item"><span>Serial</span><strong><?= htmlspecialchars($r['serial_no']) ?></strong></div>
                        <div class="info-item">
                            <span>Status</span>
                            <span class="badge <?= strtolower($r['status']) ?>"><?= $r['status'] ?></span>
                        </div>
                    </div>
                    <?php if($r['status'] == 'Returned' || $r['status'] == 'Available'): ?>
                    <form method="POST" style="display:flex; gap:10px; align-items:flex-end;">
                        <input type="hidden" name="issue_tag" value="<?= htmlspecialchars($r['asset_Tag']) ?>">
                        <div class="form-group" style="flex:1; margin:0;">
                            <label>New Employee Name</label>
                            <input type="text" name="new_employee" required placeholder="Employee name...">
                        </div>
                        <button type="submit" name="reissue_submit" class="btn btn-success">Re-Issue ✓</button>
                    </form>
                    <?php else: ?>
                    <div class="alert error">⚠ Asset is currently Issued to <strong><?= htmlspecialchars($r['employee']) ?></strong>. Return it first.</div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert error" style="margin-top:12px;">⚠ Asset Tag not found in database.</div>
            <?php endif;
        endif; ?>
    </div>
    </div>
</div>

<!-- ASSETS TABLE -->
<div class="card" id="assetsSection" style="scroll-margin-top:70px;">
    <div class="card-header" style="cursor:default;">
        <h3>
            📋 Assets
            <span class="count-badge"><?= mysqli_num_rows($result) ?></span>
            <?php if($search): ?>
                <span style="color:var(--text-muted);font-weight:400;font-size:13px;">— "<?= htmlspecialchars($search) ?>" ke results</span>
            <?php else: ?>
                <span style="color:var(--text-muted);font-weight:400;font-size:13px;">— latest 50 &nbsp;|&nbsp; <a href="all_assets.php" style="color:var(--accent2);text-decoration:none;">View All →</a></span>
            <?php endif; ?>
        </h3>
        <div style="display:flex;gap:8px;">
            <a href="all_assets.php" class="export-btn" style="border-color:#1f6feb;color:#1f6feb;">📋 All Assets</a>
            <a href="export.php" class="export-btn">⬇ Export</a>
        </div>
    </div>
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" class="search-row" id="searchForm" action="#assetsSection">
            <input type="text" name="search" id="liveSearch"
                   placeholder="🔍 Asset Tag, Employee, Model..."
                   value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if($search): ?>
            <a href="index.php" class="btn" style="background:var(--surface2); border:1px solid var(--border); color:var(--text);">✕ Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
        <?php if(mysqli_num_rows($result) > 0): ?>
        <table id="assetsTable" style="scroll-margin-top: 70px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Asset Tag</th>
                    <th>Type</th>
                    <th>Model</th>
                    <th>Config</th>
                    <th>Employee</th>
                    <th>Status</th>
                    <th>Department</th>
                    <th>Location</th>
                    <th>Issue Date</th>
                    <th>Vendor</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php $i=1; while($row = mysqli_fetch_assoc($result)):
                $statusClass = $row['status'] == 'Issued' ? 'issued' : 'returned';
                $issueDate = $row['issue_date'] ? date('d M Y', strtotime($row['issue_date'])) : '—';
            ?>
            <tr style="animation-delay: <?= min($i * 0.03, 0.5) ?>s">
                <td style="color:var(--text-muted); font-family:var(--mono); font-size:12px;"><?= $i++ ?></td>
                <td><span class="tag"><?= htmlspecialchars($row['asset_Tag']) ?></span></td>
                <td><?= htmlspecialchars($row['type']) ?></td>
                <td><?= htmlspecialchars($row['model']) ?></td>
                <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($row['Config']) ?></td>
                <td><strong><?= htmlspecialchars($row['employee']) ?></strong></td>
                <td><span class="badge <?= $statusClass ?>"><?= $row['status'] ?></span></td>
                <td><?= htmlspecialchars($row['department']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td style="font-size:12px; color:var(--text-muted);"><?= $issueDate ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($row['vendor']) ?></td>
                <td style="font-family:var(--mono); font-size:13px;">
                    <?= $row['item_price'] ? '₹'.number_format($row['item_price'],0) : '—' ?>
                </td>
                <td>
                    <div class="action-links">
                        <?php if($_SESSION['role'] == 'super_admin' || $_SESSION['role'] == 'admin'): ?>
                            <a href="index.php?edit=<?= $row['id'] ?>" class="act-btn edit">Edit</a>
                        <?php endif; ?>
                        <?php if($row['status'] == 'Issued'): ?>
                            <a href="index.php?return=<?= $row['id'] ?>" class="act-btn ret"
                               onclick="return confirm('Mark this asset as returned?')">Return</a>
                        <?php endif; ?>
                        <a href="history.php?tag=<?= urlencode($row['asset_Tag']) ?>" class="act-btn hist">History</a>
                        <a href="asset_complaints.php?tag=<?= urlencode($row['asset_Tag']) ?>" class="act-btn" style="color:#d29922;border-color:#d29922;" title="Complaint History">📊 Complaints</a>
                        <?php if($_SESSION['role'] == 'super_admin'): ?>
                            <a href="index.php?delete=<?= $row['id'] ?>" class="act-btn del"
                               onclick="return confirm('Permanently delete this asset?')">Delete</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty">
            <div class="icon">🔍</div>
            <p><?= $search ? "No results found for \"".htmlspecialchars($search)."\"" : "No assets yet. Issue one above!" ?></p>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- /page -->

<script>
// ── Counter animation
document.querySelectorAll('.stat-num[data-target]').forEach(el => {
    const target = +el.dataset.target;
    let current = 0;
    const step = Math.max(1, Math.ceil(target / 40));
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current;
        if(current >= target) clearInterval(timer);
    }, 30);
});

// ── Toast system
const toastMessages = {
    'added':    { msg: 'Asset issued successfully!', icon: '✅' },
    'updated':  { msg: 'Asset updated successfully!', icon: '💾' },
    'deleted':  { msg: 'Asset deleted.',              icon: '🗑', type: 'error' },
    'returned': { msg: 'Asset marked as returned!',   icon: '🔓', type: 'warn' },
    'reissued': { msg: 'Asset re-issued successfully!', icon: '🔄' },
};
const urlParams = new URLSearchParams(location.search);
const toastKey  = urlParams.get('toast');
if(toastKey && toastMessages[toastKey]){
    const { msg, icon, type } = toastMessages[toastKey];
    showToast(icon + ' ' + msg, type || 'success');
    // Clean URL
    history.replaceState({}, '', location.pathname);
}
function showToast(message, type='success'){
    const wrap = document.getElementById('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast ' + (type === 'error' ? 'error' : type === 'warn' ? 'warn' : '');
    t.textContent = message;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4200);
}

// ── Collapsible cards
function toggleCard(id){
    document.getElementById(id).classList.toggle('collapsed');
}
// Auto-expand issue card if editing
<?php if(!empty($edit_data)): ?>
document.getElementById('issueCard').classList.remove('collapsed');
document.getElementById('reissueCard').classList.add('collapsed');
document.getElementById('issueForm').scrollIntoView({behavior:'smooth', block:'start'});
<?php else: ?>
// Start collapsed
document.getElementById('reissueCard').classList.add('collapsed');
<?php endif; ?>

// ── Live client-side search filter
const searchInput = document.getElementById('liveSearch');
const tableBody   = document.querySelector('#assetsTable tbody');
if(searchInput && tableBody){
    searchInput.addEventListener('input', function(){
        const q = this.value.toLowerCase();
        tableBody.querySelectorAll('tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// ── Search form submit - scroll to table
const searchForm = document.getElementById('searchForm');
if(searchForm){
    searchForm.addEventListener('submit', function(){
        sessionStorage.setItem('scrollToTable', '1');
    });
}

// ── Auto scroll to table after search
<?php if($search): ?>
window.addEventListener('load', () => {
    setTimeout(() => {
        const sec = document.getElementById('assetsSection');
        if(sec) sec.scrollIntoView({behavior:'smooth', block:'start'});
    }, 100);
});
<?php endif; ?>
</script>
</body>
</html>
