<?php
include 'auth.php';
include 'db.php';

$tag = isset($_GET['tag']) ? mysqli_real_escape_string($conn, $_GET['tag']) : '';
if(!$tag){ header("Location: index.php"); exit; }

// Asset info
$asset = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM assets WHERE asset_Tag='$tag'"));

// Complaint stats
$total_complaints = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets WHERE asset_tag='$tag'"))['c'];
$open_complaints  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets WHERE asset_tag='$tag' AND status='Open'"))['c'];
$resolved         = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets WHERE asset_tag='$tag' AND status='Resolved'"))['c'];
$avg_rating       = mysqli_fetch_assoc(mysqli_query($conn,"SELECT AVG(feedback_rating) as avg FROM tickets WHERE asset_tag='$tag' AND feedback_rating IS NOT NULL"))['avg'];

// Category breakdown
$cat_stats = mysqli_query($conn,"SELECT category, COUNT(*) as cnt FROM tickets WHERE asset_tag='$tag' GROUP BY category ORDER BY cnt DESC");

// All complaints
$complaints = mysqli_query($conn,"SELECT * FROM tickets WHERE asset_tag='$tag' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asset Complaints — <?php echo htmlspecialchars($tag); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#080c10;--surface:#0d1117;--surface2:#161b22;--surface3:#1c2128;
    --border:#21262d;--border2:#30363d;--accent:#1f6feb;--green:#238636;
    --danger:#da3633;--warn:#d29922;--purple:#8b5cf6;
    --text:#cdd9e5;--muted:#768390;--radius:12px;--radius-sm:8px;
    --mono:'JetBrains Mono',monospace;--sans:'Space Grotesk',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--sans);background:var(--bg);color:var(--text);min-height:100vh;}
nav{background:rgba(13,17,23,0.95);border-bottom:1px solid var(--border);padding:0 28px;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;backdrop-filter:blur(20px);}
.nav-brand{display:flex;align-items:center;gap:12px;}
.nav-logo{width:36px;height:36px;background:linear-gradient(135deg,var(--accent),var(--purple));border-radius:10px;display:grid;place-items:center;font-size:18px;}
.nav-brand span{font-weight:700;font-size:16px;}
.nav-link{color:var(--muted);text-decoration:none;font-size:13px;border:1px solid var(--border2);padding:6px 14px;border-radius:var(--radius-sm);transition:all .2s;}
.nav-link:hover{color:var(--text);border-color:var(--text);}
.page{max-width:1000px;margin:0 auto;padding:28px 24px;}

/* ASSET HERO */
.hero{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:24px;animation:fadeUp .4s ease;}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.hero-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px;flex-wrap:wrap;}
.hero-icon{width:56px;height:56px;background:linear-gradient(135deg,var(--accent),var(--purple));border-radius:14px;display:grid;place-items:center;font-size:26px;box-shadow:0 4px 16px rgba(31,111,235,0.3);}
.hero-info h2{font-size:20px;font-weight:700;margin-bottom:4px;}
.hero-tag{font-family:var(--mono);font-size:13px;color:var(--accent);background:#1f6feb18;border:1px solid #1f6feb40;padding:3px 10px;border-radius:5px;display:inline-block;}
.hero-meta{display:flex;gap:16px;flex-wrap:wrap;margin-top:8px;font-size:13px;color:var(--muted);}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;text-align:center;animation:fadeUp .4s ease both;transition:transform .2s;}
.stat-card:hover{transform:translateY(-3px);}
.stat-num{font-size:30px;font-weight:700;font-family:var(--mono);}
.stat-label{font-size:12px;color:var(--muted);margin-top:5px;}

/* CATEGORY BREAKDOWN */
.cat-grid{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
.cat-item{background:var(--surface2);border:1px solid var(--border2);border-radius:var(--radius-sm);padding:8px 14px;display:flex;align-items:center;gap:8px;}
.cat-name{font-size:13px;font-weight:600;}
.cat-count{font-family:var(--mono);font-size:14px;font-weight:700;color:var(--accent);}

/* CARD */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px;box-shadow:0 2px 12px rgba(0,0,0,0.2);}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.card-header h3{font-size:15px;font-weight:600;display:flex;align-items:center;gap:8px;}

