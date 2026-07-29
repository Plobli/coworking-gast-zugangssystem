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

// Link löschen
if (isset($_POST['delete_token'])) {
    $tokenId = trim($_POST['token_id'] ?? '');
    if ($tokenId !== '') {
        $db = new TokenDatabase();
        if ($db->deleteToken($tokenId)) {
            $success = 'Link wurde gelöscht.';
            logGuestAccess("Admin: Link gelöscht", [
                'token_id' => $tokenId,
                'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
    }
}

// Link generieren
if (isset($_POST['generate'])) {
    $guestName = trim($_POST['guest_name'] ?? '');
    $validFrom = trim($_POST['valid_from'] ?? '');
    $validUntil = trim($_POST['valid_until'] ?? '');
    $accessType = ($_POST['access_type'] ?? '') === 'house_and_coworking' ? 'house_and_coworking' : 'house_only';

    // Validierung
    if (empty($guestName)) {
        $error = 'Name des Gastes ist erforderlich';
    } elseif (empty($validFrom)) {
        $error = 'Startdatum ist erforderlich';
    } elseif (empty($validUntil)) {
        $error = 'Enddatum ist erforderlich';
    } else {
        // Datums parsen (Format: YYYY-MM-DD)
        $startTimestamp = strtotime($validFrom . ' 00:00:00');
        $expiryTimestamp = strtotime($validUntil . ' 23:59:59');
        
        if ($startTimestamp === false) {
            $error = 'Ungültiges Startdatum';
        } elseif ($expiryTimestamp === false) {
            $error = 'Ungültiges Enddatum';
        } elseif ($startTimestamp >= $expiryTimestamp) {
            $error = 'Startdatum muss vor dem Enddatum liegen';
        } else {
            try {
                $guestToken = generateGuestToken($guestName, $startTimestamp, $expiryTimestamp, $accessType);
                $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? envRequired('APP_DOMAIN'));
                $generatedLink = $baseUrl . '/guest-access-page.php?token=' . urlencode($guestToken);

                $startsAt = date('d.m.Y', $startTimestamp);
                $expiresAt = date('d.m.Y', $expiryTimestamp);
                $accessLabel = $accessType === 'house_and_coworking' ? 'Haustür + Coworking-Tür' : 'nur Haustür';
                $success = "Link erfolgreich generiert! Zugang: $accessLabel. Gültig von: $startsAt (00:00) bis: $expiresAt (23:59)";

                // Admin-Aktion loggen
                logGuestAccess("Admin: Link generiert", [
                    'guest_name' => $guestName,
                    'starts' => date('Y-m-d H:i:s', $startTimestamp),
                    'expires' => date('Y-m-d H:i:s', $expiryTimestamp),
                    'access_type' => $accessType,
                    'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
            } catch (Exception $e) {
                $error = 'Fehler beim Generieren des Links: ' . $e->getMessage();
                debugLog('Guest token generation failed', ['error' => $e->getMessage()]);
            }
        }
    }
}

// Übersicht: Links in Kategorien einteilen
$now = time();
$recentThreshold = $now - 7 * 86400;
$db = new TokenDatabase();
$allTokens = $db->listTokens();

$activeLinks = [];
$upcomingLinks = [];
$expiredLinks = [];

foreach ($allTokens as $t) {
    if ($t['starts'] <= $now && $t['expires'] >= $now) {
        $activeLinks[] = $t;
    } elseif ($t['starts'] > $now) {
        $upcomingLinks[] = $t;
    } elseif ($t['expires'] < $now && $t['expires'] >= $recentThreshold) {
        $expiredLinks[] = $t;
    }
}

usort($activeLinks, fn($a, $b) => $a['expires'] <=> $b['expires']);
usort($upcomingLinks, fn($a, $b) => $a['starts'] <=> $b['starts']);
usort($expiredLinks, fn($a, $b) => $b['expires'] <=> $a['expires']);

function accessTypeLabel(string $accessType): string
{
    return $accessType === 'house_and_coworking' ? 'Haustür + Coworking-Tür' : 'nur Haustür';
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
            max-width: 900px;
            width: 100%;
        }

        form:not(.delete-form) {
            max-width: 500px;
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

        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            font-weight: 400;
        }

        .radio-option:has(input:checked) {
            border-color: #495057;
            background: #f8f9fa;
        }

        .radio-option input[type="radio"] {
            width: auto;
            cursor: pointer;
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

        .overview {
            margin-top: 40px;
            overflow-x: auto;
        }

        .overview h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 12px;
            margin-top: 30px;
        }

        .overview h2:first-child {
            margin-top: 0;
        }

        .link-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .link-table th, .link-table td {
            text-align: left;
            padding: 10px 8px;
            border-bottom: 1px solid #e9ecef;
        }

        .link-table th {
            color: #666;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }

        .empty-hint {
            color: #666;
            font-size: 14px;
            padding: 8px 0 0;
        }

        .delete-form {
            display: inline;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }

        .delete-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Admin-Interface -->
        <div class="admin-header">
            <h1>🏠 Gäste-Zugang</h1>
            <p>Zeitbegrenzte Links für Airbnb- und Team-Gäste</p>
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
                <label>Zugangsart</label>
                <label class="radio-option">
                    <input type="radio" name="access_type" value="house_only"
                           <?= ($_POST['access_type'] ?? 'house_only') === 'house_only' ? 'checked' : '' ?>>
                    Nur Haustür (Airbnb-Gast)
                </label>
                <label class="radio-option">
                    <input type="radio" name="access_type" value="house_and_coworking"
                           <?= ($_POST['access_type'] ?? '') === 'house_and_coworking' ? 'checked' : '' ?>>
                    Haustür + Coworking-Tür (Team-Gast)
                </label>
            </div>

            <div class="form-group">
                <label for="valid_from">Link gültig ab (Datum)</label>
                <input type="date" id="valid_from" name="valid_from" 
                       value="<?= htmlspecialchars($_POST['valid_from'] ?? date('Y-m-d')) ?>" 
                       min="<?= date('Y-m-d') ?>" required>
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

        <div class="overview">
            <?php
            function renderLinkTable(array $links, string $emptyText): void
            {
                if (empty($links)) {
                    echo '<p class="empty-hint">' . htmlspecialchars($emptyText) . '</p>';
                    return;
                }
                ?>
                <table class="link-table">
                    <thead>
                        <tr>
                            <th>Gast</th>
                            <th>Zugang</th>
                            <th>Gültig von</th>
                            <th>Gültig bis</th>
                            <th>Nutzungen</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                        <tr>
                            <td><?= htmlspecialchars($link['guest_name']) ?></td>
                            <td><?= htmlspecialchars(accessTypeLabel($link['access_type'] ?? 'house_only')) ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y', $link['starts'])) ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y', $link['expires'])) ?></td>
                            <td><?= (int)($link['used_count'] ?? 0) ?></td>
                            <td>
                                <form class="delete-form" method="post" onsubmit="return confirm('Diesen Link wirklich löschen?');">
                                    <input type="hidden" name="token_id" value="<?= htmlspecialchars($link['id']) ?>">
                                    <button type="submit" name="delete_token" class="delete-btn">Löschen</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            }
            ?>

            <h2>Aktive Links</h2>
            <?php renderLinkTable($activeLinks, 'Keine aktiven Links.'); ?>

            <h2>Zukünftig aktive Links</h2>
            <?php renderLinkTable($upcomingLinks, 'Keine zukünftig aktiven Links.'); ?>

            <h2>Kürzlich abgelaufene Links (letzte 7 Tage)</h2>
            <?php renderLinkTable($expiredLinks, 'Keine kürzlich abgelaufenen Links.'); ?>
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