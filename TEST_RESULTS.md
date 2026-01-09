# FAST2 API Test Results - Utrymmen, Enheter, File Upload

Test Date: 2026-01-09
Test Script: `test_utrymmen_enheter_upload.php`

## Summary

Three FAST2 API endpoints were tested to diagnose issues in the Joomla extension:

| Endpoint | Status | Details |
|----------|--------|---------|
| List Utrymmen | ✅ **WORKING** | Successfully fetched 5 utrymmen |
| List Enheter | ✅ **WORKING** | Successfully fetched 4 enheter |
| File Upload | ❌ **FAILING** | HTTP 500 "Runtime Error" |

## Test Details

### Test 1: List Utrymmen (Spaces/Rooms)
**Endpoint:** `GET /ao-produkt/v1/fastastrukturen/utrymmen?objektId={objektId}`

**Result:** ✅ SUCCESS
- Fetched 5 utrymmen for objekt 9120801
- Response includes: id, beskrivning, rumsnummer, utrymmesTypKod
- Data saved to: `utrymmen_9120801_2026-01-09_143256.json`

**Sample Response:**
```json
{
  "id": 116488,
  "beskrivning": "Innomhus",
  "rumsnummer": null,
  "utrymmesTypKod": "INOMH"
}
```

**Conclusion:** The utrymmen endpoint is working correctly. If the Joomla extension is not saving utrymmen correctly, the problem is likely in the frontend form handling or data submission logic, NOT in the API call itself.

---

### Test 2: List Enheter (Units)
**Endpoint:** `GET /ao-produkt/v1/fastastrukturen/enheter?utrymmesId={utrymmesId}`

**Result:** ✅ SUCCESS
- Fetched 4 enheter for utrymme 116488
- Response includes: id, utrymme, enhetstyp, beskrivning, underhall, felanmalan, and many other fields
- Data saved to: `enheter_116488_2026-01-09_143256.json`

**Sample Response:**
```json
{
  "id": 604170,
  "utrymme": {
    "id": 116488
  },
  "enhetstyp": {
    "kod": "Övrigt-K",
    "id": 3040
  },
  "beskrivning": "Övrigt",
  "underhall": false,
  "felanmalan": true,
  "underhallsdatum": null,
  "installationsdatum": null,
  "serienummer": null,
  "garanti": 0,
  "underhallAtgard": null,
  "egenskap": null,
  "mangd": 1,
  "notering": null
}
```

**Conclusion:** The enheter endpoint is working correctly. If the Joomla extension is not saving enheter correctly, the problem is likely in the frontend form handling or data submission logic, NOT in the API call itself.

---

### Test 3: Upload Temporary File
**Endpoint:** `POST /ao-produkt/v1/filetransfer/tempfile`

**Result:** ❌ FAIL - HTTP 500

**Error Response:**
```json
{
  "code": "101500",
  "type": "Status report",
  "message": "Runtime Error",
  "description": "Error in Sender"
}
```

**Tests Performed:**
1. ❌ Large image file (964KB JPEG) - HTTP 500
2. ❌ Small text file (30 bytes) - HTTP 500

**Request Details:**
- Method: POST with multipart/form-data
- Form field: `file` → CURLFile
- Headers:
  - `Authorization: Bearer {oauth2_token}`
  - `X-Auth-Token: {api_token}`
- Content-Type: NOT set (letting cURL handle boundary)

**Comparison with Joomla Extension:**
The Joomla extension uses the same approach:
```php
// From helper.php line 94
$body = ['file' => new CURLFile($tmpName, $type, $name)];
```

And from ProxyToRealApi.php:
```php
// Lines 123-126
if ($body instanceof CURLFile || is_array($body) && $this->isMultipartData($body)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    // Don't set Content-Type header for multipart, cURL handles it
}
```

**Possible Causes:**

