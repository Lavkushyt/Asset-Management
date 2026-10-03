<?php
include 'auth.php';
include 'db.php';
checkRole(['super_admin']); // Sirf super admin delete kar sakta hai

if(isset($_GET['id'])){
    $id = $_GET['id'];
    mysqli_query($conn, "DELETE FROM assets WHERE id='$id'");
}

header("Location: index.php");
exit;
?>