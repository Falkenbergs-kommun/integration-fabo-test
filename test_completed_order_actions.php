#!/usr/bin/env php
<?php
/**
 * Test Script: Fetch Actions on Completed Work Order
 *
 * This script fetches a completed work order from FAST2 API and examines
 * what action/activity data is available on it.
 *
 * It demonstrates two methods of retrieving action information:
 * 1. Time registrations via /v1/arbetsorder/tider endpoint
 * 2. Action description field (information.atgard) in work order
 *
 * Usage: php test_completed_order_actions.php [arbetsorderId]
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 API Test Scripts
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/fetch_arbetsordrar.php';

/**
 * Fetch time registrations for a work order
 *
 * @param Fast2WorkOrderClient $client The API client
 * @param string|int $arbetsorderId Work order ID
 * @return array Time registrations
 */
function fetchTimeRegistrations($client, $arbetsorderId, $config)
{
    echo '[' . date('Y-m-d H:i:s') . '] ⏱️  Fetching time registrations for work order ' . $arbetsorderId . "...\n";

    // First authenticate if needed
    $oauth2Token = getOAuth2TokenForTimes($config);
    $apiToken = loginToApiForTimes($config, $oauth2Token);

    $url = $config['FAST2_BASE_URL'] . '/ao-produkt/v1/arbetsorder/tider?arbetsorderId=' . $arbetsorderId;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $oauth2Token,
        'X-Auth-Token: ' . $apiToken,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("Time registrations request failed: {$error}");
    }

    if ($httpCode !== 200) {
        throw new Exception("Time registrations request failed (HTTP {$httpCode}): {$response}");
    }

    $data = json_decode($response, true);
    if ($data === null) {
        throw new Exception('Failed to parse time registrations response as JSON');
    }

    echo '[' . date('Y-m-d H:i:s') . '] ✅ Successfully fetched ' . count($data) . " time registration(s)\n";

    return $data;
}

/**
 * Get OAuth2 token
 */
function getOAuth2TokenForTimes($config)
{
    $credentials = base64_encode($config['CONSUMER_KEY'] . ':' . $config['CONSUMER_SECRET']);

    $ch = curl_init($config['OAUTH2_TOKEN_ENDPOINT']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("OAuth2 token request failed (HTTP {$httpCode})");
    }

    $data = json_decode($response, true);
    return $data['access_token'];
}

/**
 * Login to API
 */
function loginToApiForTimes($config, $oauth2Token)
{
    $url = $config['FAST2_BASE_URL'] . '/ao-produkt/v1/auth/login';
    $loginData = [
        'username' => $config['FAST2_USERNAME'],
        'password' => $config['FAST2_PASSWORD'],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $oauth2Token,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("API login failed (HTTP {$httpCode})");
    }

    $data = json_decode($response, true);
    return $data['access_token'];
}

/**
 * Display a work order with all its details
 */
