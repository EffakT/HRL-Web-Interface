<?php

use App\Helpers\QueryServer;

// TEST-01 audit follow-up — the webhook/live-server-info code paths were only ever tested
// against the `GameServerQuery` fake; nothing exercised the real UDP socket implementation
// itself. Runs a tiny fixture UDP server in a forked child process (bound before forking, so
// both processes share the same already-bound socket with no readiness race) and drives the
// real `QueryServer` client against it over a genuine loopback socket.

/**
 * @param  callable(string $request): ?string  $respond  Given the raw request bytes, returns the
 *                                                       raw response bytes to send back, or null
 *                                                       to simulate a server that never replies.
 */
function withFakeUdpServer(callable $respond, callable $test): mixed
{
    $serverSocket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    socket_bind($serverSocket, '127.0.0.1', 0);
    socket_getsockname($serverSocket, $addr, $port);

    $pid = pcntl_fork();

    if ($pid === -1) {
        socket_close($serverSocket);

        throw new RuntimeException('pcntl_fork() failed — cannot run real-UDP fixture tests here');
    }

    if ($pid === 0) {
        socket_set_option($serverSocket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 3, 'usec' => 0]);

        $buffer = '';
        $fromIp = '';
        $fromPort = 0;
        $bytes = @socket_recvfrom($serverSocket, $buffer, 65535, 0, $fromIp, $fromPort);

        if ($bytes !== false) {
            $response = $respond($buffer);

            if ($response !== null) {
                socket_sendto($serverSocket, $response, strlen($response), 0, $fromIp, $fromPort);
            }
        }

        socket_close($serverSocket);
        exit(0);
    }

    socket_close($serverSocket);

    try {
        return $test($port);
    } finally {
        pcntl_waitpid($pid, $status);
    }
}

it('parses a real valid GameSpy-style reply received over a genuine UDP socket', function () {
    $result = withFakeUdpServer(
        fn (string $request) => $request === '\\query' ? '\\hostname\\Real Test Server\\numplayers\\2\\player_0\\Alice\\player_1\\Bob\\' : null,
        fn (int $port) => (new QueryServer)->query('127.0.0.1', $port, 2),
    );

    expect($result)->toBe([
        'hostname' => 'Real Test Server',
        'numplayers' => '2',
        'player_0' => 'Alice',
        'player_1' => 'Bob',
    ]);
});

it('parses correctly regardless of key order in the real reply', function () {
    $result = withFakeUdpServer(
        fn () => '\\numplayers\\1\\hostname\\Reordered Server\\player_0\\Zed\\',
        fn (int $port) => (new QueryServer)->query('127.0.0.1', $port, 2),
    );

    expect($result)->toBe([
        'numplayers' => '1',
        'hostname' => 'Reordered Server',
        'player_0' => 'Zed',
    ]);
});

it('does not crash on a malformed reply with no backslash delimiters', function () {
    $result = withFakeUdpServer(
        fn () => 'not a valid gamespy response',
        fn (int $port) => (new QueryServer)->query('127.0.0.1', $port, 2),
    );

    expect($result)->toBe([]);
});

it('does not crash on a malformed reply with an odd number of tokens', function () {
    $result = withFakeUdpServer(
        fn () => '\\hostname\\Orphan Key\\numplayers',
        fn (int $port) => (new QueryServer)->query('127.0.0.1', $port, 2),
    );

    expect($result)->toBe(['hostname' => 'Orphan Key']);
});

it('times out and reports an error when the real server never replies', function () {
    $query = new QueryServer;

    $result = withFakeUdpServer(
        fn () => null,
        fn (int $port) => $query->query('127.0.0.1', $port, 1),
    );

    expect($result)->toBeFalse()
        ->and($query->getError())->not->toBeNull();
});

it('rejects an invalid IP before ever touching the network', function () {
    $query = new QueryServer;

    expect($query->query('not-an-ip', 12345))->toBeFalse()
        ->and($query->getError())->toBe('Invalid IP address');
});

it('rejects an out-of-range port before ever touching the network', function () {
    $query = new QueryServer;

    expect($query->query('127.0.0.1', 70000))->toBeFalse()
        ->and($query->getError())->toBe('Invalid port');
});
