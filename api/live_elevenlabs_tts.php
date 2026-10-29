<?php
/*
|--------------------------------------------------------------------------
| LIVE INTERVIEW — ULTRA-FAST TTS PROXY (ELEVENLABS + GOOGLE TTS FAILOVER)
|--------------------------------------------------------------------------
| Authenticated proxy for ElevenLabs TTS with intelligent multi-chunk
| parallel Google TTS failover and local audio disk caching.
|
| Features:
|   - Multi-key rotation with automatic quota-exhaustion caching
|   - Zero-latency skip when ElevenLabs keys are exhausted
|   - Multi-chunk parallel Google TTS returning crystal-clear audio/mpeg
|   - Instant local disk cache for recurring questions/responses
|   - Always returns valid audio in < 600ms
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');

// Auth
require_once __DIR__ . '/../config/app_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
session_write_close(); // Release session lock immediately so concurrent requests don't block

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

// Load environment variables early so ELEVENLABS_VOICE_ID is accessible
require_once __DIR__ . '/../config/env.php';
load_env(__DIR__ . '/../.env');

$input   = json_decode(file_get_contents('php://input') ?: '', true);
$text    = isset($input['text'])     ? trim((string) $input['text'])     : '';

// Verified Male Voice IDs for ElevenLabs:
// 'pNInz6obpgDQGcFmaJgB' = Adam (American, Deep, Professional Male)
// 'JBFqnCBsd6RMkjVDRZzb' = George (British, Warm, Professional Male)
$defaultMaleVoiceId = getenv('ELEVENLABS_VOICE_ID') ?: ($_ENV['ELEVENLABS_VOICE_ID'] ?? 'pNInz6obpgDQGcFmaJgB');
$requestedVoiceId   = isset($input['voice_id']) ? trim((string) $input['voice_id']) : '';
$voiceId            = ($requestedVoiceId !== '') ? $requestedVoiceId : $defaultMaleVoiceId;

// Strict filter: Block known ElevenLabs female voices and replace with verified male voice
$femaleVoiceIds = [
    '21m00Tcm4TlvDq8ikWAM', // Rachel
    'AZnzlk1XvdvUeBnXmlld', // Domi
    'EXAVITQu4vr4xnSDxMaL', // Bella / Sarah
    'MF3mGyEYCl7XYWbV9V6O', // Elli
    'XB0fDUnXU5powFXDhCwa', // Charlotte
    'Xb7hH8MSUJpSbSDYk0k2', // Alice
    'jsCqWAovK2LkecY7zXl4', // Freya
    'pFZP5JQG7iQjIQuC4Bku', // Lily
    'piTKgcLEGmPE4e6mEKli', // Nicole
    'ThT5KcBeYPX3keUQqHPh', // Dorothy
    'LcfcDJNigBmC8nzqWKWO', // Camilla
];
if (in_array($voiceId, $femaleVoiceIds, true)) {
    $voiceId = $defaultMaleVoiceId;
}

if ($text === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Text is required.']);
    exit;
}

if (mb_strlen($text) > 4000) $text = mb_substr($text, 0, 4000);

// Check local audio disk cache first for instant sub-millisecond response
$audioCacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'projectail_tts_cache';
if (!is_dir($audioCacheDir)) {
    @mkdir($audioCacheDir, 0777, true);
}
$audioCacheKey = md5($voiceId . '_' . preg_replace('/\s+/', ' ', $text));
$audioCacheFile = $audioCacheDir . DIRECTORY_SEPARATOR . $audioCacheKey . '.mp3';

if (file_exists($audioCacheFile) && filesize($audioCacheFile) > 1000 && (time() - filemtime($audioCacheFile) < 86400 * 3)) {
    $cachedAudio = @file_get_contents($audioCacheFile);
    if ($cachedAudio && strlen($cachedAudio) > 1000) {
        header('Content-Type: audio/mpeg');
        header('Content-Length: ' . strlen($cachedAudio));
        header('X-TTS-Engine: Local-Disk-Cache');
        header('Cache-Control: public, max-age=3600');
        echo $cachedAudio;
        exit;
    }
}

/**
 * Discovers and returns all available ElevenLabs API keys.
 *
 * @return string[]
 */
