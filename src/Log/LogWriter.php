<?php

declare(strict_types=1);

namespace Jonquil\Log;

/**
 * Class LogWriter
 * @package Jonquil\Log
 */
class LogWriter
{
    const FILE_PERMISSIONS = 0666;
    const FILE_EXTENSION = '.log.txt';
    const LINE_LENGTH = 80;
    const LINE_SEPARATOR_PATTERN = '=';

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'directory_not_writable'    => 'The directory "%s" is not writable',
        'undefined_date_format'     => 'Undefined date format',
        'undefined_directory'       => 'Undefined directory path',
    ];

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $dateFormat;

    /**
     * Initializes the class properties.
     * @param string $directory
     * @param string $dateFormat
     */
    public function __construct(string $directory, string $dateFormat)
    {
        if (empty($directory)) {
            throw new \InvalidArgumentException(
                self::$errors['undefined_directory']
            );
        } elseif (!is_writable($directory)) {
            throw new \InvalidArgumentException(
                sprintf(self::$errors['directory_not_writable'], $directory)
            );
        } elseif (empty($dateFormat)) {
            throw new \InvalidArgumentException(
                self::$errors['undefined_date_format']
            );
        }

        $this->dateFormat = $dateFormat;
        $this->directory = $directory;
    }

    /**
     * @param string $message
     */
    public function write(string $message)
    {
		// Define the file path
		$file = $this->directory . DIRECTORY_SEPARATOR . date('Y_m_d')
            . self::FILE_EXTENSION;

		if (!file_exists($file)) {
			// Create a new file
			file_put_contents($file, $this->getFileHeader() . PHP_EOL);
			// Set its permissions
			chmod($file, self::FILE_PERMISSIONS);
		}

        $output = date($this->dateFormat) . ' ';
        $output .= $message . PHP_EOL;

        file_put_contents($file, $output, FILE_APPEND);
    }

    /**
     * @return string
     */
    protected function getFileHeader(): string
    {
        return $this->getLineSeparator()
            . date('F j, Y') . PHP_EOL
            . $this->getLineSeparator();
    }

    /**
     * @param string $pattern
     * @param int $length
     * @param bool $appendLineBreak
     * @return string
     */
    protected function getLineSeparator(
        string $pattern = '',
        int $length = 0,
        bool $appendLineBreak = true
    ): string {
        if (strlen($pattern) < 1) {
            $pattern = self::LINE_SEPARATOR_PATTERN;
        }
        if ($length < 1) {
            $length = self::LINE_LENGTH;
        }

        $separator = str_repeat($pattern, (int) ceil($length / strlen($pattern)));
        $separator = substr($separator, 0, $length);

        if ($appendLineBreak) {
            $separator .= PHP_EOL;
        }

        return $separator;
    }
}

// -- End of file
