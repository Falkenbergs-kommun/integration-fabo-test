#!/usr/bin/env php
<?php
/**
 * Inspect a specific work order and show all fields
 */

require_once __DIR__ . '/fetch_arbetsordrar.php';

if (!isset($argv[1])) {
    echo "Usage: php inspect_order.php [orderId]\n";
    exit(1);
}

$orderId = $argv[1];

$config = loadEnv(__DIR__ . '/.env');
$client = new Fast2WorkOrderClient($config, false);

echo "\n🔍 Fetching work order {$orderId}...\n\n";

$orders = $client->fetchWorkOrders();
$order = null;

foreach ($orders as $o) {
    if ($o['id'] == $orderId) {
        $order = $o;
        break;
    }
}

if (!$order) {
    echo "❌ Order {$orderId} not found\n\n";
    exit(1);
}

echo "✅ Found order {$orderId}\n\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "RAW JSON STRUCTURE:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Pretty print JSON
echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "SEARCHING FOR 'Ursprung 1001':\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Search for "Ursprung 1001" in the order data
function searchInArray($array, $searchTerm, $path = '') {
    $results = [];

    foreach ($array as $key => $value) {
        $currentPath = $path ? $path . '.' . $key : $key;

        if (is_array($value)) {
            $results = array_merge($results, searchInArray($value, $searchTerm, $currentPath));
        } elseif (is_string($value) && stripos($value, $searchTerm) !== false) {
            $results[] = [
                'path' => $currentPath,
                'value' => $value
            ];
        }
    }

    return $results;
}

$results = searchInArray($order, 'Ursprung 1001');

if (empty($results)) {
    echo "❌ 'Ursprung 1001' not found in this work order\n\n";
} else {
    echo "✅ Found " . count($results) . " occurrence(s):\n\n";

    foreach ($results as $result) {
        echo "📍 Field path: {$result['path']}\n";
        echo "📝 Value:\n";
        echo "   " . wordwrap($result['value'], 70, "\n   ") . "\n\n";
    }
}

echo "\n";
