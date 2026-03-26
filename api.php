<?php
/**
 * Split-Flap Display API endpoint
 * Returns current grid data as JSON for AJAX refresh.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/display.php';

$configFile = __DIR__ . '/config.json';

if (!file_exists($configFile)) {
    echo json_encode(['error' => 'No config found']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
if (!is_array($config)) {
    echo json_encode(['error' => 'Invalid config']);
    exit;
}

$rows  = max(1, min(10, intval($config['rows'] ?? 3)));
$cols  = max(10, min(60, intval($config['cols'] ?? 22)));
$theme = ($config['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
$mode  = $config['mode'] ?? 'message';

$text = buildDisplayText($config);
$grid = buildGrid($text, $rows, $cols);

$flapColor = preg_match('/^#[0-9a-fA-F]{6}$/', $config['flap_color'] ?? '') ? $config['flap_color'] : '#e8d44d';

echo json_encode([
    'grid'          => $grid,
    'theme'         => $theme,
    'mode'          => $mode,
    'show_seconds'  => !empty($config['show_seconds']),
    'sound_enabled' => isset($config['sound_enabled']) ? (bool)$config['sound_enabled'] : true,
    'flap_color'    => $flapColor,
]);
