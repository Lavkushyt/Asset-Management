<?php
include 'auth.php';
include 'db.php';

$search        = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type']   ?? '';

$where = "WHERE 1=1";
if($search != ''){
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (asset_Tag LIKE '%$s%' OR employee LIKE '%$s%' OR model LIKE '%$s%' OR type LIKE '%$s%' OR department LIKE '%$s%' OR serial_no LIKE '%$s%' OR vendor LIKE '%$s%')";
}
if($filter_status != ''){
    $fs = mysqli_real_escape_string($conn, $filter_status);
    $where .= " AND status='$fs'";
}
if($filter_type != ''){
    $ft = mysqli_real_escape_string($conn, $filter_type);
    $where .= " AND type='$ft'";
}

$result     = mysqli_query($conn, "SELECT * FROM assets $where ORDER BY id DESC");
$total_rows = mysqli_num_rows($result);
$types_res  = mysqli_query($conn, "SELECT DISTINCT type FROM assets WHERE type != '' ORDER BY type");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Assets — IT Asset Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0d1117;--surface:#161b22;--surface2:#21262d;
    --border:#30363d;--accent:#1f6feb;--green:#238636;
    --danger:#da3633;--warn:#d29922;--text:#e6edf3;--muted:#8b949e;
    --radius:10px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Space Grotesk',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

nav{
    background:var(--surface);border-bottom:1px solid var(--border);
    padding:0 24px;display:flex;align-items:center;
    justify-content:space-between;height:60px;
    position:sticky;top:0;z-index:100;
}
.nav-brand{display:flex;align-items:center;gap:10px;}
.nav-logo{width:32px;height:32px;background:var(--accent);border-radius:8px;display:grid;place-items:center;font-size:16px;}
.nav-brand span{font-weight:700;font-size:15px;}
.nav-right{display:flex;align-items:center;gap:10px;}
.nav-back{
    display:inline-flex;align-items:center;gap:6px;color:var(--muted);
    text-decoration:none;font-size:13px;border:1px solid var(--border);
    padding:6px 14px;border-radius:7px;transition:all .2s;
}
.nav-back:hover{color:var(--text);border-color:var(--text);}
.export-btn{
    display:inline-flex;align-items:center;gap:6px;padding:7px 14px;
    background:var(--surface2);border:1px solid var(--border);border-radius:7px;
    color:var(--text);text-decoration:none;font-size:13px;font-weight:600;transition:all .2s;
}
.export-btn:hover{border-color:var(--green);color:var(--green);}

.page{max-width:1700px;margin:0 auto;padding:24px 20px;}

