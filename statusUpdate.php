<?php

use GuzzleHttp\Client;
use LastFmApi\Api\AuthApi;
use LastFmApi\Api\UserApi;
use MKraemer\ReactPCNTL\PCNTL;
use React\EventLoop\Factory;

require_once 'vendor/autoload.php';

// Load .env file
(new Dotenv\Dotenv(__DIR__))->load();


/**
 * Retrieve the last track listened the user listened to.
 *
 * @return array
 */
function getTrackInfo()
{
    try {
        $auth = new AuthApi('setsession', array('apiKey' => getenv('LASTFM_KEY')));
        $userAPI = new UserApi($auth);
        $trackInfo = $userAPI->getRecentTracks([
            'user' => getenv('LASTFM_USER'),
            'limit' => '1'
        ]);
        return (isset($trackInfo[0]["nowplaying"])) ? $trackInfo[0] : null;
    } catch (Exception $e) {
        echo 'Unable to authenticate against Last.fm API.', PHP_EOL;
        exit;
    }
}

/**
 * @param $status
 */
function updateSlackStatus($status, $emoji = ':hear_no_evil:')
{
    if ($status) echo $status . PHP_EOL;
    if ($emoji === 0) $emoji = '';
    $client = new Client();
    $client->post('https://slack.com/api/users.profile.set', [
        'form_params' => [
            'token' => getenv('SLACK_TOKEN'),
            'profile' => json_encode([
                'status_text' => $status,
                'status_emoji' => $emoji
            ])
        ]
    ]);
}

echo "Starting up, waiting for input..." . PHP_EOL;
$loop = Factory::create();
$pcntl = new PCNTL($loop);

$pcntl->on(SIGINT, function () {
    updateSlackStatus('', 0);
    echo "Process terminated, shutting down!" . PHP_EOL;
    die();
});

$loop->addPeriodicTimer(10, function () use (&$currentStatus) {
    $trackInfo = getTrackInfo();
    if (!$trackInfo) {
        updateSlackStatus('', 0);
        $currentStatus = '';
    }
    else {
        $status = $trackInfo['artist']['name'] . ' - ' . $trackInfo['name'];
        if ($currentStatus !== $status) {
            updateSlackStatus($status);
            $currentStatus = $status;
        }
    }
});

$loop->run();
