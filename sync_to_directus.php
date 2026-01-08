#!/usr/bin/env php
<?php
/**
 * Sync FAST2 Fastigheter to Directus
 *
 * This script fetches properties from FAST2 API and synchronizes them
 * to Directus using soft delete strategy (inactive instead of delete).
 *
 * Usage: php sync_to_directus.php [-v|--verbose]
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 Fastigheter Sync
 */

// Enable error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/fetch_fastigheter.php';
require_once __DIR__ . '/DirectusClient.php';

/**
 * Transform FAST2 API node data to Directus format
 *
 * @param array $node FAST2 API node data
 * @return array Directus-formatted data
 */
function transformToDirectusFormat($node)
{
    return [
        'id' => $node['id'],

        // Address fields
        'adress' => $node['adress']['adress'] ?? null,
        'lghnummer' => $node['adress']['lghnummer'] ?? null,
        'postnummer' => $node['adress']['postnummer'] ?? null,
        'postort' => $node['adress']['postort'] ?? null,
        'xkoord' => $node['adress']['xkoord'] ?? null,
        'ykoord' => $node['adress']['ykoord'] ?? null,

        // Detail fields
        'vaning_id' => $node['detalj']['vaning']['id'] ?? null,
        'vaning_beskrivning' => $node['detalj']['vaning']['beskrivning'] ?? null,
        'vaning_nummer' => $node['detalj']['vaning']['nummer'] ?? null,
        'hiss' => $node['detalj']['hiss'] ?? false,
        'yta' => $node['detalj']['yta'] ?? null,
        'anmarkning' => $node['detalj']['anmarkning'] ?? null,
        'text' => $node['detalj']['text'] ?? null,
        'hyressparr' => $node['detalj']['hyressparr'] ?? null,
        'hyressparr_anmarkning' => $node['detalj']['hyressparrAnmarkning'] ?? null,
        'besiktigad_datum' => $node['detalj']['besiktigadDatum'] ?? null,

        // Type fields
        'objekts_otyp' => $node['typ']['objektsOTyp'] ?? null,
        'objekts_typ' => $node['typ']['objektsTyp'] ?? null,
        'typ_beskrivning' => $node['typ']['beskrivning'] ?? null,

        // Customer fields
        'kund_id1' => $node['kunder']['id1'] ?? null,
        'kund_id2' => $node['kunder']['id2'] ?? null,

        // Relation fields
        'foretag_nr' => $node['relationer']['foretagNr'] ?? null,
        'fastighet_nr' => $node['relationer']['fastighetNr'] ?? null,
        'byggnad_nr' => $node['relationer']['byggnadNr'] ?? null,
        'sokomrade1_nr' => $node['relationer']['sokomrade1Nr'] ?? null,
        'sokomrade2_nr' => $node['relationer']['sokomrade2Nr'] ?? null,
        'sokomrade3_nr' => $node['relationer']['sokomrade3Nr'] ?? null,
        'adminomrade_nr' => $node['relationer']['adminomradeNr'] ?? null,
        'felomrade_nr' => $node['relationer']['felomradeNr'] ?? null,
        'underhallomrade_nr' => $node['relationer']['underhallomradeNr'] ?? null,
        'ingar_i' => $node['relationer']['ingarI'] ?? null,

        // Raw data for future use
        'raw_data' => $node,

        // Status and sync timestamp
        'status' => 'active',
        'last_synced' => date('Y-m-d H:i:s')
    ];
}

/**
 * Main synchronization logic
 */
