<?php

declare(strict_types=1);

namespace Jonquil\Cache;

/**
 * File Store
 * @package Jonquil\Cache
 */
class FileStore implements CacheInterface
{
    const DEFAULT_LIFETIME = 3600;
    const DEFAULT_FILE_PREFIX = 'cache_';
    const FILE_EXTENSION = '.txt';

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'directory_not_writable'    => 'The directory "%s" is not writable',
        'undefined_directory'       => 'Undefined cache directory path',
    ];

    /**
     * @var string The path for storage/retrieval of cached data
     */
    protected $directory;

    /**
     * @var string A cache file name prefix
     */
    protected $prefix;

    /**
     * @var int The default lifetime of a cache item in seconds
     */
    protected $lifetime;

    /**
     * Initializes the class properties
     *
     * @param string $directory
     * @param string $prefix
     * @param int $lifetime
     */
    public function __construct(
        string $directory,
        string $prefix = '',
        int $lifetime = 0
    ) {
        if (empty($directory)) {
            throw new \InvalidArgumentException(
                self::$errors['undefined_directory']
            );
        } elseif (!is_writable($directory)) {
            throw new \InvalidArgumentException(
                sprintf(self::$errors['directory_not_writable'], $directory)
            );
        }

        $this->directory = $directory;
        $this->prefix = empty($prefix) ? self::DEFAULT_FILE_PREFIX : $prefix;
        $this->lifetime = empty($lifetime) ? self::DEFAULT_LIFETIME : $lifetime;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        $file = $this->getFilePath($id);

        if (file_exists($file)) {
            $data = file_get_contents($file) ;
            $expirationTime = substr($data, 0, 10);
            if (time() < $expirationTime) {
                return unserialize(substr($data, 10));
            } else {
                unlink($file);
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $id, $data, int $lifetime = 0): bool
    {
        $file = $this->getFilePath($id);

        if (!$lifetime) {
            $lifetime = $this->lifetime;
        }

        $lifetime = time() + $lifetime;

        return (bool) file_put_contents($file , $lifetime . serialize($data), LOCK_EX);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $id): bool
    {
        $file = $this->getFilePath($id);

        if (file_exists($file)) {
            unlink($file);
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): bool
    {
        $directory = opendir($this->directory);

        while (($file = readdir($directory)) !== false) {
            if (is_file($this->directory . $file)) {
                unlink($this->directory . $file);
            }
        }

        return true;
    }

    /**
     * Returns an absolute file path for a cached item
     *
     * @param string $id Cache item ID
     * @return string
     */
    protected function getFilePath(string $id): string
    {
        return $this->directory . $this->prefix
            . md5($id) . self::FILE_EXTENSION;
    }
}

// -- End of file
