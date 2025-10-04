<?php
/**
 * Verbesserte Bootstrap-Funktionen mit flexibleren API-Endpunkten
 * Diese Datei kann temporär anstelle der originalen Bootstrap verwendet werden
 */

// Alle originalen Funktionen von bootstrap.php hier einfügen, außer openHouseDoor()

/**
 * Verbesserte Haustür-Öffnung mit mehreren Endpunkt-Versuchen
 */
function openHouseDoorImproved(): array
{
    try {
        $piServiceUrl = envRequired('PI_SERVICE_URL');
        $username = envRequired('PI_API_USERNAME');
        $password = envRequired('PI_API_PASSWORD');
        $cfClientId = envRequired('CF_ACCESS_CLIENT_ID');
        $cfClientSecret = envRequired('CF_ACCESS_CLIENT_SECRET');
        
        // Verschiedene mögliche API-Endpunkte versuchen
        $possibleEndpoints = [
            '/api/doors/house/open',     // Original
            '/api/door/house/open',      // Singular
            '/api/doors/open',           // Ohne 'house'
            '/api/door/open',            // Einfacher
            '/api/open',                 // Sehr einfach
        ];
        
        $lastError = '';
        $lastHttpCode = 0;
        
        foreach ($possibleEndpoints as $endpoint) {
            $url = rtrim($piServiceUrl, '/') . $endpoint;
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERPWD => "{$username}:{$password}",
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    "CF-Access-Client-Id: {$cfClientId}",
                    "CF-Access-Client-Secret: {$cfClientSecret}",
                    'User-Agent: Airbnb-Guest-System/1.0'
                ],
                CURLOPT_POSTFIELDS => json_encode(['action' => 'open']),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            // Debug-Informationen loggen
            debugLog("Door API attempt", [
                'endpoint' => $endpoint,
                'url' => $url,
                'http_code' => $httpCode,
                'curl_error' => $error,
                'response_length' => strlen($response ?: ''),
                'response_preview' => substr($response ?: '', 0, 100)
            ]);
            
            if ($error) {
                $lastError = "Verbindungsfehler zu $endpoint: {$error}";
                continue; // Nächsten Endpunkt versuchen
            }
            
            $lastHttpCode = $httpCode;
            
            // HTTP 200 = Erfolg
            if ($httpCode === 200) {
                // Antwort analysieren
                $responseData = json_decode($response, true);
                if ($responseData && isset($responseData['success']) && $responseData['success']) {
                    return ['success' => true, 'message' => 'Haustür geöffnet', 'endpoint' => $endpoint];
                } elseif ($responseData && isset($responseData['message'])) {
                    return ['success' => true, 'message' => $responseData['message'], 'endpoint' => $endpoint];
                } else {
                    return ['success' => true, 'message' => 'Haustür geöffnet (Annahme)', 'endpoint' => $endpoint];
                }
            }
            
            // HTTP 401 = Authentifizierung fehlgeschlagen (aber Endpunkt existiert)
            if ($httpCode === 401) {
                return ['success' => false, 'message' => "Authentifizierung fehlgeschlagen bei $endpoint. Prüfen Sie Benutzername/Passwort."];
            }
            
            // HTTP 403 = Cloudflare Access Problem (aber Endpunkt existiert)
            if ($httpCode === 403) {
                return ['success' => false, 'message' => "Cloudflare Access fehlgeschlagen bei $endpoint. Prüfen Sie die CF-Credentials."];
            }
            
            // HTTP 405 = Method not allowed (Endpunkt existiert, aber nur GET erlaubt)
            if ($httpCode === 405) {
                // Versuche GET-Request
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_USERPWD => "{$username}:{$password}",
                    CURLOPT_HTTPHEADER => [
                        "CF-Access-Client-Id: {$cfClientId}",
                        "CF-Access-Client-Secret: {$cfClientSecret}"
                    ]
                ]);
                
                $getResponse = curl_exec($ch);
                $getHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($getHttpCode === 200) {
                    return ['success' => true, 'message' => 'Haustür geöffnet via GET', 'endpoint' => $endpoint];
                }
            }
            
            // HTTP 404 = Endpunkt nicht gefunden, weiter zum nächsten
            if ($httpCode === 404) {
                continue;
            }
            
            // Andere HTTP-Codes
            $lastError = "API-Fehler bei $endpoint: HTTP {$httpCode}";
        }
        
        // Wenn alle Endpunkte fehlgeschlagen sind
        return [
            'success' => false, 
            'message' => $lastError ?: "Alle API-Endpunkte fehlgeschlagen. Letzter HTTP-Code: {$lastHttpCode}",
            'debug_info' => [
                'tried_endpoints' => $possibleEndpoints,
                'last_http_code' => $lastHttpCode,
                'base_url' => $piServiceUrl
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => "Konfigurationsfehler: " . $e->getMessage(),
            'debug_info' => [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]
        ];
    }
}
?>