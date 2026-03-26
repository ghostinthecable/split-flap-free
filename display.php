<?php
/**
 * Split-Flap Display — Shared display text builder.
 * Used by both splitflap.php and api.php.
 */

function fetchWeather(array $config): ?array {
    $lat   = floatval($config['weather']['latitude'] ?? 0);
    $lon   = floatval($config['weather']['longitude'] ?? 0);
    $units = ($config['weather']['units'] ?? 'fahrenheit') === 'celsius' ? 'celsius' : 'fahrenheit';

    $tempParam = $units === 'celsius' ? 'temperature_unit=celsius' : 'temperature_unit=fahrenheit';
    $url = "https://api.open-meteo.com/v1/forecast"
         . "?latitude={$lat}&longitude={$lon}"
         . "&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m"
         . "&{$tempParam}&wind_speed_unit=mph&timezone=auto";

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header'  => "User-Agent: SplitFlapDisplay/1.0\r\n",
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['current'])) {
        return null;
    }

    return $data['current'];
}

function weatherCodeToText(int $code): string {
    $map = [
        0  => 'CLEAR SKY',
        1  => 'MOSTLY CLEAR',
        2  => 'PARTLY CLOUDY',
        3  => 'OVERCAST',
        45 => 'FOGGY',
        48 => 'RIME FOG',
        51 => 'LIGHT DRIZZLE',
        53 => 'DRIZZLE',
        55 => 'HEAVY DRIZZLE',
        56 => 'FRZN DRIZZLE',
        57 => 'FRZN DRIZZLE',
        61 => 'LIGHT RAIN',
        63 => 'RAIN',
        65 => 'HEAVY RAIN',
        66 => 'FREEZING RAIN',
        67 => 'FREEZING RAIN',
        71 => 'LIGHT SNOW',
        73 => 'SNOW',
        75 => 'HEAVY SNOW',
        77 => 'SNOW GRAINS',
        80 => 'LIGHT SHOWERS',
        81 => 'SHOWERS',
        82 => 'HEAVY SHOWERS',
        85 => 'SNOW SHOWERS',
        86 => 'SNOW SHOWERS',
        95 => 'THUNDERSTORM',
        96 => 'HAIL STORM',
        99 => 'HAIL STORM',
    ];
    return $map[$code] ?? 'UNKNOWN';
}

/**
 * Build the grid text lines from config.
 * Returns array of strings, one per row.
 */
function buildDisplayText(array $config): string {
    $mode = $config['mode'] ?? 'message';

    // Migrate legacy "weather" mode
    if ($mode === 'weather') {
        $mode = 'dashboard';
    }

    if ($mode === 'dashboard') {
        return buildDashboardText($config);
    }

    // Default: custom message
    return strtoupper($config['message'] ?? 'HELLO WORLD');
}

function buildDashboardText(array $config): string {
    $loc      = strtoupper($config['weather']['location_name'] ?? 'UNKNOWN');
    $units    = ($config['weather']['units'] ?? 'fahrenheit') === 'celsius' ? 'celsius' : 'fahrenheit';
    $tempUnit = $units === 'celsius' ? 'C' : 'F';

    // Line 1: Date & time (server time)
    $days   = ['SUN','MON','TUE','WED','THU','FRI','SAT'];
    $months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    $now    = time();
    $day    = $days[intval(date('w', $now))];
    $mon    = $months[intval(date('n', $now)) - 1];
    $date   = intval(date('j', $now));
    $hr     = intval(date('G', $now));
    $mn     = date('i', $now);
    $ampm   = $hr >= 12 ? 'PM' : 'AM';
    $h12    = $hr % 12 ?: 12;

    $line1 = "{$day} {$mon} {$date}  {$h12}:{$mn} {$ampm}";

    // Line 2: Location
    $line2 = $loc;

    // Line 3: Weather
    $weather = fetchWeather($config);
    if ($weather) {
        $temp     = round($weather['temperature_2m'] ?? 0);
        $humidity = round($weather['relative_humidity_2m'] ?? 0);
        $wind     = round($weather['wind_speed_10m'] ?? 0);
        $code     = intval($weather['weather_code'] ?? 0);
        $desc     = weatherCodeToText($code);

        $line3 = "{$temp}*{$tempUnit}  {$desc}";
    } else {
        $line3 = 'WEATHER UNAVAILABLE';
    }

    return $line1 . "\n" . $line2 . "\n" . $line3;
}

/**
 * Convert display text into a grid array of padded strings.
 */
function buildGrid(string $text, int $rows, int $cols): array {
    $lines = explode("\n", $text);
    $grid = [];
    for ($r = 0; $r < $rows; $r++) {
        $line = $r < count($lines) ? $lines[$r] : '';
        $grid[] = str_pad(mb_substr($line, 0, $cols), $cols, ' ');
    }
    return $grid;
}
