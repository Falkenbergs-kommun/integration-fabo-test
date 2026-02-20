#!/usr/bin/env php
<?php
require_once 'fetch_arbetsordrar.php';

$config = loadEnv('.env');
$client = new Fast2WorkOrderClient($config, false);
$orders = $client->fetchWorkOrders();

// Group by status
$statusGroups = [];
foreach ($orders as $order) {
    $status = $order['status']['statusKod'] ?? 'UNKNOWN';
    $statusBesk = $order['status']['statusBesk'] ?? '';
    $key = $status . '|' . $statusBesk;
    if (!isset($statusGroups[$key])) {
        $statusGroups[$key] = ['count' => 0, 'example_id' => $order['id']];
    }
    $statusGroups[$key]['count']++;
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  STATUS-ÖVERSIKT                                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
foreach ($statusGroups as $key => $data) {
    list($kod, $besk) = explode('|', $key);
    echo sprintf("%-6s %-20s %3d ärenden (exempel-ID: %d)\n", $kod, $besk, $data['count'], $data['example_id']);
}
echo "\nTotalt: " . count($orders) . " arbetsordrar\n\n";
