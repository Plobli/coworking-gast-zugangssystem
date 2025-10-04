<?php
/**
 * Spezifischer API-Test für Haustür-Öffnung
 * Testet verschiedene Variationen der API-Konfiguration
 */
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: text/plain');

echo "=== Raspberry Pi API Debug Test ===\n\n";

// Konfiguration anzeigen (ohne Passwörter)
$baseUrl = envRequired('PI_SERVICE_URL');
$username = envRequired('PI_API_USERNAME');
$cfClientId = envRequired('CF_ACCESS_CLIENT_ID');

echo "Base URL: $baseUrl\n";
echo "Username: $username\n";
echo "CF Client ID: " . substr($cfClientId, 0, 10) . "...\n\n";

// Test 1: Ohne Cloudflare Access Headers
echo "=== Test 1: Nur Basic Auth (ohne CF Access) ===\n";
$result1 = testDoorAPI(false);
echo "HTTP Code: " . $result1['http_code'] . "\n";
echo "Response: " . ($result1['response'] ?: 'Empty') . "\n";
echo "cURL Error: " . ($result1['curl_error'] ?: 'None') . "\n\n";

// Test 2: Mit Cloudflare Access Headers
echo "=== Test 2: Basic Auth + Cloudflare Access ===\n";
$result2 = testDoorAPI(true);
echo "HTTP Code: " . $result2['http_code'] . "\n";
echo "Response: " . ($result2['response'] ?: 'Empty') . "\n";
echo "cURL Error: " . ($result2['curl_error'] ?: 'None') . "\n\n";

// Test 3: Nur GET Request um API-Verfügbarkeit zu testen
echo "=== Test 3: GET Request (API erreichbar?) ===\n";
$result3 = testDoorAPIGet();
echo "HTTP Code: " . $result3['http_code'] . "\n";
echo "Response: " . ($result3['response'] ?: 'Empty') . "\n";
echo "cURL Error: " . ($result3['curl_error'] ?: 'None') . "\n\n";

// Test 4: Basis URL ohne Endpunkt
echo "=== Test 4: Basis URL Test ===\n";
$result4 = testBaseURL();
echo "HTTP Code: " . $result4['http_code'] . "\n";
echo "Response: " . ($result4['response'] ?: 'Empty') . "\n";
echo "cURL Error: " . ($result4['curl_error'] ?: 'None') . "\n\n";

// Empfehlungen basierend auf den Ergebnissen
echo "=== Analyse & Empfehlungen ===\n";

if ($result1['http_code'] == 200) {
    echo "✅ API funktioniert ohne Cloudflare Access\n";
} elseif ($result2['http_code'] == 200) {
    echo "✅ API funktioniert mit Cloudflare Access\n";
} elseif ($result1['http_code'] == 401 || $result2['http_code'] == 401) {
    echo "🔐 Authentifizierungsproblem - prüfen Sie Username/Passwort\n";
} elseif ($result1['http_code'] == 403 || $result2['http_code'] == 403) {
    echo "🚫 Cloudflare Access Problem - prüfen Sie CF Credentials\n";
} elseif ($result1['http_code'] == 404 || $result2['http_code'] == 404) {
    echo "❌ API-Endpunkt existiert nicht - URL ist falsch\n";
} elseif ($result1['http_code'] == 405 || $result2['http_code'] == 405) {
    echo "🔄 POST-Methode nicht erlaubt - versuchen Sie andere HTTP-Methoden\n";
} else {
    echo "⚠️ Unerwarteter Fehler - prüfen Sie Netzwerk und Server\n";
}

// Funktionen
function testDoorAPI($withCloudflare = true) {
    $baseUrl = envRequired('PI_SERVICE_URL');
    $username = envRequired('PI_API_USERNAME');
    $password = envRequired('PI_API_PASSWORD');
    
    $headers = [
        'Content-Type: application/json',
    ];
    
    if ($withCloudflare) {
        $cfClientId = envRequired('CF_ACCESS_CLIENT_ID');
        $cfClientSecret = envRequired('CF_ACCESS_CLIENT_SECRET');
        $headers[] = "CF-Access-Client-Id: {$cfClientId}";
        $headers[] = "CF-Access-Client-Secret: {$cfClientSecret}";
    }
    
    $url = "{$baseUrl}/api/doors/house/open";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => "{$username}:{$password}",
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode(['action' => 'open']),
        CURLOPT_VERBOSE => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $error
    ];
}

function testDoorAPIGet() {
    $baseUrl = envRequired('PI_SERVICE_URL');
    $username = envRequired('PI_API_USERNAME');
    $password = envRequired('PI_API_PASSWORD');
    $cfClientId = envRequired('CF_ACCESS_CLIENT_ID');
    $cfClientSecret = envRequired('CF_ACCESS_CLIENT_SECRET');
    
    $url = "{$baseUrl}/api/doors/house/open";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => "{$username}:{$password}",
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "CF-Access-Client-Id: {$cfClientId}",
            "CF-Access-Client-Secret: {$cfClientSecret}"
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $error
    ];
}

function testBaseURL() {
    $baseUrl = envRequired('PI_SERVICE_URL');
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl,
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $error
    ];
}
?>