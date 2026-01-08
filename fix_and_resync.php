#!/usr/bin/env php
<?php
/**
 * Fix ID Field Type and Re-sync
 *
 * This script fixes the ID field type issue by:
 * 1. Deleting the existing fast2_fastigheter collection
 * 2. Recreating it with proper string type for ID field
 * 3. Running the synchronization
 *
 * Usage: php fix_and_resync.php [-v|--verbose]
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 Fastigheter Sync
 */

// Enable error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/DirectusClient.php';

/**
 * Load environment variables from .env file
 */
function loadEnv($filePath)
{
    if (!file_exists($filePath)) {
        throw new Exception('.env file not found.');
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }

    return $config;
}

/**
 * Main execution
 */
function main()
{
    global $argv;
    $verbose = in_array('-v', $argv) || in_array('--verbose', $argv);

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Fix ID Field Type & Re-sync                               ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    try {
        // Load configuration
        $envFile = __DIR__ . '/.env';
        echo "📁 Loading configuration...\n";
        $config = loadEnv($envFile);

        // Validate required config
        $required = ['DIRECTUS_API_URL', 'DIRECTUS_API_TOKEN'];
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new Exception("Missing required configuration: {$key}");
            }
        }

        // Create Directus client
        $client = new DirectusClient($config['DIRECTUS_API_URL'], $config['DIRECTUS_API_TOKEN'], $verbose);
        $collectionName = 'fast2_fastigheter';

        // Step 1: Delete existing collection
        echo "\n🗑️  Step 1/3: Deleting existing collection...\n";
        if ($client->collectionExists($collectionName)) {
            echo "   Collection exists, deleting...\n";
            $client->deleteCollection($collectionName);
            echo "✅ Collection deleted successfully\n";
        } else {
            echo "ℹ️  Collection doesn't exist, skipping deletion\n";
        }

        // Step 2: Run create_directus_collection.php
        echo "\n📦 Step 2/3: Creating collection with correct schema...\n";
        echo "   Running create_directus_collection.php...\n";

        $output = [];
        $returnCode = 0;
        exec('php ' . __DIR__ . '/create_directus_collection.php 2>&1', $output, $returnCode);

        foreach ($output as $line) {
            echo "   " . $line . "\n";
        }

        if ($returnCode !== 0) {
            throw new Exception("Failed to create collection (exit code: {$returnCode})");
        }

        echo "✅ Collection created successfully\n";

        // Step 3: Run sync_to_directus.php
        echo "\n🔄 Step 3/3: Running synchronization...\n";
        echo "   Running sync_to_directus.php...\n\n";

        $syncCommand = 'php ' . __DIR__ . '/sync_to_directus.php';
        if ($verbose) {
            $syncCommand .= ' -v';
        }

        passthru($syncCommand, $syncReturnCode);

        if ($syncReturnCode !== 0) {
            throw new Exception("Sync failed (exit code: {$syncReturnCode})");
        }

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  Fix Complete!                                             ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "✅ All steps completed successfully!\n";
        echo "   - Collection recreated with string ID field\n";
        echo "   - All properties should now be synced without errors\n\n";

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Run the script
main();
