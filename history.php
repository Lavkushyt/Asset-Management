<?php
include 'db.php';
$tag = isset($_GET['tag']) ? mysqli_real_escape_string($conn, $_GET['tag']) : '';
if(!$tag){ header("Location: index.php"); exit; }

$total_issue  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM asset_history WHERE asset_tag='$tag' AND action='Issued'"))['c'];
$total_return = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM asset_history WHERE asset_tag='$tag' AND action='Returned'"))['c'];
$q = mysqli_query($conn,"SELECT * FROM asset_history WHERE asset_tag='$tag' ORDER BY action_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>History — <?= htmlspecialchars($tag) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0d1117; --surface:#161b22; --surface2:#21262d;
    --border:#30363d; --accent:#1f6feb; --green:#238636;
    --warn:#d29922; --text:#e6edf3; --muted:#8b949e;
}
*,*::before,*::after{box-sizing:border-box; margin:0; padding:0;}
body{font-family:'Space Grotesk',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding:24px 20px;}
.page{max-width:760px; margin:0 auto;}
.back{display:inline-flex; align-items:center; gap:6px; color:var(--muted); text-decoration:none; font-size:13px; margin-bottom:24px; transition:color .2s;}
.back:hover{color:var(--text);}
.hero{background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:24px; margin-bottom:24px; animation:fadeUp .4s ease;}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)}}
.hero-top{display:flex; align-items:center; gap:12px; margin-bottom:20px;}
.hero-icon{width:48px; height:48px; background:linear-gradient(135deg,var(--accent),var(--green)); border-radius:12px; display:grid; place-items:center; font-size:22px;}
.hero-title h2{font-size:18px; font-weight:700;}
.hero-title p{font-size:13px; color:var(--muted); margin-top:2px; font-family:'JetBrains Mono',monospace;}
.stats-row{display:grid; grid-template-columns:1fr 1fr; gap:12px;}
.stat{background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;}
.stat-num{font-size:28px; font-weight:700; font-family:'JetBrains Mono',monospace; line-height:1;}
.stat-num.green{color:#3fb950;}
.stat-num.orange{color:var(--warn);}
.stat-label{font-size:12px; color:var(--muted);}
/* TIMELINE */
.timeline{position:relative;}
.timeline::before{content:''; position:absolute; left:20px; top:0; bottom:0; width:2px; background:var(--border);}
.tl-item{position:relative; padding:0 0 20px 52px; animation:fadeUp .4s ease both;}
.tl-dot{
    position:absolute; left:12px; top:4px;
    width:18px; height:18px; border-radius:50%;
    border:2px solid var(--border); background:var(--bg);
    display:grid; place-items:center; font-size:10px;
}
.tl-dot.issued {border-color:#3fb950; background:#23863620;}
.tl-dot.returned{border-color:var(--warn); background:#d2992220;}
.tl-card{
    background:var(--surface); border:1px solid var(--border);
    border-radius:10px; padding:14px 16px;
    transition:transform .2s, box-shadow .2s;
}
.tl-card:hover{transform:translateX(4px); box-shadow:4px 0 20px #0005;}
.tl-card.issued {border-left:3px solid #3fb950;}
.tl-card.returned{border-left:3px solid var(--warn);}
.tl-action{font-weight:700; font-size:14px;}
.tl-action.issued {color:#3fb950;}
.tl-action.returned{color:var(--warn);}
.tl-employee{color:var(--muted); font-size:13px; margin-top:4px;}
.tl-date{font-size:12px; color:var(--muted); font-family:'JetBrains Mono',monospace; margin-top:6px;}
.empty{text-align:center; padding:48px 20px; color:var(--muted);}
.empty .icon{font-size:40px; margin-bottom:12px;}
</style>
</head>
<body>
<div class="page">
    <a href="index.php" class="back">← Back to Assets</a>

    <div class="hero">
        <div class="hero-top">
            <div class="hero-icon">📦</div>
            <div class="hero-title">
                <h2>Asset History</h2>
                <p><?= htmlspecialchars($tag) ?></p>
            </div>
        </div>
        <div class="stats-row">
            <div class="stat">
                <div>
                    <div class="stat-num green"><?= $total_issue ?></div>
                    <div class="stat-label">Times Issued</div>
                </div>
            </div>
            <div class="stat">
                <div>
                    <div class="stat-num orange"><?= $total_return ?></div>
                    <div class="stat-label">Times Returned</div>
                </div>
            </div>
        </div>
    </div>

    <div class="timeline">
        <?php if(mysqli_num_rows($q) > 0):
            $i = 0;
            while($h = mysqli_fetch_assoc($q)):
                $cls = strtolower($h['action']) === 'issued' ? 'issued' : 'returned';
                $icon = $cls === 'issued' ? '↑' : '↓';
        ?>
        <div class="tl-item" style="animation-delay:<?= $i * 0.06 ?>s">
            <div class="tl-dot <?= $cls ?>"><?= $icon ?></div>
            <div class="tl-card <?= $cls ?>">
                <div class="tl-action <?= $cls ?>"><?= htmlspecialchars($h['action']) ?></div>
                <div class="tl-employee">👤 <?= htmlspecialchars($h['employee']) ?></div>
                <div class="tl-date">🕐 <?= date('d M Y, h:i A', strtotime($h['action_date'])) ?></div>
            </div>
        </div>
        <?php $i++; endwhile;
        else: ?>
        <div class="empty">
            <div class="icon">🕳</div>
            <p>No history found for this asset.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
