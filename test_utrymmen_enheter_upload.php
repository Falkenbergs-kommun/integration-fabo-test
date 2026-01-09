#!/usr/bin/env php
<?php
/**
 * Test Utrymmen, Enheter and File Upload
 *
 * This script tests three FAST2 API endpoints that are used in the Joomla extension:
 * 1. List utrymmen (spaces/rooms) for an objekt
 * 2. List enheter (units) for an utrymme
 * 3. Upload a file to get a temporary file ID
 *
 * Usage: php test_utrymmen_enheter_upload.php [objektId] [utrymmesId] [file_path]
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 API Test Scripts
 */

// Enable error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/fetch_fastigheter.php';

/**
 * Test FAST2 API Client for Utrymmen, Enheter and File Upload
 */
class Fast2TestClient
{
    private $oauth2TokenEndpoint;
    private $consumerKey;
    private $consumerSecret;
    private $baseUrl;
    private $username;
    private $password;
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
        if ($this->oauth2Token !== null) {
            return $this->oauth2Token;
        }

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
        if ($this->apiToken !== null) {
            return $this->apiToken;
        }

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
            throw new Exception('API login response missing access_token');
        }

        $this->apiToken = $data;
        $this->log('✅ FAST2 API login successful');

        return $this->apiToken;
    }

    /**
     * List utrymmen (spaces/rooms) for an object
     */
    public function listUtrymmen($objektId)
    {
        // Ensure we have tokens
        if (!$this->oauth2Token) {
            $this->getOAuth2Token();
        }
        if (!$this->apiToken) {
            $this->loginToApi();
        }

        $this->log("📋 Fetching utrymmen for objekt {$objektId}...");

        $url = $this->baseUrl . '/ao-produkt/v1/fastastrukturen/utrymmen?objektId=' . urlencode($objektId);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->oauth2Token,
            'X-Auth-Token: ' . $this->apiToken['access_token'],
        ]);

        if ($this->verbose) {
            $this->log('   Request URL: ' . $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Utrymmen request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Utrymmen request failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception('Failed to parse utrymmen response as JSON');
        }

        $this->log('✅ Successfully fetched ' . count($data) . ' utrymmen');

        return $data;
    }

    /**
     * List enheter (units) for an utrymme
     */
    public function listEnheter($utrymmesId)
    {
        // Ensure we have tokens
        if (!$this->oauth2Token) {
            $this->getOAuth2Token();
        }
        if (!$this->apiToken) {
            $this->loginToApi();
        }

        $this->log("📋 Fetching enheter for utrymme {$utrymmesId}...");

        $url = $this->baseUrl . '/ao-produkt/v1/fastastrukturen/enheter?utrymmesId=' . urlencode($utrymmesId);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->oauth2Token,
            'X-Auth-Token: ' . $this->apiToken['access_token'],
        ]);

        if ($this->verbose) {
            $this->log('   Request URL: ' . $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Enheter request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Enheter request failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception('Failed to parse enheter response as JSON');
        }

        $this->log('✅ Successfully fetched ' . count($data) . ' enheter');

        return $data;
    }

    /**
     * Upload a temporary file
     */
    public function uploadTempFile($filePath)
    {
        // Ensure we have tokens
        if (!$this->oauth2Token) {
            $this->getOAuth2Token();
        }
        if (!$this->apiToken) {
            $this->loginToApi();
        }

        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        $this->log("📤 Uploading file: " . basename($filePath) . "...");

        $url = $this->baseUrl . '/ao-produkt/v1/filetransfer/tempfile';

        // Create CURLFile for upload
        $cFile = new CURLFile($filePath, mime_content_type($filePath), basename($filePath));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cFile]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->oauth2Token,
            'X-Auth-Token: ' . $this->apiToken['access_token'],
        ]);

        if ($this->verbose) {
            $this->log('   Request URL: ' . $url);
            $this->log('   File size: ' . filesize($filePath) . ' bytes');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("File upload failed: {$error}");
        }

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new Exception("File upload failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception('Failed to parse upload response as JSON: ' . $response);
        }

        $this->log('✅ File uploaded successfully');

        return $data;
    }
}

/**
 * Main execution
 */
