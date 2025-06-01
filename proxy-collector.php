<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-store');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Method Not Allowed'
    ], JSON_PRETTY_PRINT);
    exit;
}

$channel = $_GET['channel'] ?? null;

if (!$channel) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Missing channel parameter',
        'usage' => '/?channel=@channelname or /?channel=https://t.me/channelname or /?channel=t.me/channelname',
        'example' => '/?channel=@channelname'
    ], JSON_PRETTY_PRINT);
    exit;
}

$normalizedUrl = normalizeChannelUrl($channel);
if (!$normalizedUrl) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid channel format',
        'valid_formats' => ['@channelname', 'https://t.me/channelname', 't.me/channelname', 'https://t.me/s/channelname']
    ], JSON_PRETTY_PRINT);
    exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $normalizedUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($html === false || $httpCode !== 200) {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to fetch channel page'
    ], JSON_PRETTY_PRINT);
    exit;
}

$proxies = extractProxies($html);

if (empty($proxies)) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'error' => 'No proxy links found in channel'
    ], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    'ok' => true,
    'channel' => extractChannelHandle($channel),
    'proxies' => $proxies
], JSON_PRETTY_PRINT);

function extractChannelHandle($input) {
    $input = trim($input);
    
    if (strpos($input, '@') === 0) {
        return $input;
    }
    
    if (strpos($input, 't.me/') !== false) {
        $parts = explode('/', $input);
        $username = end($parts) ?: prev($parts);
        return '@' . $username;
    }
    
    return '@' . $input;
}

function extractProxies($html) {
    $patterns = [
        '/https:\/\/t\.me\/proxy\?server=[^&\s]+&port=\d+&secret=[a-f0-9]+/i',
        '/tg:\/\/proxy\?server=[^&\s]+&port=\d+&secret=[a-f0-9]+/i',
        '/https:\/\/t\.me\/proxy\?[^"\s<>]+/i',
        '/tg:\/\/proxy\?[^"\s<>]+/i'
    ];
    
    $allMatches = [];
    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $html, $matches);
        $allMatches = array_merge($allMatches, $matches[0]);
    }
    
    $unique = array_unique($allMatches);
    return array_map(function($link) {
        return str_replace('&amp;', '&', $link);
    }, array_values($unique));
}

function normalizeChannelUrl($input) {
    $input = trim($input);
    
    if (strpos($input, '@') === 0) {
        $username = substr($input, 1);
        return "https://t.me/s/$username";
    }
    
    if (strpos($input, 'https://t.me/') === 0) {
        $urlParts = explode('/', str_replace('https://t.me/', '', $input));
        $username = $urlParts[0];
        
        if ($username === 's' && isset($urlParts[1])) {
            $username = $urlParts[1];
        }
        
        return "https://t.me/s/$username";
    }
    
    if (strpos($input, 't.me/') === 0) {
        $urlParts = explode('/', str_replace('t.me/', '', $input));
        $username = $urlParts[0];
        
        if ($username === 's' && isset($urlParts[1])) {
            $username = $urlParts[1];
        }
        
        return "https://t.me/s/$username";
    }
    
    if ($input && strpos($input, '/') === false && strpos($input, '@') === false) {
        return "https://t.me/s/$input";
    }
    
    return null;
}

?>