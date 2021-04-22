<?php

declare(strict_types=1);

namespace Jonquil\Session;

/**
 * NULL Session Handler
 * @package Jonquil\Session
 */
class NullSessionHandler implements \SessionHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionId): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxLifetime): bool
    {
        return true;
    }
}

// -- End of file
