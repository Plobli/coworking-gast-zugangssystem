<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Basic Auth Schutz für Admin-Bereich (kompatibel mit verschiedenen Webservern)
$adminUser = envOr('ADMIN_USERNAME', 'admin');
$adminPassword = envRequired('GUEST_ADMIN_PASSWORD');

// Basic Auth Header extrahieren (funktioniert mit Apache/Nginx + PHP-FPM)
$authUser = null;
$authPass = null;

if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    // Standard PHP Basic Auth (funktioniert mit PHP Built-in Server)
    $authUser = $_SERVER['PHP_AUTH_USER'];
    $authPass = $_SERVER['PHP_AUTH_PW'];
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    // Authorization Header parsen (Apache/Nginx mit mod_rewrite)
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (strpos($auth, 'Basic ') === 0) {
        $credentials = base64_decode(substr($auth, 6));
        if (strpos($credentials, ':') !== false) {
            list($authUser, $authPass) = explode(':', $credentials, 2);
        }
    }
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    // Fallback für Apache mit CGI/FastCGI
    $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (strpos($auth, 'Basic ') === 0) {
        $credentials = base64_decode(substr($auth, 6));
        if (strpos($credentials, ':') !== false) {
            list($authUser, $authPass) = explode(':', $credentials, 2);
        }
    }
}

// Authentifizierung prüfen
if ($authUser !== $adminUser || $authPass !== $adminPassword) {
    header('WWW-Authenticate: Basic realm="Airbnb Gäste-Zugang Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo '401 Unauthorized - Adminbereich';
    exit;
}

$generatedLink = '';
$error = '';
$success = '';

// Link generieren
if (isset($_POST['generate'])) {
    $guestName = trim($_POST['guest_name'] ?? '');
    $validUntil = trim($_POST['valid_until'] ?? '');
    
    // Validierung
    if (empty($guestName)) {
        $error = 'Name des Gastes ist erforderlich';
    } elseif (empty($validUntil)) {
        $error = 'Gültigkeitsdatum ist erforderlich';
    } else {
        // Datum parsen (Format: YYYY-MM-DD)
        $expiryTimestamp = strtotime($validUntil . ' 23:59:59');
        if ($expiryTimestamp === false || $expiryTimestamp <= time()) {
            $error = 'Ungültiges Datum oder Datum liegt in der Vergangenheit';
        } else {
            try {
                $guestToken = generateGuestToken($guestName, $expiryTimestamp);
                $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? envRequired('APP_DOMAIN'));
                $generatedLink = $baseUrl . '/guest-access.html?token=' . urlencode($guestToken);
                
                $expiresAt = date('d.m.Y', $expiryTimestamp);
                $success = "Link erfolgreich generiert! Gültig bis: $expiresAt (23:59)";
                
                // Admin-Aktion loggen
                logGuestAccess("Admin: Link generiert", [
                    'guest_name' => $guestName,
                    'expires' => date('Y-m-d H:i:s', $expiryTimestamp),
                    'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
            } catch (Exception $e) {
                $error = 'Fehler beim Generieren des Links: ' . $e->getMessage();
                debugLog('Guest token generation failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Airbnb Gäste-Zugang - Admin</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .admin-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .admin-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .admin-header p {
            color: #666;
            font-size: 16px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        input[type="text"], input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input[type="text"]:focus, input[type="date"]:focus {
            outline: none;
            border-color: #495057;
            box-shadow: 0 0 0 3px rgba(73, 80, 87, 0.1);
        }
        
        .btn {
            background-color: #495057;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s ease, transform 0.1s ease;
        }
        
        .btn:hover {
            background-color: #343a40;
            transform: translateY(-1px);
        }
        
        .generated-link {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .generated-link h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .link-display {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            word-break: break-all;
            font-family: monospace;
            font-size: 14px;
            color: #495057;
            margin-bottom: 15px;
        }
        
        .copy-btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .copy-btn:hover {
            background: #218838;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Admin-Interface -->
        <div class="admin-header">
            <h1>🏠 Airbnb Gäste-Zugang</h1>
            <p>Zeitbegrenzte Links für Haustür-Zugang</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="guest_name">Name des Gastes</label>
                <input type="text" id="guest_name" name="guest_name" 
                       value="<?= htmlspecialchars($_POST['guest_name'] ?? '') ?>" 
                       placeholder="z.B. Max Mustermann" required>
            </div>
            
            <div class="form-group">
                <label for="valid_until">Link gültig bis (Datum)</label>
                <input type="date" id="valid_until" name="valid_until" 
                       value="<?= htmlspecialchars($_POST['valid_until'] ?? '') ?>" 
                       min="<?= date('Y-m-d') ?>" required>
            </div>
            
            <button type="submit" name="generate" class="btn">
                🔗 Gäste-Link generieren
            </button>
        </form>
        
        <?php if ($generatedLink): ?>
        <div class="generated-link">
            <h3>✅ Generierter Gäste-Link:</h3>
            <div class="link-display" id="generatedLink">
                <?= htmlspecialchars($generatedLink) ?>
            </div>
            <button class="copy-btn" onclick="copyToClipboard()">
                📋 Link kopieren
            </button>
        </div>
        <?php endif; ?>
        
        <div class="footer">
        </div>
    </div>
    
    <script>
    function copyToClipboard() {
        const linkElement = document.getElementById('generatedLink');
        const text = linkElement.textContent;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Link wurde in die Zwischenablage kopiert!');
            }).catch(err => {
                console.error('Fehler beim Kopieren:', err);
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }
    
    function fallbackCopy(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            alert('Link wurde in die Zwischenablage kopiert!');
        } catch (err) {
            console.error('Fallback copy failed:', err);
            alert('Bitte kopieren Sie den Link manuell.');
        }
        document.body.removeChild(textArea);
    }
    
    // Set default date to tomorrow
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.getElementById('valid_until');
        if (!dateInput.value) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            dateInput.value = tomorrow.toISOString().split('T')[0];
        }
    });
    </script>
</body>
</html>