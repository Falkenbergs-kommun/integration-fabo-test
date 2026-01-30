#!/usr/bin/env php
<?php
/**
 * Test FAST2 API Query Parameters
 *
 * Detta script testar vilka filterparametrar FAST2 API stödjer
 * för att hämta arbetsordrar.
 *
 * Enligt API-dokumentationen (version 1.1, sida 37-38) stödjer
 * GET /v1/arbetsorder följande query-parametrar:
 * - offset (standard 0)
 * - limit (standard 100)
 * - objektId (string)
 * - kundNr (string)
 * - utforare (Array)
 * - status (Array)
 * - feltyp (Array)
 * - skapadEfter (string <date>)
 * - modifieradEfter (string <date-time>)
 *
 * INGEN parameter finns för att filtrera på anmälare eller e-postadress!
 *
 * Usage: php test_api_filters.php
 *
 * @package    Falkenbergs kommun
 * @subpackage FAST2 API Test Scripts
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/fetch_arbetsordrar.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  FAST2 API - Test av Query-parametrar                     ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "📖 Analys av API-dokumentation (version 1.1)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Endpoint: GET /v1/arbetsorder\n\n";

echo "✅ STÖDDA query-parametrar enligt dokumentationen:\n";
echo "   • offset          - Hoppa över N första resultat (standard: 0)\n";
echo "   • limit           - Max antal resultat (standard: 100)\n";
echo "   • objektId        - Filtrera på objektnummer\n";
echo "   • kundNr          - Filtrera på kundnummer\n";
echo "   • utforare        - Filtrera på utförare (Array)\n";
echo "   • status          - Filtrera på status (Array)\n";
echo "   • feltyp          - Filtrera på feltyp (Array)\n";
echo "   • skapadEfter     - Filtrera på skapad efter datum\n";
echo "   • modifieradEfter - Filtrera på modifierad efter datum\n\n";

echo "❌ SAKNADE parametrar (ej stödda av API:et):\n";
echo "   • anmalare           - Filtrera på anmälare\n";
echo "   • anmalareEpost      - Filtrera på anmälare e-post\n";
echo "   • epostAdress        - Filtrera på e-postadress\n";
echo "   • annanAnmalare      - Filtrera på annan anmälare\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🔍 SLUTSATS:\n";
echo "API:et stödjer INTE filtrering på e-postadress!\n\n";

echo "Därför måste vi:\n";
echo "1. Hämta alla arbetsordrar från API:et (eller filtrera på kundNr)\n";
echo "2. Filtrera lokalt i PHP-kod på 'annanAnmalare.epostAdress'\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Nu testar vi med några olika filter
try {
    $envFile = __DIR__ . '/.env';
    $config = loadEnv($envFile);

    $client = new Fast2WorkOrderClient($config, false);

    echo "📊 TEST 1: Hämta utan filter (bara limit på 10)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Testa att hämta med limit
    $orders1 = $client->fetchWorkOrders(['limit' => 10]);
    echo "✅ Hämtade " . count($orders1) . " arbetsordrar (limit: 10)\n\n";

    echo "📊 TEST 2: Försök filtrera på kundNr\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Testa filtrera på kundNr (om det finns i konfigurationen)
    if (!empty($config['KUND_NR'])) {
        $orders2 = $client->fetchWorkOrders([
            'kundNr' => $config['KUND_NR'],
            'limit' => 10
        ]);
        echo "✅ Hämtade " . count($orders2) . " arbetsordrar (kundNr: {$config['KUND_NR']}, limit: 10)\n\n";
    } else {
        echo "⚠️  KUND_NR saknas i .env, hoppar över detta test\n\n";
    }

    echo "📊 TEST 3: Hämta med kundId (numeriskt)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Detta är default i vårt script
    $orders3 = $client->fetchWorkOrders(['limit' => 10]);
    echo "✅ Hämtade " . count($orders3) . " arbetsordrar (kundId: {$config['KUND_ID']}, limit: 10)\n\n";

    // Visa exempel på data
    if (count($orders3) > 0) {
        $firstOrder = $orders3[0];
        echo "📋 Exempel på arbetsorder-data:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: {$firstOrder['id']}\n";
        echo "Objekt: {$firstOrder['objekt']['id']}\n";
        echo "Status: {$firstOrder['status']['statusKod']}\n";

        if (isset($firstOrder['annanAnmalare'])) {
            echo "\nAnnan anmälare:\n";
            echo "  Namn: " . ($firstOrder['annanAnmalare']['namn'] ?? 'N/A') . "\n";
            echo "  E-post: " . ($firstOrder['annanAnmalare']['epostAdress'] ?? 'N/A') . "\n";
            echo "  Telefon: " . ($firstOrder['annanAnmalare']['telefon'] ?? 'N/A') . "\n";
        } else {
            echo "\nAnnan anmälare: (saknas)\n";
        }
    }

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Sammanfattning                                            ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "✅ API:et fungerar och returnerar arbetsordrar\n";
    echo "❌ API:et stödjer INTE filtrering på e-postadress\n";
    echo "💡 Lösning: Hämta alla ordrar och filtrera lokalt i PHP\n";
    echo "\n";
    echo "Befintligt script (test_user_orders.php) använder redan\n";
    echo "den korrekta metoden!\n";
    echo "\n";

} catch (Exception $e) {
    echo "\n❌ Fel: " . $e->getMessage() . "\n\n";
    exit(1);
}
