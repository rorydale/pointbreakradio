<?php

// Basic, resilient JSON endpoint used by the holding page

// Response headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Minimal .env loader (no external deps)
function loadDotEnv($path)
{
    if (!is_readable($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        // Strip surrounding quotes
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        // Export to env and superglobals
        putenv("$key=$val");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

// Load .env (if present), then read token
loadDotEnv(__DIR__ . '/.env');
$token = getenv('GITHUB_ACCESS_TOKEN');

// Repo details
$owner = 'rorydale';
$repo = 'pointbreakradio';

// Simple file cache to avoid rate limits (10 minutes)
$cacheDir  = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/commits.json';
$cacheTtl  = 600; // seconds

// Helper to output and exit
function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

// Serve fresh or cached data
$now = time();
if (is_readable($cacheFile) && ($now - filemtime($cacheFile) < $cacheTtl)) {
    $cached = json_decode((string)file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        respond($cached);
    }
}

// Fetch from GitHub
$query = http_build_query([
    'per_page' => 30, // fetch a page, we'll filter by date-title
]);
$url = "https://api.github.com/repos/{$owner}/{$repo}/commits?{$query}";

$headers = [
    'User-Agent: pointbreakradio-site',
    'Accept: application/vnd.github+json',
    'X-GitHub-Api-Version: 2022-11-28',
];
if (!empty($token)) {
    $headers[] = 'Authorization: Bearer ' . $token;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 12,
]);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $status < 200 || $status >= 300) {
    // If fetch fails, try cache fallback
    if (is_readable($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            respond($cached, 200);
        }
    }
    respond(['error' => 'Unable to fetch commits', 'status' => $status, 'detail' => $curlErr], 502);
}

$api = json_decode($response, true);
if (!is_array($api)) {
    respond(['error' => 'Invalid API response'], 502);
}

// Extract shows from commit messages where the first line is YYYY-MM-DD
$shows = [];
foreach ($api as $item) {
    if (!isset($item['commit']['message'])) {
        continue;
    }
    $message = (string)$item['commit']['message'];
    $parts   = explode("\n\n", $message, 2); // [title, rest]
    $title   = trim($parts[0] ?? '');

    // Validate title as a date
    $dt = DateTime::createFromFormat('Y-m-d', $title);
    $valid = $dt && $dt->format('Y-m-d') === $title;
    if (!$valid) {
        continue;
    }

    $description = '';
    if (!empty($parts[1])) {
        $second = trim($parts[1]);
        // If there's a hyphen, take the content after the first one; else use whole second part
        $hyphenPos = strpos($second, '-');
        if ($hyphenPos !== false) {
            $second = substr($second, $hyphenPos + 1);
        }
        $description = ucfirst(trim(preg_replace('/\s+/', ' ', $second)));
    }

    $shows[] = [
        'title'       => $title,
        'description' => $description,
    ];
}

// Sort by title date desc, just in case
usort($shows, function ($a, $b) {
    return strcmp($b['title'], $a['title']);
});

// Limit to a reasonable count for the holding page
$shows = array_slice($shows, 0, 12);

// Save to cache (best-effort)
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
@file_put_contents($cacheFile, json_encode($shows, JSON_UNESCAPED_SLASHES));

respond($shows);
