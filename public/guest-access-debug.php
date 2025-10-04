<?php
// Debug-Version von guest-access.php mit besserer Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', 0); // Auf Produktionsserver off, aber wir loggen alles

try {
    require_once __DIR__ . '/../config/bootstrap.php';
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Bootstrap-Konfiguration konnte nicht geladen werden', 'debug' => $e->getMessage()]));
}

// CORS Headers für API-Zugriff
header('Content-Type: application/json');

// Rate Limiting - DoS Schutz
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
try {
    if (!checkRateLimit($clientIp, 20, 300)) { // Max 20 Versuche in 5 Minuten
        http_response_code(429);
        die(json_encode(['error' => 'Zu viele Versuche. Bitte später erneut versuchen.']));
    }
} catch (Exception $e) {
    // Rate Limiting Fehler nicht kritisch - weiter machen
    debugLog('Rate limit check failed', ['error' => $e->getMessage()]);
}

// Token aus URL extrahieren
$token = $_GET['token'] ?? '';
$validateOnly = isset($_GET['validate_only']) && $_GET['validate_only'] === 'true';
$response = ['success' => false, 'message' => '', 'debug' => []];

if (empty($token)) {
    $response['error'] = 'Kein Zugangs-Token gefunden';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

try {
    // Token validieren und entschlüsseln
    $guestData = validateGuestToken($token);
    
    if (!$guestData) {
        $response['error'] = 'Ungültiger oder abgelaufener Zugangs-Token';
        http_response_code(403);
        try {
            logGuestAccess("Token ungültig", ['ip' => $clientIp, 'token_preview' => substr($token, 0, 20)]);
        } catch (Exception $e) {
            // Logging-Fehler nicht kritisch
        }
        echo json_encode($response);
        exit;
    }
    
    // Zusätzliche Sicherheitsprüfungen
    if (!isset($guestData['type']) || $guestData['type'] !== 'guest_access') {
        $response['error'] = 'Token nicht für Haustür-Zugang gültig';
        http_response_code(403);
        try {
            logGuestAccess("Token falscher Typ", ['ip' => $clientIp, 'data' => $guestData]);
        } catch (Exception $e) {
            // Logging-Fehler nicht kritisch
        }
        echo json_encode($response);
        exit;
    }
    
    // Zeitgrenzen prüfen
    if (!isset($guestData['expires']) || time() > $guestData['expires']) {
        $response['error'] = 'Zugangs-Token ist abgelaufen';
        if (isset($guestData['expires'])) {
            $response['expired_at'] = date('d.m.Y H:i', $guestData['expires']);
        }
        http_response_code(410);
        try {
            logGuestAccess("Token abgelaufen", ['ip' => $clientIp, 'guest' => $guestData['guest_name'] ?? 'unknown']);
        } catch (Exception $e) {
            // Logging-Fehler nicht kritisch
        }
        echo json_encode($response);
        exit;
    }
    
    // Nur Token validieren oder auch Tür öffnen?
    if ($validateOnly) {
        // Nur Validierung ohne Türöffnung
        $response['success'] = true;
        $response['message'] = 'Token ist gültig';
        $response['guest_name'] = $guestData['guest_name'] ?? 'Unbekannt';
        $response['valid_until'] = isset($guestData['expires']) ? date('d.m.Y', $guestData['expires']) : 'Unbekannt';
        
        // Token-Validierung loggen (ohne Türöffnung)
        try {
            logGuestAccess("Token validiert", [
                'ip' => $clientIp,
                'guest_name' => $guestData['guest_name'] ?? 'unknown',
                'expires' => isset($guestData['expires']) ? date('Y-m-d H:i:s', $guestData['expires']) : 'unknown'
            ]);
        } catch (Exception $e) {
            // Logging-Fehler nicht kritisch
        }
        
        http_response_code(200);
    } else {
        // Haustür öffnen - mit besserer Fehlerbehandlung
        try {
            // Prüfe ob alle erforderlichen Umgebungsvariablen gesetzt sind
            $requiredEnvVars = ['PI_SERVICE_URL', 'PI_API_USERNAME', 'PI_API_PASSWORD', 'CF_ACCESS_CLIENT_ID', 'CF_ACCESS_CLIENT_SECRET'];
            $missingVars = [];
            
            foreach ($requiredEnvVars as $var) {
                if (empty(env($var))) {
                    $missingVars[] = $var;
                }
            }
            
            if (!empty($missingVars)) {
                $response['error'] = 'Haustür-Konfiguration unvollständig. Fehlende Variablen: ' . implode(', ', $missingVars);
                http_response_code(500);
                debugLog('Missing environment variables for door opening', ['missing' => $missingVars]);
            } else {
                // Prüfe ob cURL verfügbar ist
                if (!function_exists('curl_init')) {
                    $response['error'] = 'cURL nicht verfügbar. Türöffnung nicht möglich.';
                    http_response_code(500);
                    debugLog('cURL not available for door opening');
                } else {
                    $doorResult = openHouseDoor();
                    
                    if ($doorResult['success']) {
                        $response['success'] = true;
                        $response['message'] = 'Haustür wird geöffnet... Bitte warten Sie bis zu 10 Sekunden.';
                        $response['guest_name'] = $guestData['guest_name'] ?? 'Unbekannt';
                        $response['valid_until'] = isset($guestData['expires']) ? date('d.m.Y', $guestData['expires']) : 'Unbekannt';
                        
                        // Erfolgreichen Zugang loggen
                        try {
                            logGuestAccess("Haustür geöffnet", [
                                'ip' => $clientIp,
                                'guest_name' => $guestData['guest_name'] ?? 'unknown',
                                'expires' => isset($guestData['expires']) ? date('Y-m-d H:i:s', $guestData['expires']) : 'unknown'
                            ]);
                        } catch (Exception $e) {
                            // Logging-Fehler nicht kritisch
                        }
                        
                        // Cloudflare Worker Log (falls verwendet)
                        try {
                            sendLogToCloudflare($guestData['guest_name'] ?? 'unknown', "Haustür");
                        } catch (Exception $e) {
                            // Cloudflare-Logging-Fehler nicht kritisch
                        }
                        
                        http_response_code(200);
                    } else {
                        $response['error'] = 'Türöffnung fehlgeschlagen: ' . $doorResult['message'];
                        $response['debug_info'] = $doorResult;
                        http_response_code(500);
                        
                        try {
                            logGuestAccess("Türöffnung fehlgeschlagen", [
                                'ip' => $clientIp,
                                'guest' => $guestData['guest_name'] ?? 'unknown',
                                'error' => $doorResult['message']
                            ]);
                        } catch (Exception $e) {
                            // Logging-Fehler nicht kritisch
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $response['error'] = 'Fehler bei der Türöffnung: ' . $e->getMessage();
            http_response_code(500);
            debugLog('Door opening exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }
    
} catch (Exception $e) {
    $response['error'] = 'Systemfehler bei der Token-Verarbeitung: ' . $e->getMessage();
    $response['debug_trace'] = $e->getTraceAsString();
    http_response_code(500);
    debugLog('Gast-Zugang Exception', ['error' => $e->getMessage(), 'ip' => $clientIp, 'trace' => $e->getTraceAsString()]);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>