function main()
{
    global $argv;

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  FAST2 API - Test Utrymmen, Enheter & File Upload         ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    try {
        // Load configuration
        $envFile = __DIR__ . '/.env';
        echo "📁 Loading configuration from .env...\n";
        $config = loadEnv($envFile);
        echo "✅ Configuration loaded\n\n";

        // Create client
        $client = new Fast2TestClient($config, true);

        // Get parameters from command line or use defaults
        $objektId = $argv[1] ?? '9120801';  // Default objekt from work orders
        $utrymmesId = $argv[2] ?? null;
        $filePath = $argv[3] ?? __DIR__ . '/testfiles/Generated Image January 06, 2026 - 8_58PM.jpeg';

        // Test 1: List utrymmen
        echo "═══════════════════════════════════════════════════════════\n";
        echo "TEST 1: List Utrymmen (Spaces/Rooms)\n";
        echo "═══════════════════════════════════════════════════════════\n\n";

        $utrymmen = $client->listUtrymmen($objektId);

        echo "\n📊 Results:\n";
        echo "   Found " . count($utrymmen) . " utrymmen\n\n";

        if (count($utrymmen) > 0) {
            echo "   Sample data (first 3):\n";
            foreach (array_slice($utrymmen, 0, 3) as $utrymme) {
                echo "   - ID: " . ($utrymme['id'] ?? 'N/A') . "\n";
                echo "     Beskrivning: " . ($utrymme['beskrivning'] ?? 'N/A') . "\n";
                echo "     Rumsnummer: " . ($utrymme['rumsnummer'] ?? 'N/A') . "\n";
                echo "     Typ: " . ($utrymme['utrymmesTypKod'] ?? 'N/A') . "\n\n";
            }

            // Save to JSON
            $filename = __DIR__ . "/utrymmen_{$objektId}_" . date('Y-m-d_His') . ".json";
            file_put_contents($filename, json_encode($utrymmen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "   💾 Saved to: {$filename}\n";

            // Use first utrymme for next test if not provided
            if ($utrymmesId === null && count($utrymmen) > 0) {
                $utrymmesId = $utrymmen[0]['id'];
            }
        }

        // Test 2: List enheter (only if we have an utrymmesId)
        if ($utrymmesId !== null) {
            echo "\n═══════════════════════════════════════════════════════════\n";
            echo "TEST 2: List Enheter (Units)\n";
            echo "═══════════════════════════════════════════════════════════\n\n";

            $enheter = $client->listEnheter($utrymmesId);

            echo "\n📊 Results:\n";
            echo "   Found " . count($enheter) . " enheter\n\n";

            if (count($enheter) > 0) {
                echo "   Sample data (first 3):\n";
                foreach (array_slice($enheter, 0, 3) as $enhet) {
                    echo "   - ID: " . ($enhet['id'] ?? 'N/A') . "\n";
                    echo "     Beskrivning: " . ($enhet['beskrivning'] ?? 'N/A') . "\n";
                    echo "     Enhetstypt: " . ($enhet['enhetstypBesk'] ?? 'N/A') . "\n\n";
                }

                // Save to JSON
                $filename = __DIR__ . "/enheter_{$utrymmesId}_" . date('Y-m-d_His') . ".json";
                file_put_contents($filename, json_encode($enheter, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo "   💾 Saved to: {$filename}\n";
            }
        } else {
            echo "\n⏭️  Skipping enheter test (no utrymmesId available)\n";
        }

        // Test 3: Upload file
        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "TEST 3: Upload Temporary File\n";
        echo "═══════════════════════════════════════════════════════════\n\n";

        if (file_exists($filePath)) {
            $uploadResult = $client->uploadTempFile($filePath);

            echo "\n📊 Results:\n";
            echo "   Upload response:\n";
            echo "   " . json_encode($uploadResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

            // Save result to JSON
            $filename = __DIR__ . "/upload_result_" . date('Y-m-d_His') . ".json";
            file_put_contents($filename, json_encode($uploadResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "   💾 Saved to: {$filename}\n";
        } else {
            echo "   ⚠️  Test file not found: {$filePath}\n";
            echo "   ℹ️  To test file upload, place a file at: {$filePath}\n";
            echo "   Or provide a file path as the third argument:\n";
            echo "   php test_utrymmen_enheter_upload.php [objektId] [utrymmesId] [file_path]\n";
        }

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  All Tests Completed!                                      ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Run the script
main();
