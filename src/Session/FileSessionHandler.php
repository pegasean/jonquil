<?php

declare(strict_types=1);

namespace Jonquil\Session;

/**
 * File Session Handler
 * @package Jonquil\Session
 */
class FileSessionHandler implements \SessionHandlerInterface
{
    const DEFAULT_FILE_PREFIX = 'session_';

    /**
     * @var string The path for storage/retrieval of session data
     */
    protected $savePath;

    /**
     * @var string A session file name prefix
     */
    protected $prefix;

    /**
     * Initializes the class properties.
     *
     * @param string $savePath Directory path for storing session files
     * @param string $prefix Prefix prepended to the name of every session file
     */
    public function __construct(
        string $savePath = '',
        string $prefix = ''
    ) {
        $savePath = trim($savePath, '/ ');
        if (empty($savePath)) {
            $savePath = sys_get_temp_dir();
        }

        $this->savePath = $savePath;

        if (is_dir($this->savePath) === false) {
            mkdir($this->savePath, 0777, true);
        }

        $this->prefix = empty($prefix) ? self::DEFAULT_FILE_PREFIX : $prefix;
    }

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
        $file = $this->getPath() . $sessionId;
        return touch($file) ? file_get_contents($file) : '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData): bool
    {
        $file = $this->getPath() . $sessionId;
        return file_put_contents($file, $sessionData) === false
            ? false : true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException When a session file is not accessible.
     */
    public function destroy($sessionId): bool
    {
        $file = $this->getPath() . $sessionId;
        if (is_file($file)) {
            unlink($file);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxLifetime): bool
    {
        foreach (glob($this->getPath() . '*') as $file) {
            if ((filemtime($file) + $maxLifetime) < time()) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Builds an absolute path to the file, including its prefix.
     *
     * @return string The path to a session file, including its prefix.
     */
    protected function getPath(): string
    {
        return $this->savePath . '/' . $this->prefix;
    }
}

// -- End of file
