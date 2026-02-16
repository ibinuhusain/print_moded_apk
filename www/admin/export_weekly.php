<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Get this week's collections (from Monday to Sunday)
$start_of_week = date('Y-m-d', strtotime('monday this week'));
$end_of_week = date('Y-m-d', strtotime('sunday this week'));

$stmt = $pdo->prepare("
    SELECT c.*, da.date_assigned, u.name as agent_name, u.username as agent_username, 
           s.name as store_name, r.name as region_name
    FROM collections c
    JOIN daily_assignments da ON c.assignment_id = da.id
    JOIN users u ON da.agent_id = u.id
    JOIN stores s ON da.store_id = s.id
    LEFT JOIN regions r ON s.region_id = r.id
    WHERE DATE(da.date_assigned) BETWEEN ? AND ?
    ORDER BY da.date_assigned, u.name, s.name
");
$stmt->execute([$start_of_week, $end_of_week]);
$collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create CSV content
$filename = "weekly_collections_" . $start_of_week . "_to_" . $end_of_week . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add BOM to handle special characters in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, [
    'Date', 
    'Agent Name', 
    'Agent Username', 
    'Store Name', 
    'Region', 
    'Amount Collected', 
    'Pending Amount', 
    'Comments', 
    'Submitted to Bank', 
    'Submitted At'
]);

// Write data rows
foreach ($collections as $collection) {
    fputcsv($output, [
        $collection['date_assigned'],
        $collection['agent_name'],
        $collection['agent_username'],
        $collection['store_name'],
        $collection['region_name'] ?? 'N/A',
        $collection['amount_collected'],
        $collection['pending_amount'],
        $collection['comments'],
        $collection['submitted_to_bank'] ? 'Yes' : 'No',
        $collection['submitted_at'] ?? ''
    ]);
}

fclose($output);
exit;
?>