/* COMPLAINT CARDS */
.complaint-list{display:flex;flex-direction:column;gap:12px;padding:16px;}
.complaint-card{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;animation:fadeUp .3s ease both;transition:transform .2s,border-color .2s;}
.complaint-card:hover{transform:translateX(4px);}
.complaint-card.open-card    {border-left:3px solid #ff7b72;}
.complaint-card.progress-card{border-left:3px solid var(--warn);}
.complaint-card.resolved-card{border-left:3px solid #3fb950;}
.c-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
.c-no{font-family:var(--mono);font-size:12px;color:var(--accent);font-weight:600;}
.c-badges{display:flex;gap:6px;flex-wrap:wrap;}
.c-subject{font-size:15px;font-weight:600;margin-bottom:6px;}
.c-meta{font-size:12px;color:var(--muted);display:flex;gap:12px;flex-wrap:wrap;}

/* STATUS PRIORITY BADGES */
.priority{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
.priority.Low    {background:#8b949e18;color:#8b949e;border:1px solid #8b949e40;}
.priority.Medium {background:#1f6feb18;color:#79c0ff;border:1px solid #1f6feb40;}
.priority.High   {background:#d2992218;color:#d29922;border:1px solid #d2992240;}
.priority.Urgent {background:#da363318;color:#ff7b72;border:1px solid #da363340;}
.status{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
.status::before{content:'';width:6px;height:6px;border-radius:50%;}
.status.Open        {background:#da363318;color:#ff7b72;border:1px solid #da363340;}
.status.Open::before{background:#ff7b72;animation:pulse 2s infinite;}
.status.In-Progress {background:#d2992218;color:#d29922;border:1px solid #d2992240;}
.status.In-Progress::before{background:#d29922;}
.status.Resolved    {background:#23863618;color:#3fb950;border:1px solid #23863640;}
.status.Resolved::before{background:#3fb950;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 3px #ff7b7233}50%{box-shadow:0 0 0 6px #ff7b7211}}

/* FEEDBACK */
.feedback-stars{font-size:16px;margin-top:6px;}
.empty{text-align:center;padding:50px 20px;color:var(--muted);}
.empty .icon{font-size:44px;margin-bottom:12px;}

@media(max-width:600px){.stats{grid-template-columns:repeat(2,1fr);}}
</style>
</head>
<body>

<nav>
    <div class="nav-brand">
        <div class="nav-logo">📊</div>
        <span>Asset Complaint History</span>
    </div>
    <div class="nav-right">
        <a href="history.php?tag=<?php echo urlencode($tag); ?>" class="nav-link">📜 Asset History</a>
        <a href="index.php" class="nav-link">← Dashboard</a>
    </div>
</nav>

<div class="page">

    <!-- ASSET HERO -->
    <div class="hero">
        <div class="hero-top">
            <div style="display:flex;align-items:center;gap:16px;">
                <div class="hero-icon">💻</div>
                <div class="hero-info">
                    <h2><?php echo htmlspecialchars($asset['model'] ?? $tag); ?></h2>
                    <span class="hero-tag"><?php echo htmlspecialchars($tag); ?></span>
                    <div class="hero-meta">
                        <?php if($asset): ?>
                        <span>🖥 <?php echo htmlspecialchars($asset['type']); ?></span>
                        <span>👤 <?php echo htmlspecialchars($asset['employee'] ?: 'Unassigned'); ?></span>
                        <span>🏢 <?php echo htmlspecialchars($asset['department']); ?></span>
                        <span>📍 <?php echo htmlspecialchars($asset['location']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if($avg_rating): ?>
            <div style="text-align:center;background:var(--surface2);border:1px solid var(--border2);border-radius:var(--radius-sm);padding:12px 20px;">
                <div style="font-size:24px;font-weight:700;color:var(--warn);font-family:var(--mono);"><?php echo number_format($avg_rating,1); ?></div>
                <div style="font-size:16px;margin:2px 0;">
                    <?php for($s=1;$s<=5;$s++) echo $s<=$avg_rating?'⭐':'☆'; ?>
                </div>
                <div style="font-size:11px;color:var(--muted);">Avg. Rating</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- CATEGORY BREAKDOWN -->
        <?php if(mysqli_num_rows($cat_stats) > 0): ?>
        <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;font-weight:600;">Complaint Categories</div>
        <div class="cat-grid">
            <?php
            $cat_colors = ['#1f6feb','#8b5cf6','#d29922','#da3633','#0891b2','#238636'];
            $ci = 0;
            mysqli_data_seek($cat_stats,0);
            while($cs = mysqli_fetch_assoc($cat_stats)):
                $color = $cat_colors[$ci++ % count($cat_colors)];
            ?>
            <div class="cat-item" style="border-color:<?php echo $color; ?>40;background:<?php echo $color; ?>12;">
                <span class="cat-count" style="color:<?php echo $color; ?>"><?php echo $cs['cnt']; ?>×</span>
                <span class="cat-name"><?php echo htmlspecialchars($cs['category']); ?></span>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat-card" style="border-top:3px solid var(--accent);animation-delay:.05s">
            <div style="font-size:22px;margin-bottom:6px;">🎫</div>
            <div class="stat-num" style="color:#79c0ff;" data-target="<?php echo $total_complaints; ?>">0</div>
            <div class="stat-label">Total Complaints</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--danger);animation-delay:.1s">
            <div style="font-size:22px;margin-bottom:6px;">🔴</div>
            <div class="stat-num" style="color:#ff7b72;" data-target="<?php echo $open_complaints; ?>">0</div>
            <div class="stat-label">Open</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--green);animation-delay:.15s">
            <div style="font-size:22px;margin-bottom:6px;">✅</div>
            <div class="stat-num" style="color:#3fb950;" data-target="<?php echo $resolved; ?>">0</div>
            <div class="stat-label">Resolved</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--warn);animation-delay:.2s">
            <div style="font-size:22px;margin-bottom:6px;">⭐</div>
            <div class="stat-num" style="color:var(--warn);"><?php echo $avg_rating ? number_format($avg_rating,1) : '—'; ?></div>
            <div class="stat-label">Avg Rating</div>
        </div>
    </div>

    <!-- COMPLAINTS LIST -->
    <div class="card">
        <div class="card-header">
            <h3>
                📋 All Complaints
                <span style="background:var(--surface2);border:1px solid var(--border2);color:var(--muted);font-size:12px;padding:2px 10px;border-radius:20px;font-family:var(--mono);font-weight:400;"><?php echo $total_complaints; ?></span>
            </h3>
        </div>
        <div class="complaint-list">
        <?php if($total_complaints > 0):
            $i = 0;
            while($t = mysqli_fetch_assoc($complaints)):
                $sc  = str_replace(' ','-',$t['status']);
                $cls = $t['status']=='Resolved'?'resolved-card':($t['status']=='In Progress'?'progress-card':'open-card');
                $i++;
        ?>
        <div class="complaint-card <?php echo $cls; ?>" style="animation-delay:<?php echo $i*0.05; ?>s">
            <div class="c-top">
                <span class="c-no"><?php echo $t['ticket_no']; ?></span>
                <div class="c-badges">
                    <span class="priority <?php echo $t['priority']; ?>"><?php echo $t['priority']; ?></span>
                    <span class="status <?php echo $sc; ?>"><?php echo $t['status']; ?></span>
                </div>
            </div>
            <div class="c-subject"><?php echo htmlspecialchars($t['subject']); ?></div>
            <div class="c-meta">
                <span>📂 <?php echo $t['category']; ?></span>
                <span>👤 <?php echo htmlspecialchars($t['name']); ?></span>
                <span>🕐 <?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?></span>
                <?php if($t['assigned_to']): ?>
                <span style="color:#a78bfa;">👨‍💻 <?php echo htmlspecialchars($t['assigned_to']); ?></span>
                <?php endif; ?>
            </div>
            <?php if($t['feedback_rating']): ?>
            <div class="feedback-stars">
                <?php for($s=1;$s<=5;$s++) echo $s<=$t['feedback_rating']?'⭐':'☆'; ?>
                <span style="font-size:12px;color:var(--muted);margin-left:4px;"><?php echo $t['feedback_rating']; ?>/5</span>
                <?php if($t['feedback_comment']): ?>
                <span style="font-size:12px;color:var(--muted);margin-left:8px;">"<?php echo htmlspecialchars($t['feedback_comment']); ?>"</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile;
        else: ?>
        <div class="empty">
            <div class="icon">🎉</div>
            <p>No complaints for this asset yet!</p>
        </div>
        <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.querySelectorAll('.stat-num[data-target]').forEach(el => {
    const target = +el.dataset.target;
    if(!target) return;
    let current = 0;
    const step = Math.max(1,Math.ceil(target/30));
    const timer = setInterval(() => {
        current = Math.min(current+step,target);
        el.textContent = current;
        if(current>=target) clearInterval(timer);
    },40);
});
</script>
</body>
</html>
