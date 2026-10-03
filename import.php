<?php
include 'auth.php';
include 'db.php';
checkRole(['super_admin','admin']);

$results  = [];
$imported = 0;
$skipped  = 0;
$errors   = 0;

if(isset($_POST['import']) && isset($_FILES['csvfile'])){
    $file = $_FILES['csvfile']['tmp_name'];

    if(!file_exists($file)){
        $results[] = ['type'=>'error','msg'=>'File upload failed!'];
    } else {
        $handle = fopen($file, 'r');
        $row_num = 0;

        while(($row = fgetcsv($handle, 2000, ',')) !== false){
            $row_num++;

            // Skip header rows — row 1 (title) and row 2 (column names)
            if($row_num <= 2) continue;

            // Skip empty rows
            if(empty(array_filter($row))) continue;

            // Map columns from CSV
            $employee     = trim($row[0] ?? '');
            $location     = trim($row[1] ?? '');
            $department   = trim($row[2] ?? '');
            $type         = trim($row[3] ?? '');
            $asset_tag    = trim($row[4] ?? '');
            $vendor       = trim($row[5] ?? '');
            $model        = trim($row[6] ?? '');
            $serial_no    = trim($row[7] ?? '');
            // Config = 3 columns combined
            $config       = trim(implode(' / ', array_filter([
                trim($row[8] ?? ''),
                trim($row[9] ?? ''),
                trim($row[10] ?? '')
            ])));
            $purchase_date_raw = trim($row[11] ?? '');

            // Skip if no asset tag
            if(empty($asset_tag)) continue;

            // Purchase date format fix
            $purchase_date = 'NULL';
            if(!empty($purchase_date_raw)){
                // Clean up date string
                $purchase_date_raw = trim($purchase_date_raw);
                // Try multiple formats
                $formats = ['d-m-Y','d/m/Y','Y-m-d','m/d/Y','d-m-y','d.m.Y'];
                foreach($formats as $fmt){
                    $d = DateTime::createFromFormat($fmt, $purchase_date_raw);
                    if($d && $d->format($fmt) == $purchase_date_raw){
                        $purchase_date = "'".$d->format('Y-m-d')."'";
                        break;
                    }
                    // Try without strict check
                    if($d){
                        $purchase_date = "'".$d->format('Y-m-d')."'";
                        break;
                    }
                }
            }

            // Status — agar employee blank hai toh Available
            $status = (!empty($employee) && strtolower($employee) != 'spare with it' && stripos($employee,'scrap')===false) ? 'Issued' : 'Available';
            if(stripos($employee,'scrap') !== false || stripos($employee,'spare') !== false){
                $status   = 'Available';
                $employee = '';
            }

            // Escape
            $asset_tag  = mysqli_real_escape_string($conn, $asset_tag);
            $employee   = mysqli_real_escape_string($conn, $employee);
            $location   = mysqli_real_escape_string($conn, $location);
            $department = mysqli_real_escape_string($conn, $department);
            $type       = mysqli_real_escape_string($conn, $type);
            $vendor     = mysqli_real_escape_string($conn, $vendor);
            $model      = mysqli_real_escape_string($conn, $model);
            $serial_no  = mysqli_real_escape_string($conn, $serial_no);
            $config     = mysqli_real_escape_string($conn, $config);

            // Check duplicate asset tag
            $check = mysqli_query($conn,"SELECT id FROM assets WHERE asset_Tag='$asset_tag'");
            if(mysqli_num_rows($check) > 0){
                $skipped++;
                $results[] = ['type'=>'skip','msg'=>"Skip: $asset_tag — already exists — skipped"];
                continue;
            }

            // Insert
            $sql = "INSERT INTO assets 
                (asset_Tag, type, model, Config, serial_no, employee, status, purchase_date, department, location, vendor, issue_date)
                VALUES 
                ('$asset_tag','$type','$model','$config','$serial_no','$employee','$status',$purchase_date,'$department','$location','$vendor', NOW())";

            if(mysqli_query($conn, $sql)){
                $imported++;
                $results[] = ['type'=>'success','msg'=>"✅ Imported: $asset_tag — $model ($employee)"];

                // History entry if issued
                if($status == 'Issued' && !empty($employee)){
                    $now = date('Y-m-d H:i:s');
                    mysqli_query($conn,"INSERT INTO asset_history (asset_tag,employee,action,action_date) VALUES ('$asset_tag','$employee','Issued','$now')");
                }
            } else {
                $errors++;
                $results[] = ['type'=>'error','msg'=>"❌ Error: $asset_tag — ".mysqli_error($conn)];
            }
        }
        fclose($handle);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Import Assets — IT Asset Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d1117;--surface:#161b22;--surface2:#21262d;--border:#30363d;--accent:#1f6feb;--green:#238636;--danger:#da3633;--warn:#d29922;--text:#e6edf3;--muted:#8b949e;--radius:10px;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Space Grotesk',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
nav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100;}
.nav-brand{display:flex;align-items:center;gap:10px;}
.nav-logo{width:32px;height:32px;background:var(--green);border-radius:8px;display:grid;place-items:center;font-size:16px;}
.nav-brand span{font-weight:700;font-size:15px;}
.nav-link{color:var(--muted);text-decoration:none;font-size:13px;border:1px solid var(--border);padding:6px 14px;border-radius:7px;transition:all .2s;}
.nav-link:hover{color:var(--text);border-color:var(--text);}
.page{max-width:900px;margin:0 auto;padding:24px 20px;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;text-align:center;animation:fadeUp .4s ease both;}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.stat-num{font-size:32px;font-weight:700;font-family:'JetBrains Mono',monospace;}
.stat-label{font-size:12px;color:var(--muted);margin-top:6px;}

/* UPLOAD CARD */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:20px;overflow:hidden;}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);}
.card-header h3{font-size:15px;font-weight:600;display:flex;align-items:center;gap:8px;}
.card-body{padding:24px;}

