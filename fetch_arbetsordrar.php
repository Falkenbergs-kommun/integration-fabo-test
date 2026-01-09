#!/usr/bin/env php
<?php
/**
 * Fetch Work Orders from FAST2 API
 *
 * This script authenticates against the FAST2 API and fetches all work orders
 * (arbetsordrar) for the configured customer. The response is saved as JSON.
 *
 * Usage: php fetch_arbetsordrar.php
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 API Test Scripts
 */

// Enable error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Load environment variables from .env file
 */
function loadEnv($filePath)
{
    if (!file_exists($filePath)) {
        throw new Exception('.env file not found. Copy .env.example to .env and fill in your credentials.');
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];

    foreach ($lines as $line) {
        // Skip comments
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
 * FAST2 API Client for Work Orders
 */
class Fast2WorkOrderClient
{
    private $oauth2TokenEndpoint;
    private $consumerKey;
    private $consumerSecret;
    private $baseUrl;
    private $username;
    private $password;
    private $kundNr;
    private $verbose;

    private $oauth2Token = null;
    private $apiToken = null;

    public function __construct($config, $verbose = false)
    {
        $this->oauth2TokenEndpoint = $config['OAUTH2_TOKEN_ENDPOINT'];
        $this->consumerKey = $config['CONSUMER_KEY'];
        $this->consumerSecret = $config['CONSUMER_SECRET'];
        $this->baseUrl = $config['FAST2_BASE_URL'];
        $this->username = $config['FAST2_USERNAME'];
        $this->password = $config['FAST2_PASSWORD'];
        $this->kundNr = $config['KUND_NR'];
        $this->verbose = $verbose;
    }

    /**
     * Log message with timestamp
     */
    private function log($message)
    {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    }

    /**
     * Step 1: Get OAuth2 token from WSO2 Gateway
     */
    private function getOAuth2Token()
    {
        $this->log('🔐 Authenticating with OAuth2...');

        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $ch = curl_init($this->oauth2TokenEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        if ($this->verbose) {
            $this->log('   Sending request...');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("OAuth2 token request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("OAuth2 token request failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception('OAuth2 response missing access_token');
        }

        $this->oauth2Token = $data['access_token'];
        $this->log('✅ OAuth2 authentication successful');

        return $this->oauth2Token;
    }

    /**
     * Step 2: Login to FAST2 API with username/password
     */
    private function loginToApi()
    {
        $this->log('🔑 Logging in to FAST2 API...');

        $url = $this->baseUrl . '/ao-produkt/v1/auth/login';
        $loginData = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->oauth2Token,
        ]);

        if ($this->verbose) {
            $this->log('   Sending login request...');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("API login failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("API login failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            if ($this->verbose) {
                $this->log('   Response: ' . substr($response, 0, 500));
            }
            throw new Exception('API login response missing access_token');
        }

        $this->apiToken = $data;
        $this->log('✅ FAST2 API login successful');

        if ($this->verbose && isset($data['expires_in'])) {
            $this->log('   Token expires in: ' . $data['expires_in'] . ' seconds');
        }

        return $this->apiToken;
    }

    /**
     * Step 3: Fetch work orders
     */
    public function fetchWorkOrders($filters = [])
    {
        // Ensure we have tokens
        if (!$this->oauth2Token) {
            $this->getOAuth2Token();
        }
        if (!$this->apiToken) {
            $this->loginToApi();
        }

        $this->log('📋 Fetching work orders...');

        // Build query string with filters
        $queryParams = [];

        // Add default filters if not specified
        if (!isset($filters['kundNr'])) {
            $queryParams['kundNr'] = $this->kundNr;
        }

        // Add any additional filters
        foreach ($filters as $key => $value) {
            $queryParams[$key] = $value;
        }

        $queryString = http_build_query($queryParams);
        $url = $this->baseUrl . '/ao-produkt/v1/arbetsorder';
        if ($queryString) {
            $url .= '?' . $queryString;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->oauth2Token,
            'X-Auth-Token: ' . $this->apiToken['access_token'],
        ]);

        if ($this->verbose) {
            $this->log('   Sending work orders request to: ' . $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Work orders request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Work orders request failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception('Failed to parse work orders response as JSON');
        }

        // The response is an array of work orders
        $workOrders = $data;

        // Filter out confidential work orders
        $workOrders = array_filter($workOrders, function($order) {
            return !isset($order['externtNr']) || $order['externtNr'] !== 'CONFIDENTIAL';
        });

        $this->log('✅ Successfully fetched ' . count($workOrders) . ' work orders');

        return $workOrders;
    }
}

/**
 * Main execution
 */
function mainFetchArbetsordrar()
{
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  FAST2 API - Fetch Work Orders                             ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    try {
        // Load configuration
        $envFile = __DIR__ . '/.env';
        echo "📁 Loading configuration from .env...\n";
        $config = loadEnv($envFile);

        // Validate required configuration
        $required = ['OAUTH2_TOKEN_ENDPOINT', 'CONSUMER_KEY', 'CONSUMER_SECRET',
                     'FAST2_BASE_URL', 'FAST2_USERNAME', 'FAST2_PASSWORD', 'KUND_NR'];
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new Exception("Missing required configuration: {$key}");
            }
        }

        echo "✅ Configuration loaded\n\n";

        // Create client and fetch work orders
        $client = new Fast2WorkOrderClient($config, true);

        // You can add filters here, e.g., status, date range, etc.
        // Example: $filters = ['status' => 'PAGAR,REG', 'feltyp' => 'F'];
        $workOrders = $client->fetchWorkOrders();

        // Save to JSON file
        $timestamp = date('Y-m-d_His');
        $filename = __DIR__ . "/arbetsordrar_{$timestamp}.json";

        echo "\n💾 Saving work orders to file...\n";
        $jsonData = json_encode($workOrders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($filename, $jsonData);

        echo "✅ Saved to: {$filename}\n";
        echo "\nFile size: " . number_format(filesize($filename)) . " bytes\n";
        echo "Work orders: " . count($workOrders) . "\n";

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  Success!                                                  ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Run the script if executed directly
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    mainFetchArbetsordrar();
}