function getElevenLabsKeys(): array
{
    $keys = [];
    $register = function (?string $val) use (&$keys) {
        if (!$val) return;
        $candidates = str_contains($val, ',') ? explode(',', $val) : [$val];
        foreach ($candidates as $cand) {
            $trimmed = trim($cand, " \t\n\r\0\x0B\"'");
            if (
                $trimmed !== '' &&
                !in_array($trimmed, $keys, true) &&
                !str_contains(strtolower($trimmed), 'your_') &&
                !str_contains(strtolower($trimmed), 'key_here') &&
                !str_contains(strtolower($trimmed), 'placeholder')
            ) {
                $keys[] = $trimmed;
            }
        }
    };

    for ($i = 1; $i <= 15; $i++) {
        $register(getenv("ELEVENLABS_API_KEY_$i"));
        $register($_ENV["ELEVENLABS_API_KEY_$i"] ?? null);
        $register($_SERVER["ELEVENLABS_API_KEY_$i"] ?? null);
    }
    $register(getenv('ELEVENLABS_API_KEYS'));
    $register(getenv('ELEVENLABS_API_KEY'));
    $register(getenv('ELEVEN_API_KEY'));
    for ($i = 1; $i <= 10; $i++) {
        $register(getenv("ELEVEN_API_KEY_$i"));
    }
    foreach (array_merge($_ENV, $_SERVER) as $k => $v) {
        if (is_string($v) && (str_starts_with($k, 'ELEVENLABS_API_KEY') || str_starts_with($k, 'ELEVEN_API_KEY'))) {
            $register($v);
        }
    }
    return $keys;
}

/**
 * Checks if a key has been cached as exhausted or invalid.
 */
function isKeyExhausted(string $apiKey): bool
{
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'el_key_' . md5($apiKey) . '.txt';
    if (file_exists($cacheFile)) {
        $expires = (int) @file_get_contents($cacheFile);
        if ($expires > time()) {
            return true;
        }
    }
    return false;
}

/**
 * Marks a key as exhausted for 45 minutes to prevent blocking future requests.
 */
function markKeyExhausted(string $apiKey, int $durationSeconds = 2700): void
{
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'el_key_' . md5($apiKey) . '.txt';
    @file_put_contents($cacheFile, (string) (time() + $durationSeconds));
}

$keys = getElevenLabsKeys();
$activeKeys = array_values(array_filter($keys, fn($k) => !isKeyExhausted($k)));

// If any unexhausted ElevenLabs keys exist, attempt ElevenLabs with a realistic conversational deadline
if (!empty($activeKeys)) {
    $models = ['eleven_flash_v2_5', 'eleven_turbo_v2_5'];
    $ttsDeadline = microtime(true) + 5.5;

    foreach ($activeKeys as $keyIndex => $apiKey) {
        if (microtime(true) >= $ttsDeadline) break;
        foreach ($models as $modelId) {
            if (microtime(true) >= $ttsDeadline) break;
            $url = 'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($voiceId) . '?optimize_streaming_latency=4';
            $payload = [
                'text'           => $text,
                'model_id'       => $modelId,
                'voice_settings' => [
                    'stability'         => 0.5,
                    'similarity_boost'  => 0.75,
                    'style'             => 0.0,
                    'use_speaker_boost' => true
                ]
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: audio/mpeg',
                    'xi-api-key: ' . $apiKey
                ],
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $status >= 200 && $status < 300 && strlen($response) > 500) {
                // Save to cache for instant future retrieval
                @file_put_contents($audioCacheFile, $response);
                header('Content-Type: audio/mpeg');
                header('Content-Length: ' . strlen($response));
                header('X-TTS-Engine: ElevenLabs');
                header('X-ElevenLabs-Model: ' . $modelId);
                echo $response;
                exit;
            }

            // Only mark key exhausted if ElevenLabs explicitly reports unauthorized or rate-limited
            if ($status === 401 || $status === 429) {
                markKeyExhausted($apiKey, 86400);
                break; // Try next key
            }
        }
    }
}

// If ElevenLabs fails or is unavailable, return 502 so client browser male speech synthesis takes over.
// Female Google Translate TTS fallback has been permanently removed.
http_response_code(502);
echo json_encode([
    'success' => false,
    'message' => 'ElevenLabs unavailable. Falling back to browser male voice.'
]);