function main()
{
    // Check for verbose flag
    global $argv;
    $verbose = in_array('-v', $argv) || in_array('--verbose', $argv);

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  FAST2 → Directus Sync - Fastigheter                      ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    if ($verbose) {
        echo "ℹ️  Verbose mode enabled\n\n";
    }

    $startTime = microtime(true);
    $stats = [
        'fast2_total' => 0,
        'directus_before' => 0,
        'created' => 0,
        'updated' => 0,
        'marked_inactive' => 0,
        'errors' => []
    ];

    try {
        // Load configuration
        $envFile = __DIR__ . '/.env';
        echo "📁 Loading configuration...\n";
        $config = loadEnv($envFile);

        // Validate required config
        $required = [
            'OAUTH2_TOKEN_ENDPOINT',
            'CONSUMER_KEY',
            'CONSUMER_SECRET',
            'FAST2_BASE_URL',
            'FAST2_USERNAME',
            'FAST2_PASSWORD',
            'KUND_ID',
            'DIRECTUS_API_URL',
            'DIRECTUS_API_TOKEN'
        ];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new Exception("Missing required configuration: {$key}");
            }
        }

        echo "✅ Configuration loaded\n\n";

        // Step 1: Fetch properties from FAST2 API
        echo "🔄 Step 1/4: Fetching properties from FAST2 API...\n";
        $fast2Client = new Fast2ApiClient($config, false);  // verbose=false for cleaner output
        $apiData = $fast2Client->fetchProperties();

        if (!isset($apiData['edges']) || !is_array($apiData['edges'])) {
            throw new Exception("Invalid API response: missing 'edges' array");
        }

        $stats['fast2_total'] = count($apiData['edges']);
        echo "✅ Fetched {$stats['fast2_total']} properties from FAST2\n\n";

        // Step 2: Fetch existing properties from Directus
        echo "🔄 Step 2/4: Fetching existing properties from Directus...\n";
        $directusClient = new DirectusClient(
            $config['DIRECTUS_API_URL'],
            $config['DIRECTUS_API_TOKEN'],
            $verbose
        );

        $collectionName = 'fast2_fastigheter';

        // Check if collection exists
        if (!$directusClient->collectionExists($collectionName)) {
            throw new Exception(
                "Collection '{$collectionName}' does not exist in Directus.\n" .
                "Please run 'php create_directus_collection.php' first."
            );
        }

        $existingData = $directusClient->getItems($collectionName, [], 10000);
        $existingProperties = $existingData['data'] ?? [];
        $stats['directus_before'] = count($existingProperties);

        echo "✅ Found {$stats['directus_before']} existing properties in Directus\n\n";

        // Build lookup maps
        $existingById = [];
        foreach ($existingProperties as $prop) {
            $existingById[$prop['id']] = $prop;
        }

        // Collect all FAST2 IDs
        $fast2Ids = [];
        foreach ($apiData['edges'] as $edge) {
            $fast2Ids[] = $edge['node']['id'];
        }

        // Step 3: Sync properties (create/update)
        echo "🔄 Step 3/4: Synchronizing properties...\n";

        foreach ($apiData['edges'] as $index => $edge) {
            $node = $edge['node'];
            $propertyId = $node['id'];

            $progressNum = $index + 1;
            if ($verbose || $progressNum % 10 === 0 || $progressNum === $stats['fast2_total']) {
                echo "  Processing: {$progressNum}/{$stats['fast2_total']} - {$propertyId}\n";
            }

            try {
                // Transform data
                $directusData = transformToDirectusFormat($node);

                // Check if property exists
                $existing = $existingById[$propertyId] ?? null;

                if ($existing) {
                    // Update existing property
                    // Only update if status needs to change or if we want to refresh data
                    $needsUpdate = $existing['status'] !== 'active';

                    if ($needsUpdate || true) {  // Always update to refresh data
                        $directusClient->updateItem($collectionName, $propertyId, $directusData);
                        $stats['updated']++;

                        if ($verbose) {
                            echo "    ✅ Updated: {$propertyId}\n";
                        }
                    }
                } else {
                    // Create new property
                    $directusClient->createItem($collectionName, $directusData);
                    $stats['created']++;

                    if ($verbose) {
                        echo "    ✨ Created: {$propertyId}\n";
                    }
                }

            } catch (Exception $e) {
                $error = "Failed to sync {$propertyId}: " . $e->getMessage();
                $stats['errors'][] = $error;
                echo "    ⚠️  Error: {$error}\n";
            }
        }

        echo "✅ Synchronized {$stats['fast2_total']} properties\n\n";

        // Step 4: Mark inactive properties (soft delete)
        echo "🔄 Step 4/4: Marking inactive properties...\n";

        foreach ($existingProperties as $existing) {
            $propertyId = $existing['id'];

            // If property is not in FAST2 anymore and is currently active
            if (!in_array($propertyId, $fast2Ids) && $existing['status'] === 'active') {
                try {
                    $directusClient->updateItem($collectionName, $propertyId, [
                        'status' => 'inactive',
                        'last_synced' => date('Y-m-d H:i:s')
                    ]);

                    $stats['marked_inactive']++;

                    if ($verbose) {
                        echo "  ⚫ Marked inactive: {$propertyId}\n";
                    }

                } catch (Exception $e) {
                    $error = "Failed to mark {$propertyId} as inactive: " . $e->getMessage();
                    $stats['errors'][] = $error;
                    echo "  ⚠️  Error: {$error}\n";
                }
            }
        }

        echo "✅ Marked {$stats['marked_inactive']} properties as inactive\n\n";

        // Calculate final stats
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $activeCount = $stats['directus_before'] + $stats['created'] - $stats['marked_inactive'];
        $inactiveCount = $stats['marked_inactive'];
        $totalCount = $activeCount + $inactiveCount;

        // Display summary
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  Synchronization Complete                                  ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "FAST2 Properties: {$stats['fast2_total']}\n";
        echo "Directus before sync: {$stats['directus_before']}\n";
        echo "---\n";
        echo "Created: {$stats['created']}\n";
        echo "Updated: {$stats['updated']}\n";
        echo "Marked inactive: {$stats['marked_inactive']}\n";
        echo "---\n";
        echo "Total in Directus now: {$totalCount} ({$activeCount} active, {$inactiveCount} inactive)\n";
        echo "Duration: {$duration}s\n";

        if (!empty($stats['errors'])) {
            echo "\n⚠️  Errors encountered: " . count($stats['errors']) . "\n";
            foreach ($stats['errors'] as $error) {
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
