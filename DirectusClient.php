<?php
/**
 * Directus API Client
 *
 * Handles communication with Directus API for CRUD operations on collections.
 * Based on patterns from existing Directus integrations in the project.
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 Fastigheter Sync
 */

class DirectusClient
{
    private $baseUrl;
    private $token;
    private $verbose = false;

    /**
     * Constructor
     *
     * @param string $baseUrl Directus base URL (e.g., https://nav.utvecklingfalkenberg.se)
     * @param string $token Directus API Bearer token
     * @param bool $verbose Enable verbose logging
     */
    public function __construct($baseUrl, $token, $verbose = false)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->verbose = $verbose;
    }

    /**
     * GET - Fetch items from a collection
     *
     * @param string $collection Collection name
     * @param array $filter Filter criteria (Directus format)
     * @param int $limit Maximum number of items to fetch
     * @param int $offset Number of items to skip (for pagination)
     * @param array $fields Specific fields to return (empty = all fields)
     * @return array Response data with 'data' key containing items
     * @throws Exception if request fails
     */
    public function getItems($collection, $filter = [], $limit = 1000, $offset = 0, $fields = [])
    {
        $params = ['limit' => $limit, 'offset' => $offset];

        if (!empty($filter)) {
            $params['filter'] = json_encode($filter);
        }

        if (!empty($fields)) {
            $params['fields'] = implode(',', $fields);
        }

        $query = http_build_query($params);
        $url = "{$this->baseUrl}/items/{$collection}?{$query}";

        if ($this->verbose) {
            $this->log("   GET: {$url}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Directus GET request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Directus GET failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);

        if (!isset($data['data'])) {
            throw new Exception("Invalid Directus response: missing 'data' key");
        }

        return $data;
    }

    /**
     * POST - Create a new item in a collection
     *
     * @param string $collection Collection name
     * @param array $data Item data
     * @return array Response data
     * @throws Exception if request fails
     */
    public function createItem($collection, $data)
    {
        $url = "{$this->baseUrl}/items/{$collection}";

        if ($this->verbose) {
            $this->log("   POST: {$url}");
            $this->log("   Data: " . json_encode($data));
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Directus POST request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Directus POST failed (HTTP {$httpCode}): {$response}");
        }

        return json_decode($response, true);
    }

    /**
     * PATCH - Update an existing item in a collection
     *
     * @param string $collection Collection name
     * @param string|int $id Item ID
     * @param array $data Data to update
     * @return array Response data
     * @throws Exception if request fails
     */
    public function updateItem($collection, $id, $data)
    {
        $url = "{$this->baseUrl}/items/{$collection}/{$id}";

        if ($this->verbose) {
            $this->log("   PATCH: {$url}");
            $this->log("   Data: " . json_encode($data));
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Directus PATCH request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Directus PATCH failed (HTTP {$httpCode}): {$response}");
        }

        return json_decode($response, true);
    }

    /**
     * Check if a collection exists
     *
     * @param string $collection Collection name
     * @return bool True if collection exists
     */
    public function collectionExists($collection)
    {
        $url = "{$this->baseUrl}/collections/{$collection}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Create a collection
     *
     * @param string $collection Collection name
     * @param array $schema Collection schema definition
     * @return array Response data
     * @throws Exception if request fails
     */
    public function createCollection($collection, $schema = [])
    {
        $url = "{$this->baseUrl}/collections";

        $data = [
            'collection' => $collection,
            'meta' => [
                'collection' => $collection,
                'icon' => 'home_work',
                'note' => 'FAST2 Fastigheter - Synkroniserad från FAST2 API',
                'display_template' => '{{id}} - {{adress}}',
                'hidden' => false,
                'singleton' => false,
                'translations' => null,
                'archive_field' => null,
                'archive_value' => null,
                'unarchive_value' => null,
                'sort_field' => null
            ],
            'schema' => array_merge([
                'name' => $collection
            ], $schema)
        ];

        if ($this->verbose) {
            $this->log("   Creating collection: {$collection}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Failed to create collection: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Failed to create collection (HTTP {$httpCode}): {$response}");
        }

        return json_decode($response, true);
    }

    /**
     * Create a field in a collection
     *
     * @param string $collection Collection name
     * @param string $field Field name
     * @param array $schema Field schema definition
     * @return array Response data
     * @throws Exception if request fails
     */
    public function createField($collection, $field, $schema)
    {
        $url = "{$this->baseUrl}/fields/{$collection}";

        $data = array_merge([
            'field' => $field,
            'collection' => $collection
        ], $schema);

        if ($this->verbose) {
            $this->log("   Creating field: {$collection}.{$field}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Failed to create field {$field}: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Failed to create field {$field} (HTTP {$httpCode}): {$response}");
        }

        return json_decode($response, true);
    }

    /**
     * Delete a field from a collection
     *
     * @param string $collection Collection name
     * @param string $field Field name to delete
     * @return bool True on success
     * @throws Exception if request fails
     */
    public function deleteField($collection, $field)
    {
        $url = "{$this->baseUrl}/fields/{$collection}/{$field}";

        if ($this->verbose) {
            $this->log("   Deleting field: {$collection}.{$field}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Failed to delete field {$field}: {$error}");
        }

        if ($httpCode !== 204 && $httpCode !== 200) {
            throw new Exception("Failed to delete field {$field} (HTTP {$httpCode}): {$response}");
        }

        return true;
    }

    /**
     * Update a field in a collection
     *
     * @param string $collection Collection name
     * @param string $field Field name
     * @param array $schema Field schema definition
     * @return array Response data
     * @throws Exception if request fails
     */
    public function updateField($collection, $field, $schema)
    {
        $url = "{$this->baseUrl}/fields/{$collection}/{$field}";

        $data = array_merge([
            'field' => $field,
            'collection' => $collection
        ], $schema);

        if ($this->verbose) {
            $this->log("   Updating field: {$collection}.{$field}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Failed to update field {$field}: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Failed to update field {$field} (HTTP {$httpCode}): {$response}");
        }

        return json_decode($response, true);
    }

    /**
     * Delete a collection
     *
     * @param string $collection Collection name to delete
     * @return bool True on success
     * @throws Exception if request fails
     */
    public function deleteCollection($collection)
    {
        $url = "{$this->baseUrl}/collections/{$collection}";

        if ($this->verbose) {
            $this->log("   Deleting collection: {$collection}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Failed to delete collection: {$error}");
        }

        if ($httpCode !== 204 && $httpCode !== 200) {
            throw new Exception("Failed to delete collection (HTTP {$httpCode}): {$response}");
        }

        return true;
    }

    /**
     * GET aggregate counts grouped by a field (e.g. status)
     *
     * Uses Directus aggregate API to get item counts without fetching all data.
     * Example result: [['status' => 'active', 'count' => 17000], ...]
     *
     * @param string $collection Collection name
     * @param string $groupByField Field to group by (default: 'status')
     * @return array Rows with groupBy field value and 'count'
     * @throws Exception if request fails
     */
    public function getItemCounts($collection, $groupByField = 'status')
    {
        $params = [
            'aggregate[count]' => '*',
            'groupBy[]'        => $groupByField,
        ];

        $url = "{$this->baseUrl}/items/{$collection}?" . http_build_query($params);

        if ($this->verbose) {
            $this->log("   GET (aggregate): {$url}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Directus aggregate GET failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Directus aggregate GET failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);

        if (!isset($data['data'])) {
            throw new Exception("Invalid Directus aggregate response: missing 'data' key");
        }

        // Normalize: rename 'count(*)' key to 'count'
        return array_map(function ($row) {
            $row['count'] = (int) ($row['count'] ?? $row['count(*)'] ?? 0);
            unset($row['count(*)']);
            return $row;
        }, $data['data']);
    }

    /**
     * Log message to console
     *
     * @param string $message Message to log
     */
    private function log($message)
    {
        if ($this->verbose) {
            echo $message . "\n";
        }
    }
}