function displayWorkOrder($order)
{
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║  ARBETSORDER: {$order['id']}                                      \n";
    echo "╚═══════════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    // Basic info
    echo "📋 Typ: {$order['arbetsorderTyp']['arbetsordertypBesk']} ({$order['arbetsorderTyp']['arbetsordertypKod']})\n";
    $statusBesk = isset($order['status']['statusBesk']) ? $order['status']['statusBesk'] : '';
    echo "📊 Status: {$order['status']['statusKod']}";
    if ($statusBesk) {
        echo " - {$statusBesk}";
    }
    echo "\n";
    echo "⚡ Prioritet: {$order['prio']['prioBesk']} ({$order['prio']['prioKod']})\n";
    echo "🏢 Objekt: {$order['objekt']['id']}\n";

    // Dates
    echo "\n📅 Datum:\n";
    if (!empty($order['registrerad']['datumRegistrerad'])) {
        $datum = $order['registrerad']['datumRegistrerad'];
        $formatted = substr($datum, 0, 4) . '-' . substr($datum, 4, 2) . '-' . substr($datum, 6, 2);
        echo "   Registrerad: {$formatted}\n";
    }
    if (!empty($order['bestallning']['datumBestallning'])) {
        $datum = $order['bestallning']['datumBestallning'];
        $formatted = substr($datum, 0, 4) . '-' . substr($datum, 4, 2) . '-' . substr($datum, 6, 2);
        echo "   Beställd: {$formatted}\n";
    }
    if (!empty($order['utford']['datumUtford'])) {
        $datum = $order['utford']['datumUtford'];
        $formatted = substr($datum, 0, 4) . '-' . substr($datum, 4, 2) . '-' . substr($datum, 6, 2);
        echo "   Utförd: {$formatted}\n";
    }

    // Description
    if (!empty($order['information']['beskrivning'])) {
        echo "\n📝 Beskrivning:\n";
        $beskrivning = wordwrap($order['information']['beskrivning'], 65);
        $lines = explode("\n", $beskrivning);
        foreach ($lines as $line) {
            echo "   " . $line . "\n";
        }
    }

    // Fras
    if (!empty($order['fras']['frasBesk'])) {
        echo "\n🔖 Fras: {$order['fras']['frasBesk']}\n";
    }

    // Comment
    if (!empty($order['information']['kommentar'])) {
        echo "\n💬 Kommentar:\n";
        $kommentar = wordwrap($order['information']['kommentar'], 65);
        $lines = explode("\n", $kommentar);
        foreach ($lines as $line) {
            echo "   " . $line . "\n";
        }
    }

    // *** THIS IS THE IMPORTANT FIELD FOR ACTIONS! ***
    if (!empty($order['information']['atgard'])) {
        echo "\n✅ ÅTGÄRD (information.atgard):\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $atgard = wordwrap($order['information']['atgard'], 65);
        $lines = explode("\n", $atgard);
        foreach ($lines as $line) {
            echo "   " . $line . "\n";
        }
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    } else {
        echo "\n⚠️  ÅTGÄRD: Ingen åtgärd registrerad (information.atgard är tom)\n";
    }

    // Reporter
    if (!empty($order['annanAnmalare'])) {
        echo "\n👤 Anmälare:\n";
        if (!empty($order['annanAnmalare']['namn'])) {
            echo "   Namn: {$order['annanAnmalare']['namn']}\n";
        }
        if (!empty($order['annanAnmalare']['epostAdress'])) {
            echo "   E-post: {$order['annanAnmalare']['epostAdress']}\n";
        }
    }

    // Performer
    if (!empty($order['utforare']['utforareBesk'])) {
        echo "\n👷 Utförare: {$order['utforare']['utforareBesk']}\n";
    }

    echo "\n";
}

/**
 * Display time registrations
 */
function displayTimeRegistrations($timeRegs)
{
    if (empty($timeRegs)) {
        echo "⚠️  Inga tidregistreringar hittades\n\n";
        return;
    }

    echo "╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║  TIDREGISTRERINGAR / AKTIVITETER                                  ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Antal tidregistreringar: " . count($timeRegs) . "\n\n";

    foreach ($timeRegs as $index => $reg) {
        echo "───────────────────────────────────────────────────────────────────\n";
        echo "Tidregistrering #" . ($index + 1) . " (ID: {$reg['id']})\n";
        echo "───────────────────────────────────────────────────────────────────\n";

        // Resource/Person
        if (!empty($reg['resursId'])) {
            echo "👤 Resurs ID: {$reg['resursId']}\n";
        }

        // Time
        if (isset($reg['timmar']) || isset($reg['minuter'])) {
            $timmar = $reg['timmar'] ?? 0;
            $minuter = $reg['minuter'] ?? 0;
            echo "⏱️  Tid: {$timmar}h {$minuter}min\n";
        }

        // Start/End time
        if (!empty($reg['starttid'])) {
            echo "🕐 Starttid: {$reg['starttid']}\n";
        }
        if (!empty($reg['sluttid'])) {
            echo "🕐 Sluttid: {$reg['sluttid']}\n";
        }

        // Date range
        if (!empty($reg['datumFom'])) {
            $datum = $reg['datumFom'];
            $formatted = substr($datum, 0, 4) . '-' . substr($datum, 4, 2) . '-' . substr($datum, 6, 2);
            echo "📅 Datum från: {$formatted}\n";
        }
        if (!empty($reg['datumTom'])) {
            $datum = $reg['datumTom'];
            $formatted = substr($datum, 0, 4) . '-' . substr($datum, 4, 2) . '-' . substr($datum, 6, 2);
            echo "📅 Datum till: {$formatted}\n";
        }

        // Activity
        if (!empty($reg['aktivitet'])) {
            echo "🔨 AKTIVITET: {$reg['aktivitet']}\n";
        }

        // Phrase
        if (!empty($reg['frasId'])) {
            echo "🔖 Fras ID: {$reg['frasId']}\n";
        }

        // Price
        if (isset($reg['pris'])) {
            echo "💰 Pris: {$reg['pris']} kr\n";
        }

        echo "\n";
    }
}

/**
 * Main execution
 */
