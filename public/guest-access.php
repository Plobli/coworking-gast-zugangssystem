<?php
require_once __DIR__ . '/../config/bootstrap.php';

// CORS Headers für API-Zugriff
header('Content-Type: application/json');

// Rate Limiting - DoS Schutz
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIp, 20, 300)) { // Max 20 Versuche in 5 Minuten
    http_response_code(429);
    die(json_encode(['error' => 'Zu viele Versuche. Bitte später erneut versuchen.']));
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
        logGuestAccess("Token ungültig", ['ip' => $clientIp, 'token_preview' => substr($token, 0, 20)]);
        echo json_encode($response);
        exit;
    }
    
    // Zusätzliche Sicherheitsprüfungen
    if ($guestData['type'] !== 'guest_access') {
        $response['error'] = 'Token nicht für Haustür-Zugang gültig';
        http_response_code(403);
        logGuestAccess("Token falscher Typ", ['ip' => $clientIp, 'data' => $guestData]);
        echo json_encode($response);
        exit;
    }
    
    // Zeitgrenzen prüfen
    if (time() > $guestData['expires']) {
        $response['error'] = 'Zugangs-Token ist abgelaufen';
        $response['expired_at'] = date('d.m.Y H:i', $guestData['expires']);
        http_response_code(410);
        logGuestAccess("Token abgelaufen", ['ip' => $clientIp, 'guest' => $guestData['guest_name']]);
        echo json_encode($response);
        exit;
    }
    
    // Nur Token validieren oder auch Tür öffnen?
    if ($validateOnly) {
        // Nur Validierung ohne Türöffnung
        $response['success'] = true;
        $response['message'] = 'Token ist gültig';
        $response['guest_name'] = $guestData['guest_name'];
        $response['valid_until'] = date('d.m.Y', $guestData['expires']);
        
        // Token-Validierung loggen (ohne Türöffnung)
        logGuestAccess("Token validiert", [
            'ip' => $clientIp,
            'guest_name' => $guestData['guest_name'],
            'expires' => date('Y-m-d H:i:s', $guestData['expires'])
        ]);
        
        http_response_code(200);
    } else {
        // Haustür öffnen
        $doorResult = openHouseDoor();
        
        if ($doorResult['success']) {
            $response['success'] = true;
            $response['message'] = 'Haustür wird geöffnet... Bitte warten Sie bis zu 10 Sekunden.';
            $response['guest_name'] = $guestData['guest_name'];
            $response['valid_until'] = date('d.m.Y', $guestData['expires']);
            
            // Erfolgreichen Zugang loggen
            logGuestAccess("Haustür geöffnet", [
                'ip' => $clientIp,
                'guest_name' => $guestData['guest_name'],
                'expires' => date('Y-m-d H:i:s', $guestData['expires'])
            ]);
            
            // Cloudflare Worker Log (falls verwendet)
            sendLogToCloudflare($guestData['guest_name'], "Haustür");
            
            http_response_code(200);
        } else {
            $response['error'] = 'Türöffnung fehlgeschlagen: ' . $doorResult['message'];
            http_response_code(500);
            
            logGuestAccess("Türöffnung fehlgeschlagen", [
                'ip' => $clientIp,
                'guest' => $guestData['guest_name'],
                'error' => $doorResult['message']
            ]);
        }
    }
    
} catch (Exception $e) {
    $response['error'] = 'Systemfehler bei der Token-Verarbeitung';
    http_response_code(500);
    debugLog('Gast-Zugang Exception', ['error' => $e->getMessage(), 'ip' => $clientIp]);
}

echo json_encode($response);