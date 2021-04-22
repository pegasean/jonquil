<?php

declare(strict_types=1);

namespace Jonquil\Configuration;

use ArrayAccess;
use LogicException;

/**
 * Class Repository
 * @package Jonquil\Configuration
 */
class Repository implements ArrayAccess
{
    /**
     * @var array Error messages
     */
    protected static $errors = [
        'changes_not_allowed'   => 'Modifications are not allowed',
        'key_already_exists'    => 'The key "%s" already exists',
    ];

	/**
	 * @var bool Is it mutable?
	 */
    protected $allowChanges;

	/**
	 * @var array The configuration data
	 */
    protected $data;

    /**
     * Initializes the class properties.
     *
     * @param array $data Application configuration
     * @param bool $allowChanges If the configuration data may be altered
     */
    public function __construct(array $data, bool $allowChanges = false)
    {
        $this->allowChanges = $allowChanges;
        $this->data = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->data[$key] = new self($value, $this->allowChanges);
            } else {
                $this->data[$key] = $value;
            }
        }
    }

	/**
	 * Returns a value, associated with a key.
	 *
     * @param string $key
     * @param mixed $default
	 * @return mixed
	 */
    public function get(string $key, $default = null)
    {
        return $this->exists($key) ? $this->data[$key] : $default;
    }

	/**
	 * Sets a value, associated with a key.
     *
     * @param string $key
     * @param mixed $value
	 */
    public function set(string $key, $value)
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if ($this->exists($key)) {
            if (is_array($value)) {
                $this->data[$key] = new self($value, true);
            } else {
                $this->data[$key] = $value;
            }
        }
    }

	/**
	 * Adds a new value to the configuration.
     *
     * @param string $key
     * @param mixed $value
	 */
    public function add(string $key, $value)
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        } elseif ($this->exists($key)) {
            throw new LogicException(
                sprintf(static::$errors['key_already_exists'], $key)
            );
        }

        if ($key) {
            if (is_array($value)) {
                $this->data[$key] = new self($value, true);
            } else {
                $this->data[$key] = $value;
            }
        }
    }

	/**
	 * Removes a value from the configuration.
     *
     * @param string $key
	 */
    public function remove(string $key)
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if ($this->exists($key)) {
            unset($this->data[$key]);
        }
    }

	/**
	 * Returns the configuration data as an array.
	 *
	 * @return array
	 */
    public function toArray(): array
    {
        $array = [];
        foreach ($this->data as $key => $value) {
            if ($value instanceof self) {
                $array[$key] = $value->toArray();
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }

	/**
	 * Returns all keys of the data array.
	 *
	 * @return array
	 */
    public function getArrayKeys(): array
    {
        $array = [];
        foreach ($this->data as $key => $value) {
            $array[] = $key;
        }
        return $array;
    }

	/**
	 * Checks whether a key exists.
	 *
     * @param string $key
	 * @return boolean
	 */
    public function exists($key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Checks whether the configuration data is immutable.
     *
     * @return bool
     */
    public function isImmutable(): bool
    {
        return !$this->allowChanges;
    }

	/**
	 * Makes the configuration data immutable.
	 */
    public function makeImmutable()
    {
        $this->allowChanges = false;
        foreach ($this->data as $key => $value) {
            if ($value instanceof self) {
                $value->makeImmutable();
            }
        }
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->exists($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function __set(string $key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __isset(string $key)
    {
        return $this->exists($key);
    }

    /**
     * @param string $key
     */
    public function __unset(string $key)
    {
        $this->remove($key);
    }
}

// -- End of file
