<?php
/**
 * Split-Flap Display — Control Panel
 * Manages configuration for the split-flap display board.
 * Uses Open-Meteo Geocoding API (free, no key) for location search.
 */

$configFile = __DIR__ . '/config.json';

$defaults = [
    'mode'          => 'message',
    'message'       => 'HELLO WORLD',
    'weather'       => [
        'latitude'      => 40.7128,
        'longitude'     => -74.0060,
        'location_name' => 'New York, NY',
        'units'         => 'fahrenheit',
    ],
    'theme'         => 'dark',
    'show_seconds'  => false,
    'sound_enabled' => true,
    'flap_color'    => '#e8d44d',
    'rows'          => 3,
    'cols'          => 22,
];

if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    if (!is_array($config)) {
        $config = $defaults;
    }
    if (($config['mode'] ?? '') === 'weather') {
        $config['mode'] = 'dashboard';
    }
    $config = array_replace_recursive($defaults, $config);
} else {
    $config = $defaults;
}

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode  = isset($_POST['mode']) && in_array($_POST['mode'], ['message', 'dashboard']) ? $_POST['mode'] : 'message';
    $theme = isset($_POST['theme']) && in_array($_POST['theme'], ['dark', 'light']) ? $_POST['theme'] : 'dark';

    $message = isset($_POST['message']) ? strtoupper(trim($_POST['message'])) : '';
    $message = preg_replace('/[^A-Z0-9 \n.,!?\-:\/@#&%]/', '', $message);

    $rows          = isset($_POST['rows']) ? max(1, min(10, intval($_POST['rows']))) : 3;
    $cols          = isset($_POST['cols']) ? max(10, min(60, intval($_POST['cols']))) : 22;
    $show_seconds  = isset($_POST['show_seconds']) && $_POST['show_seconds'] === '1';
    $sound_enabled = isset($_POST['sound_enabled']) && $_POST['sound_enabled'] === '1';
    $flap_color    = isset($_POST['flap_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['flap_color']) ? $_POST['flap_color'] : '#e8d44d';

    $latitude      = isset($_POST['latitude']) ? floatval($_POST['latitude']) : $config['weather']['latitude'];
    $longitude     = isset($_POST['longitude']) ? floatval($_POST['longitude']) : $config['weather']['longitude'];
    $location_name = isset($_POST['location_name']) ? trim($_POST['location_name']) : $config['weather']['location_name'];
    $units         = isset($_POST['units']) && in_array($_POST['units'], ['fahrenheit', 'celsius']) ? $_POST['units'] : 'fahrenheit';

    if ($mode === 'dashboard') {
        if ($latitude < -90 || $latitude > 90) {
            $error = 'Latitude must be between -90 and 90.';
        } elseif ($longitude < -180 || $longitude > 180) {
            $error = 'Longitude must be between -180 and 180.';
        }
    }

    if (empty($error)) {
        $config = [
            'mode'          => $mode,
            'message'       => $message,
            'weather'       => [
                'latitude'      => $latitude,
                'longitude'     => $longitude,
                'location_name' => $location_name,
                'units'         => $units,
            ],
            'theme'         => $theme,
            'show_seconds'  => $show_seconds,
            'sound_enabled' => $sound_enabled,
            'flap_color'    => $flap_color,
            'rows'          => $rows,
            'cols'          => $cols,
        ];

        $written = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($written === false) {
            $error = 'Failed to write config.json — check file permissions.';
        } else {
            $saved = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Split-Flap Control Panel</title>
    <style>
        :root {
            --bg: #0f0f1a;
            --surface: #181828;
            --surface-raised: #1e1e32;
            --border: #2a2a4a;
            --accent: #e94560;
            --accent-glow: rgba(233, 69, 96, 0.25);
            --text: #e8e8f0;
            --text-muted: #8888aa;
            --input-bg: #12122a;
            --input-border: #2a2a4a;
            --success: #4ecca3;
            --error: #e94560;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px;
            background-image: radial-gradient(ellipse at 50% 0%, rgba(233,69,96,0.06) 0%, transparent 60%);
        }
        .container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 44px;
            width: 100%;
            max-width: 660px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.03) inset;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 700;
        }
        h1 .logo {
            background: linear-gradient(135deg, var(--accent), #c73a52);
            color: #fff;
            font-family: 'Courier New', monospace;
            font-weight: 900;
            font-size: 0.8rem;
            padding: 7px 11px;
            border-radius: 8px;
            letter-spacing: 3px;
            box-shadow: 0 2px 12px var(--accent-glow);
        }
        .subtitle { color: var(--text-muted); margin-bottom: 32px; font-size: 0.88rem; }
        .alert {
            padding: 13px 18px; border-radius: 10px; margin-bottom: 22px;
            font-size: 0.88rem; font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: rgba(78,204,163,0.1); color: var(--success); border: 1px solid rgba(78,204,163,0.25); }
        .alert-error   { background: rgba(233,69,96,0.1); color: var(--error); border: 1px solid rgba(233,69,96,0.25); }
        fieldset {
            border: 1px solid var(--border); border-radius: 14px;
            padding: 22px; margin-bottom: 18px;
            background: var(--surface-raised); transition: border-color 0.3s;
        }
        fieldset:focus-within { border-color: rgba(233,69,96,0.4); }
        legend { font-weight: 600; font-size: 0.9rem; padding: 0 10px; color: var(--accent); letter-spacing: 0.3px; }
        label {
            display: block; font-size: 0.82rem; color: var(--text-muted);
            margin-bottom: 6px; margin-top: 16px; font-weight: 500; letter-spacing: 0.2px;
        }
        label:first-of-type { margin-top: 0; }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%; padding: 11px 14px;
            border: 1px solid var(--input-border); border-radius: 10px;
            background: var(--input-bg); color: var(--text);
            font-size: 0.92rem; outline: none;
            transition: border-color 0.25s, box-shadow 0.25s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow);
        }
        textarea {
            resize: vertical; min-height: 80px;
            font-family: 'Courier New', monospace; text-transform: uppercase;
        }
        .radio-group { display: flex; gap: 0; margin-top: 6px; }
        .radio-group label { flex: 1; margin: 0; }
        .radio-group input[type="radio"] { display: none; }
        .radio-group .radio-btn {
            display: block; text-align: center; padding: 11px 6px;
            border: 1px solid var(--input-border); cursor: pointer;
            font-size: 0.85rem; font-weight: 500; color: var(--text-muted);
            transition: all 0.25s; background: var(--input-bg);
        }
        .radio-group label:first-child .radio-btn { border-radius: 10px 0 0 10px; }
        .radio-group label:last-child .radio-btn { border-radius: 0 10px 10px 0; border-left: none; }
        .radio-group label:not(:first-child):not(:last-child) .radio-btn { border-left: none; }
        .radio-group input[type="radio"]:checked + .radio-btn {
            background: linear-gradient(135deg, var(--accent), #c73a52);
            border-color: var(--accent); color: #fff; font-weight: 600;
            box-shadow: 0 2px 12px var(--accent-glow);
        }
        .row { display: flex; gap: 14px; }
        .row > div { flex: 1; }
        .section { display: none; }
        .section.active { display: block; }
        .actions { display: flex; gap: 12px; margin-top: 28px; }
        button, .btn-secondary {
            padding: 13px 26px; border: none; border-radius: 10px;
            font-size: 0.92rem; font-weight: 600; cursor: pointer; transition: all 0.25s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #c73a52);
            color: #fff; flex: 1; box-shadow: 0 4px 16px var(--accent-glow);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 24px var(--accent-glow); }
        .btn-primary:active { transform: translateY(0); }
        .btn-secondary {
            background: var(--input-bg); color: var(--text);
            border: 1px solid var(--border); text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
        .hint { font-size: 0.76rem; color: var(--text-muted); margin-top: 5px; line-height: 1.4; }
        .hint a { color: var(--accent); }

        /* ── Location Autocomplete ── */
        .location-search-wrap { position: relative; }
        .location-search-wrap input { padding-right: 36px; }
        .location-spinner {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            width: 16px; height: 16px;
            border: 2px solid var(--border); border-top-color: var(--accent);
            border-radius: 50%; animation: spin 0.6s linear infinite; display: none;
        }
        .location-spinner.active { display: block; }
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }
        .location-results {
            position: absolute; top: 100%; left: 0; right: 0;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; margin-top: 4px; max-height: 260px;
            overflow-y: auto; z-index: 100;
            box-shadow: 0 12px 40px rgba(0,0,0,0.5); display: none;
        }
        .location-results.open { display: block; }
        .location-result {
            padding: 12px 16px; cursor: pointer;
            transition: background 0.15s; border-bottom: 1px solid var(--border);
        }
        .location-result:last-child { border-bottom: none; }
        .location-result:hover, .location-result.highlighted { background: rgba(233,69,96,0.1); }
        .location-result-name { font-size: 0.92rem; font-weight: 600; color: var(--text); }
        .location-result-detail { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }
        .location-selected {
            display: flex; align-items: center; gap: 10px;
            margin-top: 10px; padding: 10px 14px;
            background: rgba(78,204,163,0.08); border: 1px solid rgba(78,204,163,0.2);
            border-radius: 10px; font-size: 0.85rem; color: var(--success);
        }
        .location-selected .coords {
            color: var(--text-muted); font-size: 0.78rem;
            margin-left: auto; font-family: 'Courier New', monospace;
        }
        .preview-box {
            margin-top: 14px; padding: 14px;
            background: #0a0a18; border: 1px solid var(--border);
            border-radius: 10px; font-family: 'Courier New', monospace;
            font-size: 0.82rem; color: #f0e060; line-height: 1.6;
            white-space: pre; overflow-x: auto;
        }

        /* ── Colour picker row ── */
        .color-row {
            display: flex; align-items: center; gap: 14px; margin-top: 6px;
        }
        .color-row input[type="color"] {
            -webkit-appearance: none;
            appearance: none;
            width: 48px; height: 40px;
            border: 2px solid var(--input-border);
            border-radius: 10px;
            background: var(--input-bg);
            cursor: pointer;
            padding: 2px;
            transition: border-color 0.25s;
        }
        .color-row input[type="color"]:hover { border-color: var(--accent); }
        .color-row input[type="color"]::-webkit-color-swatch-wrapper { padding: 2px; }
        .color-row input[type="color"]::-webkit-color-swatch { border: none; border-radius: 6px; }
        .color-row input[type="color"]::-moz-color-swatch { border: none; border-radius: 6px; }
        .color-preview {
            flex: 1; padding: 10px 14px;
            background: #1a1a1a; border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 0.95rem;
            letter-spacing: 2px; text-align: center;
            border: 1px solid #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="logo">ABCD</span> Split-Flap Display</h1>
        <p class="subtitle">Configure what your split-flap board displays.</p>

        <?php if ($saved): ?>
            <div class="alert alert-success">Configuration saved successfully.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="configForm">
            <fieldset>
                <legend>Display Mode</legend>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="mode" value="message" <?= $config['mode'] === 'message' ? 'checked' : '' ?>>
                        <span class="radio-btn">Custom Message</span>
                    </label>
                    <label>
                        <input type="radio" name="mode" value="dashboard" <?= $config['mode'] === 'dashboard' ? 'checked' : '' ?>>
                        <span class="radio-btn">Dashboard</span>
                    </label>
                </div>
            </fieldset>

            <!-- Custom Message -->
            <fieldset class="section" id="section-message">
                <legend>Custom Message</legend>
                <label for="message">Message Text</label>
                <textarea name="message" id="message" maxlength="500" placeholder="HELLO WORLD"><?= htmlspecialchars($config['message']) ?></textarea>
                <p class="hint">Uppercase letters, numbers, and basic punctuation. Use Enter for multiple lines — each line maps to a display row.</p>
            </fieldset>

            <!-- Dashboard -->
            <fieldset class="section" id="section-dashboard">
                <legend>Dashboard — Weather &amp; Date/Time</legend>
                <p class="hint" style="margin-bottom:12px;">The dashboard shows the current date, time, temperature, and weather conditions — all on one board. Select your location below.</p>

                <label for="location_search">Search Location</label>
                <div class="location-search-wrap">
                    <input type="text" id="location_search" placeholder="Start typing a city name..." autocomplete="off">
                    <div class="location-spinner" id="locationSpinner"></div>
                    <div class="location-results" id="locationResults"></div>
                </div>
                <p class="hint">Powered by Open-Meteo Geocoding — free, no API key required.</p>

                <div class="location-selected" id="locationSelected" style="<?= empty($config['weather']['location_name']) ? 'display:none' : '' ?>">
                    <span id="selectedName"><?= htmlspecialchars($config['weather']['location_name']) ?></span>
                    <span class="coords" id="selectedCoords"><?= $config['weather']['latitude'] ?>, <?= $config['weather']['longitude'] ?></span>
                </div>

                <input type="hidden" name="location_name" id="location_name" value="<?= htmlspecialchars($config['weather']['location_name']) ?>">
                <input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($config['weather']['latitude']) ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars($config['weather']['longitude']) ?>">

                <label for="units">Temperature Units</label>
                <select name="units" id="units">
                    <option value="fahrenheit" <?= $config['weather']['units'] === 'fahrenheit' ? 'selected' : '' ?>>Fahrenheit (&deg;F)</option>
                    <option value="celsius" <?= $config['weather']['units'] === 'celsius' ? 'selected' : '' ?>>Celsius (&deg;C)</option>
                </select>

                <div class="preview-box" id="dashboardPreview">Loading preview...</div>
                <p class="hint">Preview of how the dashboard will appear on the board.</p>
            </fieldset>

            <!-- Appearance -->
            <fieldset>
                <legend>Appearance</legend>

                <label>Theme</label>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="theme" value="dark" <?= $config['theme'] === 'dark' ? 'checked' : '' ?>>
                        <span class="radio-btn">Dark</span>
                    </label>
                    <label>
                        <input type="radio" name="theme" value="light" <?= $config['theme'] === 'light' ? 'checked' : '' ?>>
                        <span class="radio-btn">Light</span>
                    </label>
                </div>

                <label>Flap Text Colour</label>
                <div class="color-row">
                    <input type="color" name="flap_color" id="flap_color" value="<?= htmlspecialchars($config['flap_color'] ?? '#e8d44d') ?>">
                    <div class="color-preview" id="colorPreview" style="color: <?= htmlspecialchars($config['flap_color'] ?? '#e8d44d') ?>">SPLIT-FLAP</div>
                </div>
                <p class="hint">Choose the character colour for the split-flap tiles.</p>

                <label>Flap Sounds</label>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="sound_enabled" value="1" <?= !empty($config['sound_enabled']) ? 'checked' : '' ?>>
                        <span class="radio-btn">On</span>
                    </label>
                    <label>
                        <input type="radio" name="sound_enabled" value="0" <?= empty($config['sound_enabled']) ? 'checked' : '' ?>>
                        <span class="radio-btn">Off</span>
                    </label>
                </div>
                <p class="hint">Toggle the mechanical click audio on the display.</p>

                <label>Seconds Ticker</label>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="show_seconds" value="1" <?= !empty($config['show_seconds']) ? 'checked' : '' ?>>
                        <span class="radio-btn">On</span>
                    </label>
                    <label>
                        <input type="radio" name="show_seconds" value="0" <?= empty($config['show_seconds']) ? 'checked' : '' ?>>
                        <span class="radio-btn">Off</span>
                    </label>
                </div>
                <p class="hint">When enabled, the clock displays split-flap seconds digits with tick audio.</p>

                <div class="row" style="margin-top:16px;">
                    <div>
                        <label for="rows">Rows</label>
                        <input type="number" name="rows" id="rows" min="1" max="10" value="<?= intval($config['rows']) ?>">
                    </div>
                    <div>
                        <label for="cols">Columns</label>
                        <input type="number" name="cols" id="cols" min="10" max="60" value="<?= intval($config['cols']) ?>">
                    </div>
                </div>
                <p class="hint">Grid size for the board. Dashboard mode works best with 3+ rows and 20+ columns.</p>
            </fieldset>

            <div class="actions">
                <button type="submit" class="btn-primary">Save Configuration</button>
                <a href="splitflap.php" target="_blank" class="btn-secondary">Open Display</a>
            </div>
        </form>
    </div>

    <script>
    (function() {
        'use strict';

        // ── Mode toggle ──
        function updateSections() {
            const mode = document.querySelector('input[name="mode"]:checked').value;
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            const target = document.getElementById('section-' + mode);
            if (target) target.classList.add('active');
            if (mode === 'dashboard') updatePreview();
        }
        document.querySelectorAll('input[name="mode"]').forEach(r => r.addEventListener('change', updateSections));
        updateSections();

        // ── Colour picker live preview ──
        const colorInput = document.getElementById('flap_color');
        const colorPreview = document.getElementById('colorPreview');
        colorInput.addEventListener('input', function() {
            colorPreview.style.color = this.value;
        });

        // ── Dashboard preview ──
        function updatePreview() {
            const preview = document.getElementById('dashboardPreview');
            if (!preview) return;
            const cols = parseInt(document.getElementById('cols').value) || 22;
            const loc = document.getElementById('location_name').value || 'NEW YORK, NY';
            const unit = document.getElementById('units').value;
            const unitLabel = unit === 'celsius' ? 'C' : 'F';
            const sampleTemp = unit === 'celsius' ? '22' : '72';
            const now = new Date();
            const days = ['SUN','MON','TUE','WED','THU','FRI','SAT'];
            const months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
            const day = days[now.getDay()];
            const mon = months[now.getMonth()];
            const date = now.getDate();
            const hr = now.getHours();
            const mn = String(now.getMinutes()).padStart(2,'0');
            const ampm = hr >= 12 ? 'PM' : 'AM';
            const h12 = hr % 12 || 12;

            const line1 = (day + ' ' + mon + ' ' + date + '  ' + h12 + ':' + mn + ' ' + ampm).substring(0, cols).padEnd(cols);
            const line2 = loc.toUpperCase().substring(0, cols).padEnd(cols);
            const line3 = (sampleTemp + '*' + unitLabel + '  PARTLY CLOUDY').substring(0, cols).padEnd(cols);

            preview.textContent = line1 + '\n' + line2 + '\n' + line3;
        }
        setInterval(updatePreview, 1000);
        document.getElementById('cols').addEventListener('input', updatePreview);
        document.getElementById('units').addEventListener('change', updatePreview);

        // ── Location autocomplete ──
        const searchInput = document.getElementById('location_search');
        const resultsBox = document.getElementById('locationResults');
        const spinner = document.getElementById('locationSpinner');
        const selectedBox = document.getElementById('locationSelected');
        const selectedName = document.getElementById('selectedName');
        const selectedCoords = document.getElementById('selectedCoords');
        const hiddenName = document.getElementById('location_name');
        const hiddenLat = document.getElementById('latitude');
        const hiddenLon = document.getElementById('longitude');

        let debounceTimer = null;
        let highlightIdx = -1;
        let currentResults = [];

        searchInput.addEventListener('input', function() {
            const q = this.value.trim();
            clearTimeout(debounceTimer);
            highlightIdx = -1;
            if (q.length < 2) {
                resultsBox.classList.remove('open'); resultsBox.innerHTML = '';
                spinner.classList.remove('active'); return;
            }
            spinner.classList.add('active');
            debounceTimer = setTimeout(() => {
                fetch('https://geocoding-api.open-meteo.com/v1/search?name=' + encodeURIComponent(q) + '&count=8&language=en&format=json')
                    .then(r => r.json())
                    .then(data => { spinner.classList.remove('active'); currentResults = data.results || []; renderResults(); })
                    .catch(() => { spinner.classList.remove('active'); resultsBox.classList.remove('open'); });
            }, 300);
        });

        function renderResults() {
            if (currentResults.length === 0) {
                resultsBox.innerHTML = '<div style="padding:14px 16px;color:var(--text-muted);font-size:0.88rem;">No locations found.</div>';
                resultsBox.classList.add('open'); return;
            }
            resultsBox.innerHTML = currentResults.map((loc, i) => {
                const detail = [loc.admin1||'', loc.country||''].filter(Boolean).join(', ');
                const cls = i === highlightIdx ? 'location-result highlighted' : 'location-result';
                return '<div class="'+cls+'" data-index="'+i+'"><div class="location-result-name">'+escapeHtml(loc.name||'')+'</div><div class="location-result-detail">'+escapeHtml(detail)+' &mdash; '+loc.latitude.toFixed(4)+', '+loc.longitude.toFixed(4)+'</div></div>';
            }).join('');
            resultsBox.classList.add('open');
            resultsBox.querySelectorAll('.location-result').forEach(el => {
                el.addEventListener('click', function() { selectResult(parseInt(this.dataset.index)); });
            });
        }

        function selectResult(idx) {
            const loc = currentResults[idx]; if (!loc) return;
            const displayName = loc.name + (loc.admin1 ? ', '+loc.admin1 : '') + (loc.country ? ', '+loc.country : '');
            hiddenName.value = displayName; hiddenLat.value = loc.latitude; hiddenLon.value = loc.longitude;
            selectedName.textContent = displayName;
            selectedCoords.textContent = loc.latitude.toFixed(4)+', '+loc.longitude.toFixed(4);
            selectedBox.style.display = 'flex';
            searchInput.value = ''; resultsBox.classList.remove('open'); resultsBox.innerHTML = '';
            currentResults = []; updatePreview();
        }

        searchInput.addEventListener('keydown', function(e) {
            if (!resultsBox.classList.contains('open') || currentResults.length === 0) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); highlightIdx = Math.min(highlightIdx+1, currentResults.length-1); renderResults(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlightIdx = Math.max(highlightIdx-1, 0); renderResults(); }
            else if (e.key === 'Enter' && highlightIdx >= 0) { e.preventDefault(); selectResult(highlightIdx); }
            else if (e.key === 'Escape') { resultsBox.classList.remove('open'); }
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.location-search-wrap')) resultsBox.classList.remove('open');
        });

        function escapeHtml(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }
    })();
    </script>
</body>
</html>
