<?php

namespace App\Helpers;

/**
 * Real UDP implementation of the GameSpy-style `\query\` protocol Halo PC/CE dedicated
 * servers respond to. Rebuilt from `QueryServer.php-legacy` — the request/response wire
 * format is unchanged, but responses are now parsed into genuine key/value pairs instead of
 * the legacy code's fragile fixed-array-offset reads (`numplayers` at a hardcoded index,
 * player/score/ping/team arrays at hardcoded slot offsets). See docs/database.md.
 */
class QueryServer implements GameServerQuery
{
    private ?string $errorText = null;

    public function query(string $ip, int $port, int $timeoutSeconds = 2): array|false
    {
        $this->errorText = null;

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->errorText = 'Invalid IP address';

            return false;
        }

        if ($port < 1 || $port > 65535) {
            $this->errorText = 'Invalid port';

            return false;
        }

        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket === false) {
            $this->errorText = socket_strerror(socket_last_error());

            return false;
        }

        try {
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeoutSeconds, 'usec' => 0]);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $timeoutSeconds, 'usec' => 0]);

            if (@socket_connect($socket, $ip, $port) === false) {
                $this->errorText = socket_strerror(socket_last_error($socket));

                return false;
            }

            // Wire format is the literal 6 bytes `\query` (no trailing backslash) — confirmed
            // from the legacy implementation's `socket_send($sock, "\\query", 6, ...)` call.
            if (@socket_send($socket, '\\query', 6, 0) === false) {
                $this->errorText = socket_strerror(socket_last_error($socket));

                return false;
            }

            $buffer = '';
            $bytes = @socket_recv($socket, $buffer, 10000, 0);

            if ($bytes === false || $bytes === 0) {
                $this->errorText = $bytes === false
                    ? socket_strerror(socket_last_error($socket))
                    : 'No response received';

                return false;
            }
        } finally {
            socket_close($socket);
        }

        return $this->parse($buffer);
    }

    public function getError(): ?string
    {
        return $this->errorText;
    }

    /**
     * The response is one backslash-delimited string of alternating key/value tokens, e.g.
     * `\hostname\Foo Server\numplayers\2\player_0\Alice\player_1\Bob\`. The leading token
     * (before the first backslash) and a trailing empty token (after a trailing backslash, if
     * present) carry no data and are dropped; everything else pairs up as key => value.
     *
     * @return array<string, string>
     */
    private function parse(string $buffer): array
    {
        $tokens = explode('\\', $buffer);
        array_shift($tokens);

        if (count($tokens) % 2 !== 0 && end($tokens) === '') {
            array_pop($tokens);
        }

        $data = [];

        for ($i = 0; $i + 1 < count($tokens); $i += 2) {
            $data[$tokens[$i]] = $tokens[$i + 1];
        }

        return $data;
    }
}
