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
