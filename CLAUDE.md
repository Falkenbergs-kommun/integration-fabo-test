# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a FAST2 API integration project for Falkenbergs kommun that fetches property (fastigheter) and work order (arbetsordrar) data from the FAST2 facility management system and synchronizes it to a Directus CMS instance.

**Key Purpose**: Maintain up-to-date copies of facility management data from FAST2 in Directus for use in internal web applications and reporting systems.

**Data Flow**: FAST2 API → OAuth2 + API Token Auth → Transform → Directus CMS (soft delete sync)

## Authentication Architecture

All API interactions follow a two-stage authentication pattern:

1. **OAuth2 (WSO2 API Gateway)**: First, obtain an OAuth2 bearer token using client credentials flow
2. **FAST2 API Login**: Then authenticate with FAST2 API using username/password to get an API token
3. **API Requests**: Include both tokens in headers (`Authorization: Bearer {oauth2_token}` and `X-Auth-Token: {api_token}`)

This pattern is implemented consistently across all scripts in the `Fast2ApiClient` and related classes.

## Core Components

### DirectusClient.php
Reusable Directus API client providing:
- CRUD operations on Directus collections
- Collection and field schema management
- Verbose logging support (pass `true` to constructor)

**Key Methods**:
- `getItems($collection, $filter, $limit)` - Fetch items with optional filtering
- `createItem($collection, $data)` - Create new item
- `updateItem($collection, $id, $data)` - Update existing item
- `createCollection($collection, $schema)` - Create collection with schema
- `createField($collection, $field, $schema)` - Add field to collection

### Fast2ApiClient (in fetch_fastigheter.php)
Handles FAST2 API authentication and data fetching:
- OAuth2 token management with reuse
- FAST2 API login and token caching
- Generic request method for any FAST2 endpoint

### Synchronization Strategy
All sync scripts use **soft delete** pattern:
- New items from FAST2 → created in Directus with `status='active'`
- Updated items → PATCH with `status='active'` and `last_synced` timestamp
- Items missing from FAST2 → marked `status='inactive'` (never hard deleted)
- Each sync compares FAST2 source with Directus state by ID

## Configuration

All scripts use `.env` file (copy from `.env.example`):

```env
# OAuth2 for WSO2 API Gateway
OAUTH2_TOKEN_ENDPOINT=https://api.example.com/oauth2/token
CONSUMER_KEY=...
CONSUMER_SECRET=...

# FAST2 API
FAST2_BASE_URL=https://api.example.com
FAST2_USERNAME=...
FAST2_PASSWORD=...

# Customer identifiers (note the difference!)
KUND_ID=296751          # Numeric ID for fetching properties
KUND_NR=SERVKOMMUN      # String code for work order filtering

# Directus
DIRECTUS_API_URL=https://nav.utvecklingfalkenberg.se
DIRECTUS_API_TOKEN=...  # Must have admin permissions for collection creation
```

**Important**: `KUND_ID` (numeric) vs `KUND_NR` (string) are different identifiers used by different FAST2 API endpoints.

## Common Development Tasks

### Fetch Data from FAST2

```bash
# Fetch properties (saves to fastigheter_YYYY-MM-DD_HHMMSS.json)
php fetch_fastigheter.php

# Fetch work orders (saves to arbetsordrar_YYYY-MM-DD_HHMMSS.json)
php fetch_arbetsordrar.php
```

### Sync Data to Directus

```bash
# Sync properties (with verbose output)
php sync_to_directus.php -v

# Sync work orders (with verbose output)
php sync_arbetsordrar_to_directus.php -v
```

### Setup Directus Collections

```bash
# Create properties collection (fast2_fastigheter)
php create_directus_collection.php

# Create work orders collection (fast2_arbetsordrar)
php create_directus_arbetsordrar_collection.php

# Fix schema issues and resync properties
php fix_and_resync.php
```

### Test API Endpoints

```bash
# Test utrymmen (rooms), enheter (units), and file upload endpoints
php test_utrymmen_enheter_upload.php [objektId] [utrymmesId] [file_path]

# Example with specific values
php test_utrymmen_enheter_upload.php 9120801 116488 testfiles/test-upload.txt
```

