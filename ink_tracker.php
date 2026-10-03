<?php
include 'auth.php';
include 'db.php';

$my_name  = $_SESSION['name'];
$is_admin = in_array($_SESSION['role'], ['super_admin','admin']);
$is_super = $_SESSION['role'] == 'super_admin';

// ADD PRINTER
if(isset($_POST['add_printer']) && $is_admin){
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']));
    $asset_tag   = mysqli_real_escape_string($conn, trim($_POST['asset_tag'] ?? ''));
    $model       = mysqli_real_escape_string($conn, trim($_POST['model'] ?? ''));
    $department  = mysqli_real_escape_string($conn, $_POST['department']);
    $location    = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $assigned_to = mysqli_real_escape_string($conn, trim($_POST['assigned_to'] ?? ''));
    $ink_type    = mysqli_real_escape_string($conn, trim($_POST['ink_type'] ?? ''));
    $remarks     = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));
    $added_by    = mysqli_real_escape_string($conn, $my_name);
    mysqli_query($conn,"INSERT INTO printers (name,asset_tag,model,department,location,assigned_to,ink_type,remarks,added_by) VALUES ('$name','$asset_tag','$model','$department','$location','$assigned_to','$ink_type','$remarks','$added_by')");
    header("Location: ink_tracker.php?toast=printer_added"); exit;
}

// EDIT PRINTER
if(isset($_POST['edit_printer']) && $is_admin){
    $pid         = intval($_POST['printer_id']);
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']));
    $asset_tag   = mysqli_real_escape_string($conn, trim($_POST['asset_tag'] ?? ''));
    $model       = mysqli_real_escape_string($conn, trim($_POST['model'] ?? ''));
    $department  = mysqli_real_escape_string($conn, $_POST['department']);
    $location    = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $assigned_to = mysqli_real_escape_string($conn, trim($_POST['assigned_to'] ?? ''));
    $ink_type    = mysqli_real_escape_string($conn, trim($_POST['ink_type'] ?? ''));
    $remarks     = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));
    $status      = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Active');
    mysqli_query($conn,"UPDATE printers SET name='$name',asset_tag='$asset_tag',model='$model',department='$department',location='$location',assigned_to='$assigned_to',ink_type='$ink_type',remarks='$remarks',status='$status' WHERE id=$pid");
    header("Location: ink_tracker.php?toast=printer_updated"); exit;
}

// DELETE PRINTER
if(isset($_GET['delete_printer']) && $is_super){
    $did = intval($_GET['delete_printer']);
    mysqli_query($conn,"DELETE FROM printers WHERE id=$did");
    mysqli_query($conn,"DELETE FROM ink_history WHERE printer_id=$did");
    mysqli_query($conn,"DELETE FROM printer_repairs WHERE printer_id=$did");
    header("Location: ink_tracker.php?toast=deleted"); exit;
}

// ADD REFILL
if(isset($_POST['add_refill'])){
    $pid         = intval($_POST['printer_id']);
    $ink_brand   = mysqli_real_escape_string($conn, trim($_POST['ink_brand'] ?? ''));
    $quantity    = intval($_POST['quantity'] ?? 1);
    $refill_date = mysqli_real_escape_string($conn, $_POST['refill_date']);
    $remarks     = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));
    $done_by     = mysqli_real_escape_string($conn, $my_name);
    $printer     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM printers WHERE id=$pid"));
    if($printer){
        $pname = mysqli_real_escape_string($conn, $printer['name']);
        $dept  = mysqli_real_escape_string($conn, $printer['department']);
        $itype = mysqli_real_escape_string($conn, $printer['ink_type']);
        mysqli_query($conn,"INSERT INTO ink_history (printer_id,printer_name,department,ink_type,ink_brand,quantity,refill_date,done_by,remarks) VALUES ($pid,'$pname','$dept','$itype','$ink_brand',$quantity,'$refill_date','$done_by','$remarks')");
    }
    header("Location: ink_tracker.php?toast=refill_added"); exit;
}

// ADD REPAIR
if(isset($_POST['add_repair']) && $is_admin){
    $pid         = intval($_POST['printer_id']);
    $issue       = mysqli_real_escape_string($conn, trim($_POST['issue']));
    $repair_cost = floatval($_POST['repair_cost'] ?? 0);
    $vendor      = mysqli_real_escape_string($conn, trim($_POST['vendor'] ?? ''));
    $repair_date = mysqli_real_escape_string($conn, $_POST['repair_date']);
    $remarks     = mysqli_real_escape_string($conn, trim($_POST['repair_remarks'] ?? ''));
    $done_by     = mysqli_real_escape_string($conn, $my_name);
    $printer     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT name FROM printers WHERE id=$pid"));
    $pname       = $printer ? mysqli_real_escape_string($conn, $printer['name']) : '';
    mysqli_query($conn,"INSERT INTO printer_repairs (printer_id,printer_name,issue,repair_cost,vendor,repair_date,done_by,remarks) VALUES ($pid,'$pname','$issue',$repair_cost,'$vendor','$repair_date','$done_by','$remarks')");
    header("Location: ink_tracker.php?toast=repair_added"); exit;
}

