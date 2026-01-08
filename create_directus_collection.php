#!/usr/bin/env php
<?php
/**
 * Create Directus Collection for FAST2 Fastigheter
 *
 * This script creates the 'fast2_fastigheter' collection in Directus
 * with all required fields for storing property data from FAST2 API.
 *
 * Usage: php create_directus_collection.php
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
        throw new Exception('.env file not found. Please configure it first.');
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
 * Get field definitions for the collection
 */
function getFieldDefinitions()
{
    return [
        // Primary key - explicitly define as string to support large IDs like "11002011601"
        [
            'field' => 'id',
            'type' => 'string',
            'meta' => [
                'interface' => 'input',
                'readonly' => true,
                'hidden' => false,
                'width' => 'half'
            ],
            'schema' => [
                'is_primary_key' => true,
                'has_auto_increment' => false,
                'is_nullable' => false,
                'max_length' => 255
            ]
        ],

        // Status and sync fields
        [
            'field' => 'status',
            'type' => 'string',
            'meta' => [
                'interface' => 'select-dropdown',
                'options' => [
                    'choices' => [
                        ['text' => 'Active', 'value' => 'active'],
                        ['text' => 'Inactive', 'value' => 'inactive']
                    ]
                ],
                'display' => 'labels',
                'display_options' => [
                    'choices' => [
                        ['text' => 'Active', 'value' => 'active', 'foreground' => '#FFFFFF', 'background' => '#00C897'],
                        ['text' => 'Inactive', 'value' => 'inactive', 'foreground' => '#FFFFFF', 'background' => '#A2B5CD']
                    ]
                ],
                'required' => true,
                'width' => 'half'
            ],
            'schema' => [
                'default_value' => 'active',
                'is_nullable' => false
            ]
        ],
        [
            'field' => 'last_synced',
            'type' => 'timestamp',
            'meta' => [
                'interface' => 'datetime',
                'readonly' => true,
                'width' => 'half'
            ],
            'schema' => [
                'is_nullable' => true
            ]
        ],

        // Address fields
        [
            'field' => 'adress',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'full'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'lghnummer',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'postnummer',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'postort',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'xkoord',
            'type' => 'integer',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'ykoord',
            'type' => 'integer',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],

        // Detail fields
        [
            'field' => 'vaning_id',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'vaning_beskrivning',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'vaning_nummer',
            'type' => 'integer',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'hiss',
            'type' => 'boolean',
            'meta' => ['interface' => 'boolean', 'width' => 'half'],
            'schema' => ['default_value' => false, 'is_nullable' => false]
        ],
        [
            'field' => 'yta',
            'type' => 'integer',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'anmarkning',
            'type' => 'text',
            'meta' => ['interface' => 'input-multiline', 'width' => 'full'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'text',
            'type' => 'text',
            'meta' => ['interface' => 'input-multiline', 'width' => 'full'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'hyressparr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'hyressparr_anmarkning',
            'type' => 'text',
            'meta' => ['interface' => 'input-multiline', 'width' => 'full'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'besiktigad_datum',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],

        // Type fields
        [
            'field' => 'objekts_otyp',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'objekts_typ',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'typ_beskrivning',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'full'],
            'schema' => ['is_nullable' => true]
        ],

        // Customer fields
        [
            'field' => 'kund_id1',
            'type' => 'integer',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'kund_id2',
            'type' => 'integer',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],

        // Relation fields
        [
            'field' => 'foretag_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'fastighet_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'byggnad_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'sokomrade1_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'sokomrade2_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'sokomrade3_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'adminomrade_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'felomrade_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'underhallomrade_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'ingar_i',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],

        // Raw data (JSON)
        [
            'field' => 'raw_data',
            'type' => 'json',
            'meta' => [
                'interface' => 'input-code',
                'options' => ['language' => 'json'],
                'width' => 'full'
            ],
            'schema' => ['is_nullable' => true]
        ]
    ];
}

/**
 * Main execution
 */
function main()
{
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Create Directus Collection - fast2_fastigheter           ║\n";
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
        $client = new DirectusClient($config['DIRECTUS_API_URL'], $config['DIRECTUS_API_TOKEN'], true);

        $collectionName = 'fast2_fastigheter';

        // Check if collection already exists
        echo "\n🔍 Checking if collection exists...\n";
        if ($client->collectionExists($collectionName)) {
            echo "⚠️  Collection '{$collectionName}' already exists!\n";
            echo "\nWould you like to continue anyway? This will try to add missing fields.\n";
            echo "Type 'yes' to continue or anything else to abort: ";

            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);

            if (strtolower($line) !== 'yes') {
                echo "\n❌ Aborted.\n\n";
                exit(0);
            }
        } else {
            // Create collection
            echo "\n📦 Creating collection '{$collectionName}'...\n";
            $client->createCollection($collectionName, [
                'name' => $collectionName
            ]);
            echo "✅ Collection created successfully\n";

            // Delete the auto-created numeric ID field
            echo "\n🔧 Fixing auto-created ID field...\n";
            try {
                echo "  Deleting auto-created numeric ID field...\n";
                $client->deleteField($collectionName, 'id');
                echo "  ✅ Deleted auto-created ID field\n";
            } catch (Exception $e) {
                echo "  ⚠️  Could not delete ID field: " . $e->getMessage() . "\n";
            }
        }

        // Create fields
        echo "\n📝 Creating fields...\n";
        $fields = getFieldDefinitions();
        $created = 0;
        $skipped = 0;

        foreach ($fields as $fieldDef) {
            try {
                // Special handling for ID field - make sure it's string type
                if ($fieldDef['field'] === 'id') {
                    echo "  Creating string ID field (primary key)...\n";
                }
                $client->createField($collectionName, $fieldDef['field'], $fieldDef);
                $created++;
                echo "  ✅ Created field: {$fieldDef['field']}\n";
            } catch (Exception $e) {
                // Field probably already exists, try to update it
                if ($fieldDef['field'] === 'id') {
                    echo "  ⚠️  ID field exists, trying to update to string type...\n";
                    try {
                        $client->updateField($collectionName, 'id', $fieldDef);
                        $created++;
                        echo "  ✅ Updated ID field to string type\n";
                    } catch (Exception $e2) {
                        $skipped++;
                        echo "  ❌ Could not update ID field: " . $e2->getMessage() . "\n";
                    }
                } else {
                    $skipped++;
                    echo "  ⏭️  Skipped field: {$fieldDef['field']} (already exists)\n";
                }
            }
        }

        // Summary
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  Summary                                                   ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "Collection: {$collectionName}\n";
        echo "Fields created: {$created}\n";
        echo "Fields skipped: {$skipped}\n";
        echo "Total fields: " . count($fields) . "\n";
        echo "\n✅ Setup completed successfully!\n";
        echo "\nYou can now run the sync script:\n";
        echo "  php sync_to_directus.php\n\n";

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Run the script
main();