.filter-bar{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);padding:16px 20px;
    margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;
}
.filter-group{display:flex;flex-direction:column;gap:5px;flex:1;min-width:180px;}
.filter-group label{font-size:12px;color:var(--muted);font-weight:500;text-transform:uppercase;letter-spacing:.4px;}
.filter-group input,
.filter-group select{
    background:var(--surface2);border:1px solid var(--border);color:var(--text);
    padding:9px 14px;border-radius:7px;font-family:'Space Grotesk',sans-serif;
    font-size:13px;transition:border-color .2s;
}
.filter-group input:focus,
.filter-group select:focus{outline:none;border-color:var(--accent);}
.filter-group select option{background:var(--surface2);}
.btn{
    padding:9px 18px;border:none;border-radius:7px;font-family:'Space Grotesk',sans-serif;
    font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;
    display:inline-flex;align-items:center;gap:6px;text-decoration:none;white-space:nowrap;
}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{background:#388bfd;transform:translateY(-1px);}
.btn-clear{background:var(--surface2);border:1px solid var(--border);color:var(--text);}
.btn-clear:hover{border-color:var(--muted);}

.result-info{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:12px;flex-wrap:wrap;gap:8px;
}
.result-info h2{font-size:16px;font-weight:600;display:flex;align-items:center;gap:8px;}
.count-badge{
    background:var(--surface2);border:1px solid var(--border);
    color:var(--muted);font-size:12px;padding:2px 10px;
    border-radius:20px;font-family:'JetBrains Mono',monospace;
}
.filter-tags{display:flex;gap:6px;flex-wrap:wrap;}
.filter-tag{
    background:#1f6feb22;border:1px solid #1f6feb55;
    color:var(--accent);font-size:11px;padding:2px 10px;
    border-radius:20px;font-family:'JetBrains Mono',monospace;
}

.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{
    background:var(--surface2);color:var(--muted);padding:11px 14px;text-align:left;
    font-size:11px;text-transform:uppercase;letter-spacing:.6px;
    border-bottom:1px solid var(--border);white-space:nowrap;font-weight:600;
}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s;animation:rowIn .35s ease both;}
@keyframes rowIn{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
tbody tr:hover{background:var(--surface2);}
tbody td{padding:10px 14px;vertical-align:middle;}

.tag{
    font-family:'JetBrains Mono',monospace;font-size:12px;
    background:var(--surface2);border:1px solid var(--border);
    padding:3px 8px;border-radius:5px;color:var(--accent);white-space:nowrap;
}
.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
.badge.issued{background:#23863618;color:#3fb950;border:1px solid #23863640;}
.badge.issued::before{content:'';width:6px;height:6px;border-radius:50%;background:#3fb950;animation:pulse 2s infinite;}
.badge.returned{background:#8b949e18;color:#8b949e;border:1px solid #8b949e40;}
.badge.returned::before{content:'';width:6px;height:6px;border-radius:50%;background:#8b949e;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 3px #3fb95033}50%{box-shadow:0 0 0 6px #3fb95011}}
.serial{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);}
.price{font-family:'JetBrains Mono',monospace;font-size:13px;color:#3fb950;white-space:nowrap;}

.actions{display:flex;gap:5px;flex-wrap:wrap;}
.act-btn{
    padding:4px 9px;border-radius:5px;font-size:11px;font-weight:600;
    text-decoration:none;transition:all .15s;border:1px solid transparent;
    cursor:pointer;background:transparent;font-family:'Space Grotesk',sans-serif;white-space:nowrap;
}
.act-btn.edit{color:var(--accent);border-color:var(--accent);}
.act-btn.edit:hover{background:var(--accent);color:#fff;}
.act-btn.del{color:var(--danger);border-color:var(--danger);}
.act-btn.del:hover{background:var(--danger);color:#fff;}
.act-btn.ret{color:var(--warn);border-color:var(--warn);}
.act-btn.ret:hover{background:var(--warn);color:#000;}
.act-btn.hist{color:var(--muted);border-color:var(--border);}
.act-btn.hist:hover{background:var(--surface2);color:var(--text);}

.empty{text-align:center;padding:60px 20px;color:var(--muted);}
.empty .icon{font-size:48px;margin-bottom:14px;}
.empty p{font-size:15px;}

@media(max-width:768px){
    .filter-bar{flex-direction:column;}
    .filter-group{min-width:100%;}
}
</style>
</head>
<body>

<nav>
    <div class="nav-brand">
        <div class="nav-logo">📦</div>
        <span>All Assets</span>
    </div>
    <div class="nav-right">
        <a href="export.php" class="export-btn">⬇ Export CSV</a>
        <a href="index.php" class="nav-back">← Dashboard</a>
    </div>
</nav>

<div class="page">

    <form method="GET">
        <div class="filter-bar">
            <div class="filter-group" style="flex:2;min-width:220px;">
                <label>🔍 Search</label>
                <input type="text" name="search" id="searchInput" autocomplete="off"
                       placeholder="Tag, employee, model, serial, vendor..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>📂 Type</label>
                <select name="type">
                    <option value="">All Types</option>
                    <?php while($t = mysqli_fetch_assoc($types_res)):
                        $sel = $filter_type == $t['type'] ? 'selected' : ''; ?>
                        <option <?php echo $sel; ?>><?php echo htmlspecialchars($t['type']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>📊 Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option <?php echo $filter_status=='Issued'   ?'selected':''; ?> value="Issued">Issued</option>
                    <option <?php echo $filter_status=='Returned' ?'selected':''; ?> value="Returned">Returned</option>
                    <option <?php echo $filter_status=='Available'?'selected':''; ?> value="Available">Available</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <?php if($search || $filter_status || $filter_type): ?>
                <a href="all_assets.php" class="btn btn-clear">✕ Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="result-info">
        <h2>
            📋 All Assets
            <span class="count-badge"><?php echo $total_rows; ?> records</span>
        </h2>
        <div class="filter-tags">
            <?php if($search):        ?><span class="filter-tag">search: <?php echo htmlspecialchars($search); ?></span><?php endif; ?>
            <?php if($filter_type):   ?><span class="filter-tag">type: <?php echo htmlspecialchars($filter_type); ?></span><?php endif; ?>
            <?php if($filter_status): ?><span class="filter-tag">status: <?php echo htmlspecialchars($filter_status); ?></span><?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
        <?php if($total_rows > 0): ?>
        <table id="assetsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Asset Tag</th>
                    <th>Type</th>
                    <th>Model</th>
                    <th>Config</th>
                    <th>Serial No.</th>
                    <th>Employee</th>
                    <th>Status</th>
                    <th>Issue Date</th>
                    <th>Return Date</th>
                    <th>Purchase Date</th>
                    <th>Department</th>
                    <th>Location</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Vendor</th>
                    <th>Item Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php $i=1; while($r = mysqli_fetch_assoc($result)):
                $sc = strtolower($r['status']) === 'issued' ? 'issued' : 'returned';
            ?>
            <tr style="animation-delay:<?php echo min($i * 0.025, 0.6); ?>s">
                <td style="color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:12px;"><?php echo $i++; ?></td>
                <td><span class="tag"><?php echo htmlspecialchars($r['asset_Tag']); ?></span></td>
                <td><?php echo htmlspecialchars($r['type']); ?></td>
                <td style="white-space:nowrap;"><?php echo htmlspecialchars($r['model']); ?></td>
                <td style="color:var(--muted);font-size:12px;"><?php echo htmlspecialchars($r['Config']); ?></td>
                <td><span class="serial"><?php echo htmlspecialchars($r['serial_no']); ?></span></td>
                <td><strong><?php echo htmlspecialchars($r['employee']); ?></strong></td>
                <td><span class="badge <?php echo $sc; ?>"><?php echo $r['status']; ?></span></td>
                <td style="font-size:12px;color:var(--muted);white-space:nowrap;">
                    <?php echo $r['issue_date']    ? date('d M Y', strtotime($r['issue_date']))    : '—'; ?>
                </td>
                <td style="font-size:12px;color:var(--muted);white-space:nowrap;">
                    <?php echo $r['return_date']   ? date('d M Y', strtotime($r['return_date']))   : '—'; ?>
                </td>
                <td style="font-size:12px;color:var(--muted);white-space:nowrap;">
                    <?php echo $r['purchase_date'] ? date('d M Y', strtotime($r['purchase_date'])) : '—'; ?>
                </td>
                <td><?php echo htmlspecialchars($r['department']); ?></td>
                <td><?php echo htmlspecialchars($r['location']); ?></td>
                <td style="font-size:12px;white-space:nowrap;"><?php echo htmlspecialchars($r['phone']); ?></td>
                <td style="font-size:12px;"><?php echo htmlspecialchars($r['email']); ?></td>
                <td><?php echo htmlspecialchars($r['vendor']); ?></td>
                <td class="price"><?php echo $r['item_price'] ? '₹'.number_format($r['item_price'],0) : '—'; ?></td>
                <td>
                    <div class="actions">
                        <?php if(in_array($_SESSION['role'],['super_admin','admin'])): ?>
                            <a href="index.php?edit=<?php echo $r['id']; ?>" class="act-btn edit">✏ Edit</a>
                        <?php endif; ?>
                        <?php if($r['status'] == 'Issued'): ?>
                            <a href="index.php?return=<?php echo $r['id']; ?>"
                               class="act-btn ret"
                               onclick="return confirm('Mark this asset as returned?')">↩ Return</a>
                        <?php endif; ?>
                        <a href="history.php?tag=<?php echo urlencode($r['asset_Tag']); ?>" class="act-btn hist">📜 History</a>
                        <a href="asset_complaints.php?tag=<?php echo urlencode($r['asset_Tag']); ?>" class="act-btn" style="color:#d29922;border-color:#d29922;">📊 Complaints</a>
                        <?php if($_SESSION['role'] == 'super_admin'): ?>
                            <a href="index.php?delete=<?php echo $r['id']; ?>"
                               class="act-btn del"
                               onclick="return confirm('Permanently delete this asset?')">🗑 Delete</a>
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
            <p>
                <?php echo ($search || $filter_type || $filter_status)
                    ? "No results found — try different filters."
                    : "No assets found in the database."; ?>
            </p>
        </div>
        <?php endif; ?>
        </div>
    </div>

</div>

<script>
const inp  = document.getElementById('searchInput');
const tbody = document.querySelector('#assetsTable tbody');
if(inp && tbody){
    inp.addEventListener('input', function(){
        const q = this.value.toLowerCase();
        tbody.querySelectorAll('tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
</script>
</body>
</html>
