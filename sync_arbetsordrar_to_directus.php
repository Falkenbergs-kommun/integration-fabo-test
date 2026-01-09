#!/usr/bin/env php
<?php
/**
 * Sync Work Orders from FAST2 to Directus
 *
 * This script:
 * 1. Fetches all work orders from FAST2 API
 * 2. Syncs them to Directus collection 'fast2_arbetsordrar'
 * 3. Implements soft delete (marks work orders as inactive if removed from FAST2)
 *
 * Usage: php sync_arbetsordrar_to_directus.php [-v|--verbose]
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 API Test Scripts
 */

// Enable error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/DirectusClient.php';
require_once __DIR__ . '/fetch_arbetsordrar.php';

/**
 * Transform FAST2 work order to Directus format
 */
function transformWorkOrderToDirectusFormat($workOrder)
{
    return [
        'id' => $workOrder['id'],
        'externt_id' => $workOrder['externtId'] ?? null,
        'externt_nr' => $workOrder['externtNr'] ?? null,

        // Related IDs
        'objekt_id' => $workOrder['objekt']['id'] ?? null,
        'kund_id' => $workOrder['kund']['id'] ?? null,

        // Work order type
        'arbetsorder_typ_kod' => $workOrder['arbetsorderTyp']['arbetsordertypKod'] ?? null,
        'arbetsorder_typ_besk' => $workOrder['arbetsorderTyp']['arbetsordertypBesk'] ?? null,
        'status_kod' => $workOrder['status']['statusKod'] ?? null,

        // Priority
        'prio_kod' => $workOrder['prio']['prioKod'] ?? null,
        'prio_besk' => $workOrder['prio']['prioBesk'] ?? null,

        // Descriptions
        'beskrivning' => $workOrder['information']['beskrivning'] ?? null,
        'kommentar' => $workOrder['information']['kommentar'] ?? null,
        'anmarkning' => $workOrder['information']['anmarkning'] ?? null,
        'atgard' => $workOrder['information']['atgard'] ?? null,

        // Dates - convert from YYYYMMDD format to ISO date
        'datum_registrerad' => isset($workOrder['registrerad']['datumRegistrerad'])
            ? convertFast2Date($workOrder['registrerad']['datumRegistrerad'])
            : null,
        'datum_bestalld' => isset($workOrder['bestalld']['datumBestalld'])
            ? convertFast2Date($workOrder['bestalld']['datumBestalld'])
            : null,
        'datum_accepterad' => isset($workOrder['accepterad']['datumAccepterad'])
            ? convertFast2Date($workOrder['accepterad']['datumAccepterad'])
            : null,
        'datum_utford' => isset($workOrder['utford']['datumUtford'])
            ? convertFast2Date($workOrder['utford']['datumUtford'])
            : null,
        'datum_modifierad' => $workOrder['modifierad']['datumModifierad'] ?? null,

        // Performer (utförare)
        'utforare_id' => $workOrder['utforare']['id'] ?? null,
        'utforare_namn' => $workOrder['utforare']['namn'] ?? null,

        // Reporter (anmälare)
        'annan_anmalare_namn' => $workOrder['annanAnmalare']['namn'] ?? null,
        'annan_anmalare_epost' => $workOrder['annanAnmalare']['epostAdress'] ?? null,
        'annan_anmalare_telefon' => $workOrder['annanAnmalare']['telefon'] ?? null,

        // Complex objects as JSON
        'bunt' => isset($workOrder['bunt']) ? json_encode($workOrder['bunt']) : null,
        'utforare' => isset($workOrder['utforare']) ? json_encode($workOrder['utforare']) : null,
        'utrymme' => isset($workOrder['utrymme']) ? json_encode($workOrder['utrymme']) : null,
        'enhet' => isset($workOrder['enhet']) ? json_encode($workOrder['enhet']) : null,
        'registrerad' => isset($workOrder['registrerad']) ? json_encode($workOrder['registrerad']) : null,
        'planering' => isset($workOrder['planering']) ? json_encode($workOrder['planering']) : null,
        'ekonomi' => isset($workOrder['ekonomi']) ? json_encode($workOrder['ekonomi']) : null,

        // Status tracking
        'status' => 'active',
        'last_synced' => date('Y-m-d H:i:s'),

        // Raw data backup
        'raw_data' => json_encode($workOrder),
    ];
}

/**
 * Convert FAST2 date format (YYYYMMDD) to ISO format (YYYY-MM-DD)
 */
function convertFast2Date($dateString)
{
    if (empty($dateString) || $dateString === null) {
        return null;
    }

    // Check if it's already in ISO format or a timestamp
    if (strpos($dateString, '-') !== false || strpos($dateString, 'T') !== false) {
        return $dateString;
    }

    // Convert YYYYMMDD to YYYY-MM-DD
    if (strlen($dateString) === 8 && is_numeric($dateString)) {
        return substr($dateString, 0, 4) . '-' . substr($dateString, 4, 2) . '-' . substr($dateString, 6, 2);
    }

    return $dateString;
}

