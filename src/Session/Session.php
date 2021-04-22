<?php

declare(strict_types=1);

namespace Jonquil\Session;

use InvalidArgumentException;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use SessionHandlerInterface;

/**
 * Provides utilities for session management
 *
 * Some methods are derived from the HttpFoundation component of Symfony2.
 * The code is subject to the MIT license. For the full copyright, see:
 * @see http://symfony.com/doc/current/contributing/code/license.html
 *
 * @package Jonquil\Session
 */
class Session
{
    /**
     * @var string A key for the flash messages array stored in the session data
     */
    const FLASH_MESSAGES_KEY = '__FLASH';

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'invalid_handler'       => 'Invalid session handler',
        'failed_start'          => 'Failed to start the session',
        'headers_sent'          => 'Failed to start the session because'
            . ' the headers have already been sent',
        'cannot_change_id'      => 'The ID of an active session'
            . ' cannot be changed',
        'cannot_change_name'    => 'The name of an active session'
            . ' cannot be changed',
    ];

    /**
     * @var array Initialization options
     */
    protected static $options = [
        'auto_start',
        'cache_limiter',
        'cookie_domain',
        'cookie_httponly',
        'cookie_lifetime',
        'cookie_path',
        'cookie_secure',
        'entropy_file',
        'entropy_length',
        'gc_divisor',
        'gc_maxlifetime',
        'gc_probability',
        'hash_bits_per_character',
        'hash_function',
        'name',
        'referer_check',
        'serialize_handler',
        'use_cookies',
        'use_only_cookies',
        'use_trans_sid',
        'upload_progress.enabled',
        'upload_progress.cleanup',
        'upload_progress.prefix',
        'upload_progress.name',
        'upload_progress.freq',
        'upload_progress.min-freq',
        'url_rewriter.tags',
    ];

    /**
     * @var boolean Is the session started?
     */
    protected $started;

    /**
     * @var boolean Is the session closed?
     */
    protected $closed;

    /**
     * @var array Session save handler
     */
    protected $handler;

    /**
     * @var array Session data
     */
    protected $data;

    /**
     * Initializes the session properties.
     *
     * @param SessionHandlerInterface $handler Session handler
     * @param array $options Session options
     * @throws InvalidArgumentException If an invalid handler is passed.
     */
    public function __construct(
        SessionHandlerInterface $handler,
        array $options
    ) {
        $this->setOptions($options);

        if (version_compare(phpversion(), '5.4.0', '>=')) {
            session_register_shutdown();
        }
        else {
            register_shutdown_function('session_write_close');
        }

        $this->started = false;
        $this->closed = false;
        $this->handler = null;
        $this->data = [];

        $this->setSaveHandler($handler);
    }

    /**
     * Starts the session.
     *
     * @throws \RuntimeException If a problem occurs while starting the session
     * @return bool
     */
    public function start(): bool
    {
        // The session has been started
        if ($this->started && !$this->closed) {
            return true;
        }

        if (ini_get('session.use_cookies') && headers_sent()) {
            throw new \RuntimeException(self::$errors['headers_sent']);
        }

        // Start the session
        if (!session_start()) {
            throw new \RuntimeException(self::$errors['failed_start']);
        }

        $this->loadSession();

        return true;
    }

    /**
     * Checks whether the session is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->exists('auth_user');
    }

    /**
     * Returns a value from the session data.
     *
     * @param string $key An array key
     * @param mixed $default A default value
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->exists($key) ? $this->data[$key] : $default;
    }

    /**
     * Returns and deletes a value from the session data.
     *
     * @param string $key An array key
     * @param mixed $default A default value
     * @return mixed
     */
    public function steal(string $key, $default = null)
    {
        $value = $this->get($key, $default);

        unset($this->data[$key]);

        return $value;
    }

    /**
     * Adds or updates a value from the session data.
     *
     * @param string $key An array key
     * @param mixed $value A new value
     * @return $this
     */
    public function set(string $key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Removes values from the session data.
     *
     * @param string $key An array key
     * @param ...
     * @return $this
     */
    public function delete(string $key)
    {
        $arguments = func_get_args();

        foreach ($arguments as $key) {
            unset($this->data[$key]);
        }

        return $this;
    }

    /**
     * Checks whether a session key exists.
     *
     * @param string $key An array key
     * @return bool
     */
    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Returns the session ID.
     *
     * @return string
     */
    public function getId(): string
    {
        if (!$this->started) {
            return '';
        }

        return session_id();
    }

    /**
     * Sets the session ID.
     *
     * @param string $id
     */
    public function setId($id)
    {
        if ($this->started) {
            throw new \RuntimeException(self::$errors['cannot_change_id']);
        }
        session_id($id);
    }

    /**
     * Returns the session name.
     *
     * @return string
     */
    public function getName(): string
    {
        return session_name();
    }

    /**
     * Sets the session name.
     *
     * @param string $name
     */
    public function setName(string $name)
    {
        if ($this->started) {
            throw new \RuntimeException(self::$errors['cannot_change_name']);
        }
        session_name($name);
    }

    /**
     * Regenerates an ID that represents this storage.
     *
     * @param bool $destroy Destroy session when regenerating?
     * @param int $lifetime Sets the cookie lifetime for the session cookie.
     * A null value will leave the system settings unchanged, 0 sets the cookie
     * to expire with browser session. It is in seconds.
     * @return bool True if session regenerated, false if an error occurs
     * @throws \RuntimeException
     */
    public function regenerate(
        bool $destroy = false,
        int $lifetime = 0
    ): bool {
        if ($lifetime !== 0) {
            ini_set('session.cookie_lifetime', $lifetime);
        }
        return session_regenerate_id($destroy);
    }

    /**
     * Forces the session to be saved and closed.
     */
    public function save()
    {
        session_write_close();
        $this->closed = true;
    }

    /**
     * Clears all session data in memory.
     */
    public function clear()
    {
        $this->data = [];
        $_SESSION = [];
    }

    /**
     * Destroys a session.
     */
    public function destroy()
    {
        session_unset();
        $this->clear();
        session_destroy();
        $this->started = false;
        $this->closed = false;
    }

    /**
     * Sets session.* ini variables.
     *
     * For convenience we omit 'session.' from the beginning of the keys.
     * Explicitly ignores other ini keys.
     *
     * @param array $options session ini directives array(key => value).
     *
     * @see http://php.net/session.configuration
     */
    public function setOptions(array $options)
    {
        $validOptions = array_flip(self::$options);

        foreach ($options as $key => $value) {
            if (isset($validOptions[$key])) {
                ini_set('session.' . $key, (string) $value);
            }
        }
    }

    /**
     * Registers save handler as a PHP session handler.
     *
     * To use internal PHP session save handlers, override this method
     * using ini_set with session.save_handlers and session.save_path e.g.
     *
     *     ini_set('session.save_handlers', 'files');
     *     ini_set('session.save_path', /temp');
     *
     * @see http://php.net/session-set-save-handler
     * @see http://php.net/sessionhandlerinterface
     * @see http://php.net/sessionhandler
     *
     * @param object $saveHandler Save handler
     */
    public function setSaveHandler($saveHandler)
    {
        if ($saveHandler instanceof SessionHandlerInterface) {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                session_set_save_handler($saveHandler, false);
            }
            else {
                session_set_save_handler(
                    [$saveHandler, 'open'],
                    [$saveHandler, 'close'],
                    [$saveHandler, 'read'],
                    [$saveHandler, 'write'],
                    [$saveHandler, 'destroy'],
                    [$saveHandler, 'gc']
                );
            }
            $this->handler = $saveHandler;
        }
        else {
            throw new InvalidArgumentException(
                self::$errors['invalid_handler']
            );
        }
    }

    /**
     * Load the session with attributes.
     *
     * After starting the session, PHP retrieves the session from whatever
     * handlers are set to (either PHP's internal, or a custom save handler
     * set with session_set_save_handler()). PHP takes the return value from
     * the read() handler, unserializes it and populates $_SESSION
     * with the result automatically.
     */
    protected function loadSession()
    {
        $this->data = &$_SESSION;

        // Register the flash message container
        if (!$this->exists(self::FLASH_MESSAGES_KEY)) {
            $this->data[self::FLASH_MESSAGES_KEY] = [];
        }

        $this->started = true;
        $this->closed = false;
    }

    /**
     * Adds a flash message for a type.
     *
     * @param string $type
     * @param string|array $message
     */
    public function addMessage(string $type, $message)
    {
        if (is_array($message)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveArrayIterator($message)
            );
            $message = [];
            foreach ($iterator as $msg) {
                $message[] = (string) $msg;
            }
        } else {
            $message = (string) $message;
        }
        $this->data[self::FLASH_MESSAGES_KEY][$type][] = $message;
    }

    /**
     * Returns flash message for a given type.
     *
     * @param string $type Message category type
     * @param array $default Default value if $type doee not exist
     * @return array
     */
    public function peekMessages(string $type, array $default = []): array
    {
        return $this->hasMessages($type)
            ? $this->data[self::FLASH_MESSAGES_KEY][$type] : $default;
    }

    /**
     * Returns all flash messages.
     *
     * @return array
     */
    public function peekAllMessages(): array
    {
        return $this->data[self::FLASH_MESSAGES_KEY];
    }

    /**
     * Returns and clears flash messages for a type from the stack.
     *
     * @param string $type
     * @param array $default Default value if $type does not exist
     * @return array
     */
    public function getMessages(string $type, array $default = []): array
    {
        if (!$this->hasMessages($type)) {
            return $default;
        }

        $messages = $this->data[self::FLASH_MESSAGES_KEY][$type];

        unset($this->data[self::FLASH_MESSAGES_KEY][$type]);

        return $messages;
    }

    /**
     * Returns and clears all flash messages from the stack.
     *
     * @return array
     */
    public function getAllMessages(): array
    {
        $messages = $this->peekAllMessages();
        $this->data[self::FLASH_MESSAGES_KEY] = [];

        return $messages;
    }

    /**
     * Has flash messages for a given type?
     *
     * @param string $type
     * @return bool
     */
    public function hasMessages(string $type = ''): bool
    {
        if (empty($type)) {
            return count($this->data[self::FLASH_MESSAGES_KEY]) > 0;
        } else {
            return $this->hasMessageType($type)
                && (count($this->data[self::FLASH_MESSAGES_KEY][$type]) > 0);
        }
    }

    /**
     * Returns a list of all defined types.
     *
     * @return array
     */
    public function getMessageTypes(): array
    {
        return array_keys($this->data[self::FLASH_MESSAGES_KEY]);
    }

    /**
     * Is a flash message type registered?
     *
     * @param string $type
     * @return bool
     */
    public function hasMessageType(string $type): bool
    {
        return array_key_exists($type, $this->data[self::FLASH_MESSAGES_KEY]);
    }

    /**
     * Session object is rendered to a serialized string.
     * The output string is encoded using base64_encode.
     *
     * @return string
     */
    public function __toString(): string
    {
        // Serialize the data array
        $data = serialize($this->data);

        // Obfuscate the data with base64 encoding
        $data = base64_encode($data);

        return $data;
    }
}

// -- End of file
