<?php

declare(strict_types=1);

namespace App\Token;

interface TokenInterface
{
    /**
     * Return consumer identifier (from token).
     */
    public function getConsumer(): string;

    /**
     * Return timestamp when the token is issued (from token).
     */
    public function getTimestamp(): int;

    /**
     * Check if token is valid.
     */
    public function check(string $secret): bool;
}
