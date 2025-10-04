<?php
/**
 * Spezifischer Test für access-proxy.php Endpunkt
 * Testet den korrigierten API-Aufruf
 */
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: text/plain');

echo "=== Access Proxy Test ===\n\n";

$url = envRequired('PI_SERVICE_URL');
$username = envRequired('PI_API_USERNAME');  
$password = envRequired('PI_API_PASSWORD');
$cfClientId = envRequired('CF_ACCESS_CLIENT_ID');
$cfClientSecret = envRequired('CF_ACCESS_CLIENT_SECRET');

echo "Testing URL: $url\n";
echo "Username: $username\n\n";

// Test 1: POST mit korrektem Payload
echo "=== Test 1: POST mit house + open ===\n";
$result1 = testAccessProxy(['door' => 'house', 'action' => 'open']);
echo "HTTP Code: " . $result1['http_code'] . "\n";
echo "Response: " . ($result1['response'] ?: 'Empty') . "\n";
echo "cURL Error: " . ($result1['curl_error'] ?: 'None') . "\n\n";

// Test 2: POST nur mit action
echo "=== Test 2: POST nur mit action ===\n";
$result2 = testAccessProxy(['action' => 'open']);
echo "HTTP Code: " . $result2['http_code'] . "\n";
echo "Response: " . ($result2['response'] ?: 'Empty') . "\n";
echo "cURL Error: " . ($result2['curl_error'] ?: 'None') . "\n\n";

// Test 3: GET Request
echo "=== Test 3: GET Request ===\n";
$result3 = testAccessProxyGet();
echo "HTTP Code: " . $result3['http_code'] . "\n";
echo "Response: " . ($result3['response'] ?: 'Empty') . "\n";
echo "cURL Error: " . ($result3['curl_error'] ?: 'None') . "\n\n";

// Test 4: Ohne Cloudflare Access
echo "=== Test 4: Ohne Cloudflare Access ===\n";
$result4 = testAccessProxy(['door' => 'house', 'action' => 'open'], false);
echo "HTTP Code: " . $result4['http_code'] . "\n";
echo "Response: " . ($result4['response'] ?: 'Empty') . "\n";
echo "cURL Error: " . ($result4['curl_error'] ?: 'None') . "\n\n";

// Analyse
echo "=== Analyse ===\n";
$allResults = [$result1, $result2, $result3, $result4];
$successCodes = array_filter($allResults, fn($r) => $r['http_code'] >= 200 && $r['http_code'] < 300);
$authCodes = array_filter($allResults, fn($r) => $r['http_code'] == 401);
$forbiddenCodes = array_filter($allResults, fn($r) => $r['http_code'] == 403);

if (!empty($successCodes)) {
    echo "✅ Erfolgreiche Antworten gefunden! HTTP 2xx\n";
    echo "Die API funktioniert grundsätzlich.\n";
} elseif (!empty($authCodes)) {
    echo "🔐 HTTP 401 - Authentifizierung fehlgeschlagen\n";
    echo "Prüfen Sie Username und Passwort.\n";
} elseif (!empty($forbiddenCodes)) {
    echo "🚫 HTTP 403 - Cloudflare Access Problem\n"; 
    echo "Prüfen Sie die CF-Access Credentials.\n";
} else {
    echo "❌ Kein erfolgreicher Test. Möglicherweise ist der Endpunkt anders.\n";
    echo "Versuchen Sie die ursprüngliche URL ohne /access-proxy.php\n";
}

function testAccessProxy($payload, $withCloudflare = true) {
    global $url, $username, $password, $cfClientId, $cfClientSecret;
    
    $headers = ['Content-Type: application/json'];
    
    if ($withCloudflare) {
        $headers[] = "CF-Access-Client-Id: {$cfClientId}";
        $headers[] = "CF-Access-Client-Secret: {$cfClientSecret}";
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => "{$username}:{$password}",
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
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

function testAccessProxyGet() {
    global $url, $username, $password, $cfClientId, $cfClientSecret;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => "{$username}:{$password}",
        CURLOPT_HTTPHEADER => [
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
?>