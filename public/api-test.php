<?php
/**
 * API-Endpunkt Tester für Raspberry Pi Service
 * Hilft dabei, den korrekten API-Endpunkt zu finden
 */
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

$baseUrl = envRequired('PI_SERVICE_URL');
$username = envRequired('PI_API_USERNAME');
$password = envRequired('PI_API_PASSWORD');
$cfClientId = envRequired('CF_ACCESS_CLIENT_ID');
$cfClientSecret = envRequired('CF_ACCESS_CLIENT_SECRET');

// Verschiedene mögliche API-Endpunkte testen
$possibleEndpoints = [
    '/api/doors/house/open',     // Aktueller Endpunkt
    '/api/door/house/open',      // Ohne 's'
    '/api/doors/open',           // Ohne 'house'
    '/api/door/open',            // Einfacher
    '/api/open',                 // Sehr einfach
    '/api/doors/house',          // Ohne 'open'
    '/api/doors',                // Liste aller Türen
    '/api',                      // API-Root
    '',                          // Basis-URL
];

$results = [];

foreach ($possibleEndpoints as $endpoint) {
    $url = rtrim($baseUrl, '/') . $endpoint;
    
    echo "Testing: $url\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERPWD => "{$username}:{$password}",
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "CF-Access-Client-Id: {$cfClientId}",
            "CF-Access-Client-Secret: {$cfClientSecret}"
        ],
        // Erste Anfrage als GET um zu sehen ob der Endpunkt existiert
        CURLOPT_HTTPGET => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    $results[] = [
        'endpoint' => $endpoint,
        'full_url' => $url,
        'http_code' => $httpCode,
        'curl_error' => $error ?: null,
        'content_type' => $contentType,
        'response_preview' => $response ? substr($response, 0, 200) : null,
        'response_length' => $response ? strlen($response) : 0
    ];
}

// Zusätzlich: POST-Anfrage an den ursprünglichen Endpunkt testen
echo "\n=== Testing POST request to original endpoint ===\n";
$url = $baseUrl . '/api/doors/house/open';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERPWD => "{$username}:{$password}",
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "CF-Access-Client-Id: {$cfClientId}",
        "CF-Access-Client-Secret: {$cfClientSecret}"
    ],
    CURLOPT_POSTFIELDS => json_encode(['action' => 'open'])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$results[] = [
    'endpoint' => '/api/doors/house/open (POST)',
    'full_url' => $url,
    'http_code' => $httpCode,
    'curl_error' => $error ?: null,
    'method' => 'POST',
    'payload' => ['action' => 'open'],
    'response_preview' => $response ? substr($response, 0, 200) : null,
    'response_length' => $response ? strlen($response) : 0
];

echo json_encode([
    'base_url' => $baseUrl,
    'test_results' => $results,
    'summary' => [
        'working_endpoints' => array_filter($results, fn($r) => $r['http_code'] >= 200 && $r['http_code'] < 300),
        'possible_endpoints' => array_filter($results, fn($r) => $r['http_code'] !== 404),
        'recommendation' => 'Look for endpoints with HTTP 200, 401, or 405 status codes'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>