/* UPLOAD ZONE */
.upload-zone{
    border:2px dashed var(--border);border-radius:var(--radius);
    padding:40px;text-align:center;cursor:pointer;
    transition:all .3s;margin-bottom:16px;
}
.upload-zone:hover{border-color:var(--accent);background:#1f6feb08;}
.upload-zone.dragover{border-color:var(--green);background:#23863610;}
.upload-icon{font-size:48px;margin-bottom:12px;}
.upload-zone h4{font-size:16px;font-weight:600;margin-bottom:6px;}
.upload-zone p{font-size:13px;color:var(--muted);}
#fileInput{display:none;}
.file-selected{background:var(--surface2);border:1px solid var(--green);border-radius:7px;padding:10px 14px;font-size:13px;color:#3fb950;margin-bottom:16px;display:none;}

/* FORMAT INFO */
.format-info{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px;}
.format-info h4{font-size:13px;font-weight:600;color:var(--muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px;}
.col-list{display:flex;flex-wrap:wrap;gap:6px;}
.col-tag{background:var(--bg);border:1px solid var(--border);color:var(--accent);font-size:12px;padding:3px 10px;border-radius:5px;font-family:'JetBrains Mono',monospace;}

.btn{padding:12px 24px;border:none;border-radius:8px;font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:6px;}
.btn-success{background:var(--green);color:#fff;width:100%;justify-content:center;font-size:15px;padding:14px;}
.btn-success:hover{background:#2ea043;transform:translateY(-1px);}
.btn-success:disabled{background:#8b949e;cursor:not-allowed;transform:none;}

/* RESULTS */
.results-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.results-header{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.results-header h3{font-size:14px;font-weight:600;}
.results-list{max-height:400px;overflow-y:auto;padding:12px;}
.result-item{padding:7px 12px;border-radius:6px;font-size:13px;margin-bottom:4px;font-family:'JetBrains Mono',monospace;}
.result-item.success{background:#23863612;color:#3fb950;}
.result-item.skip{background:#d2992212;color:#d29922;}
.result-item.error{background:#da363312;color:#ff7b72;}
</style>
</head>
<body>
<nav>
    <div class="nav-brand">
        <div class="nav-logo">📥</div>
        <span>Import Assets</span>
    </div>
    <a href="index.php" class="nav-link">← Dashboard</a>
</nav>

<div class="page">

<?php if($imported > 0 || $skipped > 0 || $errors > 0): ?>
<!-- RESULTS STATS -->
<div class="stats">
    <div class="stat-card" style="border-top:3px solid var(--green);">
        <div style="font-size:24px;margin-bottom:8px;">✅</div>
        <div class="stat-num" style="color:#3fb950;"><?php echo $imported; ?></div>
        <div class="stat-label">Successfully Imported</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--warn);">
        <div style="font-size:24px;margin-bottom:8px;">⏭</div>
        <div class="stat-num" style="color:var(--warn);"><?php echo $skipped; ?></div>
        <div class="stat-label">Skipped (Duplicate)</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--danger);">
        <div style="font-size:24px;margin-bottom:8px;">❌</div>
        <div class="stat-num" style="color:#ff7b72;"><?php echo $errors; ?></div>
        <div class="stat-label">Errors</div>
    </div>
</div>
<?php endif; ?>

<!-- UPLOAD FORM -->
<div class="card">
    <div class="card-header">
        <h3>📥 CSV File Upload Submit</h3>
    </div>
    <div class="card-body">
        <div class="format-info">
            <h4>📋 CSV Format — Aapki File ka Column Order</h4>
            <div class="col-list">
                <span class="col-tag">1. Employee</span>
                <span class="col-tag">2. Location</span>
                <span class="col-tag">3. Department</span>
                <span class="col-tag">4. Type</span>
                <span class="col-tag">5. Asset Tag</span>
                <span class="col-tag">6. Vendor</span>
                <span class="col-tag">7. Model</span>
                <span class="col-tag">8. Serial No</span>
                <span class="col-tag">9. Storage</span>
                <span class="col-tag">10. RAM</span>
                <span class="col-tag">11. Processor</span>
                <span class="col-tag">12. Purchase Date</span>
            </div>
            <p style="font-size:12px;color:var(--muted);margin-top:10px;">
                ⚠ Row 1 (Title) aur Row 2 (Headers) automatically skip ho jayengi<br>
                ⚠ Duplicate Asset Tags skip ho jayenge<br>
                ⚠ "SPARE WITH IT" aur "Scrap" wale Available mark honge
            </p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="importForm">
            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                <div class="upload-icon">📊</div>
                <h4>CSV File Yahan Drop Submit</h4>
                <p>Or click to browse — .csv format only</p>
            </div>
            <input type="file" id="fileInput" name="csvfile" accept=".csv" onchange="fileSelected(this)">
            <div class="file-selected" id="fileSelected">📄 <span id="fileName"></span></div>
            <button type="submit" name="import" class="btn btn-success" id="importBtn" disabled>
                📥 Import Submit
            </button>
        </form>
    </div>
</div>

<!-- RESULTS -->
<?php if(!empty($results)): ?>
<div class="results-card">
    <div class="results-header">
        <h3>📋 Import Results</h3>
        <span style="font-size:12px;color:var(--muted);"><?php echo count($results); ?> rows processed</span>
    </div>
    <div class="results-list">
        <?php foreach($results as $r): ?>
        <div class="result-item <?php echo $r['type']; ?>"><?php echo htmlspecialchars($r['msg']); ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</div>

<script>
function fileSelected(input){
    if(input.files && input.files[0]){
        document.getElementById('fileName').textContent = input.files[0].name;
        document.getElementById('fileSelected').style.display = 'block';
        document.getElementById('importBtn').disabled = false;
        document.getElementById('uploadZone').style.borderColor = 'var(--green)';
    }
}

// Drag and drop
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const fi = document.getElementById('fileInput');
    fi.files = e.dataTransfer.files;
    fileSelected(fi);
});

// Confirm before import
document.getElementById('importForm').addEventListener('submit', function(e){
    if(!confirm('Proceed with import? Duplicate asset tags will be skipped.')){
        e.preventDefault();
    }
});
</script>
</body>
</html>
