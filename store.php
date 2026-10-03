<?php
include 'auth.php';
include 'db.php';

$my_name = $_SESSION['name'];
$my_role = $_SESSION['role'];
$is_super = $my_role == 'super_admin';
$is_admin = in_array($my_role, ['super_admin','admin']);

// ── ADD NEW ITEM
if(isset($_POST['add_item']) && $is_admin){
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']));
    $category    = mysqli_real_escape_string($conn, $_POST['category']);
    $quantity    = intval($_POST['quantity']);
    $min_qty     = intval($_POST['min_quantity']);
    $unit        = mysqli_real_escape_string($conn, $_POST['unit']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $added_by    = mysqli_real_escape_string($conn, $my_name);

    mysqli_query($conn,"INSERT INTO store_items (name,category,quantity,min_quantity,unit,description,added_by)
        VALUES ('$name','$category',$quantity,$min_qty,'$unit','$description','$added_by')");

    // Log initial stock as IN
    if($quantity > 0){
        $item_id = mysqli_insert_id($conn);
        mysqli_query($conn,"INSERT INTO store_log (item_id,item_name,type,quantity,employee,purpose,done_by)
            VALUES ($item_id,'$name','IN',$quantity,'$added_by','Initial Stock','$added_by')");
    }
    header("Location: store.php?toast=added"); exit;
}

// ── DELETE ITEM
if(isset($_GET['delete_item']) && $is_super){
    $did = intval($_GET['delete_item']);
    mysqli_query($conn,"DELETE FROM store_items WHERE id=$did");
    mysqli_query($conn,"DELETE FROM store_log WHERE item_id=$did");
    header("Location: store.php?toast=deleted"); exit;
}

// ── STOCK IN
if(isset($_POST['stock_in']) && $is_admin){
    $item_id  = intval($_POST['item_id']);
    $qty      = intval($_POST['quantity']);
    $remarks  = mysqli_real_escape_string($conn, trim($_POST['remarks']));
    $done_by  = mysqli_real_escape_string($conn, $my_name);
    $item     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM store_items WHERE id=$item_id"));

    if($item && $qty > 0){
        mysqli_query($conn,"UPDATE store_items SET quantity=quantity+$qty WHERE id=$item_id");
        mysqli_query($conn,"INSERT INTO store_log (item_id,item_name,type,quantity,purpose,remarks,done_by)
            VALUES ($item_id,'".mysqli_real_escape_string($conn,$item['name'])."','IN',$qty,'Stock In','$remarks','$done_by')");
        header("Location: store.php?toast=stockin"); exit;
    }
}

// ── STOCK OUT
if(isset($_POST['stock_out'])){
    $item_id   = intval($_POST['item_id']);
    $qty       = intval($_POST['quantity']);
    $employee  = mysqli_real_escape_string($conn, trim($_POST['employee']));
    $dept      = mysqli_real_escape_string($conn, $_POST['department']);
    $purpose   = mysqli_real_escape_string($conn, trim($_POST['purpose']));
    $remarks   = mysqli_real_escape_string($conn, trim($_POST['remarks']));
    $done_by   = mysqli_real_escape_string($conn, $my_name);
    $item      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM store_items WHERE id=$item_id"));

    if($item && $qty > 0 && $item['quantity'] >= $qty){
        mysqli_query($conn,"UPDATE store_items SET quantity=quantity-$qty WHERE id=$item_id");
        mysqli_query($conn,"INSERT INTO store_log (item_id,item_name,type,quantity,employee,department,purpose,remarks,done_by)
            VALUES ($item_id,'".mysqli_real_escape_string($conn,$item['name'])."','OUT',$qty,'$employee','$dept','$purpose','$remarks','$done_by')");
        header("Location: store.php?toast=stockout"); exit;
    } else {
        $error = "Insufficient stock! Available: ".($item['quantity'] ?? 0)." ".$item['unit'];
    }
}

// ── UPDATE MIN QUANTITY
if(isset($_POST['update_min']) && $is_admin){
    $item_id = intval($_POST['item_id']);
    $min_qty = intval($_POST['min_quantity']);
    mysqli_query($conn,"UPDATE store_items SET min_quantity=$min_qty WHERE id=$item_id");
    header("Location: store.php?toast=updated"); exit;
}

// ── DATA
$filter_cat = $_GET['cat'] ?? '';
$item_where = $filter_cat ? "WHERE category='".mysqli_real_escape_string($conn,$filter_cat)."'" : "";
$items      = mysqli_query($conn,"SELECT * FROM store_items $item_where ORDER BY category, name");
$total_items= mysqli_num_rows($items);

// All categories for filter
$cats_res = mysqli_query($conn,"SELECT DISTINCT category FROM store_items WHERE category != '' ORDER BY category");
$all_cats = [];
while($c = mysqli_fetch_assoc($cats_res)) $all_cats[] = $c['category'];
$low_stock  = mysqli_query($conn,"SELECT * FROM store_items WHERE quantity <= min_quantity ORDER BY quantity ASC");
$low_count  = mysqli_num_rows($low_stock);
$recent_log = mysqli_query($conn,"SELECT * FROM store_log ORDER BY action_date DESC LIMIT 50");

// Stats
$total_out = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(quantity) as t FROM store_log WHERE type='OUT'"))['t'] ?? 0;
$total_in  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(quantity) as t FROM store_log WHERE type='IN'"))['t'] ?? 0;

// Categories
$categories_res = mysqli_query($conn,"SELECT DISTINCT category FROM store_items WHERE category != '' ORDER BY category");
$categories = [];
while($c = mysqli_fetch_assoc($categories_res)) $categories[] = $c['category'];

// Filter log
$log_filter_item = $_GET['log_item'] ?? '';
$log_filter_type = $_GET['log_type'] ?? '';
$log_where = "WHERE 1=1";
if($log_filter_item) $log_where .= " AND item_id=".intval($log_filter_item);
if($log_filter_type) $log_where .= " AND type='".mysqli_real_escape_string($conn,$log_filter_type)."'";
$filtered_log = mysqli_query($conn,"SELECT * FROM store_log $log_where ORDER BY action_date DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IT Store — Asset Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#080c10;--surface:#0d1117;--surface2:#161b22;--surface3:#1c2128;
    --border:#21262d;--border2:#30363d;--accent:#1f6feb;--green:#238636;
    --danger:#da3633;--warn:#d29922;--purple:#8b5cf6;--teal:#0891b2;
    --text:#cdd9e5;--muted:#768390;--radius:12px;--radius-sm:8px;
    --mono:'JetBrains Mono',monospace;--sans:'Space Grotesk',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--sans);background:var(--bg);color:var(--text);min-height:100vh;}

/* NAV */
nav{background:rgba(13,17,23,0.95);border-bottom:1px solid var(--border);padding:0 28px;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;backdrop-filter:blur(20px);}
.nav-brand{display:flex;align-items:center;gap:12px;}
.nav-logo{width:36px;height:36px;background:linear-gradient(135deg,var(--teal),var(--green));border-radius:10px;display:grid;place-items:center;font-size:18px;box-shadow:0 4px 12px rgba(8,145,178,0.3);}
.nav-brand span{font-weight:700;font-size:16px;}
.nav-right{display:flex;align-items:center;gap:10px;}
.nav-link{color:var(--muted);text-decoration:none;font-size:13px;border:1px solid var(--border2);padding:6px 14px;border-radius:var(--radius-sm);transition:all .2s;font-weight:500;}
.nav-link:hover{color:var(--text);border-color:var(--text);}

.page{max-width:1400px;margin:0 auto;padding:28px 24px;}

/* TOAST */
.toast-wrap{position:fixed;top:70px;right:20px;z-index:999;display:flex;flex-direction:column;gap:8px;}
.toast{background:var(--surface);border:1px solid var(--border2);padding:13px 20px;border-radius:var(--radius);font-size:14px;min-width:260px;box-shadow:0 8px 32px #0007;animation:slideIn .3s ease,fadeOut .4s ease 3.6s forwards;border-left:4px solid var(--green);}
.toast.error{border-left-color:var(--danger);}
.toast.warn{border-left-color:var(--warn);}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes fadeOut{to{opacity:0;transform:translateX(120%)}}

/* LOW STOCK ALERT */
.low-alert{background:#da363318;border:1px solid #da363340;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;animation:fadeUp .4s ease;}
.low-alert-title{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
.low-alert-title h3{font-size:15px;font-weight:700;color:#ff7b72;}
.low-chips{display:flex;gap:8px;flex-wrap:wrap;}
.low-chip{background:#da363330;border:1px solid #da363360;color:#ff7b72;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--mono);transition:all .2s;}
.low-chip:hover{background:#da363350;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;text-align:center;animation:fadeUp .4s ease both;transition:transform .2s,box-shadow .2s;}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 32px rgba(0,0,0,0.3);}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.stat-num{font-size:30px;font-weight:700;font-family:var(--mono);}
.stat-label{font-size:12px;color:var(--muted);margin-top:5px;}

/* LAYOUT */
.layout{display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;}
@media(max-width:1100px){.layout{grid-template-columns:1fr;}}

/* CARD */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:20px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.2);}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.card-header h3{font-size:15px;font-weight:600;display:flex;align-items:center;gap:8px;}
.card-body{padding:20px;}
.collapsible{overflow:hidden;transition:max-height .4s ease,opacity .3s;max-height:2000px;opacity:1;}
.collapsed .collapsible{max-height:0;opacity:0;}
.collapse-btn{cursor:pointer;user-select:none;}
.collapse-icon{transition:transform .3s;color:var(--muted);}
.collapsed .collapse-icon{transform:rotate(-90deg);}

/* FORM */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group.full{grid-column:1/-1;}
.form-group label{font-size:12px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}
.form-group input,.form-group select,.form-group textarea{background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:10px 14px;border-radius:var(--radius-sm);font-family:var(--sans);font-size:14px;transition:border-color .2s;width:100%;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(31,111,235,0.15);}
.form-group select option{background:var(--surface2);}
.btn{padding:10px 20px;border:none;border-radius:var(--radius-sm);font-family:var(--sans);font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:6px;}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{background:#388bfd;transform:translateY(-1px);}
.btn-success{background:var(--green);color:#fff;}
.btn-success:hover{background:#2ea043;transform:translateY(-1px);}
.btn-warn{background:var(--warn);color:#000;}
.btn-warn:hover{background:#e0a820;transform:translateY(-1px);}
.btn-danger{background:var(--danger);color:#fff;}
.btn-danger:hover{background:#f14b48;}
.btn-full{width:100%;justify-content:center;padding:12px;margin-top:8px;font-size:15px;}
.btn-sm{padding:5px 12px;font-size:12px;}

/* STOCK TABLE */
.table-wrap{overflow-x:auto;max-height:500px;overflow-y:auto;}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{background:var(--surface2);color:var(--muted);padding:11px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--border2);white-space:nowrap;font-weight:600;position:sticky;top:0;z-index:5;}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s;animation:rowIn .3s ease both;}
@keyframes rowIn{from{opacity:0;transform:translateX(-4px)}to{opacity:1;transform:translateX(0)}}
tbody tr:hover{background:var(--surface2);}
tbody td{padding:11px 14px;vertical-align:middle;}

/* STOCK BADGES */
.qty-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:700;font-family:var(--mono);}
.qty-ok   {background:#23863618;color:#3fb950;border:1px solid #23863640;}
.qty-low  {background:#d2992218;color:#d29922;border:1px solid #d2992240;animation:warnPulse 2s infinite;}
.qty-zero {background:#da363318;color:#ff7b72;border:1px solid #da363340;animation:dangerPulse 2s infinite;}
@keyframes warnPulse{0%,100%{box-shadow:0 0 0 0 #d2992230}50%{box-shadow:0 0 0 5px #d2992210}}
@keyframes dangerPulse{0%,100%{box-shadow:0 0 0 0 #da363330}50%{box-shadow:0 0 0 5px #da363310}}

.cat-badge{background:var(--surface2);border:1px solid var(--border2);color:var(--muted);font-size:11px;padding:2px 8px;border-radius:5px;font-family:var(--mono);}

/* LOG BADGES */
.type-in {display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;background:#23863618;color:#3fb950;border:1px solid #23863640;}
.type-out{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;background:#da363318;color:#ff7b72;border:1px solid #da363340;}

/* ACTION MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:#000b;z-index:200;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.active{display:flex;}
.modal{background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius);padding:28px;width:100%;max-width:480px;box-shadow:0 24px 64px #0009;animation:fadeUp .3s ease;position:relative;max-height:90vh;overflow-y:auto;}
.modal h3{font-size:16px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
.modal-close{position:absolute;top:14px;right:16px;background:transparent;border:none;color:var(--muted);font-size:20px;cursor:pointer;transition:color .2s;}
.modal-close:hover{color:var(--text);}
.modal-actions{display:flex;gap:8px;margin-top:16px;justify-content:flex-end;}

/* ALERT */
.alert{padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:16px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:8px;}
.alert.error{background:#da363318;border:1px solid #da363340;color:#ff7b72;}

/* CATEGORY FILTER */
.cat-filter-btn{
    display:inline-flex;align-items:center;gap:5px;
    padding:6px 16px;border-radius:20px;font-size:13px;font-weight:600;
    text-decoration:none;cursor:pointer;transition:all .2s;
    border:1px solid var(--border2);color:var(--muted);
    background:var(--surface);
}
.cat-filter-btn:hover{border-color:var(--text);color:var(--text);}
.cat-filter-btn.active{background:var(--green);border-color:var(--green);color:#fff;}

/* EMPTY */
.empty{text-align:center;padding:50px 20px;color:var(--muted);}
.empty .icon{font-size:44px;margin-bottom:12px;}

@media(max-width:600px){
    .stats{grid-template-columns:repeat(2,1fr);}
    .form-grid,.form-grid-3{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<nav>
    <div class="nav-brand">
        <div class="nav-logo">🏪</div>
        <span>IT Store</span>
    </div>
    <div class="nav-right">
        <a href="index.php" class="nav-link">📦 Assets</a>
        <a href="logout.php" class="nav-link" style="color:var(--danger);border-color:rgba(218,54,51,0.4);">Logout</a>
    </div>
</nav>

<div class="toast-wrap" id="toastWrap"></div>

<!-- Action Modals -->
<!-- STOCK IN MODAL -->
<div class="modal-overlay" id="inModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('inModal')">✕</button>
        <h3 style="color:#3fb950;">📥 Stock In</h3>
        <?php if(isset($error)): ?><div class="alert error">⚠ <?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="item_id" id="in_item_id">
            <div class="form-group" style="margin-bottom:14px;">
                <label>Item</label>
                <input type="text" id="in_item_name" readonly style="background:var(--surface3);color:var(--muted);">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label>Quantity *</label>
                <input type="number" name="quantity" min="1" required placeholder="How many?">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label>Remarks</label>
                <input type="text" name="remarks" placeholder="Source, vendor, etc.">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal('inModal')" class="btn" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);">Cancel</button>
                <button type="submit" name="stock_in" class="btn btn-success">📥 Stock In</button>
            </div>
        </form>
    </div>
</div>

<!-- STOCK OUT MODAL -->
<div class="modal-overlay" id="outModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('outModal')">✕</button>
        <h3 style="color:#ff7b72;">📤 Stock Out</h3>
        <form method="POST">
            <input type="hidden" name="item_id" id="out_item_id">
            <div class="form-group" style="margin-bottom:12px;">
                <label>Item</label>
                <input type="text" id="out_item_name" readonly style="background:var(--surface3);color:var(--muted);">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label>Available Stock</label>
                <input type="text" id="out_item_qty" readonly style="background:var(--surface3);color:#d29922;font-weight:700;">
            </div>
            <div class="form-grid" style="margin-bottom:12px;">
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" min="1" required placeholder="How many?">
                </div>
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department" required>
                        <option value="">Select</option>
                        <?php foreach(['IT','HR','Finance','Admin','Operations','Marketing','Sales'] as $d): ?>
                        <option><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label>Employee Name *</label>
                <input type="text" name="employee" required placeholder="Who is taking this?">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label>Purpose *</label>
                <input type="text" name="purpose" required placeholder="Why is this needed?">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label>Remarks</label>
                <input type="text" name="remarks" placeholder="Additional notes...">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal('outModal')" class="btn" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);">Cancel</button>
                <button type="submit" name="stock_out" class="btn btn-danger">📤 Stock Out</button>
            </div>
        </form>
    </div>
</div>

<div class="page">

<?php if(isset($error)): ?>
<div class="alert error" style="margin-bottom:20px;">⚠ <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- EXPORT BUTTONS -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;">
    <span style="font-size:13px;color:var(--muted);font-weight:600;">⬇ Export:</span>

    <!-- Stock Export -->
    <a href="store_export.php?type=stock"
       class="btn btn-success btn-sm"
       style="text-decoration:none;">
        📦 Current Stock (CSV)
    </a>

    <!-- Log Export with date range -->
    <form method="GET" action="store_export.php" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="type" value="log">
        <input type="date" name="from" value="<?php echo date('Y-m-01'); ?>"
               style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:5px 10px;border-radius:var(--radius-sm);font-family:var(--sans);font-size:12px;">
        <span style="color:var(--muted);font-size:12px;">to</span>
        <input type="date" name="to" value="<?php echo date('Y-m-d'); ?>"
               style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:5px 10px;border-radius:var(--radius-sm);font-family:var(--sans);font-size:12px;">
        <button type="submit" class="btn btn-primary btn-sm">📋 IN/OUT Log (CSV)</button>
    </form>
</div>

<!-- LOW STOCK ALERT -->
<?php if($low_count > 0): ?>
<div class="low-alert">
    <div class="low-alert-title">
        <span style="font-size:22px;">🚨</span>
        <h3><?php echo $low_count; ?> item(s) running low on stock!</h3>
    </div>
    <div class="low-chips">
        <?php
        mysqli_data_seek($low_stock, 0);
        while($ls = mysqli_fetch_assoc($low_stock)):
            $color = $ls['quantity'] == 0 ? '#ff7b72' : '#d29922';
        ?>
        <span class="low-chip" style="border-color:<?php echo $color; ?>55;color:<?php echo $color; ?>;background:<?php echo $color; ?>18;"
              onclick="openOutModal(<?php echo $ls['id']; ?>,'<?php echo addslashes($ls['name']); ?>',<?php echo $ls['quantity']; ?>)">
            <?php echo htmlspecialchars($ls['name']); ?> — <?php echo $ls['quantity']; ?> <?php echo $ls['unit']; ?> left
        </span>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats">
    <div class="stat-card" style="border-top:3px solid var(--teal);animation-delay:.05s">
        <div style="font-size:22px;margin-bottom:6px;">📦</div>
        <div class="stat-num" style="color:#22d3ee;" data-target="<?php echo $total_items; ?>">0</div>
        <div class="stat-label">Total Items</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--danger);animation-delay:.1s">
        <div style="font-size:22px;margin-bottom:6px;">🚨</div>
        <div class="stat-num" style="color:#ff7b72;" data-target="<?php echo $low_count; ?>">0</div>
        <div class="stat-label">Low Stock</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--green);animation-delay:.15s">
        <div style="font-size:22px;margin-bottom:6px;">📥</div>
        <div class="stat-num" style="color:#3fb950;" data-target="<?php echo $total_in; ?>">0</div>
        <div class="stat-label">Total IN</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--warn);animation-delay:.2s">
        <div style="font-size:22px;margin-bottom:6px;">📤</div>
        <div class="stat-num" style="color:#d29922;" data-target="<?php echo $total_out; ?>">0</div>
        <div class="stat-label">Total OUT</div>
    </div>
</div>

<div class="layout">
<div>

<!-- CATEGORY FILTER -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
    <span style="font-size:13px;color:var(--muted);font-weight:500;">Filter:</span>
    <a href="store.php" 
       class="cat-filter-btn <?php echo $filter_cat==''?'active':''; ?>">
        📦 All
    </a>
    <a href="store.php?cat=Network" 
       class="cat-filter-btn <?php echo $filter_cat=='Network'?'active':''; ?>" 
       style="<?php echo $filter_cat=='Network'?'background:#0891b2;border-color:#0891b2;color:#fff;':''; ?>">
        🌐 Network
    </a>
    <a href="store.php?cat=Operations" 
       class="cat-filter-btn <?php echo $filter_cat=='Operations'?'active':''; ?>"
       style="<?php echo $filter_cat=='Operations'?'background:#8b5cf6;border-color:#8b5cf6;color:#fff;':''; ?>">
        ⚙️ Operations
    </a>
    <?php foreach($all_cats as $cat):
        if(in_array($cat, ['Network','Operations'])) continue;
    ?>
    <a href="store.php?cat=<?php echo urlencode($cat); ?>" 
       class="cat-filter-btn <?php echo $filter_cat==$cat?'active':''; ?>">
        <?php echo htmlspecialchars($cat); ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- STOCK TABLE -->
<div class="card">
    <div class="card-header">
        <h3>
            📦 <?php echo $filter_cat ? htmlspecialchars($filter_cat).' Items' : 'All Stock'; ?>
            <span style="background:var(--surface2);border:1px solid var(--border2);color:var(--muted);font-size:12px;padding:2px 10px;border-radius:20px;font-family:var(--mono);font-weight:400;"><?php echo $total_items; ?></span>
            <?php if($filter_cat): ?>
            <a href="store.php" style="font-size:12px;color:var(--muted);font-weight:400;margin-left:4px;">✕ Clear</a>
            <?php endif; ?>
        </h3>
        <?php if($is_admin): ?>
        <button onclick="toggleCard('addCard')" class="btn btn-success btn-sm">+ Add Item</button>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
    <?php if($total_items > 0):
        mysqli_data_seek($items, 0); $i=1;
        while($item = mysqli_fetch_assoc($items)):
            $qty = $item['quantity'];
            $min = $item['min_quantity'];
            $qty_class = $qty == 0 ? 'qty-zero' : ($qty <= $min ? 'qty-low' : 'qty-ok');
    ?>
    <table>
        <?php if($i == 1): ?>
        <thead>
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Min Qty</th>
                <th>Unit</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php endif; ?>
        <tr style="animation-delay:<?php echo $i*0.03; ?>s">
            <td style="color:var(--muted);font-family:var(--mono);font-size:12px;"><?php echo $i++; ?></td>
            <td>
                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                <?php if($item['description']): ?>
                <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?php echo htmlspecialchars($item['description']); ?></div>
                <?php endif; ?>
            </td>
            <td><span class="cat-badge"><?php echo htmlspecialchars($item['category']); ?></span></td>
            <td><span class="qty-badge <?php echo $qty_class; ?>"><?php echo $qty; ?></span></td>
            <td style="color:var(--muted);font-family:var(--mono);font-size:13px;"><?php echo $min; ?></td>
            <td style="color:var(--muted);font-size:12px;"><?php echo $item['unit']; ?></td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                    <?php if($is_admin): ?>
                    <button onclick="openInModal(<?php echo $item['id']; ?>,'<?php echo addslashes($item['name']); ?>')" 
                            class="btn btn-success btn-sm">📥 IN</button>
                    <?php endif; ?>
                    <button onclick="openOutModal(<?php echo $item['id']; ?>,'<?php echo addslashes($item['name']); ?>',<?php echo $qty; ?>)"
                            class="btn btn-warn btn-sm" <?php echo $qty==0?'disabled style="opacity:.5;cursor:not-allowed;"':''; ?>>📤 OUT</button>
                    <button onclick="openLogModal(<?php echo $item['id']; ?>,'<?php echo addslashes($item['name']); ?>')"
                            class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);">📋 Log</button>
                    <?php if($is_super): ?>
                    <a href="store.php?delete_item=<?php echo $item['id']; ?>" 
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete <?php echo addslashes($item['name']); ?>?')">🗑</a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
        <div class="icon">📦</div>
        <p>No items in store yet.<br>Add your first item!</p>
    </div>
    <?php endif; ?>
    </div>
</div>

<!-- ADD ITEM FORM -->
<?php if($is_admin): ?>
<div class="card" id="addCard" style="display:none;">
    <div class="card-header">
        <h3>➕ Add New Item</h3>
        <button onclick="toggleCard('addCard')" class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);">✕ Close</button>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Item Name *</label>
                    <input type="text" name="name" required placeholder="e.g. HDD 1TB, Pendrive 16GB, etc.">
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <input type="text" name="category" required placeholder="e.g. Storage, Networking, Cable" list="cat-list">
                    <datalist id="cat-list">
                        <option>Network</option>
                        <option>Operations</option>
                        <option>Storage</option>
                        <option>Cable</option>
                        <option>Peripheral</option>
                        <option>Power</option>
                        <option>Memory</option>
                        <option>Other</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <select name="unit">
                        <option value="pcs">Pieces (pcs)</option>
                        <option value="meters">Meters</option>
                        <option value="boxes">Boxes</option>
                        <option value="rolls">Rolls</option>
                        <option value="sets">Sets</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Initial Quantity</label>
                    <input type="number" name="quantity" min="0" value="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Minimum Alert Quantity</label>
                    <input type="number" name="min_quantity" min="1" value="2" placeholder="2">
                </div>
                <div class="form-group full">
                    <label>Description (optional)</label>
                    <input type="text" name="description" placeholder="Brand, specs, or notes...">
                </div>
            </div>
            <button type="submit" name="add_item" class="btn btn-success btn-full">➕ Add to Store</button>
        </form>
    </div>
</div>
<?php endif; ?>

</div>

<!-- LOG PANEL -->
<div>
<div class="card" id="logCard">
    <div class="card-header">
        <h3>📋 Activity Log</h3>
    </div>
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:8px;">
        <select id="logTypeFilter" onchange="filterLog()" style="flex:1;background:var(--surface2);border:1px solid var(--border2);color:var(--text);padding:7px 10px;border-radius:var(--radius-sm);font-family:var(--sans);font-size:13px;">
            <option value="">All Types</option>
            <option value="IN">📥 IN Only</option>
            <option value="OUT">📤 OUT Only</option>
        </select>
        <button onclick="clearLogFilter()" class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border2);color:var(--text);">Clear</button>
    </div>
    <div style="max-height:600px;overflow-y:auto;" id="logList">
    <?php
    mysqli_data_seek($filtered_log, 0);
    $li = 0;
    while($log = mysqli_fetch_assoc($filtered_log)):
        $li++;
        $is_in = $log['type'] == 'IN';
    ?>
    <div class="log-item" data-type="<?php echo $log['type']; ?>"
         style="padding:12px 16px;border-bottom:1px solid var(--border);animation:rowIn .3s ease both;animation-delay:<?php echo $li*0.02; ?>s;border-left:3px solid <?php echo $is_in?'#3fb950':'#ff7b72'; ?>;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
            <span class="<?php echo $is_in?'type-in':'type-out'; ?>">
                <?php echo $is_in?'📥 IN':'📤 OUT'; ?>
            </span>
            <span style="font-size:11px;color:var(--muted);font-family:var(--mono);">
                <?php echo date('d M, h:i A', strtotime($log['action_date'])); ?>
            </span>
        </div>
        <div style="font-size:13px;font-weight:600;margin-bottom:3px;">
            <?php echo htmlspecialchars($log['item_name']); ?>
            <span style="color:<?php echo $is_in?'#3fb950':'#ff7b72'; ?>;font-family:var(--mono);font-size:14px;margin-left:6px;">
                <?php echo $is_in?'+':'-'; ?><?php echo $log['quantity']; ?>
            </span>
        </div>
        <?php if(!$is_in && $log['employee']): ?>
        <div style="font-size:12px;color:var(--muted);">
            👤 <?php echo htmlspecialchars($log['employee']); ?>
            <?php if($log['department']): ?> · 🏢 <?php echo $log['department']; ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if($log['purpose']): ?>
        <div style="font-size:12px;color:var(--muted);margin-top:2px;">📝 <?php echo htmlspecialchars($log['purpose']); ?></div>
        <?php endif; ?>
        <div style="font-size:11px;color:var(--muted);margin-top:3px;">By: <?php echo htmlspecialchars($log['done_by']); ?></div>
    </div>
    <?php endwhile;
    if($li == 0): ?>
    <div class="empty" style="padding:30px;">
        <div class="icon" style="font-size:32px;">📋</div>
        <p>No activity yet.</p>
    </div>
    <?php endif; ?>
    </div>
</div>
</div>

</div><!-- /layout -->
</div><!-- /page -->

<script>
// Counter animation
document.querySelectorAll('.stat-num[data-target]').forEach(el => {
    const target = +el.dataset.target;
    if(target === 0) return;
    let current = 0;
    const step = Math.max(1, Math.ceil(target/30));
    const timer = setInterval(() => {
        current = Math.min(current+step, target);
        el.textContent = current;
        if(current >= target) clearInterval(timer);
    }, 40);
});

// Toast
const toastMsgs = {
    'added':    '✅ Item added to store!',
    'deleted':  '🗑 Item deleted.',
    'stockin':  '📥 Stock added successfully!',
    'stockout': '📤 Stock issued successfully!',
    'updated':  '✅ Updated successfully!',
};
const urlP = new URLSearchParams(location.search);
const tk   = urlP.get('toast');
if(tk && toastMsgs[tk]){
    showToast(toastMsgs[tk], tk==='deleted'?'error':'');
    history.replaceState({}, '', location.pathname);
}
function showToast(msg, type=''){
    const wrap = document.getElementById('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast '+(type==='error'?'error':'');
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// Card toggle
function toggleCard(id){
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// Modal functions
function openInModal(id, name){
    document.getElementById('in_item_id').value = id;
    document.getElementById('in_item_name').value = name;
    document.getElementById('inModal').classList.add('active');
}
function openOutModal(id, name, qty){
    document.getElementById('out_item_id').value = id;
    document.getElementById('out_item_name').value = name;
    document.getElementById('out_item_qty').value = qty + ' pcs available';
    document.getElementById('outModal').classList.add('active');
}
function openLogModal(id, name){
    // Filter log by item
    document.querySelectorAll('.log-item').forEach(el => {
        el.style.display = '';
    });
    window.location.href = 'store.php?log_item='+id;
}
function closeModal(id){
    document.getElementById(id).classList.remove('active');
}
// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e){
        if(e.target === this) closeModal(this.id);
    });
});

// Log filter
function filterLog(){
    const type = document.getElementById('logTypeFilter').value;
    document.querySelectorAll('.log-item').forEach(el => {
        if(!type || el.dataset.type === type){
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
}
function clearLogFilter(){
    document.getElementById('logTypeFilter').value = '';
    filterLog();
}
</script>
</body>
</html>
