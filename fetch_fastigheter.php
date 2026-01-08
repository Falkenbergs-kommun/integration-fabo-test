#!/usr/bin/env php
<?php
/**
 * FAST2 API Test Script - Fetch Properties/Objects
 *
 * This script authenticates against the FAST2 API and downloads all properties.
 * It uses the same authentication flow as mod_fbg_fabofelanm:
 * 1. OAuth2 authentication (client credentials)
 * 2. FAST2 API login (username/password)
 * 3. Fetch properties using the API token
 *
 * Usage: php fetch_fastigheter.php
 *
 * @package    Falkenbergs kommun
 * @author     Based on mod_fbg_fabofelanm
 */

// Enable error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);

class Fast2ApiClient
{
    private $config;
    private $oauth2Token = null;
    private $apiToken = null;
    private $verbose = false;

    public function __construct($config, $verbose = false)
    {
        $this->config = $config;
        $this->verbose = $verbose;
    }

    /**
     * Step 1: Get OAuth2 token for API Gateway access
     */
    private function getOAuth2Token()
    {
        if ($this->oauth2Token !== null) {
            return $this->oauth2Token;
        }

        $this->log("🔐 Authenticating with OAuth2...");

        if ($this->verbose) {
            $this->log("   Endpoint: " . $this->config['OAUTH2_TOKEN_ENDPOINT']);
            $this->log("   Consumer Key: " . substr($this->config['CONSUMER_KEY'], 0, 10) . "...");
        }

        $credentials = base64_encode(
            $this->config['CONSUMER_KEY'] . ':' . $this->config['CONSUMER_SECRET']
        );

        $ch = curl_init($this->config['OAUTH2_TOKEN_ENDPOINT']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,  // Disable SSL verification for test environment
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => $this->verbose,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        ]);

