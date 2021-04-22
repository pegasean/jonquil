<?php

declare(strict_types=1);

namespace Jonquil\Cache;

/**
 * APC Store
 * @package Jonquil\Cache
 */
class ApcStore implements CacheInterface
{
    const DEFAULT_LIFETIME = 600;
    const PREFIX_SEPARATOR = '::';

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'extension_not_loaded' => 'The APC extension is not loaded',
    ];

    /**
     * @var integer The default lifetime of a cache item in seconds
     */
    protected $lifetime;

    /**
     * @var string Prefix to be prepended to each key
     */
    protected $prefix = '';

    /**
     * Initializes the class properties
     *
     * @param int $lifetime
     */
    public function __construct(int $lifetime = 0)
    {
        if (!extension_loaded('apc')) {
            throw new \RuntimeException(
                self::$errors['extension_not_loaded']
            );
        }

        $this->lifetime = !empty($lifetime)
            ? $lifetime : self::DEFAULT_LIFETIME;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        return apc_fetch($this->prefix($id));
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $id, $data, int $lifetime = 0): bool
    {
        if (empty($lifetime)) {
            $lifetime = $this->lifetime;
        }

        return apc_store($this->prefix($id), $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $id): bool
    {
        return apc_delete($this->prefix($id));
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): bool
    {
        return apc_clear_cache('user');
    }

    /**
     * Returns a key prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Sets a key prefix.
     *
     * @param string $prefix
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Prepends the prefix to a key.
     *
     * @param string $id
     * @return string
     */
    protected function prefix(string $id): string
    {
        if (empty($this->prefix)) {
            return $id;
        } else {
            return $this->prefix . static::PREFIX_SEPARATOR . $id;
        }
    }
}

// -- End of file