1. **API Server Error:** The `/filetransfer/tempfile` endpoint may be experiencing issues on the FAST2 API server side
2. **Authentication Issue:** The file upload endpoint might require different authentication
3. **Missing Parameters:** There might be required parameters or headers we're not sending
4. **API Version Issue:** The endpoint might have changed in FAST2 API v1.8
5. **Gateway Configuration:** WSO2 API Gateway might be blocking large POST requests

**Recommendation:** Contact FAST2/Fabo support to verify:
- Is the `/ao-produkt/v1/filetransfer/tempfile` endpoint operational?
- Are there any special requirements for file uploads?
- Are there any known issues with this endpoint?
- Is there API documentation available?

---

## Diagnosis: Utrymmen and Enheter "Not Saving" Issue

Since the API endpoints for utrymmen and enheter are working correctly, the issue in the Joomla extension is likely in one of these areas:

### 1. Form Field Binding
Check if the selected utrymme/enhet IDs are being captured correctly from the form fields in the React widget.

**Files to check:**
- `widget-build/src/components/ReportForm.jsx` - Look for utrymme/enhet state management
- Check if `selectedUtrymme` and `selectedEnhet` are being included in the work order submission payload

### 2. Data Submission Payload
Verify that utrymme and enhet are being sent in the correct format when creating a work order.

**Expected payload structure:**
```json
{
  "utrymme": {
    "id": 116488
  },
  "enhet": {
    "id": 604170
  }
}
```

### 3. API Work Order Creation
Check the `/ao-produkt/v1/arbetsorder` POST endpoint requirements:
- Does it accept nested objects for utrymme/enhet?
- Or does it expect flat fields like `utrymmesId` and `enhetId`?

**Test Script Available:**
The test data in the JSON files can be used to verify the correct structure expected by the API.

---

## Test Files Generated

All test results have been saved to JSON files for analysis:

```bash
# View utrymmen data
cat utrymmen_9120801_2026-01-09_143256.json | jq

# View enheter data
cat enheter_116488_2026-01-09_143256.json | jq

# List all test files
ls -lh utrymmen_* enheter_* upload_*
```

---

## Next Steps

### For File Upload Issue:
1. Contact FAST2/Fabo technical support about the HTTP 500 error
2. Request API documentation for the file upload endpoint
3. Ask if there are any known issues or maintenance windows
4. Consider alternative file attachment methods if available

### For Utrymmen/Enheter Issue:
1. Debug the React form in `ReportForm.jsx`:
   ```javascript
   console.log('Selected Utrymme:', selectedUtrymme);
   console.log('Selected Enhet:', selectedEnhet);
   console.log('Payload being sent:', payloadData);
   ```

2. Check browser DevTools Network tab when submitting a work order:
   - Does the payload include utrymme and enhet?
   - What format are they in?

3. Verify the work order creation endpoint accepts the format being sent

4. Check if there are validation errors being silently ignored

---

## Running the Tests

To reproduce these tests:

```bash
cd /home/httpd/fbg-intranet/joomlaextensions/fabofelanm/fabo_test

# Test with default objekt (9120801) and file
php test_utrymmen_enheter_upload.php

# Test with specific objekt, utrymme, and file
php test_utrymmen_enheter_upload.php [objektId] [utrymmesId] [file_path]

# Example:
php test_utrymmen_enheter_upload.php 9120801 116488 testfiles/test-upload.txt
```

---

## Conclusion

**Utrymmen and Enheter APIs: WORKING** ✅
- Both endpoints return complete, valid data
- The issue is likely in the Joomla extension's frontend form handling or payload construction

**File Upload API: BROKEN** ❌
- Consistently returns HTTP 500 error
- Affects both direct API calls and Joomla extension
- Requires investigation with FAST2/Fabo support

**Recommendation:** Focus on debugging the utrymme/enhet form submission in the React widget, as the API endpoints themselves are working correctly. For file uploads, contact FAST2 support as this appears to be a server-side issue.
