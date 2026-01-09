# FAST2 API Test Scripts

Testscripts för att testa FAST2 API-autentisering och datahämtning, samt synkronisera fastighetsdata till Directus.

## Om projektet

Detta projekt innehåller CLI-verktyg för att:
- Hämta fastighetsdata från FAST2 API (Fabo's fastighetssystem)
- Hämta arbetsordrar (felanmälningar och beställningar) från FAST2 API
- Synkronisera fastigheter och arbetsordrar till Directus CMS
- Underhålla uppdaterade kopior av fastighets- och arbetsorderdatabaser

Projektet är utvecklat av Utvecklingsavdelningen på Falkenbergs kommun och används för att integrera FAST2-data med kommunens interna system.

## Filer i projektet

### Fastigheter (Properties)
- **fetch_fastigheter.php** - Hämtar fastigheter från FAST2 API och sparar som JSON
- **sync_to_directus.php** - Synkroniserar fastigheter till Directus
- **create_directus_collection.php** - Skapar Directus-tabellen för fastigheter
- **fix_and_resync.php** - Återskapar Directus-tabellen och synkar om (vid problem)

### Arbetsordrar (Work Orders)
- **fetch_arbetsordrar.php** - Hämtar arbetsordrar från FAST2 API och sparar som JSON
- **sync_arbetsordrar_to_directus.php** - Synkroniserar arbetsordrar till Directus
- **create_directus_arbetsordrar_collection.php** - Skapar Directus-tabellen för arbetsordrar

### Gemensamma filer
- **DirectusClient.php** - Klass för Directus API-anrop
- **.env** - Konfigurationsfil med API-credentials (ingår ej i git)
- **.env.example** - Mall för konfigurationsfil

## Setup

### 1. Kopiera och konfigurera .env-filen

```bash
cp .env.example .env
```

Redigera `.env` och fyll i dina API-credentials:

```env
# OAuth2 Credentials för API Gateway (WSO2)
OAUTH2_TOKEN_ENDPOINT=https://api.example.com/oauth2/token
CONSUMER_KEY=din_consumer_key
CONSUMER_SECRET=din_consumer_secret

# FAST2 API Credentials
FAST2_BASE_URL=https://api.example.com
FAST2_USERNAME=ditt_användarnamn
FAST2_PASSWORD=ditt_lösenord

# Kundnummer för att hämta fastigheter
KUND_NR=ditt_kundnummer
```

### 2. Kör scriptet

```bash
php fetch_fastigheter.php
```

Eller om du har gjort det körbart:

```bash
./fetch_fastigheter.php
```

## Vad gör scriptet?

Scriptet följer samma autentiseringsflöde som `mod_fbg_fabofelanm`:

1. **OAuth2 Authentication** - Autentiserar mot WSO2 API Gateway med client credentials
2. **FAST2 API Login** - Loggar in i FAST2 API med användarnamn och lösenord
3. **Fetch Properties** - Hämtar alla fastigheter/objekt för det angivna kundnumret
4. **Save Response** - Sparar JSON-svaret till en fil med tidsstämpel

## Output

Scriptet skapar en fil med format: `fastigheter_YYYY-MM-DD_HHMMSS.json`

Exempel:
```
fastigheter_2024-01-08_143052.json
```

Filen innehåller hela API-svaret i JSON-format med:
- Alla fastigheter/objekt
- Adresser
- Objektstyper
- Relationer (fastighetsnummer, etc.)

## Felsökning

### Fel: ".env file not found"
- Kontrollera att du har kopierat `.env.example` till `.env`
- Kontrollera att du kör scriptet från rätt katalog

### Fel: "Missing required configuration"
- Kontrollera att alla värden i `.env` är ifyllda
- Se till att det inte finns extra mellanslag eller citattecken

### Fel: "OAuth2 token request failed"
- Kontrollera att `CONSUMER_KEY` och `CONSUMER_SECRET` är korrekta
- Kontrollera att `OAUTH2_TOKEN_ENDPOINT` är rätt URL

### Fel: "API login failed"
- Kontrollera att `FAST2_USERNAME` och `FAST2_PASSWORD` är korrekta
- Kontrollera att `FAST2_BASE_URL` är rätt URL

### Fel: "Properties request failed"
- Kontrollera att `KUND_NR` är korrekt
- Kontrollera att användaren har behörighet att se fastigheterna

## Kodstruktur

Scriptet är baserat på följande klasser från `mod_fbg_fabofelanm`:

- `OAuth2Client.php` - OAuth2 autentisering mot WSO2
- `ApiAuthClient.php` - FAST2 API login med användarnamn/lösenord
- `ProxyToRealApi.php` - API request handling

Allt är implementerat i ett enda CLI-script utan Joomla-beroenden.

## Directus Synkronisering

Förutom att hämta fastigheter från FAST2 API kan du också synkronisera dem till Directus.

### Setup för Directus

Kontrollera att `.env` innehåller Directus-konfiguration:

```env
# Directus Configuration
DIRECTUS_API_URL=https://nav.utvecklingfalkenberg.se
DIRECTUS_API_TOKEN=din_directus_api_token
```

### Skapa Directus-tabellen

Första gången måste du skapa tabellen `fast2_fastigheter` i Directus:

```bash
php create_directus_collection.php
```

Detta skapar tabellen med alla nödvändiga fält för att spara fastighetsinformation.

### Synkronisera fastigheter

Efter att tabellen är skapad kan du synkronisera fastigheter:

```bash
php sync_to_directus.php
```

Eller med verbose mode för detaljerad loggning:

```bash
php sync_to_directus.php -v
```

### Vad gör synkroniseringen?

Scriptet:
1. **Hämtar** alla fastigheter från FAST2 API
2. **Jämför** med befintliga fastigheter i Directus
3. **Skapar** nya fastigheter som inte finns
4. **Uppdaterar** befintliga fastigheter
5. **Markerar inaktiva** (soft delete) fastigheter som inte längre finns i FAST2

**Soft delete**: Fastigheter raderas aldrig permanent, utan markeras som `status=inactive` om de försvinner från FAST2 API.

### Synkroniseringsstatistik

Efter synkronisering visas statistik:

```
╔════════════════════════════════════════════════════════════╗
║  Synchronization Complete                                  ║
╚════════════════════════════════════════════════════════════╝

FAST2 Properties: 159
Directus before sync: 155
---
Created: 4
Updated: 155
Marked inactive: 0
---
Total in Directus now: 159 (159 active, 0 inactive)
Duration: 2.3s

✅ Sync completed successfully!
```

### Verifiera i Directus

Efter synkronisering kan du se fastigheterna i Directus:
- URL: https://nav.utvecklingfalkenberg.se/admin/content/fast2_fastigheter
- Alla fält från FAST2 API är tillgängliga
- Status-fält visar om fastighet är aktiv eller inaktiv
- `last_synced` visar när fastigheten senast uppdaterades

### Felsökning av Directus-synk

#### Problem: "Numeric value out of range" fel
Om du får fel som "Numeric value for field 'id' in collection 'fast2_fastigheter' is out of range", betyder det att ID-fältet har fel datatyp. Lösning:

```bash
php fix_and_resync.php
```

Detta script:
1. Raderar befintlig kollektion
2. Återskapar den med korrekt schema (ID som string)
3. Kör synkronisering igen

#### Problem: "Collection already exists"
Om du behöver skapa om kollektionen från grunden:

```bash
# Radera kollektionen manuellt i Directus admin-gränssnitt, eller kör:
php fix_and_resync.php
```

#### Problem: "403 Forbidden" vid skapande av kollektion
Du behöver en admin-token i `.env`. Kontrollera att `DIRECTUS_API_TOKEN` har admin-behörighet för att skapa kollektioner och fält.

## Arbetsordrar (Work Orders)

Förutom fastigheter kan du också hämta och synkronisera arbetsordrar (felanmälningar och beställningar) från FAST2 API.

### Hämta arbetsordrar

```bash
php fetch_arbetsordrar.php
```

Detta hämtar alla arbetsordrar för det konfigurerade kundnumret och sparar dem som JSON-fil (ex: `arbetsordrar_2026-01-09_134710.json`).

Arbetsordrar inkluderar:
- Felanmälningar (arbetsordertypKod: "F")
- Beställningar (arbetsordertypKod: "G")
- Information om utförare, anmälare, status, prioritet
- Datum för registrering, beställning, utförande, etc.
- Ekonomiinformation och planeringsdata

**Obs**: Konfidentiella arbetsordrar (med `externtNr: "CONFIDENTIAL"`) filtreras automatiskt bort.

### Setup för Directus - Arbetsordrar

Kontrollera att `.env` innehåller Directus-konfiguration (samma som för fastigheter).

### Skapa Directus-tabellen för arbetsordrar

Första gången måste du skapa tabellen `fast2_arbetsordrar` i Directus:

```bash
php create_directus_arbetsordrar_collection.php
```

Detta skapar tabellen med 34 fält:
- Grundläggande fält (ID, typ, status, prioritet)
- Datumfält (registrerad, beställd, utförd, modifierad)
- Beskrivningsfält (beskrivning, kommentar, anmärkning, åtgärd)
- Relationer (objekt-ID, kund-ID, utförare, anmälare)
- Komplexdata som JSON (bunt, planering, ekonomi, etc.)

### Synkronisera arbetsordrar

Efter att tabellen är skapad kan du synkronisera arbetsordrar:

```bash
php sync_arbetsordrar_to_directus.php
```

Eller med verbose mode:

```bash
php sync_arbetsordrar_to_directus.php -v
```

### Vad gör synkroniseringen?

Scriptet:
1. **Hämtar** alla arbetsordrar från FAST2 API (filtrerat på kundnummer)
2. **Jämför** med befintliga arbetsordrar i Directus
3. **Skapar** nya arbetsordrar som inte finns
4. **Uppdaterar** befintliga arbetsordrar
5. **Markerar inaktiva** (soft delete) arbetsordrar som inte längre finns i FAST2

**Soft delete**: Arbetsordrar raderas aldrig permanent, utan markeras som `status=inactive` om de försvinner från FAST2 API.

### Synkroniseringsstatistik - Arbetsordrar

Efter synkronisering visas statistik:

```
╔════════════════════════════════════════════════════════════╗
║  Synchronization Complete                                  ║
╚════════════════════════════════════════════════════════════╝

FAST2 Work Orders: 4
Directus before sync: 0
---
Created: 4
Updated: 0
Marked inactive: 0
---
Total in Directus now: 4 (4 active, 0 inactive)
Duration: 0.63s

✅ Sync completed successfully!
```

### Verifiera i Directus - Arbetsordrar

Efter synkronisering kan du se arbetsordrar i Directus:
- URL: https://nav.utvecklingfalkenberg.se/admin/content/fast2_arbetsordrar
- Alla fält från FAST2 API är tillgängliga
- Status-fält visar om arbetsorder är aktiv eller inaktiv
- `last_synced` visar när arbetsorden senast uppdaterades
- Komplexdata (utförare, planering, ekonomi) är lagrad som JSON

## Testning och felsökning

### Test av API-endpoints

Ett testscript finns för att verifiera tre kritiska FAST2 API-endpoints:

```bash
php test_utrymmen_enheter_upload.php
```

Scriptet testar:
1. **Utrymmen (spaces/rooms)** - Hämtar alla utrymmen för ett objekt
2. **Enheter (units)** - Hämtar alla enheter för ett utrymme
3. **Filuppladdning** - Laddar upp en fil och får tillbaka ett temporärt fil-ID

**Testresultat:**
- ✅ Utrymmen: FUNGERAR - Returnerar fullständig data
- ✅ Enheter: FUNGERAR - Returnerar fullständig data
- ❌ Filuppladdning: FUNGERAR EJ - HTTP 500 fel (kontakta FAST2 support)

Se [TEST_RESULTS.md](TEST_RESULTS.md) för detaljerade testresultat och diagnos.

**Användning:**
```bash
# Test med default värden
php test_utrymmen_enheter_upload.php

# Test med specifika värden
php test_utrymmen_enheter_upload.php [objektId] [utrymmesId] [filsökväg]

# Exempel
php test_utrymmen_enheter_upload.php 9120801 116488 testfiles/test-upload.txt
```

**Diagnos av Joomla-extension problem:**

Om utrymmen och enheter inte sparas korrekt i Joomla-extensionen, beror detta INTE på API:et (som fungerar korrekt), utan sannolikt på:
1. Formulärhantering i React-widgeten (ReportForm.jsx)
2. Dataformat i work order payload
3. Frontend state management för valda utrymmen/enheter

Se TEST_RESULTS.md för detaljerad felsökningsguide.

## Nästa steg

Efter att ha hämtat och synkroniserat data från FAST2 kan du:

1. **Analysera datamodellen** - Studera JSON-strukturen för att förstå relationerna mellan fastigheter och arbetsordrar
2. **Skapa relationer** - Koppla samman arbetsordrar med fastigheter i Directus via objekt_id
3. **Utöka med fler endpoints** - Implementera hämtning av utrymmen, enheter, etc.
4. **Automatisera synkronisering** - Sätt upp cron-jobb för regelbunden synk
5. **Bygga applikationer** - Använd Directus API för att visa fastighets- och arbetsorderdata i webbapplikationer
6. **Rapportering** - Skapa rapporter och dashboards baserat på synkroniserad data
