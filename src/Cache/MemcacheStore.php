<?php

declare(strict_types=1);

namespace Jonquil\Cache;

/**
 * Memcache Store
 * @package Jonquil\Cache
 */
class MemcacheStore implements CacheInterface
{
    const DEFAULT_LIFETIME = 600;

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'extension_not_loaded'  => 'The Memcache extension is not loaded',
        'undefined_host'        => 'Undefined host',
        'undefined_port'        => 'Undefined port',
        'connection_failure'    => 'Unable to connect to the Memcache server',
    ];

    /**
     * @var \Memcache A memcache object
     */
    protected $memcache;

    /**
     * @var integer The default lifetime of a cache item in seconds
     */
    protected $lifetime;

    /**
     * Initializes the class properties
     *
     * @param \Memcache $memcache
     * @param string $host
     * @param int $port
     * @param int $lifetime
     */
    public function __construct(
        \Memcache $memcache,
        string $host,
        int $port,
        int $lifetime = 0
    ) {
        if (!extension_loaded('memcache')) {
            throw new \RuntimeException(
                self::$errors['extension_not_loaded']
            );
        } elseif (empty($host)) {
            throw new \InvalidArgumentException(
                self::$errors['undefined_host']
            );
        } elseif (empty($port)) {
            throw new \InvalidArgumentException(
                self::$errors['undefined_port']
            );
        }

        $this->memcache = $memcache;
        $isConnected = $this->memcache->connect($host, $port);

        if (!$isConnected) {
            throw new \RuntimeException(self::$errors['connection_failure']);
        }

        $this->lifetime = empty($lifetime) ? self::DEFAULT_LIFETIME : $lifetime;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        return $this->memcache->get($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $compressed Use Zlib compression
     */
    public function set(
        string $id,
        $data,
        int $lifetime = 0,
        bool $compressed = false
    ): bool {
        if (empty($lifetime)) {
            $lifetime = $this->lifetime;
        }

        $flag = $compressed ? MEMCACHE_COMPRESSED : 0;

        return $this->memcache->set($id, $data, $flag, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $id): bool
    {
        return $this->memcache->delete($id);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): bool
    {
        return $this->memcache->flush();
    }
}

// -- End of file
