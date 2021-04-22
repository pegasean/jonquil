<?php

declare(strict_types=1);

namespace Jonquil\Foundation;

/**
 * Class Autoloader
 * @package Jonquil\Foundation
 */
class Autoloader
{
    const NAMESPACE_SEPARATOR = '\\';

    /**
     * An associative array where the key is a namespace prefix and the value
     * is an array of base directories for classes in that namespace
     *
     * @var array
     */
    protected $prefixes = [];

    /**
     * Registers a loader with SPL autoloader stack
     */
    public function register()
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Adds a base directory for a namespace prefix.
     *
     * @param string $prefix The namespace prefix
     * @param string $baseDir A base directory for class files in the
     * namespace
     * @param bool $prepend If true, prepend the base directory to the stack
     * instead of appending it; this causes it to be searched first rather
     * than last
     */
    public function addNamespace(
        string $prefix,
        string $baseDir,
        bool $prepend = false
    ) {
        // Normalize namespace prefix
        $prefix = trim($prefix, self::NAMESPACE_SEPARATOR) . self::NAMESPACE_SEPARATOR;

        // Normalize base directory path
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // Initialize the namespace prefix array
        if (isset($this->prefixes[$prefix]) === false) {
            $this->prefixes[$prefix] = [];
        }

        // Retain the base directory for the namespace prefix
        if ($prepend) {
            array_unshift($this->prefixes[$prefix], $baseDir);
        } else {
            array_push($this->prefixes[$prefix], $baseDir);
        }
    }

    /**
     * Loads the class file for a given class name.
     *
     * @param string $class The fully-qualified class name
     * @return mixed The mapped file name on success, or boolean false on
     * failure
     */
    public function loadClass(string $class)
    {
        // Current namespace prefix
        $prefix = $class;

        // Work backwards through the namespace names of the fully-qualified
        // class name to find a mapped file name
        while (false !== $pos = strrpos($prefix, self::NAMESPACE_SEPARATOR)) {

            // Retain the trailing namespace separator in the prefix
            $prefix = substr($class, 0, $pos + 1);

            // The remainder represents the relative class name
            $relativeClass = substr($class, $pos + 1);

            // Try to load a mapped file for the prefix and relative class
            $mappedFile = $this->loadMappedFile($prefix, $relativeClass);
            if ($mappedFile) {
                return $mappedFile;
            }

            // Remove the trailing namespace separator for the next iteration
            // of strrpos()
            $prefix = rtrim($prefix, self::NAMESPACE_SEPARATOR);
        }

        // A mapped file was never found
        return false;
    }

    /**
     * Loads the mapped file for a namespace prefix and relative class.
     *
     * @param string $prefix The namespace prefix
     * @param string $relativeClass The relative class name
     * @return mixed Boolean false if no mapped file can be loaded, or the
     * name of the mapped file that was loaded
     */
    protected function loadMappedFile(string $prefix, string $relativeClass)
    {
        // Are there any base directories for this namespace prefix
        if (isset($this->prefixes[$prefix]) === false) {
            return false;
        }

        // Look through base directories for this namespace prefix
        foreach ($this->prefixes[$prefix] as $baseDir) {

            // Replace the namespace prefix with the base directory,
            // replace the namespace separators with directory separators
            // in the relative class name, append with .php
            $file = $baseDir . str_replace(self::NAMESPACE_SEPARATOR,
                    DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            // If the mapped file exists, require it
            if ($this->requireFile($file)) {
                return $file;
            }
        }

        // A file was not found
        return false;
    }

    /**
     * Requires a file from the file system.
     *
     * @param string $file The file to require
     * @return bool True if the file exists, false if not
     */
    protected function requireFile(string $file): bool
    {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        return false;
    }
}

// -- End of file
