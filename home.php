<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IT Portal — VCM</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0d1117;--surface:#161b22;--surface2:#21262d;
    --border:#30363d;--accent:#1f6feb;--green:#238636;
    --purple:#8b5cf6;--text:#e6edf3;--muted:#8b949e;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{
    font-family:'Space Grotesk',sans-serif;
    background:var(--bg);color:var(--text);
    min-height:100vh;
    display:flex;flex-direction:column;
    background-image:
        radial-gradient(ellipse at 20% 20%, #1f6feb0a 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, #8b5cf60a 0%, transparent 50%);
}

/* NAV */
nav{
    padding:16px 32px;
    display:flex;align-items:center;justify-content:space-between;
    border-bottom:1px solid var(--border);
    background:var(--surface);
}
.nav-brand{display:flex;align-items:center;gap:12px;}
.nav-logo{
    width:40px;height:40px;
    background:linear-gradient(135deg,var(--accent),var(--purple));
    border-radius:10px;display:grid;place-items:center;font-size:20px;
}
.nav-brand h1{font-size:18px;font-weight:700;}
.nav-brand p{font-size:12px;color:var(--muted);margin-top:1px;}
.nav-time{font-size:13px;color:var(--muted);font-family:monospace;}

/* MAIN */
main{
    flex:1;display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    padding:40px 20px;
}

.welcome{text-align:center;margin-bottom:50px;animation:fadeUp .6s ease;}
.welcome h2{font-size:32px;font-weight:800;margin-bottom:10px;}
.welcome h2 span{
    background:linear-gradient(135deg,var(--accent),var(--purple));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.welcome p{font-size:16px;color:var(--muted);}

/* CARDS */
.cards{
    display:grid;grid-template-columns:1fr 1fr;
    gap:24px;width:100%;max-width:720px;
    animation:fadeUp .6s ease .1s both;
}
.portal-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:16px;padding:40px 32px;
    text-decoration:none;color:var(--text);
    display:flex;flex-direction:column;align-items:center;
    text-align:center;gap:16px;
    transition:transform .25s,box-shadow .25s,border-color .25s;
    position:relative;overflow:hidden;
}
.portal-card::before{
    content:'';position:absolute;
    top:-60px;right:-60px;
    width:150px;height:150px;border-radius:50%;
    opacity:.06;transition:opacity .3s;
}
.portal-card.assets::before{background:var(--accent);}
.portal-card.tickets::before{background:var(--purple);}
.portal-card:hover{transform:translateY(-6px);box-shadow:0 20px 60px #0008;}
.portal-card.assets:hover{border-color:var(--accent);box-shadow:0 20px 60px #1f6feb22;}
.portal-card.tickets:hover{border-color:var(--purple);box-shadow:0 20px 60px #8b5cf622;}
.portal-card:hover::before{opacity:.12;}

.card-icon{
    width:72px;height:72px;border-radius:18px;
    display:grid;place-items:center;font-size:34px;
    transition:transform .25s;
}
.portal-card:hover .card-icon{transform:scale(1.1) rotate(-5deg);}
.portal-card.assets .card-icon{background:linear-gradient(135deg,#1f6feb22,#1f6feb44);border:1px solid #1f6feb44;}
.portal-card.tickets .card-icon{background:linear-gradient(135deg,#8b5cf622,#8b5cf644);border:1px solid #8b5cf644;}

.card-title{font-size:22px;font-weight:700;}
.card-desc{font-size:14px;color:var(--muted);line-height:1.6;}
.card-arrow{
    margin-top:8px;font-size:13px;font-weight:600;
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 20px;border-radius:8px;transition:gap .2s;
}
.portal-card.assets .card-arrow{background:#1f6feb22;color:var(--accent);}
.portal-card.tickets .card-arrow{background:#8b5cf622;color:var(--purple);}
.portal-card:hover .card-arrow{gap:10px;}

/* FOOTER */
footer{
    text-align:center;padding:20px;
    font-size:12px;color:var(--muted);
    border-top:1px solid var(--border);
}

@keyframes fadeUp{
    from{opacity:0;transform:translateY(24px)}
    to{opacity:1;transform:translateY(0)}
}

@media(max-width:560px){
    .cards{grid-template-columns:1fr;}
    .welcome h2{font-size:24px;}
    .portal-card{padding:28px 20px;}
}
</style>
</head>
<body>

<nav>
    <div class="nav-brand">
        <div class="nav-logo">🏢</div>
        <div>
            <h1>VCM IT Portal</h1>
            <p>Vrindavan Chandrodaya Mandir</p>
        </div>
    </div>
    <div class="nav-time" id="clock"></div>
</nav>

<main>
    <div class="welcome">
        <h2>Welcome to <span>VCM IT Portal</span></h2>
        <p>Please select your portal to continue</p>
    </div>

    <div class="cards">
        <!-- ASSET MANAGEMENT -->
        <a href="login.php" class="portal-card assets">
            <div class="card-icon">📦</div>
            <div>
                <div class="card-title">Asset Management</div>
                <div class="card-desc">Track IT assets — issue and return laptops, desktops, monitors and more</div>
            </div>
            <div class="card-arrow">IT Admin Login →</div>
        </a>

        <!-- TICKETS -->
        <a href="ticket_login.php" class="portal-card tickets">
            <div class="card-icon">🎫</div>
            <div>
                <div class="card-title">IT Helpdesk</div>
                <div class="card-desc">Have an IT issue? Submit a complaint — get help from our IT team</div>
            </div>
            <div class="card-arrow">Ticket Submit →</div>
        </a>
    </div>
</main>

<!-- HELPLINE -->
<div style="background:var(--surface);border-top:1px solid var(--border);padding:16px 32px;">
    <div style="max-width:900px;margin:0 auto;display:flex;align-items:center;justify-content:center;gap:32px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;background:#23863620;border:1px solid #23863640;border-radius:8px;display:grid;place-items:center;font-size:18px;">📞</div>
            <div>
                <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">IT Helpline</div>
                <a href="tel:7037090999" style="font-size:15px;font-weight:700;color:#3fb950;text-decoration:none;font-family:'JetBrains Mono',monospace;">+91 70370 90999</a>
            </div>
        </div>
        <div style="width:1px;height:36px;background:var(--border);"></div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;background:#1f6feb20;border:1px solid #1f6feb40;border-radius:8px;display:grid;place-items:center;font-size:18px;">✉️</div>
            <div>
                <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">IT Support Email</div>
                <a href="mailto:ithelpdesk@vcm.org.in" style="font-size:15px;font-weight:700;color:#79c0ff;text-decoration:none;">ithelpdesk@vcm.org.in</a>
            </div>
        </div>
        <div style="width:1px;height:36px;background:var(--border);"></div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;background:#8b5cf620;border:1px solid #8b5cf640;border-radius:8px;display:grid;place-items:center;font-size:18px;">🕐</div>
            <div>
                <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">Support Hours</div>
                <div style="font-size:14px;font-weight:600;color:var(--text);">Mon–Sat, 9:00 AM – 6:00 PM</div>
            </div>
        </div>
    </div>
</div>

<footer>
    © <?php echo date('Y'); ?> VCM IT Department &nbsp;·&nbsp; All rights reserved
</footer>

<script>
// Live clock
function updateClock(){
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('en-IN',{
        hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true
    }) + ' · ' + now.toLocaleDateString('en-IN',{weekday:'short',day:'numeric',month:'short'});
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>
