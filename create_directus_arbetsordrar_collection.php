#!/usr/bin/env php
<?php
/**
 * Create Directus Collection for Work Orders (Arbetsordrar)
 *
 * This script creates a Directus collection to store work orders from FAST2 API.
 * The collection includes both flattened fields for easy querying and JSON fields
 * for complex nested data.
 *
 * Usage: php create_directus_arbetsordrar_collection.php
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 API Test Scripts
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
 * Get field definitions for the work orders collection
 */
function getWorkOrderFieldDefinitions()
{
    return [
        // Primary key - string to support large IDs
        [
            'field' => 'id',
            'type' => 'integer',
            'meta' => [
                'interface' => 'input',
                'readonly' => true,
                'hidden' => false,
                'width' => 'half'
            ],
            'schema' => [
                'is_primary_key' => true,
                'has_auto_increment' => false,
                'is_nullable' => false
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

        // Work order reference fields
        [
            'field' => 'externt_id',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'externt_nr',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],

        // Related object and customer
        [
            'field' => 'objekt_id',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half', 'note' => 'Property/Object ID'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'kund_id',
            'type' => 'integer',
            'meta' => ['interface' => 'input', 'width' => 'half', 'note' => 'Customer ID'],
            'schema' => ['is_nullable' => true]
        ],

        // Work order type and status
        [
            'field' => 'arbetsorder_typ_kod',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half', 'note' => 'F=Felanmälan, G=Beställning'],
            'schema' => ['is_nullable' => true, 'max_length' => 10]
        ],
        [
            'field' => 'arbetsorder_typ_besk',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'status_kod',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half', 'note' => 'Work order status code'],
            'schema' => ['is_nullable' => true, 'max_length' => 10]
        ],

        // Priority
        [
            'field' => 'prio_kod',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true, 'max_length' => 10]
        ],
        [
            'field' => 'prio_besk',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],

        // Descriptions and information
        [
            'field' => 'beskrivning',
            'type' => 'text',
            'meta' => ['interface' => 'input-multiline', 'width' => 'full'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'kommentar',
            'type' => 'text',
            'meta' => ['interface' => 'input-multiline', 'width' => 'full'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'anmarkning',
            'type' => 'text',
            'meta' => ['interface' => 'input-multiline', 'width' => 'full'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'atgard',
            'type' => 'text',
            'meta' => ['interface' => 'input-multiline', 'width' => 'full'],
            'schema' => ['is_nullable' => true]
        ],

        // Dates
        [
            'field' => 'datum_registrerad',
            'type' => 'date',
            'meta' => ['interface' => 'datetime', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'datum_bestalld',
            'type' => 'date',
            'meta' => ['interface' => 'datetime', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'datum_accepterad',
            'type' => 'date',
            'meta' => ['interface' => 'datetime', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'datum_utford',
            'type' => 'date',
            'meta' => ['interface' => 'datetime', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'datum_modifierad',
            'type' => 'timestamp',
            'meta' => ['interface' => 'datetime', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],

        // Performer (utförare) - simplified fields
        [
            'field' => 'utforare_id',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'utforare_namn',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],

        // Reporter (anmälare) - simplified fields
        [
            'field' => 'annan_anmalare_namn',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'annan_anmalare_epost',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'annan_anmalare_telefon',
            'type' => 'string',
            'meta' => ['interface' => 'input', 'width' => 'half'],
            'schema' => ['is_nullable' => true]
        ],

        // Complex nested objects stored as JSON
        [
            'field' => 'bunt',
            'type' => 'json',
            'meta' => ['interface' => 'input-code', 'width' => 'full', 'options' => ['language' => 'json']],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'utforare',
            'type' => 'json',
            'meta' => ['interface' => 'input-code', 'width' => 'full', 'options' => ['language' => 'json']],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'utrymme',
            'type' => 'json',
            'meta' => ['interface' => 'input-code', 'width' => 'full', 'options' => ['language' => 'json']],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'enhet',
            'type' => 'json',
            'meta' => ['interface' => 'input-code', 'width' => 'full', 'options' => ['language' => 'json']],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'registrerad',
            'type' => 'json',
            'meta' => ['interface' => 'input-code', 'width' => 'full', 'options' => ['language' => 'json']],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'planering',
            'type' => 'json',
            'meta' => ['interface' => 'input-code', 'width' => 'full', 'options' => ['language' => 'json']],
            'schema' => ['is_nullable' => true]
        ],
        [
            'field' => 'ekonomi',
            'type' => 'json',
            'meta' => ['interface' => 'input-code', 'width' => 'full', 'options' => ['language' => 'json']],
            'schema' => ['is_nullable' => true]
        ],

        // Raw data backup
        [
            'field' => 'raw_data',
            'type' => 'json',
            'meta' => [
                'interface' => 'input-code',
                'width' => 'full',
                'options' => ['language' => 'json'],
                'note' => 'Complete raw data from FAST2 API'
            ],
            'schema' => ['is_nullable' => true]
        ],
    ];
}

/**
 * Main execution
 */
function main()
{
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Create Directus Collection - fast2_arbetsordrar          ║\n";
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
        $collectionName = 'fast2_arbetsordrar';

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
        }

        // Create fields
        echo "\n📝 Creating fields...\n";
        $fields = getWorkOrderFieldDefinitions();
        $created = 0;
        $skipped = 0;

        foreach ($fields as $fieldDef) {
            try {
                $client->createField($collectionName, $fieldDef['field'], $fieldDef);
                $created++;
                echo "  ✅ Created field: {$fieldDef['field']}\n";
            } catch (Exception $e) {
                // Field probably already exists
                $skipped++;
                echo "  ⏭️  Skipped field: {$fieldDef['field']} (already exists)\n";
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
        echo "Total fields: " . ($created + $skipped) . "\n";
        echo "\n✅ Setup completed successfully!\n\n";
        echo "You can now run the sync script:\n";
        echo "  php sync_arbetsordrar_to_directus.php\n\n";

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Run the script
main();
