<?php
session_start();

// No cache headers - logout ke baad back button se nahi aayega
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

function checkRole($allowed_roles = []){
    if(!in_array($_SESSION['role'], $allowed_roles)){
        echo "<script>alert('Access Denied!'); window.location='index.php';</script>";
        exit;
    }
}
?>