## API Query Parameters & Filtering

### Work Orders Endpoint: GET /v1/arbetsorder

According to FAST2 API documentation (version 1.1, pages 37-38), the following query parameters are supported:

**Pagination**:
- `offset` (integer, default: 0) - Skip N first results
- `limit` (integer, default: 100) - Maximum number of results

**Filtering**:
- `objektId` (string) - Filter by property/object ID
- `kundNr` (string) - Filter by customer number (e.g., "SERVKOMMUN")
- `utforare` (Array) - Filter by performer/executor
- `status` (Array) - Filter by status code
- `feltyp` (Array) - Filter by fault type
- `skapadEfter` (string, date format) - Filter by created after date
- `modifieradEfter` (string, datetime format) - Filter by modified after datetime

**IMPORTANT - Not Supported**: The API does NOT support filtering by:
- Email address (`anmalare.epostAdress` or `annanAnmalare.epostAdress`)
- Reporter name (`anmalare.namn`)
- Reporter phone number
- Any other reporter/user fields

### Querying User-Specific Work Orders

To fetch work orders for a specific user email:

1. **Fetch all work orders** (or filter by `kundId`/`kundNr` to limit scope)
2. **Filter locally in PHP** on `annanAnmalare.epostAdress` field

Example implementation in `test_user_orders.php`:

```php
$allOrders = $client->fetchWorkOrders(['kundId' => KUND_ID]);

$userOrders = array_filter($allOrders, function($order) use ($userEmail) {
    return isset($order['annanAnmalare']['epostAdress']) &&
           strtolower(trim($order['annanAnmalare']['epostAdress'])) === strtolower(trim($userEmail));
});
```

**Performance Note**: Using `kundId` filter reduces data transfer by limiting to work orders for a specific customer (e.g., 298079). Without this, ALL work orders in the system would need to be fetched and filtered locally.

**Test Scripts**:
- `test_api_filters.php` - Documents API filter capabilities and limitations
- `test_user_orders.php [email]` - Fetches and displays work orders for a specific user email

## FAST2 API Endpoints Reference

Based on API documentation version 1.1 (2024-05-20):

### Core Endpoints

**Work Orders (Arbetsordrar)**:
- `GET /v1/arbetsorder` - Fetch work orders (supports query params above)
- `POST /v1/arbetsorder` - Create new work order
- `GET /v1/arbetsorder/{id}` - Get specific work order by ID
- `PATCH /v1/arbetsorder/{id}` - Update work order

**Properties/Objects (Fastigheter)**:
- `GET /kund/{kundId}/objekt` - Fetch all properties for a customer
- `GET /objekt/{objektId}` - Get specific property details

**Rooms/Units (Utrymmen/Enheter)**:
- `GET /objekt/{objektId}/utrymme` - Get all rooms for a property
- `GET /utrymme/{utrymmesId}/enhet` - Get all units for a room

**File Upload**:
- `POST /ao-produkt/v1/filetransfer/tempfile` - Upload file (⚠️ Currently returns HTTP 500)

### Work Order Codes & Values

**Status Codes (statusKod)**:
Common values found in production data:
- `REG` - Registered/New
- `KLA` - Completed (Klar)
- `UTF` - Executed/In Progress (Utförd)
- `BES` - Ordered (Beställd)
- `AVV` - Waiting (Avvaktar)
- `AVB` - Canceled (Avbeställd)

**Work Order Types (arbetsordertypKod)**:
- `F` - Fault report (Felanmälan)
- `G` - Service order (Serviceanmälan/Beställning)
- Other types may exist depending on FAST2 configuration

**Priority Codes (prioKod)**:
- `A` - Highest priority (Akut)
- `B` - High priority
- `C` - Normal priority
- `D` - Low priority
- Numbers may also be used (1-4)

### Work Order Data Structure

**Key nested objects in arbetsorder response**:

```php
[
    'id' => 16703,                          // Work order ID
    'arbetsorderTyp' => [                   // Work order type
        'arbetsordertypKod' => 'G',
        'arbetsordertypBesk' => 'Serviceanmälan'
    ],
    'status' => [                           // Status
        'statusKod' => 'REG',
        'statusBesk' => 'Registrerad'
    ],
    'prio' => [                             // Priority
        'prioKod' => 'C',
        'prioBesk' => 'Normal'
    ],
    'objekt' => [                           // Property reference
        'id' => '9120801'
    ],
    'annanAnmalare' => [                    // Reporter (other than tenant)
        'namn' => 'John Doe',
        'epostAdress' => 'john@example.com',
        'telefon' => '0346-123456'
    ],
    'information' => [                      // Descriptions
        'beskrivning' => 'Fault description',
        'kommentar' => 'Comments',
        'anmarkning' => 'Notes',
        'atgard' => 'Action taken'
    ],
    'registrerad' => [                      // Registration date
        'datumRegistrerad' => '20260127'    // Format: YYYYMMDD
    ],
    'fras' => [                             // Predefined phrase
        'frasKod' => 'CODE',
        'frasBesk' => 'Description'
    ],
    'bunt' => [...],                        // Bundle info (JSON in Directus)
    'planering' => [...],                   // Planning info (JSON in Directus)
    'ekonomi' => [...],                     // Economy info (JSON in Directus)
]
```

### Property Data Structure

**Key fields in objekt (property) response**:

```php
[
    'id' => '9120801',                      // Property ID (string)
    'adress' => 'Storgatan 1',
    'lghnummer' => '1001',                  // Apartment/unit number
    'postnummer' => '31130',
    'postort' => 'Falkenberg',
    'xkoord' => '123456.78',                // Coordinates
    'ykoord' => '654321.12',
    'fastighet_nr' => 'FALKENBERG 1:1',     // Property designation
    'objekts_typ' => '10',                  // Type code
    'typ_beskrivning' => 'Lägenhet',        // Type description
    'yta' => '75',                          // Area in sqm
    'vaning_nr' => '2',                     // Floor number
    'hiss' => 'J',                          // Elevator (J/N)
]
```

## Data Models

### Fastigheter (Properties)
Directus collection: `fast2_fastigheter`

Key field groups:
- **Address**: `adress`, `lghnummer`, `postnummer`, `postort`, `xkoord`, `ykoord`
- **Details**: `vaning_*`, `hiss`, `yta`, `anmarkning`, `text`
- **Type**: `objekts_otyp`, `objekts_typ`, `typ_beskrivning`
- **Relations**: `fastighet_nr`, `byggnad_nr`, `*omrade_nr` fields
- **Metadata**: `status` (active/inactive), `last_synced`, `raw_data` (JSON)

### Arbetsordrar (Work Orders)
Directus collection: `fast2_arbetsordrar`

Key field groups:
- **Basic**: `id`, `arbetsordertyp_kod`, `status_kod`, `prioritet_kod`
- **Dates**: `registrerad_datum`, `bestall_datum`, `utford_datum`, `modifierad_datum`
- **Descriptions**: `beskrivning`, `kommentar`, `anmarkning`, `atgard`
- **Relations**: `objekt_id`, `kund_id`, `utforare_*`, `anmalare_*`
- **Complex JSON**: `bunt`, `planering`, `ekonomi`, `material`, `tillsynsobjekt`
- **Metadata**: `status` (active/inactive), `last_synced`

**Note**: Work orders with `externtNr: "CONFIDENTIAL"` are automatically filtered out by `fetch_arbetsordrar.php`.

## Best Practices & Common Patterns

### Working with API Filtering

**When to use API filters**:
- Use `kundId` or `kundNr` to limit scope to specific customer
- Use `objektId` when working with property-specific orders
- Use `status` or `feltyp` arrays to filter by work order characteristics
- Use date filters (`skapadEfter`, `modifieradEfter`) for incremental syncs

**When to use local filtering**:
- Filtering on nested object fields (e.g., `annanAnmalare.epostAdress`)
- Complex logic that API doesn't support
- Multi-field conditions
- Case-insensitive string matching

**Performance considerations**:
- Always use `kundId` filter to reduce data transfer
- Use pagination (`offset`, `limit`) for large datasets
- Cache API results when fetching multiple times
- Consider incremental sync with `modifieradEfter` for updates

### Date Format Handling