/**
 * Main synchronization logic
 */
function main()
{
    global $argv;
    $verbose = in_array('-v', $argv) || in_array('--verbose', $argv);

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  FAST2 → Directus Sync - Arbetsordrar                     ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    $startTime = microtime(true);
    $errors = [];

    try {
        // Load configuration
        echo "📁 Loading configuration...\n";
        $envFile = __DIR__ . '/.env';
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
        echo "✅ Configuration loaded\n\n";

        // Step 1: Fetch work orders from FAST2
        echo "🔄 Step 1/4: Fetching work orders from FAST2 API...\n";
        $fast2Client = new Fast2WorkOrderClient($config, $verbose);
        $fast2WorkOrders = $fast2Client->fetchWorkOrders();
        echo "✅ Fetched " . count($fast2WorkOrders) . " work orders from FAST2\n\n";

        // Step 2: Get existing work orders from Directus
        echo "🔄 Step 2/4: Fetching existing work orders from Directus...\n";
        $directus = new DirectusClient($config['DIRECTUS_API_URL'], $config['DIRECTUS_API_TOKEN'], $verbose);
        $response = $directus->getItems('fast2_arbetsordrar', [], 10000);
        $existingWorkOrders = $response['data'] ?? [];
        echo "✅ Found " . count($existingWorkOrders) . " existing work orders in Directus\n\n";

        // Build lookup map of existing work orders by ID
        $existingMap = [];
        foreach ($existingWorkOrders as $wo) {
            if (isset($wo['id'])) {
                $existingMap[$wo['id']] = $wo;
            }
        }

        // Step 3: Sync work orders
        echo "🔄 Step 3/4: Synchronizing work orders...\n";
        $created = 0;
        $updated = 0;
        $counter = 0;

        foreach ($fast2WorkOrders as $workOrder) {
            $counter++;
            $id = $workOrder['id'];

            try {
                $directusData = transformWorkOrderToDirectusFormat($workOrder);

                if (isset($existingMap[$id])) {
                    // Update existing
                    $directus->updateItem('fast2_arbetsordrar', $id, $directusData);
                    $updated++;
                } else {
                    // Create new
                    $directus->createItem('fast2_arbetsordrar', $directusData);
                    $created++;
                }

                // Mark as synced
                unset($existingMap[$id]);

                // Progress indicator
                if ($counter % 10 == 0 || $counter == count($fast2WorkOrders)) {
                    echo "  Processing: {$counter}/" . count($fast2WorkOrders) . " - {$id}\n";
                }

            } catch (Exception $e) {
                $error = "Failed to sync {$id}: " . $e->getMessage();
                $errors[] = $error;
                echo "   ⚠️  Error: {$error}\n";
            }
        }

        echo "✅ Synchronized " . count($fast2WorkOrders) . " work orders\n\n";

        // Step 4: Mark remaining work orders as inactive (soft delete)
        echo "🔄 Step 4/4: Marking inactive work orders...\n";
        $inactivated = 0;

        foreach ($existingMap as $id => $workOrder) {
            try {
                if (!isset($workOrder['status']) || $workOrder['status'] !== 'inactive') {
                    $directus->updateItem('fast2_arbetsordrar', $id, [
                        'status' => 'inactive',
                        'last_synced' => date('Y-m-d H:i:s')
                    ]);
                    $inactivated++;
                }
            } catch (Exception $e) {
                $error = "Failed to mark {$id} as inactive: " . $e->getMessage();
                $errors[] = $error;
                echo "   ⚠️  Error: {$error}\n";
            }
        }

        echo "✅ Marked {$inactivated} work orders as inactive\n\n";

        // Get final counts
        $response = $directus->getItems('fast2_arbetsordrar', [], 10000);
        $allWorkOrders = $response['data'] ?? [];
        $activeCount = count(array_filter($allWorkOrders, function($wo) {
            return isset($wo['status']) && $wo['status'] === 'active';
        }));
        $inactiveCount = count($allWorkOrders) - $activeCount;

        // Summary
        $duration = round(microtime(true) - $startTime, 2);

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  Synchronization Complete                                  ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "FAST2 Work Orders: " . count($fast2WorkOrders) . "\n";
        echo "Directus before sync: " . count($existingWorkOrders) . "\n";
        echo "---\n";
        echo "Created: {$created}\n";
        echo "Updated: {$updated}\n";
        echo "Marked inactive: {$inactivated}\n";
        echo "---\n";
        echo "Total in Directus now: " . count($allWorkOrders) . " ({$activeCount} active, {$inactiveCount} inactive)\n";
        echo "Duration: {$duration}s\n";

        if (count($errors) > 0) {
            echo "\n⚠️  Errors encountered: " . count($errors) . "\n";
            foreach ($errors as $error) {
                echo "  - {$error}\n";
            }
        }

        echo "\n✅ Sync completed successfully!\n\n";

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Run the script
main();
