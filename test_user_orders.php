#!/usr/bin/env php
<?php
/**
 * Test Script: Fetch Work Orders by User Email
 *
 * This script fetches work orders from FAST2 API and filters them by
 * the user's email address (annanAnmalare.epostAdress field).
 *
 * Usage: php test_user_orders.php [email@example.com]
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 API Test Scripts
 */

// Enable error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/fetch_arbetsordrar.php';

/**
 * Display a work order in a readable format
 */
function displayWorkOrder($order, $index)
{
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " Arbetsorder #{$index}: {$order['id']}\n";
    echo "═══════════════════════════════════════════════════════════════\n";

    // Basic info
    echo "📋 Typ: {$order['arbetsorderTyp']['arbetsordertypBesk']} ({$order['arbetsorderTyp']['arbetsordertypKod']})\n";
    echo "📊 Status: {$order['status']['statusKod']}\n";
    echo "⚡ Prioritet: {$order['prio']['prioBesk']} ({$order['prio']['prioKod']})\n";

    // Dates
    if (!empty($order['registrerad']['datumRegistrerad'])) {
        $datum = $order['registrerad']['datumRegistrerad'];
        $formatted = substr($datum, 0, 4) . '-' . substr($datum, 4, 2) . '-' . substr($datum, 6, 2);
        echo "📅 Registrerad: {$formatted}\n";
    }

    // Reporter (annanAnmalare)
    if (!empty($order['annanAnmalare'])) {
        echo "\n👤 Anmälare:\n";
        if (!empty($order['annanAnmalare']['namn'])) {
            echo "   Namn: {$order['annanAnmalare']['namn']}\n";
        }
        if (!empty($order['annanAnmalare']['epostAdress'])) {
            echo "   E-post: {$order['annanAnmalare']['epostAdress']}\n";
        }
        if (!empty($order['annanAnmalare']['telefon'])) {
            echo "   Telefon: {$order['annanAnmalare']['telefon']}\n";
        }
    }

    // Object/Property
    if (!empty($order['objekt']['id'])) {
        echo "\n🏢 Objekt: {$order['objekt']['id']}\n";
    }

    // Description
    if (!empty($order['information']['beskrivning'])) {
        echo "\n📝 Beskrivning:\n";
        $beskrivning = wordwrap($order['information']['beskrivning'], 60);
        $lines = explode("\n", $beskrivning);
        foreach ($lines as $line) {
            echo "   " . $line . "\n";
        }
    }

    // Fras (predefined phrase)
    if (!empty($order['fras']['frasBesk'])) {
        echo "\n🔖 Fras: {$order['fras']['frasBesk']}\n";
    }

    // Comment if exists
    if (!empty($order['information']['kommentar'])) {
        echo "\n💬 Kommentar: {$order['information']['kommentar']}\n";
    }

    // Action taken if exists
    if (!empty($order['information']['atgard'])) {
        echo "\n✅ Åtgärd: {$order['information']['atgard']}\n";
    }

    echo "\n";
}

/**
 * Main execution
 */
function main()
{
    global $argv;

    // Get email from command line argument or use default
    $userEmail = isset($argv[1]) ? $argv[1] : 'tomas.bollingnilsson@falkenberg.se';

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  FAST2 API - Fetch Work Orders by User Email              ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "🔍 Searching for work orders by: {$userEmail}\n\n";

    try {
        // Load configuration
        $envFile = __DIR__ . '/.env';
        echo "📁 Loading configuration from .env...\n";
        $config = loadEnv($envFile);

        // Validate required configuration
        $required = ['OAUTH2_TOKEN_ENDPOINT', 'CONSUMER_KEY', 'CONSUMER_SECRET',
                     'FAST2_BASE_URL', 'FAST2_USERNAME', 'FAST2_PASSWORD', 'KUND_ID'];
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new Exception("Missing required configuration: {$key}");
            }
        }

        echo "✅ Configuration loaded\n\n";

        // Create client and fetch all work orders
        $client = new Fast2WorkOrderClient($config, false);
        $allOrders = $client->fetchWorkOrders();

        echo "\n📊 Total work orders fetched: " . count($allOrders) . "\n";
        echo "🔎 Filtering by email: {$userEmail}...\n\n";

        // Filter work orders by user email
        $userOrders = [];
        foreach ($allOrders as $order) {
            // Check if annanAnmalare.epostAdress matches
            if (isset($order['annanAnmalare']['epostAdress']) &&
                strtolower(trim($order['annanAnmalare']['epostAdress'])) === strtolower(trim($userEmail))) {
                $userOrders[] = $order;
            }
        }

        // Display results
        if (empty($userOrders)) {
            echo "❌ Inga arbetsordrar hittades för {$userEmail}\n";
            echo "\n💡 Tips:\n";
            echo "   - Kontrollera att e-postadressen är korrekt stavad\n";
            echo "   - E-postadressen måste vara registrerad som 'Annan anmälare' i FAST2\n";
            echo "   - Arbetsordrar skapade via 'Mina sidor' systemanvändare har ofta 'annanAnmalare' satt\n";
            echo "\n";
        } else {
            echo "✅ Hittade " . count($userOrders) . " arbetsorder/ordrar för {$userEmail}\n";

            // Sort by ID (most recent first if higher IDs = newer)
            usort($userOrders, function($a, $b) {
                return $b['id'] - $a['id'];
            });

            // Display each order
            foreach ($userOrders as $index => $order) {
                displayWorkOrder($order, $index + 1);
            }

            // Summary
            echo "\n";
            echo "╔════════════════════════════════════════════════════════════╗\n";
            echo "║  Summary                                                   ║\n";
            echo "╚════════════════════════════════════════════════════════════╝\n";
            echo "\n";
            echo "Total orders for {$userEmail}: " . count($userOrders) . "\n";

            // Count by status
            $statusCounts = [];
            foreach ($userOrders as $order) {
                $status = $order['status']['statusKod'] ?? 'UNKNOWN';
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }

            echo "\nOrders by status:\n";
            foreach ($statusCounts as $status => $count) {
                echo "  {$status}: {$count}\n";
            }

            // Count by type
            $typeCounts = [];
            foreach ($userOrders as $order) {
                $type = $order['arbetsorderTyp']['arbetsordertypBesk'] ?? 'UNKNOWN';
                $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            }

            echo "\nOrders by type:\n";
            foreach ($typeCounts as $type => $count) {
                echo "  {$type}: {$count}\n";
            }

            echo "\n";
        }

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Run the script if executed directly
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    main();
}
