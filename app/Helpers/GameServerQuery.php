<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * A live UDP query against a Halo game server (see docs/database.md's "QueryServer UDP
 * protocol" section). Bound to a concrete implementation in AppServiceProvider so tests can
 * swap in a fake without opening a real socket.
 */
interface GameServerQuery
{
    /**
     * @return array<string, string>|false Parsed key/value response (e.g. `hostname`,
     *                                     `numplayers`, `player_0`), or `false` on any failure — check `getError()` for why.
     */
    public function query(string $ip, int $port, int $timeoutSeconds = 2): array|false;

    public function getError(): ?string;
}
