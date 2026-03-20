<?php

if (file_exists(__DIR__ . '/.env.php')) {
    require_once __DIR__ . '/.env.php';
}

require_once __DIR__ . '/SpotifyException.php';
require_once __DIR__ . '/Spotify.php';
require_once __DIR__ . '/SpotifyAuth.php';

use SpotifyLyricsApi\Spotify;
use SpotifyLyricsApi\SpotifyException;
use SpotifyLyricsApi\SpotifyAuth;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

$trackid = $_GET['trackid'] ?? null;
$url = $_GET['url'] ?? null;
$format = $_GET['format'] ?? null;

$re = '~[\bhttps://open.\b]*spotify[\b.com\b]*[/:]*track[/:]*([A-Za-z0-9]+)~';

$currentProgress = null;

if (!$trackid && !$url) {
    $auth = new SpotifyAuth();
    $currentInfo = $auth->getCurrentlyPlayingInfo();
    
    if (!$currentInfo) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'No track provided and no track currently playing on Spotify!', 'usage' => 'https://github.com/akashrchandran/spotify-lyrics-api']);
        return;
    }

    $trackid = $currentInfo['id'];
    $currentProgress = $currentInfo['progress_ms'];
}
if ($url) {
    preg_match($re, $url, $matches, PREG_OFFSET_CAPTURE, 0);
    $trackid = $matches[1][0];
}

