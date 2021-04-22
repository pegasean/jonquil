<?php

declare(strict_types=1);

namespace Jonquil\Exception;

use Jonquil\Log\LogWriter;

/**
 * Class ExceptionHandler
 * @package Jonquil\Exception
 */
class ExceptionHandler
{
    /**
     * @var array Error messages
     */
    protected static $errors = [
        'error_page_not_readable'    => 'The error page "%s" is not readable',
    ];

    /**
     * @var LogWriter
     */
    protected $logger;

    /**
     * @var string
     */
    protected $errorPage;

    /**
     * Initializes the class properties.
     *
     * @param LogWriter $logger
     * @param string $errorPage
     */
    public function __construct(LogWriter $logger, string $errorPage = '')
    {
        if (!empty($errorPage) && !is_readable($errorPage)) {
            throw new \InvalidArgumentException(
                sprintf(self::$errors['error_page_not_readable'], $errorPage)
            );
        }
        $this->errorPage = $errorPage;
        $this->logger = $logger;
    }

    /**
     * @param \Throwable $throwable
     * @param bool $terminate
     */
    public function reportException(
        \Throwable $throwable,
        bool $terminate = true
    ) {
        if ($terminate) {
            while (ob_get_level()) {
                ob_end_clean();
            }
        }

        $message = !($throwable instanceof \ErrorException)
            ? 'EXCEPTION: ' : '';
        $message .= $throwable->getMessage() . PHP_EOL;
        $message .= $this->getTraceAsString($throwable);

        $this->logger->write($message);

        if (ini_get('display_errors')) {
            print('<pre>' . $message . '</pre>');
            if ($terminate) {
                exit(1);
            }
        }
        elseif ($terminate) {
            $this->exit();
        }
    }

    /**
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     */
    public function reportError($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            // The error code is not included in error_reporting
            return;
        }

        $terminate = false;

        switch ($errno) {
            case E_USER_NOTICE:
            case E_NOTICE:
            case E_STRICT:
            case E_USER_DEPRECATED:
            case E_DEPRECATED:
                $errorType = 'NOTICE';
                break;
            case E_USER_WARNING:
            case E_CORE_WARNING:
            case E_WARNING:
                $errorType = 'WARNING';
                break;
            default:
                $errorType = 'ERROR';
                $terminate = true;
                break;
        }

        $msg = $errorType . ': ' . $errstr;
        $exception = new \ErrorException($msg, 0, $errno, $errfile, $errline);
        $this->reportException($exception, $terminate);
    }

    /**
     * @param \Throwable $throwable
     * @return string
     */
    public function getTraceAsString(\Throwable $throwable): string
    {
        $message = '';
        $count = 0;
        foreach ($throwable->getTrace() as $frame) {
            $args = '';
            if (isset($frame['args'])) {
                $args = [];
                foreach ($frame['args'] as $arg) {
                    if (is_string($arg)) {
                        $args[] = "'" . $arg . "'";
                    } elseif (is_array($arg)) {
                        $args[] = 'Array';
                    } elseif (is_null($arg)) {
                        $args[] = 'NULL';
                    } elseif (is_bool($arg)) {
                        $args[] = ($arg) ? 'true' : 'false';
                    } elseif (is_object($arg)) {
                        $args[] = get_class($arg);
                    } elseif (is_resource($arg)) {
                        $args[] = get_resource_type($arg);
                    } else {
                        $args[] = $arg;
                    }
                }
                $args = join(', ', $args);
            }
            $file = isset($frame['file'])
                ? $frame['file'] : '[internal function]';
            $line = isset($frame['line'])
                ? '(' . $frame['line'] . ')' : '';
            $function = isset($frame['class'])
                ? $frame['class'] . $frame['type'] . $frame['function']
                : $frame['function'];
            $message .= sprintf(
                '[%02s] %s%s: %s(%s)' . PHP_EOL,
                $count,
                $file,
                $line,
                $function,
                $args
            );
            $count++;
        }
        return $message;
    }

    /**
     * Terminates the current request.
     *
     * @param bool $displayErrorPage Whether to display a custom error page
     */
    protected function exit(bool $displayErrorPage = true)
    {
        if(!headers_sent()) {
            header('HTTP/1.0 500 Internal Server Error');
        }
        if ($displayErrorPage && is_readable($this->errorPage)) {
            include $this->errorPage;
        }
        exit(1);
    }
}

// -- End of file
