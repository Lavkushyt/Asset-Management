<?php
include 'db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=assets_'.date('Y-m-d').'.csv');

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, ['Asset Tag', 'Type', 'Model', 'Config', 'Serial No', 'Employee', 'Status', 'Issue Date', 'Return Date', 'Department', 'Location', 'Phone', 'Email', 'Vendor', 'Purchase Date', 'Item Price']);

// Data - latest wale upar aayenge
$result = mysqli_query($conn, "SELECT * FROM assets ORDER BY id DESC");

while($row = mysqli_fetch_assoc($result)){
    fputcsv($output, [
        $row['asset_Tag'],
        $row['type'],
        $row['model'],
        $row['Config'],
        $row['serial_no'],
        $row['employee'],
        $row['status'],
        $row['issue_date'],
        $row['return_date'],
        $row['department'],
        $row['location'],
        $row['phone'],
        $row['email'],
        $row['vendor'],
        $row['purchase_date'],
        $row['item_price']
    ]);
}

fclose($output);
exit;
?>