<?php

if (file_exists(__DIR__ . '/.env.php')) {
    require_once __DIR__ . '/.env.php';
}

require_once __DIR__ . '/SpotifyAuth.php';

use SpotifyLyricsApi\SpotifyAuth;

session_start();

$auth = new SpotifyAuth();
$state = bin2hex(random_bytes(16));
$_SESSION['spotify_auth_state'] = $state;

header('Location: ' . $auth->getAuthUrl($state));
exit;