FAST2 API uses different date formats in different contexts:

- **Date only**: `YYYYMMDD` (e.g., `20260127`)
- **DateTime**: ISO 8601 format for filters (e.g., `2026-01-27T10:30:00`)

When working with dates from API responses:
```php
// Convert YYYYMMDD to readable format
$datum = '20260127';
$formatted = substr($datum, 0, 4) . '-' . substr($datum, 4, 2) . '-' . substr($datum, 6, 2);
// Result: 2026-01-27
```

### Error Handling Patterns

All scripts should handle these common scenarios:

1. **OAuth2 token failure** - Invalid consumer key/secret
2. **API login failure** - Invalid username/password
3. **API request failure** - Network issues, invalid parameters
4. **Empty result sets** - Customer has no data
5. **Rate limiting** - Too many requests (rare but possible)

Enable verbose mode for debugging: `php script.php -v`

## Known Issues & Workarounds

### File Upload Endpoint (HTTP 500)
The `/ao-produkt/v1/filetransfer/tempfile` endpoint consistently returns HTTP 500 errors. This is a known server-side issue documented in TEST_RESULTS.md. If working on file upload functionality, coordinate with FAST2/Fabo support.

### Numeric Range Errors in Directus
If you see "Numeric value out of range" errors during sync, the ID field was likely created with wrong type (integer instead of string). Run `php fix_and_resync.php` to recreate the collection with correct schema.

### Utrymmen/Enheter Not Saving in Joomla Extension
The FAST2 API endpoints for utrymmen (rooms) and enheter (units) work correctly (verified by tests). If the Joomla extension isn't saving these values, the issue is in:
1. Frontend form state management (ReportForm.jsx)
2. Work order payload construction
3. Nested object format (`{"utrymme": {"id": 123}}` vs flat `utrymmesId`)

See TEST_RESULTS.md for detailed diagnosis.

## Project Context

This project is part of a larger Joomla extension (`mod_fbg_fabofelanm`) for reporting facility issues. These standalone scripts:
- Mirror the authentication flow from the Joomla module
- Provide CLI tools for bulk data operations
- Enable periodic synchronization via cron jobs
- Support testing and diagnostics independent of the Joomla environment

The scripts intentionally have no Joomla dependencies and can run as standalone CLI tools.

## Working with the Codebase

### Adding New API Endpoints
1. Study the authentication pattern in `Fast2ApiClient` class
2. Add new method that calls `$this->makeRequest('GET', '/path')`
3. Ensure both OAuth2 and API tokens are valid before calling
4. Test with a standalone script first before integrating

### Creating New Directus Collections
1. Study schema in `create_directus_collection.php` or `create_directus_arbetsordrar_collection.php`
2. Use `DirectusClient->createCollection()` and `createField()` methods
3. Map FAST2 nested objects to flat Directus fields OR store as JSON in a field
4. Always include: `id` (string), `status` (string), `last_synced` (timestamp)

### Extending Sync Scripts
1. Fetch data from FAST2 (returns array of items with `id` field)
2. Fetch existing items from Directus by collection name
3. Build ID-keyed maps for comparison: `$directusById = array_column($existing, null, 'id')`
4. Loop through FAST2 items: create if new, update if exists
5. Mark items in Directus but not in FAST2 as `status='inactive'`
6. Display statistics (created, updated, marked inactive, duration)

### Debugging Authentication Issues
Enable verbose mode on any script: `php scriptname.php -v` or pass `verbose: true` to client constructor. This shows:
- OAuth2 token requests and responses
- API login attempts
- Full request URLs
- Response codes and partial bodies

## Related Files

- **README.md** - User-facing documentation with setup instructions and troubleshooting
- **TEST_RESULTS.md** - Detailed test results for utrymmen, enheter, and file upload endpoints
- **.env** - Configuration (git-ignored, contains secrets)
- **.env.example** - Configuration template
- **testfiles/** - Sample files for upload testing

## Directus Admin URLs

- Properties: https://nav.utvecklingfalkenberg.se/admin/content/fast2_fastigheter
- Work Orders: https://nav.utvecklingfalkenberg.se/admin/content/fast2_arbetsordrar
