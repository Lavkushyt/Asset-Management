<?php
session_start();

// Session destroy
$_SESSION = [];
session_destroy();

// Cookie clear
if(isset($_COOKIE[session_name()])){
    setcookie(session_name(), '', time()-3600, '/');
}

// No cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

header("Location: home.php");
exit;
?>