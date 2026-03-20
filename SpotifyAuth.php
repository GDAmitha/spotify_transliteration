<?php

namespace SpotifyLyricsApi;

use Exception;

class SpotifyAuth
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $tokenFile;

    public function __construct()
    {
        $this->clientId = getenv('SPOTIFY_CLIENT_ID');
        $this->clientSecret = getenv('SPOTIFY_CLIENT_SECRET');
        $this->redirectUri = getenv('SPOTIFY_REDIRECT_URI');
        $this->tokenFile = sys_get_temp_dir() . '/spotify_auth_token.json';
    }

    public function getAuthUrl($state)
    {
        $scope = 'user-read-currently-playing user-read-playback-state';
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'scope' => $scope,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'show_dialog' => 'true'
        ];

        return 'https://accounts.spotify.com/authorize?' . http_build_query($params);
    }

    public function exchangeCode($code)
    {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ];

        return $this->requestToken($params);
    }

    public function refreshToken()
    {
        $tokens = $this->getStoredTokens();
        if (!$tokens || !isset($tokens['refresh_token'])) {
            throw new Exception('No refresh token available.');
        }

        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $tokens['refresh_token'],
        ];

        return $this->requestToken($params);
    }

    private function requestToken($params)
    {
        $ch = curl_init('https://accounts.spotify.com/api/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Spotify Auth Error: ' . ($data['error_description'] ?? $data['error']));
        }

        if (isset($data['access_token'])) {
            $this->storeTokens($data);
        }

        return $data;
    }

    public function storeTokens($data)
    {
        $tokens = $this->getStoredTokens() ?: [];
        $newData = array_merge($tokens, $data);
        $newData['expires_at'] = time() + $data['expires_in'];
        file_put_contents($this->tokenFile, json_encode($newData));
    }

    public function getStoredTokens()
    {
        if (!file_exists($this->tokenFile)) {
            return null;
        }
        return json_decode(file_get_contents($this->tokenFile), true);
    }

    public function getAccessToken()
    {
        $tokens = $this->getStoredTokens();
        if (!$tokens) {
            return null;
        }

        if (time() >= $tokens['expires_at']) {
            $newTokenData = $this->refreshToken();
            return $newTokenData['access_token'];
        }

        return $tokens['access_token'];
    }

    public function getCurrentlyPlayingInfo()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $ch = curl_init('https://api.spotify.com/v1/me/player/currently-playing');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 204 || !$response) {
            // Nothing playing
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['item']['id'])) {
            return [
                'id' => $data['item']['id'],
                'progress_ms' => $data['progress_ms'] ?? 0,
                'is_playing' => $data['is_playing'] ?? false
            ];
        }

        return null;
    }

    public function getCurrentlyPlayingTrackId()
    {
        $info = $this->getCurrentlyPlayingInfo();
        return $info ? $info['id'] : null;
    }
}
