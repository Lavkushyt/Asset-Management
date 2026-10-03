<?php
session_start();
include 'db.php';

// No cache headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

if(isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit;
}

if(isset($_POST['login'])){
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = MD5($_POST['password']); // Note: upgrade to password_hash() for production

    $sql    = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = mysqli_query($conn, $sql);

    if(mysqli_num_rows($result) == 1){
        $row = mysqli_fetch_assoc($result);
        $_SESSION['user_id']  = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['name']     = $row['name'];
        $_SESSION['role']     = $row['role'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — IT Asset Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root {
    --bg:      #0d1117;
    --surface: #161b22;
    --border:  #30363d;
    --accent:  #1f6feb;
    --danger:  #da3633;
    --text:    #e6edf3;
    --muted:   #8b949e;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Space Grotesk', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 20px;
    background-image:
        radial-gradient(ellipse at 20% 50%, #1f6feb0d 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, #2386360d 0%, transparent 50%);
}
.box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 40px;
    width: 100%;
    max-width: 400px;
    animation: fadeUp .5s ease;
    box-shadow: 0 24px 64px #0009;
}
@keyframes fadeUp {
    from { opacity:0; transform: translateY(20px); }
    to   { opacity:1; transform: translateY(0); }
}
.logo-wrap {
    text-align: center; margin-bottom: 32px;
}
.logo-icon {
    width: 56px; height: 56px; background: linear-gradient(135deg, #1f6feb, #238636);
    border-radius: 14px; display: inline-grid; place-items: center;
    font-size: 28px; margin-bottom: 14px;
    box-shadow: 0 8px 24px #1f6feb33;
}
.logo-wrap h1 { font-size: 20px; font-weight: 700; }
.logo-wrap p  { font-size: 13px; color: var(--muted); margin-top: 4px; }
.field { margin-bottom: 16px; }
.field label {
    display: block; font-size: 13px; font-weight: 500;
    color: var(--muted); margin-bottom: 6px;
}
.field input {
    width: 100%; padding: 11px 14px;
    background: #0d1117; border: 1px solid var(--border);
    border-radius: 8px; color: var(--text);
    font-family: 'Space Grotesk', sans-serif; font-size: 14px;
    transition: border-color .2s, box-shadow .2s;
}
.field input:focus {
    outline: none; border-color: var(--accent);
    box-shadow: 0 0 0 3px #1f6feb22;
}
.btn {
    width: 100%; padding: 13px;
    background: var(--accent); color: #fff;
    border: none; border-radius: 8px;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 15px; font-weight: 600;
    cursor: pointer; margin-top: 8px;
    transition: all .2s; letter-spacing: .3px;
}
.btn:hover { background: #388bfd; transform: translateY(-1px); box-shadow: 0 8px 24px #1f6feb44; }
.btn:active{ transform: translateY(0); }
.error-box {
    background: #da363318; border: 1px solid #da363340;
    color: #ff7b72; padding: 10px 14px; border-radius: 7px;
    font-size: 13px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px;
    animation: shake .4s ease;
}
@keyframes shake {
    0%,100%{ transform:translateX(0) }
    20%    { transform:translateX(-6px) }
    40%    { transform:translateX(6px) }
    60%    { transform:translateX(-4px) }
    80%    { transform:translateX(4px) }
}
.footer { text-align:center; margin-top:24px; font-size:12px; color:var(--muted); }
</style>
</head>
<body>
<div class="box">
    <div class="logo-wrap">
        <div class="logo-icon">📦</div>
        <h1>IT Asset Manager</h1>
        <p>Sign in to your account</p>
    </div>

    <?php if(isset($error)): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="field">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter username" required autofocus>
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" required>
        </div>
        <button type="submit" name="login" class="btn">→ Sign In</button>
    </form>
    <div class="footer">VCM · IT Asset Management System</div>
</div>
</body>
</html>
