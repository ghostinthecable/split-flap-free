# Split-Flap Display

A self-hosted, browser-based split-flap display board with a web control panel. Choose between a **Dashboard** that combines live weather with a real-time clock, or display **custom messages** -- all rendered with smooth 3D flip animations, mechanical click audio, and a configurable colour scheme.

Built entirely with vanilla PHP, HTML, CSS, and JavaScript. No frameworks, no build tools, no API keys.

---

## Features

**Display**

- Smooth CSS 3D split-flap animations with two-phase fold, shadow overlays, and staggered tile timing
- Per-tile mechanical click audio generated via the Web Audio API (five buffer variants with pitch randomisation for realism)
- Optional seconds ticker that updates the first row of the board every second with full flap animation and audio
- Responsive layout that auto-scales flap size to fit any screen, from a phone to a wall-mounted TV
- Configurable flap text colour via a colour picker in the control panel
- Light and dark themes
- System clock displayed in the corner, updated every second
- Auto-refresh every 60 seconds via AJAX (no full page reload)

**Dashboard Mode**

- Line 1: Current date and time (e.g. `THU MAR 26 1:22:45 PM` with seconds, or `THU MAR 26  1:22 PM` without)
- Line 2: Location name
- Line 3: Temperature and weather conditions (e.g. `9*C  OVERCAST`)

**Custom Message Mode**

- Enter any text in the control panel
- Multi-line messages supported -- each line maps to a row on the board
- Uppercase letters, numbers, and common punctuation

**Control Panel**

- Location search with autocomplete (type a city name, pick from a dropdown)
- Dashboard live preview that updates in real time as you change settings
- Toggle for flap sounds (on/off)
- Toggle for seconds ticker (on/off)
- Colour wheel for flap text colour with live preview
- Temperature unit selection (Fahrenheit or Celsius)
- Configurable grid dimensions (rows and columns)

---

## Requirements

- PHP 7.4 or newer
- A web server with PHP support (Apache, Nginx, Caddy, etc.)
- `allow_url_fopen = On` in `php.ini` (required for weather and geocoding requests)
- Write permission on the project directory for `config.json`

---

## Installation

1. Clone or copy the files into your web server's document root:

   ```bash
   cd /var/www/html
   git clone https://github.com/your-user/splitflap.git
   ```

2. Set ownership and permissions so PHP can write the configuration file:

   ```bash
   chown -R www-data:www-data /var/www/html/splitflap
   chmod 755 /var/www/html/splitflap
   chmod 644 /var/www/html/splitflap/*.php
   ```

3. Open the control panel in your browser:

   ```
   http://your-server/splitflap/index.php
   ```

4. Configure your display mode, location, theme, colours, and grid size, then click **Save Configuration**.

5. Click **Open Display** or navigate directly to:

   ```
   http://your-server/splitflap/splitflap.php
   ```

---

## File Structure

```
splitflap/
├── index.php       Control panel and configuration UI
├── splitflap.php   Full-screen split-flap display
├── display.php     Shared logic for weather fetching and grid building
├── api.php         JSON endpoint for AJAX refresh
├── config.json     Persisted configuration (auto-generated on first save)
└── README.md
```

---

## Configuration Reference

All settings are managed through the control panel at `index.php` and stored in `config.json`.

| Setting          | Description                                                     | Default       |
|------------------|-----------------------------------------------------------------|---------------|
| Display Mode     | `message` (custom text) or `dashboard` (weather and date/time)  | `dashboard`   |
| Message          | Text shown in message mode (uppercase, basic punctuation)       | `HELLO WORLD` |
| Location         | City selected via autocomplete search                           | `New York, NY`|
| Temperature Unit | `fahrenheit` or `celsius`                                       | `fahrenheit`  |
| Theme            | `dark` or `light`                                               | `dark`        |
| Flap Text Colour | Any hex colour value                                            | `#e8d44d`     |
| Flap Sounds      | Enable or disable mechanical click audio                        | On            |
| Seconds Ticker   | Show seconds on the board clock (dashboard mode)                | Off           |
| Rows             | Number of display rows (1--10)                                  | `3`           |
| Columns          | Number of display columns (10--60)                              | `22`          |

---

## APIs Used

Both APIs are free, open-source, and require no API key or account.

- [Open-Meteo Weather API](https://open-meteo.com/) -- Current temperature, humidity, wind speed, and weather conditions
- [Open-Meteo Geocoding API](https://open-meteo.com/en/docs/geocoding-api) -- Location search for the autocomplete in the control panel

Weather data is fetched fresh on every board refresh (every 60 seconds).

---

## Font

The display uses [Share Tech Mono](https://fonts.google.com/specimen/Share+Tech+Mono), a free monospace font from Google Fonts designed with a technical, industrial aesthetic. It loads automatically from Google Fonts and falls back to Courier New if unavailable.

---

## Browser Support

All modern browsers: Chrome, Firefox, Safari, and Edge. Audio requires a user interaction (click, tap, or keypress) before it can play, per browser autoplay policies.

---

## Usage Tips

- **Full-screen display** -- Press F11 in your browser for a clean, borderless view. Ideal for dedicated screens or wall-mounted TVs.
- **Kiosk mode** -- Launch Chromium with the `--kiosk` flag pointing at `splitflap.php` for an always-on display.
- **Dashboard sizing** -- Use at least 3 rows and 22 columns for dashboard mode so the date, location, and weather each fit on their own line.
- **Seconds ticker** -- When enabled, only the changing digits on the board flip each second, keeping the animation subtle and the audio natural.
- **Colour choices** -- The default amber (`#e8d44d`) mimics classic departure boards. Try `#33ff66` for a green LED look or `#ffffff` for clean white.

---

## License

MIT License. Use it however you like.