        $this->log("   Sending request...");
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("OAuth2 request failed (errno: {$errno}): {$error}");
        }

        if ($httpCode !== 200) {
            $this->log("   Response code: {$httpCode}");
            if ($this->verbose) {
                $this->log("   Response body: " . substr($response, 0, 500));
            }
            throw new Exception('OAuth2 token request failed: ' . $httpCode . ' ' . $response);
        }

        $tokenData = json_decode($response, true);
        if (!$tokenData || !isset($tokenData['access_token'])) {
            throw new Exception('Invalid OAuth2 token response: ' . $response);
        }

        $this->oauth2Token = $tokenData;
        $this->log("✅ OAuth2 authentication successful");

        if ($this->verbose && isset($tokenData['expires_in'])) {
            $this->log("   Token expires in: " . $tokenData['expires_in'] . " seconds");
        }

        return $this->oauth2Token;
    }

    /**
     * Step 2: Login to FAST2 API with username/password
     */
    private function loginToApi()
    {
        if ($this->apiToken !== null) {
            return $this->apiToken;
        }

        $this->log("🔑 Logging in to FAST2 API...");

        // First get OAuth2 token
        $oauth2Token = $this->getOAuth2Token();

        // Login with username/password
        $loginUrl = $this->config['FAST2_BASE_URL'] . '/ao-produkt/v1/auth/login';

        if ($this->verbose) {
            $this->log("   Login URL: " . $loginUrl);
            $this->log("   Username: " . $this->config['FAST2_USERNAME']);
        }

        $ch = curl_init($loginUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => $this->verbose,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $oauth2Token['access_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'username' => $this->config['FAST2_USERNAME'],
                'password' => $this->config['FAST2_PASSWORD'],
            ]),
        ]);

        $this->log("   Sending login request...");
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("API login request failed (errno: {$errno}): {$error}");
        }

        if ($httpCode !== 200) {
            $this->log("   Response code: {$httpCode}");
            if ($this->verbose) {
                $this->log("   Response body: " . substr($response, 0, 500));
            }
            throw new Exception('API login failed: ' . $httpCode . ' ' . $response);
        }

        $tokenData = json_decode($response, true);
        if (!$tokenData || !isset($tokenData['access_token'])) {
            throw new Exception('Invalid API token response: ' . $response);
        }

        $this->apiToken = $tokenData;
        $this->log("✅ FAST2 API login successful");

        if ($this->verbose && isset($tokenData['expires_in'])) {
            $this->log("   Token expires in: " . $tokenData['expires_in'] . " seconds");
        }

        return $this->apiToken;
    }

    /**
     * Step 3: Fetch all properties/objects
     */
    public function fetchProperties()
    {
        $this->log("📋 Fetching properties...");

        // Login to API first
        $oauth2Token = $this->getOAuth2Token();
        $apiToken = $this->loginToApi();

        // Make API request
        $url = $this->config['FAST2_BASE_URL'] . '/ao-produkt/v1/fastastrukturen/objekt/felanmalningsbara/uthyrningsbara';

        if ($this->verbose) {
            $this->log("   API URL: " . $url);
            $this->log("   Customer ID: " . $this->config['KUND_ID']);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => $this->verbose,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $oauth2Token['access_token'],
                'X-Auth-Token: ' . $apiToken['access_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'filter' => [
                    'kundId' => $this->config['KUND_ID'],
                ],
            ]),
        ]);

        $this->log("   Sending properties request...");
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Properties request failed (errno: {$errno}): {$error}");
        }

        if ($httpCode !== 200) {
            $this->log("   Response code: {$httpCode}");
            if ($this->verbose) {
                $this->log("   Response body: " . substr($response, 0, 500));
            }
            throw new Exception('Properties request failed: ' . $httpCode . ' ' . $response);
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception('Invalid JSON response: ' . $response);
        }

        // Count properties
        $count = 0;
        if (isset($data['edges']) && is_array($data['edges'])) {
            $count = count($data['edges']);
        }

        $this->log("✅ Successfully fetched {$count} properties");

        return $data;
    }

    /**
     * Log message to console
     */
    private function log($message)
    {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

/**
 * Load environment variables from .env file
 */
function loadEnv($filePath)
{
    if (!file_exists($filePath)) {
        throw new Exception('.env file not found. Please copy .env.example to .env and configure it.');
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
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
function mainFetchFastigheter()
{
    // Check for verbose flag
    global $argv;
    $verbose = in_array('-v', $argv) || in_array('--verbose', $argv);

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  FAST2 API Test Script - Fetch Properties                 ║\n";
    echo "║  Based on mod_fbg_fabofelanm                               ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    if ($verbose) {
        echo "ℹ️  Verbose mode enabled\n\n";
    }

    try {
        // Load configuration
        $envFile = __DIR__ . '/.env';
        echo "📁 Loading configuration from .env file...\n";
        $config = loadEnv($envFile);

        // Validate required config
        $required = [
            'OAUTH2_TOKEN_ENDPOINT',
            'CONSUMER_KEY',
            'CONSUMER_SECRET',
            'FAST2_BASE_URL',
            'FAST2_USERNAME',
            'FAST2_PASSWORD',
            'KUND_ID'
        ];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new Exception("Missing required configuration: {$key}");
            }
        }

        echo "✅ Configuration loaded successfully\n";

        if ($verbose) {
            echo "\nConfiguration:\n";
            echo "  OAuth2 Endpoint: " . $config['OAUTH2_TOKEN_ENDPOINT'] . "\n";
            echo "  FAST2 Base URL: " . $config['FAST2_BASE_URL'] . "\n";
            echo "  Username: " . $config['FAST2_USERNAME'] . "\n";
            echo "  Customer ID: " . $config['KUND_ID'] . "\n";
        }
        echo "\n";

        // Create API client
        $client = new Fast2ApiClient($config, $verbose);

        // Fetch properties
        $data = $client->fetchProperties();

        // Save response to file
        $timestamp = date('Y-m-d_His');
        $outputFile = __DIR__ . "/fastigheter_{$timestamp}.json";

        echo "\n💾 Saving response to file...\n";
        file_put_contents($outputFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "✅ Response saved to: {$outputFile}\n";

        // Display summary
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  Summary                                                   ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";

        if (isset($data['edges']) && is_array($data['edges'])) {
            echo "Total properties: " . count($data['edges']) . "\n";

            // Show first few properties
            echo "\nFirst properties:\n";
            $limit = min(5, count($data['edges']));
            for ($i = 0; $i < $limit; $i++) {
                $node = $data['edges'][$i]['node'] ?? null;
                if ($node) {
                    $id = $node['id'] ?? 'N/A';
                    $adress = $node['adress']['adress'] ?? 'N/A';
                    $typ = $node['typ']['objektsTyp'] ?? 'N/A';
                    echo "  - {$id}: {$adress} ({$typ})\n";
                }
            }

            if (count($data['edges']) > $limit) {
                echo "  ... and " . (count($data['edges']) - $limit) . " more\n";
            }
        }

        echo "\n✅ Script completed successfully!\n\n";

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Run the script only if called directly (not when included)
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    mainFetchFastigheter();
}