// DATA
$printers = mysqli_query($conn,"SELECT p.*,
    (SELECT refill_date FROM ink_history WHERE printer_id=p.id ORDER BY refill_date DESC LIMIT 1) as last_refill,
    (SELECT COUNT(*) FROM ink_history WHERE printer_id=p.id) as total_refills,
    (SELECT ink_brand FROM ink_history WHERE printer_id=p.id ORDER BY refill_date DESC LIMIT 1) as last_brand,
    (SELECT COUNT(*) FROM printer_repairs WHERE printer_id=p.id) as total_repairs,
    (SELECT SUM(repair_cost) FROM printer_repairs WHERE printer_id=p.id) as total_repair_cost,
    (SELECT repair_date FROM printer_repairs WHERE printer_id=p.id ORDER BY repair_date DESC LIMIT 1) as last_repair_date,
    (SELECT issue FROM printer_repairs WHERE printer_id=p.id ORDER BY repair_date DESC LIMIT 1) as last_repair_issue
    FROM printers p ORDER BY department, name");
$total_printers = mysqli_num_rows($printers);
$total_refills  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM ink_history"))['c'];
$this_month     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM ink_history WHERE MONTH(refill_date)=MONTH(NOW()) AND YEAR(refill_date)=YEAR(NOW())"))['c'];

$overdue = mysqli_query($conn,"SELECT p.*, MAX(h.refill_date) as last_refill, DATEDIFF(NOW(), MAX(h.refill_date)) as days_ago FROM printers p LEFT JOIN ink_history h ON h.printer_id=p.id WHERE p.status='Active' GROUP BY p.id HAVING (days_ago > 30 OR last_refill IS NULL) ORDER BY days_ago DESC");
$overdue_count = $overdue ? mysqli_num_rows($overdue) : 0;

$filter_dept = $_GET['dept'] ?? '';
$log_where   = "WHERE 1=1";
if($filter_dept) $log_where .= " AND h.department='".mysqli_real_escape_string($conn,$filter_dept)."'";
$filtered_log = mysqli_query($conn,"SELECT h.*,p.location FROM ink_history h LEFT JOIN printers p ON p.id=h.printer_id $log_where ORDER BY h.refill_date DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ink & Printer Tracker</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{--bg:#080c10;--surface:#0d1117;--surface2:#161b22;--surface3:#1c2128;--border:#21262d;--border2:#30363d;--accent:#1f6feb;--green:#238636;--danger:#da3633;--warn:#d29922;--purple:#8b5cf6;--text:#cdd9e5;--muted:#768390;--radius:12px;--radius-sm:8px;--mono:'JetBrains Mono',monospace;--sans:'Space Grotesk',sans-serif;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--sans);background:var(--bg);color:var(--text);min-height:100vh;}
nav{background:rgba(13,17,23,0.95);border-bottom:1px solid var(--border);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:50;backdrop-filter:blur(20px);}
.nav-brand{display:flex;align-items:center;gap:10px;}
.nav-logo{width:34px;height:34px;background:linear-gradient(135deg,#7c3aed,#db2777);border-radius:8px;display:grid;place-items:center;font-size:16px;}
.nav-brand span{font-weight:700;font-size:15px;}
.nav-right{display:flex;align-items:center;gap:8px;}
.nav-link{color:var(--muted);text-decoration:none;font-size:13px;border:1px solid var(--border2);padding:5px 12px;border-radius:6px;transition:all .2s;}
.nav-link:hover{color:var(--text);border-color:var(--text);}
.page{max-width:1400px;margin:0 auto;padding:24px 20px;}

/* TOAST */
.toast-wrap{position:fixed;top:68px;right:16px;z-index:999;}
.toast{background:var(--surface);border:1px solid var(--border2);padding:12px 18px;border-radius:10px;font-size:14px;min-width:240px;box-shadow:0 8px 32px #0007;animation:slideIn .3s ease,fadeOut .4s ease 3.6s forwards;border-left:4px solid var(--green);margin-bottom:8px;}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes fadeOut{to{opacity:0;transform:translateX(120%)}}

/* MODAL */
.modal-bg{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:100;justify-content:center;align-items:center;}
.modal-box{background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius);padding:24px;width:100%;max-width:460px;position:relative;max-height:90vh;overflow-y:auto;}
.modal-box h3{font-size:16px;font-weight:700;margin-bottom:18px;}
.modal-close{position:absolute;top:12px;right:14px;background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;}
.modal-close:hover{color:var(--text);}

/* FORM */
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:12px;}
.fg label{font-size:12px;color:var(--muted);font-weight:500;text-transform:uppercase;letter-spacing:.4px;}
.fg input,.fg select,.fg textarea{background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:9px 12px;border-radius:7px;font-family:var(--sans);font-size:14px;width:100%;}
.fg input:focus,.fg select:focus,.fg textarea:focus{outline:none;border-color:var(--accent);}
.fg select option{background:var(--surface2);}
.fg textarea{resize:vertical;min-height:70px;}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.btn{padding:9px 18px;border:none;border-radius:7px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
.btn-purple{background:var(--purple);color:#fff;}
.btn-purple:hover{background:#7c3aed;}
.btn-green{background:var(--green);color:#fff;}
.btn-green:hover{background:#2ea043;}
.btn-warn{background:var(--warn);color:#000;}
.btn-red{background:var(--danger);color:#fff;}
.btn-sm{padding:5px 10px;font-size:12px;}
.btn-full{width:100%;justify-content:center;padding:11px;margin-top:6px;}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;margin-top:14px;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
.scard{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;text-align:center;animation:fadeUp .4s ease both;transition:transform .2s;}
.scard:hover{transform:translateY(-3px);}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.snum{font-size:28px;font-weight:700;font-family:var(--mono);}
.slbl{font-size:12px;color:var(--muted);margin-top:5px;}

/* OVERDUE */
.overdue-bar{background:#da363318;border:1px solid #da363340;border-radius:var(--radius);padding:14px 18px;margin-bottom:16px;}
.overdue-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;}
.ochip{background:#da363330;border:1px solid #da363360;color:#ff7b72;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;}

/* LAYOUT */
.layout{display:grid;grid-template-columns:1fr 340px;gap:16px;}
@media(max-width:1000px){.layout{grid-template-columns:1fr;}}

/* CARD */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:16px;}
.card-hdr{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.card-hdr h3{font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px;}

/* SEARCH BAR */
.search-bar{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap;}
.search-bar input,.search-bar select{background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:7px 12px;border-radius:6px;font-family:var(--sans);font-size:13px;}
.search-bar input{flex:1;min-width:160px;}
.search-bar input:focus,.search-bar select:focus{outline:none;border-color:var(--accent);}
.search-bar select option{background:var(--surface2);}

/* PRINTER GRID */
.pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;padding:14px;}
.pcard{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;transition:transform .2s,box-shadow .2s;animation:fadeUp .3s ease both;}
.pcard:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,0.3);}
.pcard.ok{border-left:3px solid #3fb950;}
.pcard.warn{border-left:3px solid var(--warn);}
.pcard.overdue{border-left:3px solid var(--danger);}
.pcard.never{border-left:3px solid var(--muted);}
.pc-name{font-size:14px;font-weight:700;}
.pc-tag{font-family:var(--mono);font-size:11px;color:var(--accent);background:#1f6feb18;border:1px solid #1f6feb40;padding:2px 7px;border-radius:4px;display:inline-block;margin:3px 0;}
.pc-model{font-size:12px;color:var(--muted);}
.pc-dept{background:var(--surface3);border:1px solid var(--border2);color:var(--muted);font-size:11px;padding:2px 8px;border-radius:4px;font-family:var(--mono);}
.pc-row{display:flex;justify-content:space-between;font-size:12px;margin-top:5px;}
.pc-row .lbl{color:var(--muted);}
.days-ok{background:#23863618;color:#3fb950;border:1px solid #23863640;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;}
.days-warn{background:#d2992218;color:#d29922;border:1px solid #d2992240;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;animation:wp 2s infinite;}
.days-overdue{background:#da363318;color:#ff7b72;border:1px solid #da363340;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;animation:dp 2s infinite;}
.days-never{background:#8b949e18;color:#8b949e;border:1px solid #8b949e40;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;}
@keyframes wp{0%,100%{box-shadow:0 0 0 0 #d2992230}50%{box-shadow:0 0 0 4px #d2992210}}
@keyframes dp{0%,100%{box-shadow:0 0 0 0 #da363330}50%{box-shadow:0 0 0 4px #da363310}}
.ink-status{background:var(--surface3);border:1px solid var(--border2);border-radius:7px;padding:8px 10px;margin-top:8px;}
.ink-status .title{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px;}
.pc-actions{display:flex;gap:5px;margin-top:10px;flex-wrap:wrap;}

/* TABLE */
.twrap{overflow-x:auto;max-height:400px;overflow-y:auto;}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{background:var(--surface2);color:var(--muted);padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border2);white-space:nowrap;position:sticky;top:0;}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s;}
tbody tr:hover{background:var(--surface2);}
tbody td{padding:9px 12px;vertical-align:middle;}
.badge-in{display:inline-flex;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:#23863618;color:#3fb950;border:1px solid #23863640;}
.badge-out{display:inline-flex;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:#da363318;color:#ff7b72;border:1px solid #da363340;}

/* DEPT SUMMARY */
.dept-item{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:8px;}
.dept-name{font-size:14px;font-weight:700;margin-bottom:6px;}
.dept-stats{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);}
.empty{text-align:center;padding:40px;color:var(--muted);}

/* ADD FORM */
.add-form-wrap{padding:18px;border-bottom:1px solid var(--border);background:var(--surface2);}

/* EXPORT BAR */
.export-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:12px 18px;margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;}

@media(max-width:600px){.stats{grid-template-columns:repeat(2,1fr);}.fgrid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<nav>
    <div class="nav-brand">
        <div class="nav-logo">🖨</div>
        <span>Ink & Printer Tracker</span>
    </div>
    <div class="nav-right">
        <a href="store.php" class="nav-link">🏪 IT Store</a>
        <a href="index.php" class="nav-link">📦 Assets</a>
        <a href="logout.php" class="nav-link" style="color:var(--danger);border-color:rgba(218,54,51,0.4);">Logout</a>
    </div>
</nav>

<!-- REFILL MODAL -->
<div class="modal-bg" id="refillModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('refillModal')">✕</button>
        <h3 style="color:#a78bfa;">🖊 Add Ink Refill</h3>
        <form method="POST">
            <input type="hidden" name="printer_id" id="refill_pid">
            <div class="fg"><label>Printer</label><input type="text" id="refill_pname" readonly style="color:var(--muted);"></div>
            <div class="fgrid">
                <div class="fg"><label>Ink Brand / Type</label><input type="text" name="ink_brand" placeholder="e.g. HP 85A" list="ink-list"><datalist id="ink-list"><option>HP 85A Black</option><option>HP 12A Black</option><option>Canon PG-745</option><option>Canon CL-746</option><option>Epson T664</option></datalist></div>
                <div class="fg"><label>Quantity</label><input type="number" name="quantity" min="1" value="1"></div>
            </div>
            <div class="fg"><label>Refill Date *</label><input type="date" name="refill_date" value="<?php echo date('Y-m-d'); ?>" required></div>
            <div class="fg"><label>Remarks</label><input type="text" name="remarks" placeholder="Notes..."></div>
            <div class="modal-btns">
                <button type="button" onclick="closeModal('refillModal')" class="btn" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);">Cancel</button>
                <button type="submit" name="add_refill" class="btn btn-purple">🖊 Add Refill</button>
            </div>
        </form>
    </div>
</div>

<!-- REPAIR MODAL -->
<div class="modal-bg" id="repairModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('repairModal')">✕</button>
        <h3 style="color:var(--warn);">🔧 Add Repair Record</h3>
        <form method="POST">
            <input type="hidden" name="printer_id" id="repair_pid">
            <div class="fg"><label>Printer</label><input type="text" id="repair_pname" readonly style="color:var(--muted);"></div>
            <div class="fg"><label>Issue / Problem *</label><textarea name="issue" required placeholder="What was wrong?"></textarea></div>
            <div class="fgrid">
                <div class="fg"><label>Repair Cost (₹)</label><input type="number" name="repair_cost" min="0" step="0.01" placeholder="0.00"></div>
                <div class="fg"><label>Repair Date *</label><input type="date" name="repair_date" value="<?php echo date('Y-m-d'); ?>" required></div>
            </div>
            <div class="fg"><label>Vendor / Technician</label><input type="text" name="vendor" placeholder="Who repaired it?"></div>
            <div class="fg"><label>Remarks</label><input type="text" name="repair_remarks" placeholder="Notes..."></div>
            <div class="modal-btns">
                <button type="button" onclick="closeModal('repairModal')" class="btn" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);">Cancel</button>
                <button type="submit" name="add_repair" class="btn btn-warn">🔧 Save Repair</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-bg" id="editModal">
    <div class="modal-box" style="max-width:520px;">
        <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        <h3>✏ Edit Printer</h3>
        <form method="POST">
            <input type="hidden" name="printer_id" id="edit_pid">
            <div class="fgrid">
                <div class="fg"><label>Printer Name *</label><input type="text" name="name" id="e_name" required></div>
                <div class="fg"><label>Asset Tag</label><input type="text" name="asset_tag" id="e_tag"></div>
                <div class="fg"><label>Model</label><input type="text" name="model" id="e_model"></div>
                <div class="fg"><label>Department *</label>
                    <select name="department" id="e_dept" required>
                        <?php foreach(['IT','HR','Finance','Admin','Operations','Marketing','Sales'] as $d): ?>
                        <option><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg"><label>Location</label>
                    <select name="location" id="e_loc">
                        <option value="">Select</option>
                        <?php foreach(['Delhi','Mumbai','Vrindavan'] as $l): ?>
                        <option><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg"><label>Assigned To</label><input type="text" name="assigned_to" id="e_assigned"></div>
                <div class="fg"><label>Status</label>
                    <select name="status" id="e_status">
                        <option value="Active">✅ Active</option>
                        <option value="Under Repair">🔧 Under Repair</option>
                        <option value="Retired">⛔ Retired</option>
                    </select>
                </div>
                <div class="fg"><label>Ink Type</label><input type="text" name="ink_type" id="e_ink"></div>
            </div>
            <div class="fg"><label>Remarks</label><input type="text" name="remarks" id="e_remarks"></div>
            <div class="modal-btns">
                <button type="button" onclick="closeModal('editModal')" class="btn" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);">Cancel</button>
                <button type="submit" name="edit_printer" class="btn btn-purple">💾 Save</button>
            </div>
        </form>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<div class="page">

<!-- EXPORT BAR -->
<div class="export-bar">
    <span style="font-size:13px;font-weight:600;color:var(--muted);">⬇ Export:</span>
    <a href="printer_export.php?type=printers" class="btn btn-green btn-sm">🖨 Printer List</a>
    <form method="GET" action="printer_export.php" style="display:flex;gap:6px;align-items:center;">
        <input type="hidden" name="type" value="refills">
        <span style="font-size:12px;color:var(--muted);">🖊 Refill:</span>
        <input type="date" name="from" value="<?php echo date('Y-m-01'); ?>" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:5px 8px;border-radius:6px;font-size:12px;">
        <span style="color:var(--muted);font-size:12px;">to</span>
        <input type="date" name="to" value="<?php echo date('Y-m-d'); ?>" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:5px 8px;border-radius:6px;font-size:12px;">
        <button type="submit" class="btn btn-purple btn-sm">⬇ CSV</button>
    </form>
    <form method="GET" action="printer_export.php" style="display:flex;gap:6px;align-items:center;">
        <input type="hidden" name="type" value="repairs">
        <span style="font-size:12px;color:var(--muted);">🔧 Repair:</span>
        <input type="date" name="from" value="<?php echo date('Y-m-01'); ?>" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:5px 8px;border-radius:6px;font-size:12px;">
        <span style="color:var(--muted);font-size:12px;">to</span>
        <input type="date" name="to" value="<?php echo date('Y-m-d'); ?>" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:5px 8px;border-radius:6px;font-size:12px;">
        <button type="submit" class="btn btn-warn btn-sm">⬇ CSV</button>
    </form>
</div>

<!-- OVERDUE ALERT -->
<?php if($overdue_count > 0): ?>
<div class="overdue-bar">
    <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:20px;">🚨</span>
        <strong style="color:#ff7b72;"><?php echo $overdue_count; ?> printer(s) need ink refill attention!</strong>
    </div>
    <div class="overdue-chips">
    <?php mysqli_data_seek($overdue,0); while($od = mysqli_fetch_assoc($overdue)):
        $days = $od['days_ago'] ?? 999;
        $label = !$od['last_refill'] ? 'Never refilled' : $days.' days ago';
    ?>
    <span class="ochip" onclick="openRefill(<?php echo $od['id']; ?>,'<?php echo addslashes($od['name']); ?>')">
        🖨 <?php echo htmlspecialchars($od['name']); ?> — <?php echo $label; ?>
    </span>
    <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats">
    <div class="scard" style="border-top:3px solid #7c3aed;animation-delay:.05s">
        <div style="font-size:20px;margin-bottom:5px;">🖨</div>
        <div class="snum" style="color:#a78bfa;" data-target="<?php echo $total_printers; ?>">0</div>
        <div class="slbl">Total Printers</div>
    </div>
    <div class="scard" style="border-top:3px solid var(--danger);animation-delay:.1s">
        <div style="font-size:20px;margin-bottom:5px;">🚨</div>
        <div class="snum" style="color:#ff7b72;" data-target="<?php echo $overdue_count; ?>">0</div>
        <div class="slbl">Need Attention</div>
    </div>
    <div class="scard" style="border-top:3px solid var(--green);animation-delay:.15s">
        <div style="font-size:20px;margin-bottom:5px;">🖊</div>
        <div class="snum" style="color:#3fb950;" data-target="<?php echo $total_refills; ?>">0</div>
        <div class="slbl">Total Refills</div>
    </div>
    <div class="scard" style="border-top:3px solid var(--accent);animation-delay:.2s">
        <div style="font-size:20px;margin-bottom:5px;">📅</div>
        <div class="snum" style="color:#79c0ff;" data-target="<?php echo $this_month; ?>">0</div>
        <div class="slbl">This Month</div>
    </div>
</div>

<div class="layout">
<div>

<!-- PRINTERS -->
<div class="card">
    <div class="card-hdr">
        <h3>🖨 All Printers <span style="background:var(--surface2);border:1px solid var(--border2);color:var(--muted);font-size:12px;padding:2px 8px;border-radius:20px;font-family:var(--mono);"><?php echo $total_printers; ?></span></h3>
        <?php if($is_admin): ?>
        <button onclick="document.getElementById('addForm').style.display=document.getElementById('addForm').style.display=='none'?'block':'none'" class="btn btn-purple btn-sm">+ Add Printer</button>
        <?php endif; ?>
    </div>

    <!-- ADD FORM -->
    <?php if($is_admin): ?>
    <div id="addForm" style="display:none;" class="add-form-wrap">
        <form method="POST">
            <div class="fgrid">
                <div class="fg"><label>Printer Name *</label><input type="text" name="name" required placeholder="e.g. HP LaserJet — Finance"></div>
                <div class="fg"><label>Asset Tag</label><input type="text" name="asset_tag" placeholder="e.g. VCMT/VRN/PRN/01"></div>
                <div class="fg"><label>Model</label><input type="text" name="model" placeholder="e.g. HP M15w"></div>
                <div class="fg"><label>Department *</label>
                    <select name="department" required>
                        <option value="">Select</option>
                        <?php foreach(['IT','HR','Finance','Admin','Operations','Marketing','Sales'] as $d): ?>
                        <option><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg"><label>Location</label>
                    <select name="location">
                        <option value="">Select</option>
                        <?php foreach(['Delhi','Mumbai','Vrindavan'] as $l): ?>
                        <option><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg"><label>Assigned To</label><input type="text" name="assigned_to" placeholder="Employee name"></div>
                <div class="fg"><label>Ink Type</label><input type="text" name="ink_type" placeholder="e.g. HP 85A, Canon PG-745"></div>
                <div class="fg"><label>Remarks</label><input type="text" name="remarks" placeholder="Notes..."></div>
            </div>
            <button type="submit" name="add_printer" class="btn btn-purple btn-full">🖨 Add Printer</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- SEARCH -->
    <div class="search-bar">
        <input type="text" id="psearch" placeholder="🔍 Search by name, model, tag, department..." oninput="filterPrinters()">
        <select id="pdept" onchange="filterPrinters()">
            <option value="">All Departments</option>
            <?php foreach(['IT','HR','Finance','Admin','Operations','Marketing','Sales'] as $d): ?>
            <option><?php echo $d; ?></option>
            <?php endforeach; ?>
        </select>
        <select id="pstatus" onchange="filterPrinters()">
            <option value="">All Status</option>
            <option value="ok">✅ OK</option>
            <option value="warn">🟡 Attention</option>
            <option value="overdue">🔴 Overdue</option>
            <option value="never">⚪ Never</option>
        </select>
        <span id="pcount" style="font-size:12px;color:var(--muted);white-space:nowrap;"></span>
    </div>

    <!-- PRINTER CARDS -->
    <div class="pgrid" id="printerGrid">
    <?php if($total_printers > 0):
        mysqli_data_seek($printers,0); $pi=0;
        while($p = mysqli_fetch_assoc($printers)):
            $pi++;
            $days = $p['last_refill'] ? (int)((time()-strtotime($p['last_refill']))/86400) : null;
            if($days===null){$cls='never';$bcls='days-never';$btxt='Never Refilled';}
            elseif($days>60){$cls='overdue';$bcls='days-overdue';$btxt=$days.' days ago';}
            elseif($days>30){$cls='warn';$bcls='days-warn';$btxt=$days.' days ago';}
            else{$cls='ok';$bcls='days-ok';$btxt=$days.' days ago';}

            // Check ink stock
            $ink_stock = null; $ink_store_name = null;
            if(!empty($p['ink_type'])){
                $ink_q = mysqli_query($conn,"SELECT id,name,quantity FROM store_items WHERE name LIKE '%".mysqli_real_escape_string($conn,substr($p['ink_type'],0,10))."%' LIMIT 1");
                if($ink_q && mysqli_num_rows($ink_q)>0){
                    $inks = mysqli_fetch_assoc($ink_q);
                    $ink_stock = $inks['quantity'];
                    $ink_store_name = $inks['name'];
                }
            }
    ?>
    <div class="pcard <?php echo $cls; ?>"
         data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>"
         data-model="<?php echo strtolower(htmlspecialchars($p['model']??'')); ?>"
         data-dept="<?php echo $p['department']; ?>"
         data-tag="<?php echo strtolower(htmlspecialchars($p['asset_tag']??'')); ?>"
         data-status="<?php echo $cls; ?>"
         style="animation-delay:<?php echo $pi*0.05; ?>s">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
            <div>
                <div class="pc-name"><?php echo htmlspecialchars($p['name']); ?></div>
                <?php if(!empty($p['asset_tag'])): ?>
                <div class="pc-tag">🏷 <?php echo htmlspecialchars($p['asset_tag']); ?></div>
                <?php endif; ?>
                <div class="pc-model"><?php echo htmlspecialchars($p['model']??''); ?></div>
            </div>
            <span class="pc-dept"><?php echo $p['department']; ?></span>
        </div>

        <?php if(!empty($p['assigned_to'])): ?>
        <div style="font-size:12px;color:#a78bfa;margin-bottom:6px;">👤 <?php echo htmlspecialchars($p['assigned_to']); ?></div>
        <?php endif; ?>

        <div class="pc-row"><span class="lbl">Last Refill</span><span><?php echo $p['last_refill'] ? date('d M Y',strtotime($p['last_refill'])) : '—'; ?></span></div>
        <div class="pc-row"><span class="lbl">Status</span><span class="<?php echo $bcls; ?>"><?php echo $btxt; ?></span></div>
        <div class="pc-row"><span class="lbl">Total Refills</span><span style="font-family:var(--mono);font-weight:700;"><?php echo $p['total_refills']; ?> times</span></div>
        <div class="pc-row"><span class="lbl">Ink Type</span><span style="color:var(--muted);font-size:11px;"><?php echo htmlspecialchars($p['ink_type']??'—'); ?></span></div>
        <?php if($p['location']): ?><div class="pc-row"><span class="lbl">Location</span><span><?php echo htmlspecialchars($p['location']); ?></span></div><?php endif; ?>
        <?php if($p['total_repairs']>0): ?>
        <div class="pc-row" style="margin-top:6px;padding-top:6px;border-top:1px dashed var(--border2);">
            <span class="lbl">🔧 Repairs</span>
            <span style="color:var(--warn);font-weight:600;"><?php echo $p['total_repairs']; ?>x — ₹<?php echo number_format($p['total_repair_cost']??0,0); ?></span>
        </div>
        <?php if($p['last_repair_issue']): ?>
        <div style="font-size:11px;color:var(--muted);margin-top:2px;">Last: "<?php echo htmlspecialchars($p['last_repair_issue']); ?>"</div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if($p['remarks']): ?>
        <div style="background:var(--surface3);border-radius:5px;padding:6px 8px;margin-top:6px;font-size:11px;color:var(--muted);">📝 <?php echo htmlspecialchars($p['remarks']); ?></div>
        <?php endif; ?>

        <!-- INK STOCK -->
        <?php if(!empty($p['ink_type'])): ?>
        <div class="ink-status">
            <div class="title">📦 Ink Stock</div>
            <?php if($ink_store_name !== null): ?>
                <?php if($ink_stock > 0): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;color:#3fb950;font-weight:600;">✅ Available</span>
                    <span style="background:#23863618;border:1px solid #23863640;color:#3fb950;font-size:12px;font-weight:700;padding:2px 10px;border-radius:20px;font-family:var(--mono);"><?php echo $ink_stock; ?> pcs</span>
                </div>
                <?php else: ?>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;color:#ff7b72;font-weight:600;">❌ Out of Stock</span>
                    <a href="store.php" style="background:#da363318;border:1px solid #da363340;color:#ff7b72;font-size:11px;padding:2px 8px;border-radius:20px;text-decoration:none;font-weight:600;">Buy →</a>
                </div>
                <?php endif; ?>
                <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?php echo htmlspecialchars($ink_store_name); ?></div>
            <?php else: ?>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;color:var(--warn);">⚠ Not in Store</span>
                    <a href="store.php" style="background:var(--surface2);border:1px solid var(--border2);color:var(--muted);font-size:11px;padding:2px 8px;border-radius:20px;text-decoration:none;">Add →</a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ACTIONS -->
        <div class="pc-actions">
            <button onclick="openRefill(<?php echo $p['id']; ?>,'<?php echo addslashes($p['name']); ?>')" class="btn btn-purple btn-sm">🖊 Refill</button>
            <?php if($is_admin): ?>
            <button onclick="openRepair(<?php echo $p['id']; ?>,'<?php echo addslashes($p['name']); ?>')" class="btn btn-warn btn-sm">🔧 Repair</button>
            <button onclick='openEdit(<?php echo json_encode($p,JSON_HEX_APOS|JSON_HEX_QUOT); ?>)' class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);">✏</button>
            <?php endif; ?>
            <?php if($is_super): ?>
            <a href="ink_tracker.php?delete_printer=<?php echo $p['id']; ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete?')">🗑</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile;
    else: ?>
    <div class="empty" style="grid-column:1/-1;">
        <div style="font-size:40px;margin-bottom:10px;">🖨</div>
        <p>No printers yet. Click "+ Add Printer"!</p>
    </div>
    <?php endif; ?>
    </div>
</div>

<!-- REFILL HISTORY -->
<div class="card">
    <div class="card-hdr">
        <h3>📋 Refill History</h3>
        <select onchange="location.href='?dept='+this.value" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:5px 10px;border-radius:6px;font-size:12px;font-family:var(--sans);">
            <option value="">All Departments</option>
            <?php foreach(['IT','HR','Finance','Admin','Operations','Marketing','Sales'] as $d): ?>
            <option <?php echo $filter_dept==$d?'selected':''; ?>><?php echo $d; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="twrap">
    <?php $rc = mysqli_num_rows($filtered_log); if($rc>0): mysqli_data_seek($filtered_log,0); $ri=0; ?>
    <table>
        <thead><tr><th>#</th><th>Date</th><th>Printer</th><th>Dept</th><th>Ink</th><th>Qty</th><th>By</th></tr></thead>
        <tbody>
        <?php while($h=mysqli_fetch_assoc($filtered_log)): $ri++; ?>
        <tr>
            <td style="color:var(--muted);font-size:11px;"><?php echo $ri; ?></td>
            <td style="font-family:var(--mono);font-size:12px;color:#79c0ff;white-space:nowrap;"><?php echo date('d M Y',strtotime($h['refill_date'])); ?></td>
            <td><strong><?php echo htmlspecialchars($h['printer_name']); ?></strong></td>
            <td style="font-size:11px;"><span style="background:var(--surface2);border:1px solid var(--border2);padding:2px 6px;border-radius:4px;color:var(--muted);"><?php echo $h['department']; ?></span></td>
            <td style="font-size:12px;"><?php echo htmlspecialchars($h['ink_brand']?:$h['ink_type']); ?></td>
            <td style="font-family:var(--mono);font-weight:700;color:#3fb950;"><?php echo $h['quantity']; ?></td>
            <td style="font-size:12px;color:var(--muted);"><?php echo htmlspecialchars($h['done_by']); ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?><div class="empty"><p>No refill history yet.</p></div><?php endif; ?>
    </div>
</div>

</div>

<!-- RIGHT PANEL -->
<div>
<div class="card">
    <div class="card-hdr"><h3>🏢 Department Summary</h3></div>
    <div style="padding:12px;">
    <?php
    $ds = mysqli_query($conn,"SELECT p.department, COUNT(DISTINCT p.id) as pc, COUNT(h.id) as rc, MAX(h.refill_date) as lr FROM printers p LEFT JOIN ink_history h ON h.printer_id=p.id GROUP BY p.department ORDER BY p.department");
    $dscount = 0;
    while($d = mysqli_fetch_assoc($ds)):
        $dscount++;
        $days = $d['lr'] ? (int)((time()-strtotime($d['lr']))/86400) : null;
        $dc = $days===null?'#8b949e':($days>30?'#ff7b72':($days>14?'#d29922':'#3fb950'));
    ?>
    <div class="dept-item">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div class="dept-name"><?php echo $d['department']; ?></div>
            <span style="font-size:11px;color:var(--muted);"><?php echo $d['pc']; ?> printer<?php echo $d['pc']>1?'s':''; ?></span>
        </div>
        <div class="dept-stats">
            <span>Refills: <strong style="color:var(--text);"><?php echo $d['rc']; ?></strong></span>
            <span style="color:<?php echo $dc; ?>;"><?php echo $d['lr']?'Last: '.date('d M',strtotime($d['lr'])):'Never'; ?></span>
        </div>
    </div>
    <?php endwhile;
    if($dscount==0): ?>
    <div class="empty"><p>No data yet.</p></div>
    <?php endif; ?>
    </div>
</div>
</div>

</div>
</div>

<script>
// Counter
document.querySelectorAll('.snum[data-target]').forEach(el=>{
    const t=+el.dataset.target;
    if(!t)return;
    let c=0;const s=Math.max(1,Math.ceil(t/30));
    const ti=setInterval(()=>{c=Math.min(c+s,t);el.textContent=c;if(c>=t)clearInterval(ti);},40);
});

// Toast
const msgs={'printer_added':'🖨 Printer added!','printer_updated':'✅ Printer updated!','refill_added':'🖊 Refill recorded!','repair_added':'🔧 Repair saved!','deleted':'🗑 Deleted.'};
const tk=new URLSearchParams(location.search).get('toast');
if(tk&&msgs[tk]){showToast(msgs[tk]);history.replaceState({},'',location.pathname+(location.search.replace(/[?&]toast=[^&]*/,'').replace(/^&/,'?')||''));}
function showToast(msg){
    const w=document.getElementById('toastWrap');
    const t=document.createElement('div');
    t.className='toast';t.textContent=msg;
    w.appendChild(t);setTimeout(()=>t.remove(),4200);
}

// Modals
function openRefill(id,name){
    document.getElementById('refill_pid').value=id;
    document.getElementById('refill_pname').value=name;
    document.getElementById('refillModal').style.cssText='display:flex!important;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:9999;align-items:center;justify-content:center;padding:20px;';
}
function openRepair(id,name){
    document.getElementById('repair_pid').value=id;
    document.getElementById('repair_pname').value=name;
    document.getElementById('repairModal').style.cssText='display:flex!important;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:9999;align-items:center;justify-content:center;padding:20px;';
}
function openEdit(p){
    document.getElementById('edit_pid').value=p.id;
    document.getElementById('e_name').value=p.name||'';
    document.getElementById('e_tag').value=p.asset_tag||'';
    document.getElementById('e_model').value=p.model||'';
    document.getElementById('e_dept').value=p.department||'';
    document.getElementById('e_loc').value=p.location||'';
    document.getElementById('e_assigned').value=p.assigned_to||'';
    document.getElementById('e_status').value=p.status||'Active';
    document.getElementById('e_ink').value=p.ink_type||'';
    document.getElementById('e_remarks').value=p.remarks||'';
    document.getElementById('editModal').style.display='flex';
}
function closeModal(id){
    document.getElementById(id).style.display='none';
}
// Close on backdrop click
['refillModal','repairModal','editModal'].forEach(id=>{
    document.getElementById(id).addEventListener('click',function(e){
        if(e.target===this) closeModal(id);
    });
});

// Printer filter
function filterPrinters(){
    const s=(document.getElementById('psearch').value||'').toLowerCase();
    const d=(document.getElementById('pdept').value||'').toLowerCase();
    const st=(document.getElementById('pstatus').value||'').toLowerCase();
    const cards=document.querySelectorAll('.pcard');
    let v=0;
    cards.forEach(c=>{
        const nm=c.dataset.name||'';
        const mo=c.dataset.model||'';
        const dp=c.dataset.dept||'';
        const tg=c.dataset.tag||'';
        const cs=c.dataset.status||'';
        const ms=!s||nm.includes(s)||mo.includes(s)||dp.toLowerCase().includes(s)||tg.includes(s);
        const md=!d||dp.toLowerCase()===d;
        const mst=!st||cs===st;
        if(ms&&md&&mst){c.style.display='';v++;}
        else c.style.display='none';
    });
    const el=document.getElementById('pcount');
    if(el) el.textContent=v+' printer'+(v!==1?'s':'')+' found';
}
window.addEventListener('load',()=>filterPrinters());
</script>
</body>
</html>