function main()
{
    global $argv;

    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║  FAST2 API - Undersök genomförda åtgärder på arbetsorder         ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════╝\n";
    echo "\n";

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

        // Create client
        $client = new Fast2WorkOrderClient($config, true);

        // Check if specific work order ID was provided
        if (isset($argv[1]) && !empty($argv[1])) {
            $orderId = $argv[1];
            echo "🎯 Using specified work order ID: {$orderId}\n\n";

            // Fetch this specific order first to display details
            echo "📋 Fetching work order details...\n";
            $allOrders = $client->fetchWorkOrders();
            $order = null;
            foreach ($allOrders as $o) {
                if ($o['id'] == $orderId) {
                    $order = $o;
                    break;
                }
            }

            if (!$order) {
                throw new Exception("Work order {$orderId} not found");
            }
        } else {
            // Fetch all work orders and find a completed one
            echo "🔍 Searching for completed work orders...\n\n";
            $allOrders = $client->fetchWorkOrders();

            // Filter for completed orders (status UTF = Utförd, KLA = Klar)
            $completedOrders = array_filter($allOrders, function($o) {
                $status = $o['status']['statusKod'] ?? '';
                return in_array($status, ['UTF', 'KLA', 'AVS']);
            });

            if (empty($completedOrders)) {
                echo "❌ Inga avslutade arbetsordrar hittades\n";
                echo "\nProva att ange ett specifikt arbetsorder-ID:\n";
                echo "php test_completed_order_actions.php [arbetsorderId]\n\n";
                exit(1);
            }

            // Get the first completed order
            $order = reset($completedOrders);
            echo "✅ Hittade " . count($completedOrders) . " avslutade arbetsordrar\n";
            echo "📌 Undersöker arbetsorder: {$order['id']}\n\n";
        }

        // Display work order details
        displayWorkOrder($order);

        // Fetch time registrations for this order
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $timeRegs = fetchTimeRegistrations($client, $order['id'], $config);

        // Display time registrations
        displayTimeRegistrations($timeRegs);

        // Summary
        echo "╔═══════════════════════════════════════════════════════════════════╗\n";
        echo "║  SAMMANFATTNING                                                   ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "📋 Arbetsorder: {$order['id']}\n";
        $statusBesk = isset($order['status']['statusBesk']) ? ' - ' . $order['status']['statusBesk'] : '';
        echo "📊 Status: {$order['status']['statusKod']}{$statusBesk}\n";
        echo "\n";
        echo "🔍 ÅTGÄRDSDATA SOM HITTADES:\n";
        echo "\n";

        // Check what data we found
        $foundAtgard = !empty($order['information']['atgard']);
        $foundTimeRegs = !empty($timeRegs);

        echo "1. information.atgard (åtgärdsbeskrivning):\n";
        if ($foundAtgard) {
            echo "   ✅ JA - Textbeskrivning av genomförd åtgärd finns\n";
            echo "   📝 \"" . substr($order['information']['atgard'], 0, 60) . "...\"\n";
        } else {
            echo "   ❌ NEJ - Inget åtgärdsfält ifyllt\n";
        }
        echo "\n";

        echo "2. Tidregistreringar (/v1/arbetsorder/tider):\n";
        if ($foundTimeRegs) {
            echo "   ✅ JA - {$foundTimeRegs} tidregistrering(ar) finns\n";
            foreach ($timeRegs as $reg) {
                $time = ($reg['timmar'] ?? 0) . "h " . ($reg['minuter'] ?? 0) . "min";
                $activity = !empty($reg['aktivitet']) ? " - " . $reg['aktivitet'] : "";
                echo "   • ID {$reg['id']}: {$time}{$activity}\n";
            }
        } else {
            echo "   ❌ NEJ - Inga tidregistreringar finns\n";
        }
        echo "\n";

        if (!$foundAtgard && !$foundTimeRegs) {
            echo "⚠️  SLUTSATS:\n";
            echo "Detta ärende har ingen detaljerad åtgärdsinformation registrerad.\n";
            echo "Varken textfält eller tidregistreringar har fyllts i.\n";
        } elseif ($foundAtgard && !$foundTimeRegs) {
            echo "💡 SLUTSATS:\n";
            echo "Åtgärd är beskriven i textform (information.atgard) men ingen\n";
            echo "detaljerad tidsredovisning har registrerats.\n";
        } elseif (!$foundAtgard && $foundTimeRegs) {
            echo "💡 SLUTSATS:\n";
            echo "Tidsredovisning finns men ingen textbeskrivning av åtgärden.\n";
        } else {
            echo "✅ SLUTSATS:\n";
            echo "Både textbeskrivning och tidsredovisning finns tillgänglig.\n";
        }

        echo "\n";

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Run the script
main();
