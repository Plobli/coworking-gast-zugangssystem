<?php

/**
 * Airbnb Guest System Bootstrap
 * Minimale Konfiguration nur für Gast-Zugang
 */

// Simple environment loader (ohne externe Dependencies)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

/**
 * Helper function to get environment variables with default values
 */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

/**
 * Helper function to get required environment variables
 */
function envRequired(string $key): string
{
    $value = env($key);
    if (empty($value)) {
        throw new RuntimeException("Required environment variable '{$key}' is not set");
    }
    return $value;
}

/**
 * Helper function for environment variables with default (compatibility)
 */
function envOr(string $key, mixed $default = null): mixed
{
    return env($key, $default);
}

/**
 * Simple logging function
 */
function debugLog(string $message, array $context = []): void
{
    $logFile = __DIR__ . '/../storage/logs/debug.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}";
    if (!empty($context)) {
        $logEntry .= ' ' . json_encode($context);
    }
    file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Guest access logging
 */
function logGuestAccess(string $action, array $data = []): void
{
    $logFile = __DIR__ . '/../storage/logs/guest-access.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$action}: " . json_encode($data);
    file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Rate limiting function
 */
function checkRateLimit(string $clientId, int $maxAttempts = 20, int $timeWindow = 300): bool
{
    $rateLimitFile = __DIR__ . "/../storage/logs/rate_limit_{$clientId}.json";
    $rateLimitDir = dirname($rateLimitFile);
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }
    
    $now = time();
    $data = [];
    
    if (file_exists($rateLimitFile)) {
        $content = file_get_contents($rateLimitFile);
        $data = json_decode($content, true) ?: [];
    }
    
    // Remove old entries
    $data = array_filter($data, fn($timestamp) => ($now - $timestamp) < $timeWindow);
    
    // Check if limit exceeded
    if (count($data) >= $maxAttempts) {
        return false;
    }
    
    // Add current attempt
    $data[] = $now;
    file_put_contents($rateLimitFile, json_encode($data), LOCK_EX);
    
    return true;
}

/**
 * Generate guest access token
 */
function generateGuestToken(string $guestName, int $expiryTimestamp): string
{
    $key = base64_decode(envRequired('GUEST_ACCESS_ENCRYPTION_KEY'));
    
    // Compact payload for shorter URLs
    $payload = [
        'type' => 'guest_access',
        'guest_name' => $guestName,
        'expires' => $expiryTimestamp,
        'generated' => time()
    ];
    
    $plaintext = json_encode($payload);
    $nonce = random_bytes(12);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    
    if ($ciphertext === false) {
        throw new RuntimeException('Token encryption failed');
    }
    
    // URL-safe Base64 encoding for shorter tokens
    return rtrim(strtr(base64_encode($nonce . $tag . $ciphertext), '+/', '-_'), '=');
}

/**
 * Validate guest access token
 */
function validateGuestToken(string $token): ?array
{
    try {
        $key = base64_decode(envRequired('GUEST_ACCESS_ENCRYPTION_KEY'));
        
        // URL-safe Base64 decoding
        $token = str_pad(strtr($token, '-_', '+/'), strlen($token) % 4, '=', STR_PAD_RIGHT);
        $data = base64_decode($token);
        
        if (strlen($data) < 28) return null;
        
        $nonce = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        
        if ($plaintext === false) return null;
        
        $payload = json_decode($plaintext, true);
        return is_array($payload) ? $payload : null;
        
    } catch (Exception $e) {
        debugLog('Token validation error', ['error' => $e->getMessage()]);
        return null;
    }
}

/**
 * Open house door via Raspberry Pi API
 */
function openHouseDoor(): array
{
    try {
        $piServiceUrl = envRequired('PI_SERVICE_URL');
        $username = envRequired('PI_API_USERNAME');
        $password = envRequired('PI_API_PASSWORD');
        $cfClientId = envRequired('CF_ACCESS_CLIENT_ID');
        $cfClientSecret = envRequired('CF_ACCESS_CLIENT_SECRET');
        
        // Verwende den korrekten Endpunkt wie im Coworking-System
        $url = "{$piServiceUrl}/open-house-door";
        
        // Prüfe ob cURL verfügbar ist
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL-Extension nicht verfügbar'];
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,  // Coworking-System verwendet GET, nicht POST
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERPWD => "{$username}:{$password}",
            CURLOPT_HTTPHEADER => [
                "CF-Access-Client-Id: {$cfClientId}",
                "CF-Access-Client-Secret: {$cfClientSecret}"
            ],
            // Kein POST-Body needed für GET request
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Airbnb-Guest-System/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        // Debug-Informationen loggen
        debugLog('Door API Request', [
            'url' => $url,
            'effective_url' => $effectiveUrl,
            'http_code' => $httpCode,
            'curl_error' => $error,
            'response_length' => strlen($response ?? ''),
            'response_preview' => substr($response ?? '', 0, 200)
        ]);
        
        if ($error) {
            return ['success' => false, 'message' => "Verbindungsfehler: {$error}"];
        }
        
        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'Haustür geöffnet', 'response' => $response];
        } elseif ($httpCode === 401) {
            return ['success' => false, 'message' => "Authentifizierungsfehler (HTTP 401) - Prüfen Sie Username/Passwort"];
        } elseif ($httpCode === 403) {
            return ['success' => false, 'message' => "Zugriff verweigert (HTTP 403) - Prüfen Sie Cloudflare Access Credentials"];
        } elseif ($httpCode === 404) {
            return ['success' => false, 'message' => "API-Endpunkt nicht gefunden (HTTP 404) - Prüfen Sie die URL: {$url}"];
        } elseif ($httpCode === 405) {
            return ['success' => false, 'message' => "HTTP-Methode nicht erlaubt (HTTP 405) - POST nicht unterstützt"];
        } else {
            return ['success' => false, 'message' => "API-Fehler: HTTP {$httpCode}", 'response' => $response];
        }
        
    } catch (Exception $e) {
        debugLog('Door opening exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return ['success' => false, 'message' => "Systemfehler: " . $e->getMessage()];
    }
}

/**
 * Send log to Cloudflare Worker (optional)
 */
function sendLogToCloudflare(string $guestName, string $doorType): void
{
    // Optional logging - nur wenn URL konfiguriert ist
    $workerUrl = env('CLOUDFLARE_LOG_WORKER_URL');
    if (empty($workerUrl)) return;
    
    try {
        $data = [
            'guest_name' => $guestName,
            'door_type' => $doorType,
            'timestamp' => date('c'),
            'source' => 'airbnb-guest-system'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $workerUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);
        
        curl_exec($ch);
        curl_close($ch);
    } catch (Exception $e) {
        // Logging failures should not break the main functionality
        debugLog('Cloudflare log failed', ['error' => $e->getMessage()]);
    }
}