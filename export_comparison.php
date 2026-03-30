<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=model_comparison_' . date('Y-m-d_H-i-s') . '.csv');

$results = isset($_POST['results']) ? json_decode($_POST['results'], true) : [];

if (empty($results)) {
    echo "No results to export";
    exit;
}

$output = fopen('php://output', 'w');

fputcsv($output, array_keys($results[0]));

foreach ($results as $row) {
    $clean_row = array_map(function($value) {
        if (is_array($value)) return json_encode($value);
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        return $value;
    }, $row);
    fputcsv($output, $clean_row);
}

fclose($output);
?>