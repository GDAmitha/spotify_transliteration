<?php
if (file_exists(__DIR__ . '/.env.php')) {
    require_once __DIR__ . '/.env.php';
}
require_once __DIR__ . '/SpotifyAuth.php';

use SpotifyLyricsApi\SpotifyAuth;

session_start();

$auth = new SpotifyAuth();

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$storedState = $_SESSION['spotify_auth_state'] ?? null;

if ($state === null || $state !== $storedState) {
    http_response_code(400);
    echo json_encode(['error' => 'State mismatch']);
    exit;
}

unset($_SESSION['spotify_auth_state']);

try {
    $auth->exchangeCode($code);
    echo json_encode(['message' => 'Authorization successful. You can now use the lyrics API without providing a track ID.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
