<?php
include 'auth.php';
include 'db.php';

$type = $_GET['type'] ?? 'printers';
$date = date('Y-m-d');

if($type == 'printers'){
    // All Printers Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Printer_List_'.$date.'.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['VCM IT — Printer List Report']);
    fputcsv($output, ['Generated: '.$date.' | By: '.$_SESSION['name']]);
    fputcsv($output, []);
    fputcsv($output, ['#','Asset Tag','Printer Name','Model','Department','Location','Assigned To','Ink Type','Status','Last Refill','Days Since Refill','Total Refills','Total Repairs','Total Repair Cost','Remarks']);

    $printers = mysqli_query($conn,"SELECT p.*,
        (SELECT refill_date FROM ink_history WHERE printer_id=p.id ORDER BY refill_date DESC LIMIT 1) as last_refill,
        (SELECT COUNT(*) FROM ink_history WHERE printer_id=p.id) as total_refills,
        (SELECT COUNT(*) FROM printer_repairs WHERE printer_id=p.id) as total_repairs,
        (SELECT SUM(repair_cost) FROM printer_repairs WHERE printer_id=p.id) as total_repair_cost
        FROM printers p ORDER BY department, name");

    $i = 1;
    while($p = mysqli_fetch_assoc($printers)){
        $days = $p['last_refill'] ? (int)((time()-strtotime($p['last_refill']))/86400) : 'Never';
        fputcsv($output, [
            $i++,
            $p['asset_tag'] ?? '',
            $p['name'],
            $p['model'] ?? '',
            $p['department'],
            $p['location'] ?? '',
            $p['assigned_to'] ?? '',
            $p['ink_type'] ?? '',
            $p['status'],
            $p['last_refill'] ? date('d-m-Y', strtotime($p['last_refill'])) : 'Never',
            $days,
            $p['total_refills'],
            $p['total_repairs'],
            '₹'.number_format($p['total_repair_cost'] ?? 0, 2),
            $p['remarks'] ?? ''
        ]);
    }
    fclose($output);

} elseif($type == 'refills'){
    // Ink Refill History
    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Ink_Refill_History_'.$from.'_to_'.$to.'.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['VCM IT — Ink Refill History']);
    fputcsv($output, ['Period: '.$from.' to '.$to.' | By: '.$_SESSION['name']]);
    fputcsv($output, []);
    fputcsv($output, ['#','Date','Printer Name','Asset Tag','Department','Location','Ink Type','Ink Brand','Quantity','Done By','Remarks']);

    $from_s = mysqli_real_escape_string($conn, $from);
    $to_s   = mysqli_real_escape_string($conn, $to);

    $logs = mysqli_query($conn,"SELECT h.*,p.asset_tag,p.location FROM ink_history h
        LEFT JOIN printers p ON p.id=h.printer_id
        WHERE h.refill_date BETWEEN '$from_s' AND '$to_s'
        ORDER BY h.refill_date DESC");

    $i = 1; $total_qty = 0;
    while($h = mysqli_fetch_assoc($logs)){
        $total_qty += $h['quantity'];
        fputcsv($output, [
            $i++,
            date('d-m-Y', strtotime($h['refill_date'])),
            $h['printer_name'],
            $h['asset_tag'] ?? '',
            $h['department'],
            $h['location'] ?? '',
            $h['ink_type'] ?? '',
            $h['ink_brand'] ?? '',
            $h['quantity'],
            $h['done_by'],
            $h['remarks'] ?? ''
        ]);
    }
    fputcsv($output, []);
    fputcsv($output, ['Total Refills Done', $total_qty]);
    fclose($output);

} elseif($type == 'repairs'){
    // Repair History
    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Printer_Repair_History_'.$from.'_to_'.$to.'.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['VCM IT — Printer Repair History']);
    fputcsv($output, ['Period: '.$from.' to '.$to.' | By: '.$_SESSION['name']]);
    fputcsv($output, []);
    fputcsv($output, ['#','Date','Printer Name','Asset Tag','Department','Issue','Repair Cost','Vendor','Done By','Remarks']);

    $from_s = mysqli_real_escape_string($conn, $from);
    $to_s   = mysqli_real_escape_string($conn, $to);

    $repairs = mysqli_query($conn,"SELECT r.*,p.asset_tag,p.department FROM printer_repairs r
        LEFT JOIN printers p ON p.id=r.printer_id
        WHERE r.repair_date BETWEEN '$from_s' AND '$to_s'
        ORDER BY r.repair_date DESC");

    $i = 1; $total_cost = 0;
    while($r = mysqli_fetch_assoc($repairs)){
        $total_cost += $r['repair_cost'];
        fputcsv($output, [
            $i++,
            date('d-m-Y', strtotime($r['repair_date'])),
            $r['printer_name'],
            $r['asset_tag'] ?? '',
            $r['department'] ?? '',
            $r['issue'],
            '₹'.number_format($r['repair_cost'], 2),
            $r['vendor'] ?? '',
            $r['done_by'],
            $r['remarks'] ?? ''
        ]);
    }
    fputcsv($output, []);
    fputcsv($output, ['Total Repair Cost', '₹'.number_format($total_cost, 2)]);
    fclose($output);
}
exit;
?>
