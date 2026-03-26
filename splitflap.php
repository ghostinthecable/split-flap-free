<?php
/**
 * Split-Flap Display — Board View
 * Full-screen split-flap board with smooth animations, per-tile click audio,
 * auto-refresh every 60s, system clock. Optional seconds ticker on the board.
 */

require_once __DIR__ . '/display.php';

$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : null;

if (!is_array($config)) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><body style="background:#0a0a0f;color:#e94560;font-family:monospace;padding:40px"><h1>Config not found</h1><p>Visit <a href="index.php" style="color:#4ecca3">the control panel</a> to set up your display.</p></body></html>';
    exit;
}

$theme        = ($config['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
$rows         = max(1, min(10, intval($config['rows'] ?? 3)));
$cols         = max(10, min(60, intval($config['cols'] ?? 22)));
$showSeconds  = !empty($config['show_seconds']);
$soundEnabled = isset($config['sound_enabled']) ? (bool)$config['sound_enabled'] : true;
$flapColor    = preg_match('/^#[0-9a-fA-F]{6}$/', $config['flap_color'] ?? '') ? $config['flap_color'] : '#e8d44d';
$mode         = $config['mode'] ?? 'message';

$text = buildDisplayText($config);
$grid = buildGrid($text, $rows, $cols);
$gridJson = json_encode($grid);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Split-Flap Display</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        :root { --flap-text: <?= $flapColor ?>; }

        [data-theme="dark"] {
            --board-bg: #0a0a0f; --frame-bg: #141418; --frame-border: #222228;
            --flap-top: #2e2e2e; --flap-bot: #262626;
            --flap-border: #1a1a1a; --flap-shadow: rgba(0,0,0,0.7);
            --gap-line: rgba(0,0,0,0.9); --rivet: #3a3a3a;
            --clock-color: rgba(200,200,200,0.35);
            --vignette: radial-gradient(ellipse at center, transparent 50%, rgba(0,0,0,0.5) 100%);
        }
        [data-theme="light"] {
            --board-bg: #c5c1b5; --frame-bg: #d8d4c8; --frame-border: #b0ac9c;
            --flap-top: #f4f0e4; --flap-bot: #e8e4d4;
            --flap-border: #c0bcac; --flap-shadow: rgba(0,0,0,0.1);
            --gap-line: rgba(0,0,0,0.18); --rivet: #a8a498;
            --clock-color: rgba(26,26,24,0.35);
            --vignette: radial-gradient(ellipse at center, transparent 60%, rgba(0,0,0,0.06) 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; height: 100%; overflow: hidden; }

        body {
            background: var(--board-bg);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Share Tech Mono', 'Courier New', monospace;
        }
        body::after {
            content: ''; position: fixed; inset: 0;
            background: var(--vignette); pointer-events: none; z-index: 1000;
        }

        #board {
            display: flex; flex-direction: column; gap: 5px;
            padding: 22px 26px; background: var(--frame-bg);
            border: 2px solid var(--frame-border); border-radius: 8px;
            box-shadow: 0 16px 60px var(--flap-shadow), 0 2px 6px var(--flap-shadow),
                        inset 0 1px 0 rgba(255,255,255,0.03);
            z-index: 1;
        }
        .flap-row { display: flex; gap: 3px; }

        .flap { position: relative; width: var(--fw); height: var(--fh); perspective: 500px; }
        .flap-base { position: absolute; inset: 0; border-radius: 3px; overflow: hidden; }
        .fbt, .fbb {
            position: absolute; left: 0; right: 0; height: 50%;
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .fbt { top: 0; background: var(--flap-top); border: 1px solid var(--flap-border); border-bottom: none; border-radius: 3px 3px 0 0; }
        .fbb { bottom: 0; background: var(--flap-bot); border: 1px solid var(--flap-border); border-top: none; border-radius: 0 0 3px 3px; }
        .fc {
            position: absolute; width: 100%; text-align: center;
            font-size: var(--ff); font-weight: 700; color: var(--flap-text);
            line-height: var(--fh); user-select: none;
            font-family: 'Share Tech Mono', 'Courier New', monospace;
        }
        .fbt .fc { top: 0; }
        .fbb .fc { bottom: 0; }

        .flap-base::after { content: ''; position: absolute; left: 0; right: 0; top: 50%; height: 2px; background: var(--gap-line); z-index: 5; transform: translateY(-50%); }
        .flap-base::before { content: ''; position: absolute; left: 3px; top: 50%; transform: translateY(-50%); width: 4px; height: 4px; border-radius: 50%; background: var(--rivet); box-shadow: calc(var(--fw) - 10px) 0 0 0 var(--rivet); z-index: 6; }

        .ff { position: absolute; left: 0; right: 0; height: 50%; overflow: hidden; backface-visibility: hidden; will-change: transform; }
        .fft { top: 0; background: var(--flap-top); border: 1px solid var(--flap-border); border-bottom: none; border-radius: 3px 3px 0 0; transform-origin: bottom center; z-index: 10; }
        .ffb { bottom: 0; background: var(--flap-bot); border: 1px solid var(--flap-border); border-top: none; border-radius: 0 0 3px 3px; transform-origin: top center; transform: rotateX(90deg); z-index: 9; }
        .ff .fc { line-height: var(--fh); }
        .fft .fc { top: 0; }
        .ffb .fc { bottom: 0; }
        .ff-shadow { position: absolute; inset: 0; background: rgba(0,0,0,0); pointer-events: none; z-index: 12; }

        #clock {
            position: fixed; bottom: 16px; right: 20px;
            font-family: 'Share Tech Mono', 'Courier New', monospace;
            font-size: 0.9rem; color: var(--clock-color);
            letter-spacing: 1.5px; z-index: 1001;
        }
        #settings-link { position: fixed; top: 14px; right: 18px; color: var(--clock-color); text-decoration: none; font-size: 1.3rem; opacity: 0.25; transition: opacity 0.4s; z-index: 1001; }
        #settings-link:hover { opacity: 1; }
        #audio-hint { position: fixed; bottom: 16px; left: 20px; font-size: 0.72rem; color: var(--clock-color); z-index: 1001; cursor: pointer; font-family: 'Share Tech Mono', 'Courier New', monospace; }
        #audio-hint.gone { display: none; }
    </style>
</head>
<body>
    <a id="settings-link" href="index.php" title="Control Panel">&#9881;</a>
    <div id="board"></div>
    <div id="clock"></div>
    <div id="audio-hint"><?= $soundEnabled ? 'Click anywhere for sound' : '' ?></div>

    <script>
    (function() {
        'use strict';

        const ROWS = <?= $rows ?>;
        const COLS = <?= $cols ?>;
        const GRID = <?= $gridJson ?>;
        const CHARS = ' ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.,!?-:/@#&*%';
        const SHOW_SECONDS = <?= $showSeconds ? 'true' : 'false' ?>;
        const SOUND_ENABLED = <?= $soundEnabled ? 'true' : 'false' ?>;
        const MODE = '<?= $mode ?>';

        let grid = [];
        let audioCtx = null;
        let clickBufs = [];

        function resize() {
            const vw = window.innerWidth, vh = window.innerHeight;
            const maxW = (vw - 74 - (COLS - 1) * 3) / COLS;
            const maxH = (vh - 80 - (ROWS - 1) * 5) / (ROWS * 1.35);
            const s = Math.max(14, Math.min(90, Math.floor(Math.min(maxW, maxH))));
            const b = document.getElementById('board');
            b.style.setProperty('--fw', s + 'px');
            b.style.setProperty('--fh', Math.round(s * 1.35) + 'px');
            b.style.setProperty('--ff', Math.round(s * 0.65) + 'px');
        }

        // ── Audio ──
        function initAudio() {
            if (audioCtx || !SOUND_ENABLED) return;
            try {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                for (let v = 0; v < 5; v++) {
                    const sr = audioCtx.sampleRate;
                    const dur = 0.025 + Math.random() * 0.015;
                    const len = Math.ceil(sr * dur);
                    const buf = audioCtx.createBuffer(1, len, sr);
                    const d = buf.getChannelData(0);
                    const freq = 2500 + Math.random() * 2000;
                    for (let i = 0; i < len; i++) {
                        const t = i / sr;
                        const env = Math.exp(-t * (200 + Math.random() * 80));
                        d[i] = ((i < 3 ? 0.7 - i * 0.2 : 0) + Math.sin(2*Math.PI*freq*t)*0.3 + (Math.random()*2-1)*0.12) * env;
                    }
                    clickBufs.push(buf);
                }
                const hint = document.getElementById('audio-hint');
                if (hint) hint.classList.add('gone');
            } catch(e) {}
        }

        function playClick() {
            if (!SOUND_ENABLED || !audioCtx || clickBufs.length === 0) return;
            const buf = clickBufs[Math.floor(Math.random() * clickBufs.length)];
            const src = audioCtx.createBufferSource();
            src.buffer = buf;
            src.playbackRate.value = 0.88 + Math.random() * 0.24;
            const gain = audioCtx.createGain();
            gain.gain.value = 0.25 + Math.random() * 0.15;
            src.connect(gain).connect(audioCtx.destination);
            src.start();
        }

        // ── Build board ──
        function build() {
            const board = document.getElementById('board');
            board.innerHTML = ''; resize();
            for (let r = 0; r < ROWS; r++) {
                const row = document.createElement('div'); row.className = 'flap-row'; grid[r] = [];
                for (let c = 0; c < COLS; c++) {
                    const flap = document.createElement('div'); flap.className = 'flap'; flap.id = 'f'+r+'_'+c;
                    const base = document.createElement('div'); base.className = 'flap-base';
                    const top = document.createElement('div'); top.className = 'fbt';
                    const tc = document.createElement('span'); tc.className = 'fc'; tc.textContent = ' '; top.appendChild(tc);
                    const bot = document.createElement('div'); bot.className = 'fbb';
                    const bc = document.createElement('span'); bc.className = 'fc'; bc.textContent = ' '; bot.appendChild(bc);
                    base.appendChild(top); base.appendChild(bot); flap.appendChild(base);
                    row.appendChild(flap); grid[r][c] = ' ';
                }
                board.appendChild(row);
            }
        }

        // ── Flip animation ──
        function flip(el, newChar, dur) {
            const base = el.querySelector('.flap-base');
            const bt = base.querySelector('.fbt .fc'), bb = base.querySelector('.fbb .fc');
            const old = bt.textContent;
            el.querySelectorAll('.ff').forEach(e => e.remove());

            const tf = document.createElement('div'); tf.className = 'ff fft';
            const tfc = document.createElement('span'); tfc.className = 'fc'; tfc.textContent = old; tf.appendChild(tfc);
            const tfs = document.createElement('div'); tfs.className = 'ff-shadow'; tf.appendChild(tfs);

            const bf = document.createElement('div'); bf.className = 'ff ffb';
            const bfc = document.createElement('span'); bfc.className = 'fc'; bfc.textContent = newChar; bf.appendChild(bfc);
            const bfs = document.createElement('div'); bfs.className = 'ff-shadow'; bf.appendChild(bfs);

            el.appendChild(tf); el.appendChild(bf); bb.textContent = newChar;
            const half = dur * 0.5;

            requestAnimationFrame(() => {
                tf.style.transition = 'transform '+half+'ms cubic-bezier(0.55,0,0.85,0.36)';
                tf.style.transform = 'rotateX(-90deg)';
                tfs.style.transition = 'background '+half+'ms ease-in';
                tfs.style.background = 'rgba(0,0,0,0.25)';
            });
            setTimeout(() => {
                bt.textContent = newChar; tf.remove();
                requestAnimationFrame(() => {
                    bf.style.transition = 'transform '+half+'ms cubic-bezier(0.15,0.64,0.45,1)';
                    bf.style.transform = 'rotateX(0deg)';
                    bfs.style.transition = 'background '+half+'ms ease-out';
                    bfs.style.background = 'rgba(0,0,0,0)';
                });
            }, half);
            setTimeout(() => { bf.remove(); }, dur + 10);
        }

        function animateFlap(r, c, target, startDelay) {
            const el = document.getElementById('f'+r+'_'+c);
            if (!el) return;
            if (grid[r][c] === target) return;

            const ci = CHARS.indexOf(grid[r][c]), ti = CHARS.indexOf(target);
            let steps = [];
            if (ci === -1 || ti === -1) { steps = [target]; }
            else if (ti >= ci) { for (let i=ci+1;i<=ti;i++) steps.push(CHARS[i]); }
            else { for (let i=ci+1;i<CHARS.length;i++) steps.push(CHARS[i]); for (let i=0;i<=ti;i++) steps.push(CHARS[i]); }

            if (steps.length > 5) {
                const s = [], stride = (steps.length-1)/4;
                for (let i=0;i<4;i++) s.push(steps[Math.floor(i*stride)]);
                s.push(target); steps = s;
            }

            const DUR = 140;
            steps.forEach((ch, i) => {
                setTimeout(() => { flip(el, ch, DUR); playClick(); }, startDelay + i * DUR);
            });
            grid[r][c] = target;
        }

        // ── Update entire board or a single row ──
        function updateBoard(newGrid) {
            if (SOUND_ENABLED) initAudio();
            let n = 0;
            for (let r = 0; r < ROWS; r++) {
                const line = newGrid[r] || '';
                for (let c = 0; c < COLS; c++) {
                    const ch = c < line.length ? line[c] : ' ';
                    if (grid[r] && grid[r][c] === ch) continue;
                    animateFlap(r, c, ch, n * 50);
                    n++;
                }
            }
        }

        function updateRow(r, text) {
            if (SOUND_ENABLED) initAudio();
            const padded = text.substring(0, COLS).padEnd(COLS, ' ');
            let n = 0;
            for (let c = 0; c < COLS; c++) {
                const ch = padded[c];
                if (grid[r] && grid[r][c] === ch) continue;
                // For seconds ticking, animate directly — no stagger delay needed (only 1-2 chars change)
                animateFlap(r, c, ch, n * 30);
                n++;
            }
        }

        // ── Generate dashboard row 1 text client-side ──
        function makeDateTimeLine() {
            const d = new Date();
            const days = ['SUN','MON','TUE','WED','THU','FRI','SAT'];
            const months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
            const day = days[d.getDay()];
            const mon = months[d.getMonth()];
            const date = d.getDate();
            const hr = d.getHours();
            const mn = String(d.getMinutes()).padStart(2, '0');
            const ampm = hr >= 12 ? 'PM' : 'AM';
            const h12 = hr % 12 || 12;

            if (SHOW_SECONDS) {
                const sc = String(d.getSeconds()).padStart(2, '0');
                return day + ' ' + mon + ' ' + date + ' ' + h12 + ':' + mn + ':' + sc + ' ' + ampm;
            }
            return day + ' ' + mon + ' ' + date + '  ' + h12 + ':' + mn + ' ' + ampm;
        }

        // ── Corner clock (simple text) ──
        function tickClock() {
            const d = new Date();
            const ds = d.toLocaleDateString(undefined, { weekday:'short', year:'numeric', month:'short', day:'numeric' });
            const ts = [d.getHours(), d.getMinutes(), d.getSeconds()].map(v => String(v).padStart(2,'0')).join(':');
            document.getElementById('clock').textContent = ds + '  ' + ts;
        }

        // ── Seconds ticker: update row 0 of the board every second (dashboard mode only) ──
        function tickSeconds() {
            if (MODE !== 'dashboard') return;
            updateRow(0, makeDateTimeLine());
        }

        // ── AJAX refresh (every 60s) ──
        function refresh() {
            fetch('api.php?t=' + Date.now())
                .then(r => r.json())
                .then(d => {
                    if (d.grid) {
                        // If seconds ticker is active, skip row 0 — JS handles it
                        if (SHOW_SECONDS && MODE === 'dashboard') {
                            const adjusted = d.grid.slice();
                            adjusted[0] = makeDateTimeLine();
                            updateBoard(adjusted);
                        } else {
                            updateBoard(d.grid);
                        }
                    }
                    if (d.theme) document.documentElement.setAttribute('data-theme', d.theme);
                    if (d.flap_color) document.documentElement.style.setProperty('--flap-text', d.flap_color);
                })
                .catch(() => {});
        }

        // ── Init ──
        build();
        window.addEventListener('resize', resize);

        // Initial data — override row 0 with client time if seconds enabled
        const initGrid = GRID.slice();
        if (SHOW_SECONDS && MODE === 'dashboard') {
            initGrid[0] = makeDateTimeLine();
        }
        setTimeout(() => updateBoard(initGrid), 350);

        // Corner clock
        tickClock();
        setInterval(tickClock, 1000);

        // Seconds ticker on the main board
        if (SHOW_SECONDS && MODE === 'dashboard') {
            setInterval(tickSeconds, 1000);
        }

        // Content refresh
        setInterval(refresh, 60000);

        // Audio unlock
        if (SOUND_ENABLED) {
            const unlock = () => initAudio();
            document.addEventListener('click', unlock, { once: true });
            document.addEventListener('keydown', unlock, { once: true });
            document.addEventListener('touchstart', unlock, { once: true });
        } else {
            const hint = document.getElementById('audio-hint');
            if (hint) hint.classList.add('gone');
        }
    })();
    </script>
</body>
</html>
