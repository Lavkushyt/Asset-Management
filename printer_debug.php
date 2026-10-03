<?php
include 'auth.php';
include 'db.php';

echo "<h2>Debug Info</h2>";
echo "Role: " . $_SESSION['role'] . "<br>";
echo "Is Admin: " . (in_array($_SESSION['role'], ['super_admin','admin']) ? 'YES' : 'NO') . "<br>";
echo "<br>";

// Check printers table columns
$cols = mysqli_query($conn, "SHOW COLUMNS FROM printers");
echo "<h3>Printers Table Columns:</h3>";
while($c = mysqli_fetch_assoc($cols)){
    echo $c['Field'] . " — " . $c['Type'] . "<br>";
}

echo "<br><h3>Test Insert:</h3>";
$test = mysqli_query($conn, "INSERT INTO printers (name, model, department, location, assigned_to, ink_type, remarks, added_by) VALUES ('TEST PRINTER', 'Test Model', 'IT', 'Delhi', 'Test User', 'HP 85A', 'Test', 'Debug')");

if($test){
    echo "✅ INSERT SUCCESS!";
    mysqli_query($conn, "DELETE FROM printers WHERE name='TEST PRINTER'");
} else {
    echo "❌ INSERT FAILED: " . mysqli_error($conn);
}
?>
