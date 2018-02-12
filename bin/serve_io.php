<?php

use Workerman\Worker;
use PHPSocketIO\SocketIO;
use \Clue\React\Redis\Factory;
use \Clue\React\Redis\Client;
use \PHPSocketIO\Socket;

require_once __DIR__ . '/../vendor/autoload.php';

const REDIS_ROOM_PREFIX = 'chat.room.';
const REDIS_MESSAGES_PREFIX = 'chat.messages.';
const REDIS_READ_PREFIX = 'chat.read.';
const REDIS_LOG_PREFIX = 'chat.log.';
const ROOM_TIMEOUT = 1209600; // todo: allot to config

// listen port 2020 for socket.io client
$io = new SocketIO(getenv('WS_PORT'));

$redisUrl = getenv('REDIS_URL'); //workerman is not able to resolve container by dns
$redisUrl = gethostbyname($redisUrl);

$io->on('workerStart', function () use ($io, $redisUrl) {
	$io->userSockets = [];
	$loop = Worker::getEventLoop();
	$io->factory = new Factory($loop);
    $io->factory
        ->createClient($redisUrl)
        ->then(
            function (Client $client) use ($io) {
                $io->redis = $client;
            },
            function (Exception $e) {
                echo 'Error: ' . $e->getMessage() . PHP_EOL;
            }
        );
	$io->factory->createClient($redisUrl)
		->then(
			function (Client $subscribeClient) use ($io) {
                $subscribeClient->psubscribe(REDIS_MESSAGES_PREFIX . '*');
                $subscribeClient->on('pmessage', function ($keyMask, $key, $payload) use ($io) {
                    $room = str_replace(REDIS_MESSAGES_PREFIX, '', $key);
                    $messageData = json_decode($payload, true);
                    foreach ($io->userSockets[$room] ?? [] as $userSocket) {
                        $userSocket->emit('new-message', [$messageData]);
                    }
                    $io->redis->rpush(REDIS_LOG_PREFIX . $room, $payload);
                    $io->redis->expire(REDIS_LOG_PREFIX . $room, ROOM_TIMEOUT);
                });
		},
		function (Exception $e) {
			echo 'Error: ' . $e->getMessage() . PHP_EOL;
		});
});

$io->on('connection', function (Socket $socket) use ($io) {
    $room = $socket->request->_query['room'] ?? false;
    if (! $room){
        $socket->emit('err', 'no room');
        $socket->disconnect();
    }
	$userName = false;
	$socket->on('enter', function ($data) use ($io, $room, &$userName, $socket) {
        $userName = htmlspecialchars($data['user-name']);
		$userName = uniqueName($userName, array_keys($io->userSockets[$room] ?? []));
		$io->userSockets[$room][$userName] = $socket;
        $socket->emit('entered', $userName);
        $io->redis->publish(
            REDIS_MESSAGES_PREFIX . $room,
            json_encode(['userName' => 'ChatBot', 'msg' => 'Welcome ' . $userName . '!'])
        );
        $io->redis->lrange(REDIS_LOG_PREFIX . $room, 0, -1)
            ->then(function($log) use ($socket) {
                $history = [];
                foreach ($log as $messageData) {
                   $history[] = json_decode($messageData, true);
                }
                $socket->emit('new-message', $history);
            });
        $io->redis->expire(REDIS_ROOM_PREFIX . $room, ROOM_TIMEOUT);
	});

    $socket->on('disconnect', function() use ($io, $room, &$userName){
        if (isset($io->userSockets[$room][$userName])){
            unset($io->userSockets[$room][$userName]);
            $io->redis->publish(
                REDIS_MESSAGES_PREFIX . $room,
                json_encode(['userName' => 'ChatBot', 'msg' => $userName . ' leaved the chat.'])
            );
        }
    });

	$socket->on('send-message', function ($data) use ($io, $room, &$userName) {
	    if (! $userName) {
	        return;
        }
        $payload = json_encode(['userName' => $userName, 'msg' => htmlspecialchars($data['msg'])]);
        $io->redis->publish(REDIS_MESSAGES_PREFIX . $room, $payload);
        $io->redis->expire(REDIS_ROOM_PREFIX . $room, ROOM_TIMEOUT);
	});

	// on did read

});

function uniqueName($newName, $oldNames = []) {
	if (in_array($newName, $oldNames)){
		$suffix = 1;
		while(in_array($newName . '_' . $suffix, $oldNames, true)){
			$suffix++;
		}
		$newName = $newName . '_' . $suffix;
	}
	return $newName;
}

Worker::runAll();