try {
    $spotify = new Spotify(getenv('SP_DC'));
    $spotify->checkTokenExpire();
    $lyricsData = $spotify->getLyrics(track_id: $trackid);
    // #region agent log
    file_put_contents(__DIR__.'/debug.log', json_encode(['location'=>'index.php:33','message'=>'Raw lyrics data from Spotify','data'=>['has_lyrics'=>isset($lyricsData['lyrics']),'has_lines'=>isset($lyricsData['lyrics']['lines']),'first_line_keys'=>isset($lyricsData['lyrics']['lines'][0])?array_keys($lyricsData['lyrics']['lines'][0]):null,'first_line'=>$lyricsData['lyrics']['lines'][0]??null],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session','hypothesisId'=>'H2']).PHP_EOL, FILE_APPEND);
    // #endregion
    echo make_response($spotify, $lyricsData, $format, $currentProgress);
} catch (SpotifyException $e) {
    $statusCode = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($statusCode);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

// function transliterate_lyrics($lyricsLines)
// {
//     // Extract all words from lyrics lines
//     $words = array_map(fn($line) => $line['words'] ?? '', $lyricsLines);
    
//     // Prepare JSON input for Python script
//     $jsonInput = json_encode($words);
    
//     // Call transliterate.py
//     $pythonScript = __DIR__ . '/transliterate.py';
//     $command = sprintf('python3 %s %s 2>&1', 
//         escapeshellarg($pythonScript), 
//         escapeshellarg(base64_encode($jsonInput))
//     );
    
//     $output = shell_exec($command);
    
//     if ($output === null) {
//         // If transliteration fails, return original lines unchanged
//         return $lyricsLines;
//     }
    
//     $transliteratedWords = json_decode(trim($output), true);
    
//     if (!is_array($transliteratedWords)) {
//         // If parsing fails, return original lines unchanged
//         return $lyricsLines;
//     }
    
//     // Populate transliteratedWords field for each line
//     foreach ($lyricsLines as $index => &$line) {
//         if (isset($transliteratedWords[$index])) {
//             $line['transliteratedWords'] = $transliteratedWords[$index];
//         }
//     }
    
//     return $lyricsLines;
// }

function transliterate_lyrics($lyricsLines)
{
    $words = array_map(fn($line) => $line['words'] ?? '', $lyricsLines);
    $jsonInput = json_encode($words);
    $pythonScript = __DIR__ . '/transliterate.py';

    $pythonBin = '/Users/dimpleamithag/anaconda3/bin/python3';

    $command = sprintf('%s %s %s 2>&1',
        escapeshellarg($pythonBin),
        escapeshellarg($pythonScript),
        escapeshellarg(base64_encode($jsonInput))
    );

    $output = shell_exec($command);

    // ADD THIS — log everything
    file_put_contents(__DIR__.'/debug.log', json_encode([
        'location' => 'transliterate_lyrics',
        'command' => $command,
        'output_raw' => $output,
        'output_null' => $output === null,
        'json_decode_result' => json_decode(trim($output ?? ''), true),
        'json_last_error' => json_last_error_msg(),
    ]).PHP_EOL, FILE_APPEND);

    if ($output === null) {
        return $lyricsLines;
    }

    $transliteratedWords = json_decode(trim($output), true);

    if (!is_array($transliteratedWords)) {
        return $lyricsLines;
    }

    foreach ($lyricsLines as $index => &$line) {
        if (isset($transliteratedWords[$index])) {
            $line['transliteratedWords'] = $transliteratedWords[$index];
        }
    }
    unset($line); // FIX the reference bug

    return $lyricsLines;
}

function transliterate_telugu_lyrics($lyricsLines)
{
    $words = array_map(fn($line) => $line['words'] ?? '', $lyricsLines);
    $jsonInput = json_encode($words);
    $pythonScript = __DIR__ . '/transliterate_telugu.py';
    $command = sprintf('python3 %s %s 2>&1',
        escapeshellarg($pythonScript),
        escapeshellarg(base64_encode($jsonInput))
    );
    $output = shell_exec($command);

    if ($output === null) {
        return $lyricsLines;
    }

    $transliteratedWords = json_decode(trim($output), true);
    if (!is_array($transliteratedWords)) {
        return $lyricsLines;
    }

    foreach ($lyricsLines as $index => &$line) {
        if (isset($transliteratedWords[$index])) {
            $line['teluguTransliteratedWords'] = $transliteratedWords[$index];
        }
    }

    return $lyricsLines;
}

function is_telugu_lyrics($lyricsLines)
{
    foreach ($lyricsLines as $line) {
        $words = $line['words'] ?? '';
        if (is_string($words) && preg_match('/[\x{0C00}-\x{0C7F}]/u', $words)) {
            return true;
        }
    }
    return false;
}

function is_korean_lyrics($lyricsLines)
{
    foreach ($lyricsLines as $line) {
        $words = $line['words'] ?? '';
        if (is_string($words) && preg_match('/[\x{AC00}-\x{D7AF}]/u', $words)) {
            return true;
        }
    }
    return false;
}

function make_response($spotify, $lyricsData, $format, $currentProgress = null)
{
    $lyricsLines = $lyricsData['lyrics']['lines'];
    
    $isTelugu = is_telugu_lyrics($lyricsLines);
    $isKorean = is_korean_lyrics($lyricsLines);
    
    // #region agent log
    file_put_contents(__DIR__.'/debug.log', json_encode(['location'=>'index.php:46','message'=>'Lyrics lines before formatting','data'=>['format'=>$format,'line_count'=>count($lyricsLines),'sample_line'=>$lyricsLines[0]??null, 'is_telugu' => $isTelugu, 'is_korean' => $isKorean],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session','hypothesisId'=>'H3']).PHP_EOL, FILE_APPEND);
    // #endregion
    
    // Transliterate the lyrics (Korean)
    $lyricsLines = transliterate_lyrics($lyricsLines);
    // #region agent log
    file_put_contents(__DIR__.'/debug.log', json_encode(['location'=>'index.php:transliterate','message'=>'After transliteration','data'=>['sample_line'=>$lyricsLines[0]??null,'has_transliterated'=>!empty($lyricsLines[0]['transliteratedWords']??'')],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session','hypothesisId'=>'H1']).PHP_EOL, FILE_APPEND);
    // #endregion

    // Transliterate Telugu lyrics when detected
    if ($isTelugu) {
        $lyricsLines = transliterate_telugu_lyrics($lyricsLines);
        // #region agent log
        file_put_contents(__DIR__.'/debug.log', json_encode(['location'=>'index.php:telugu','message'=>'After Telugu transliteration','data'=>['sample_line'=>$lyricsLines[0]??null,'has_telugu_transliterated'=>!empty($lyricsLines[0]['teluguTransliteratedWords']??'')],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session','hypothesisId'=>'H5']).PHP_EOL, FILE_APPEND);
        // #endregion
    }
    
    $activeIndex = null;
    $windowStart = null;
    $windowEnd = null;
    // If we have a progress timestamp, find the specific line
    if ($currentProgress !== null) {
        foreach ($lyricsLines as $index => $line) {
            // Find the last line where startTimeMs is less than or equal to current progress
            if ($line['startTimeMs'] <= $currentProgress) {
                $activeIndex = $index;
            } else {
                break; // Lines are usually sorted by time
            }
        }

        if ($activeIndex !== null) {
            $windowStart = max(0, $activeIndex - 3);
            $windowEnd = min(count($lyricsLines) - 1, $activeIndex + 3);
            $lyricsLines = array_slice($lyricsLines, $windowStart, $windowEnd - $windowStart + 1);
        } else {
            $lyricsLines = [];
        }
    }

    $lines = match ($format) {
        'lrc' => $spotify->getLrcLyrics($lyricsLines),
        'srt' => $spotify->getSrtLyrics($lyricsLines),
        'raw' => $spotify->getRawLyrics($lyricsLines),
        default => $lyricsLines,
    };
    // #region agent log
    file_put_contents(__DIR__.'/debug.log', json_encode(['location'=>'index.php:52','message'=>'Lines after formatting','data'=>['format'=>$format,'is_array'=>is_array($lines),'is_string'=>is_string($lines),'sample_formatted'=>is_array($lines)?($lines[0]??null):substr($lines,0,100)],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session','hypothesisId'=>'H4']).PHP_EOL, FILE_APPEND);
    // #endregion
    
    $finalResponse = [
        'error' => false,
        'syncType' => $lyricsData['lyrics']['syncType'],
        'progress_ms' => $currentProgress,
        'active_index' => $activeIndex,
        'window_start' => $windowStart,
        'window_end' => $windowEnd,
        'is_telugu' => $isTelugu,
        'is_korean' => $isKorean,
        'lines' => $lines
    ];
    // #region agent log
    file_put_contents(__DIR__.'/debug.log', json_encode(['location'=>'index.php:59','message'=>'Final response structure','data'=>['has_error'=>isset($finalResponse['error']),'has_lines'=>isset($finalResponse['lines']),'response_keys'=>array_keys($finalResponse)],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session','hypothesisId'=>'H4']).PHP_EOL, FILE_APPEND);
    // #endregion
    
    return json_encode($finalResponse);
}
