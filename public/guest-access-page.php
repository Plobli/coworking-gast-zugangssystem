<?php
require_once __DIR__ . '/../config/bootstrap.php';

$token = trim($_GET['token'] ?? '');
$guestData = null;
$validationError = null;

if ($token === '') {
    $validationError = 'Kein Zugangs-Token angegeben.';
} else {
    $guestData = validateGuestToken($token);

    if (!$guestData || ($guestData['type'] ?? '') !== 'guest_access') {
        $validationError = 'Ungültiger oder abgelaufener Zugangs-Token.';
    } else {
        $now = time();
        if (isset($guestData['starts']) && $now < $guestData['starts']) {
            $validationError = 'Dieser Zugangs-Token ist noch nicht gültig.';
        } elseif ($now > $guestData['expires']) {
            $validationError = 'Dieser Zugangs-Token ist abgelaufen.';
        }
    }
}

$accessType = $guestData['access_type'] ?? 'house_only';
$isTeamGuest = $validationError === null && $accessType === 'house_and_coworking';

if ($validationError !== null):
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zugang</title>
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
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        .status-message {
            padding: 20px;
            border-radius: 10px;
            font-size: 16px;
            line-height: 1.5;
            background: #ffebee;
            color: #c62828;
            border: 2px solid #ffcdd2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="status-message">
            <strong>❌ Fehler</strong><br>
            <?= htmlspecialchars($validationError) ?>
        </div>
    </div>
</body>
</html>
<?php
elseif ($isTeamGuest):
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>bunte butze coworking</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="/stylesheet.css">
    <style>
        .input input:active {
            transform: scale(0.98);
            background-color: #ddd;
        }

        .input input.loading {
            animation: loadingBackground 1s infinite;
        }

        @keyframes loadingBackground {
            0% {
                background-color: rgba(131, 196, 190, 1);
            }

            50% {
                background-color: #bbb;
            }

            100% {
                background-color: rgba(131, 196, 190, 1);
            }
        }
    </style>
</head>
<body>
    <div class="logo">
        <img src="/bunte-butze-coworking-logo.png" alt="Bunte Butze Coworking Logo" style="width:100%; height:auto; max-width:150px;" />
    </div>
    <div class="container">
        <div class="header-text">
            <h1>Zugangssystem</h1>
            <h2 id="greeting">Hallo!</h2>
            <p id="subtitle">Willkommen im <strong>bunte butze coworking</strong>.</p>
            <p id="error-text" style="display: none;"></p>
        </div>

        <div id="doors" style="display: none;">
            <p>Welche Tür möchtest du öffnen?</p>
            <div class="input">
                <input type="submit" id="house-door-btn" value="Haustür" onclick="openDoor('house', this)" />
            </div>
            <div class="input" id="coworking-door-row" style="display: none;">
                <input type="submit" id="coworking-door-btn" value="CoWorkingTür" onclick="openDoor('coworking', this)" />
            </div>
            <div class="input">
                <span>Hinweis: Das Öffnen dauert bis zu 10 Sekunden.</span>
            </div>
        </div>

        <div class="input" id="message-row" style="display: none;">
            <span id="message"></span>
        </div>
    </div>

    <script>
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    const greeting = document.getElementById('greeting');
    const subtitle = document.getElementById('subtitle');
    const errorText = document.getElementById('error-text');
    const doorsBlock = document.getElementById('doors');
    const coworkingRow = document.getElementById('coworking-door-row');
    const messageRow = document.getElementById('message-row');
    const messageEl = document.getElementById('message');

    function showMessage(text) {
        messageEl.textContent = text;
        messageRow.style.display = 'flex';
    }

    function showFatalError(text) {
        subtitle.style.display = 'none';
        errorText.textContent = text;
        errorText.style.display = 'block';
    }

    if (!token) {
        showFatalError('Kein Zugangs-Token angegeben.');
    } else {
        validateToken(token);
    }

    async function validateToken(token) {
        try {
            const res = await fetch(`guest-access.php?token=${encodeURIComponent(token)}&validate_only=true`);
            const data = await res.json();

            if (!data.success) {
                showFatalError(data.error || 'Ungültiger oder abgelaufener Zugangs-Token.');
                return;
            }

            greeting.textContent = `Hallo ${data.guest_name}!`;

            if (data.access_type === 'house_and_coworking') {
                coworkingRow.style.display = 'flex';
            }
            doorsBlock.style.display = 'block';
        } catch (err) {
            showFatalError('Verbindungsfehler. Bitte versuche es erneut.');
        }
    }

    async function openDoor(door, button) {
        button.disabled = true;
        button.classList.add('loading');

        try {
            const res = await fetch(`guest-access.php?token=${encodeURIComponent(token)}&door=${door}`);
            const data = await res.json();

            if (data.success) {
                showMessage(data.message);
            } else {
                showMessage(data.error || 'Türöffnung fehlgeschlagen.');
            }
        } catch (err) {
            showMessage('Verbindungsfehler. Bitte versuche es erneut.');
        } finally {
            setTimeout(() => {
                button.disabled = false;
                button.classList.remove('loading');
            }, 3000);
        }
    }
    </script>

    <?php include __DIR__ . '/footer.inc.html'; ?>
</body>
</html>
<?php
else:
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Airbnb Gäste-Zugang</title>
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
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        .container h1 {
            color: #333;
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .container .subtitle {
            color: #495057;
            font-size: 18px;
            font-weight: 400;
            margin-bottom: 30px;
        }

        .status-message {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 16px;
            line-height: 1.5;
        }

        .status-loading {
            background: #e3f2fd;
            color: #1565c0;
            border: 2px solid #bbdefb;
        }

        .status-success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 2px solid #c8e6c9;
        }

        .status-error {
            background: #ffebee;
            color: #c62828;
            border: 2px solid #ffcdd2;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #bbdefb;
            border-radius: 50%;
            border-top-color: #1565c0;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .guest-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #495057;
            text-align: left;
        }

        .btn-reopen {
            background-color: #495057;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 20px;
        }

        .btn-reopen:hover:not(:disabled) {
            background-color: #343a40;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-reopen:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏠 Airbnb Gäste-Zugang</h1>

        <div id="status" class="status-message status-loading">
            <div class="loading-spinner"></div>
            Zugangs-Token wird überprüft...
        </div>

        <div id="guest-info" class="guest-info" style="display: none;">
            <!-- Wird via JavaScript gefüllt -->
        </div>

        <button id="open-door-btn" class="btn-reopen" style="display: none;" onclick="openDoor()">
            🔓 Haustür öffnen
        </button>

        <button id="reopen-btn" class="btn-reopen" style="display: none;" onclick="reopenDoor()">
            🔓 Tür erneut öffnen
        </button>
    </div>

    <script>
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (!token) {
        showError('Kein Zugangs-Token gefunden. Bitte verwenden Sie den vollständigen Link.');
    } else {
        validateGuestToken(token);
    }

    async function validateGuestToken(token) {
        try {
            const apiUrl = `guest-access.php?token=${encodeURIComponent(token)}&validate_only=true`;
            const response = await fetch(apiUrl);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success) {
                showTokenValid({
                    guest_name: data.guest_name,
                    valid_from: data.valid_from,
                    valid_until: data.valid_until
                });
            } else {
                showError(data.error, data);
            }

        } catch (error) {
            showError(`Verbindungsfehler: ${error.message}. Bitte versuchen Sie es erneut.`);
        }
    }

    async function processGuestAccess(token) {
        try {
            const apiUrl = `guest-access.php?token=${encodeURIComponent(token)}`;
            const response = await fetch(apiUrl);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success) {
                showSuccess(data.message, {
                    guest_name: data.guest_name,
                    valid_until: data.valid_until
                });
            } else {
                showError(data.error, data);
            }

        } catch (error) {
            showError(`Verbindungsfehler: ${error.message}. Bitte versuchen Sie es erneut.`);
        }
    }

    function showTokenValid(info = {}) {
        const statusDiv = document.getElementById('status');
        statusDiv.className = 'status-message status-success';
        statusDiv.innerHTML = `
            <strong>✅ Token gültig!</strong><br>
            Klicke auf den Button, um die Haustür zu öffnen.
        `;

        if (info.guest_name || info.valid_from || info.valid_until) {
            const infoDiv = document.getElementById('guest-info');
            infoDiv.innerHTML = '';
            if (info.guest_name) {
                infoDiv.append(Object.assign(document.createElement('strong'), { textContent: 'Gast: ' }), document.createTextNode(info.guest_name), document.createElement('br'));
            }
            if (info.valid_from) {
                infoDiv.append(Object.assign(document.createElement('strong'), { textContent: 'Gültig ab: ' }), document.createTextNode(info.valid_from), document.createElement('br'));
            }
            if (info.valid_until) {
                infoDiv.append(Object.assign(document.createElement('strong'), { textContent: 'Gültig bis: ' }), document.createTextNode(info.valid_until));
            }
            infoDiv.style.display = 'block';
        }

        const openBtn = document.getElementById('open-door-btn');
        openBtn.style.display = 'inline-block';
    }

    function showSuccess(message, info = {}) {
        const statusDiv = document.getElementById('status');
        statusDiv.className = 'status-message status-success';
        statusDiv.innerHTML = `
            <strong>✅ Erfolg!</strong><br>
            ${message}
        `;

        const openBtn = document.getElementById('open-door-btn');
        openBtn.style.display = 'none';

        const reopenBtn = document.getElementById('reopen-btn');
        reopenBtn.style.display = 'inline-block';
    }

    function showError(message, data = {}) {
        const statusDiv = document.getElementById('status');
        statusDiv.className = 'status-message status-error';
        statusDiv.innerHTML = `
            <strong>❌ Fehler</strong><br>
            ${message}
            ${data.expired_at ? `<br><br><small>Abgelaufen am: ${data.expired_at}</small>` : ''}
            ${data.valid_from ? `<br><br><small>Gültig ab: ${data.valid_from}</small>` : ''}
        `;

        const openBtn = document.getElementById('open-door-btn');
        const reopenBtn = document.getElementById('reopen-btn');
        openBtn.style.display = 'none';
        reopenBtn.style.display = 'none';
    }

    async function openDoor() {
        const openBtn = document.getElementById('open-door-btn');
        const originalText = openBtn.textContent;

        openBtn.disabled = true;
        openBtn.textContent = '⏳ Öffnet...';

        try {
            await processGuestAccess(token);
        } finally {
            setTimeout(() => {
                openBtn.disabled = false;
                openBtn.textContent = originalText;
            }, 3000);
        }
    }

    async function reopenDoor() {
        const reopenBtn = document.getElementById('reopen-btn');
        const originalText = reopenBtn.textContent;

        reopenBtn.disabled = true;
        reopenBtn.textContent = '⏳ Öffnet...';

        try {
            await processGuestAccess(token);
        } finally {
            setTimeout(() => {
                reopenBtn.disabled = false;
                reopenBtn.textContent = originalText;
            }, 3000);
        }
    }
    </script>
</body>
</html>
<?php endif; ?>
