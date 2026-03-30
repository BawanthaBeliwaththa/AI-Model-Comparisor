<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Unauthorized");
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=visitors_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Timestamp', 'IP Address', 'User Agent']);

$log_file = __DIR__ . '/visitors.log';
if (file_exists($log_file)) {
    $lines = file($log_file);
    foreach ($lines as $line) {
        $parts = explode('|', trim($line));
        if (count($parts) >= 2) {
            fputcsv($output, [
                $parts[0],
                $parts[1],
                $parts[2] ?? ''
            ]);
        }
    }
}

fclose($output);
?>