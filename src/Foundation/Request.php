<?php

declare(strict_types=1);

namespace Jonquil\Foundation;

use Jonquil\Configuration\Repository;

/**
 * Class Request
 * @package Jonquil\Foundation
 */
class Request
{
    const REQUEST_PARAMETER_NAME = 'request';
    const ERROR_FILE_EXTENSION = '.html';
    const LANGUAGE_CODE_LENGTH = 2;
    const URL_SEGMENT_SEPARATOR = '/';
	const URL_SEGMENT_LEVELS = [
		'base'          => 0,
		'language'      => 1,
		'module'        => 2,
		'controller'    => 3,
		'action'        => 4,
		'attributes'    => 5,
	];

    /**
     * @var array HTTP status codes and messages
     */
	public static $messages = [
		// Information
		100 => 'Continue',
		101 => 'Switching Protocols',

		// Success
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',

		// Redirection
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found', // 1.1
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		// 306 is deprecated but reserved
		307 => 'Temporary Redirect',

		// Client Error
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',

		// Server Error
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		509 => 'Bandwidth Limit Exceeded',
	];

    /**
     * @var array Error messages
     */
    protected static $errors = [];

	/**
	 * @var string The language of the response
	 */
    protected $language;

	/**
	 * @var string The application module to use
	 */
    protected $module;

	/**
	 * @var string The module controller to use
	 */
    protected $controller;

	/**
	 * @var string The controller action to use
	 */
    protected $action;

	/**
	 * @var array Request attributes
	 */
    protected $attributes;

	/**
	 * @var integer The server port for insecure requests
	 */
	protected $port;

	/**
	 * @var integer The server port for secure requests
	 */
    protected $securePort;

    /**
     * @var string A directory with custom error pages
     */
    protected $errorPagesDir;

    /**
     * Initializes the class properties.
     *
     * @param string $language
     * @param string $module
     * @param string $controller
     * @param string $action
     * @param string $errorPagesDir
     */
    public function __construct(
        string $language = '',
        string $module = '',
        string $controller = '',
        string $action = '',
        string $errorPagesDir = ''
    ) {
        $this->language = $language;
        $this->module = $module;
        $this->controller = $controller;
        $this->action = $action;
        $this->attributes = [];
        $this->port = null;
        $this->securePort = null;
        $this->errorPagesDir = $errorPagesDir;
    }

    /**
     * @param Repository $languages
     * @param Repository $modules
     * @param Repository $routes
     */
    public function parseRequestQuery(
        Repository $languages,
        Repository $modules,
        Repository $routes
    ) {
        $requestQuery = $this->getQuery(self::REQUEST_PARAMETER_NAME);
        if (isset($requestQuery)) {
            $requestQuery = trim(
                $requestQuery,
                self::URL_SEGMENT_SEPARATOR . ' '
            );
        }
        $request = $requestQuery
            ? explode(self::URL_SEGMENT_SEPARATOR, $requestQuery) : array();

        // Language code
        if (!empty($request)
            && (strlen($request[0]) === self::LANGUAGE_CODE_LENGTH)) {
            if ($languages->exists($request[0])) {
                $this->language = array_shift($request);
            } else {
                $this->abort(404);
            }
        }

        // Application module
        if (!empty($request) && $modules->exists($request[0])) {
            $this->module = array_shift($request);
        }

        // Module controller and controller action
        if (!empty($request)) {
            $ctrl = array_shift($request);
            if ($routes->exists($ctrl)) {
                if (is_string($routes->$ctrl)) {
                    $this->controller = $routes->$ctrl;
                }
                else {
                    $route = $routes->$ctrl->toArray();
                    $this->controller = $route[0] ?? '';
                    $this->action = $route[1] ?? '';
                    if (isset($route[2]) && is_array($route[2])) {
                        $request = $route[2];
                    }
                }
            }
            else {
                $this->controller = $ctrl;
                if (!empty($request)) {
                    $this->action = array_shift($request);
                }
            }
        }

        // Attributes
        $this->attributes = $request;
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * @return string
     */
    public function getModule(): string
    {
        return $this->module;
    }

    /**
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param int $index
     * @param string $cast
     * @return mixed
     */
    public function getAttribute(int $index, string $cast = null)
    {
        if (isset($this->attributes[$index])) {
            $value = trim($this->attributes[$index]);
            switch ($cast) {
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'boolean':
                    $value = (bool) $value;
                    break;
                case 'double':
                    $value = (float) $value;
                    break;
                default:
                    $value = (string) $value;
            }
        } else {
            $value = null;
        }
        return $value;
    }

    /**
     * @return int
     */
    public function getAttributeCount(): int
    {
        return count($this->attributes);
    }

	/**
	 * Returns a GET parameter value.
	 *
	 * If the GET parameter does not exist,
     * the $default argument will be returned.
	 *
	 * @param string $key The GET parameter key
	 * @param mixed $default A default return value
	 *
	 * @return mixed
	 */
	public function getQuery(string $key, $default = null)
	{
		return $_GET[$key] ?? $default;
	}

	/**
	 * Returns a POST parameter value.
	 *
	 * If the POST parameter does not exist,
     * the $default argument will be returned.
	 *
	 * @param string $key The POST parameter key
	 * @param mixed $default A default return value
	 *
	 * @return mixed
	 */
	public function getPost(string $key, $default = null)
	{
		return $_POST[$key] ?? $default;
	}

    /**
     * Returns a POST or GET parameter value (depending on the request type).
     *
     * If the POST/GET parameter does not exist,
     * the $default argument will be returned.
     *
     * @param string $key The POST/GET parameter key
     * @param mixed $default A default return value
     *
     * @return mixed
     */
    public function getParam(string $key, $default = null)
    {
        if ($this->isPostRequest()) {
            return $this->getPost($key, $default);
        }
        return $this->getQuery($key, $default);
    }

    /**
     * Returns a decoded POST or GET parameter value.
     *
     * @param string $key
     * @param bool $isJsonEncoded
     *
     * @return array|string
     */
    public function getDecodedParam(string $key, bool $isJsonEncoded = false)
    {
        $param = base64_decode($this->getParam($key, ''), true);
        if ($param === false) {
            return $isJsonEncoded ? [] : '';
        }
        if ($isJsonEncoded) {
            $param = json_decode($param, true);
            if (is_null($param)) {
                return [];
            }

        }
        return $param;
    }

	/**
	 * Returns the query string, if any, via which the page was accessed.
	 *
	 * @return string
	 */
	public function getQueryString(): string
	{
		return isset($_SERVER['QUERY_STRING'])
            ? $_SERVER['QUERY_STRING'] : '';
	}

	/**
	 * Returns the request type (GET, POST, HEAD, PUT, DELETE).
	 *
	 * @return string
	 */
	public function getRequestType(): string
	{
		return isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
			: 'GET';
	}

    /**
     * Checks whether this is a GET request.
     *
     * @return bool
     */
    public function isGetRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD'])
            && !strcasecmp($_SERVER['REQUEST_METHOD'], 'GET');
    }

	/**
	 * Checks whether this is a POST request.
	 *
	 * @return bool
	 */
	public function isPostRequest(): bool
	{
		return isset($_SERVER['REQUEST_METHOD'])
            && !strcasecmp($_SERVER['REQUEST_METHOD'], 'POST');
	}

    /**
     * Checks whether this is a PUT request.
     *
     * @return bool
     */
    public function isPutRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD'])
            && !strcasecmp($_SERVER['REQUEST_METHOD'], 'PUT');
    }

	/**
	 * Checks whether this is a DELETE request.
	 *
	 * @return bool
	 */
	public function isDeleteRequest(): bool
	{
		return isset($_SERVER['REQUEST_METHOD'])
            && !strcasecmp($_SERVER['REQUEST_METHOD'], 'DELETE');
	}

	/**
	 * Checks whether the request is sent via a secure channel (https).
	 *
	 * @return bool
	 */
	public function isSecureConnection(): bool
	{
		return isset($_SERVER['HTTPS'])
            && !strcasecmp($_SERVER['HTTPS'], 'on');
	}

	/**
	 * Returns the server name.
	 *
	 * @return string
	 */
	public function getServerName(): string
	{
		return $_SERVER['SERVER_NAME'];
	}

	/**
	 * Returns the server's port number.
	 *
	 * @return int
	 */
	public function getServerPort(): int
	{
		return $_SERVER['SERVER_PORT'];
	}

	/**
	 * Returns the address of the page (if any) which referred the user agent
	 * to the current page.
	 *
	 * @return mixed
	 */
	public function getUrlReferer()
	{
		return isset($_SERVER['HTTP_REFERER'])
            ? $_SERVER['HTTP_REFERER'] : null;
	}

	/**
	 * Returns the user agent (if any).
	 *
	 * @return mixed
	 */
	public function getUserAgent()
	{
		return isset($_SERVER['HTTP_USER_AGENT'])
            ? $_SERVER['HTTP_USER_AGENT'] : null;
	}

    /**
     * Returns the host name from which the user is viewing the current page
     *
     * @return mixed
     */
    public function getUserHost()
    {
        return isset($_SERVER['REMOTE_HOST'])
            ? $_SERVER['REMOTE_HOST'] : null;
    }

	/**
	 * Returns the user's IP address.
	 *
	 * @return string
	 */
    public function getUserHostAddress(): string
    {
        if (getenv('HTTP_CLIENT_IP')
            && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            return getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')
            && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            return getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR')
            && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            return getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR'])
            && $_SERVER['REMOTE_ADDR']
            && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '';
    }

	/**
	 * Returns the document types which are accepted by the user's browser
	 *
	 * @return mixed
	 */
	public function getAcceptTypes()
	{
		return isset($_SERVER['HTTP_ACCEPT'])
            ? $_SERVER['HTTP_ACCEPT'] : null;
	}

 	/**
	 * Returns the port to use for insecure requests.
	 *
	 * Defaults to 80, or the port specified by the server if the current
	 * request is insecure.
	 *
	 * @return int
	 */
	public function getPort(): int
	{
		if (is_null($this->port)) {
			$this->port = (!$this->isSecureConnection()
                && isset($_SERVER['SERVER_PORT']))
                ? (int) $_SERVER['SERVER_PORT'] : 80;
        }

		return $this->port;
	}

	/**
	 * Returns the port to use for secure requests.
	 *
	 * Defaults to 443, or the port specified by the server if the current
	 * request is secure.
	 *
	 * @return int
	 */
	public function getSecurePort(): int
	{
		if (is_null($this->securePort)) {
			$this->securePort = ($this->isSecureConnection()
                && isset($_SERVER['SERVER_PORT']))
                ? (int) $_SERVER['SERVER_PORT'] : 443;
        }

		return $this->securePort;
	}

	/**
	 * Returns the URI which was given in order to access this page.
	 *
	 * @return string
	 */
	public function getRequestUri(): string
	{
		if (!isset($_SERVER['REQUEST_URI'])) {
			return $_SERVER['PHP_SELF'];
		} else {
			return $_SERVER['REQUEST_URI'];
		}
	}

	/**
	 * Returns the current URL.
	 *
	 * @return string
	 */
	public function getCurrentUrl(): string
	{
		$requestUri = $this->getRequestUri();

		$secure = empty($_SERVER['HTTPS'])
            ? '' : (($_SERVER['HTTPS'] === 'on') ? 's' : '');
		$protocol = strtolower($_SERVER['SERVER_PROTOCOL']);
		$protocol = substr(
            $protocol, 0,
            strpos($protocol, self::URL_SEGMENT_SEPARATOR)
        ) . $secure;
		$port = in_array($_SERVER['SERVER_PORT'], ['80', '443'])
            ? '' : (':'.$_SERVER['SERVER_PORT']);
		return $protocol . '://' . $_SERVER['SERVER_NAME']
            . $port . $requestUri;
	}

	/**
	 * Redirects the browser to the specified URL.
	 *
	 * @param string $url URL to be redirected to.
	 * @param bool $terminate whether to terminate the current application
	 * @param int $statusCode the HTTP status code. Defaults to 302.
	 */
	public function redirect(
        string $url,
        bool $terminate = true,
        int $statusCode = 302
    ) {
        if(!headers_sent()) {
            header('Location: ' . $url, true, $statusCode);
        }

		if ($terminate) {
			exit(0);
        }
	}

	/**
	 * Terminates a request with an error response.
	 *
	 * @param int $statusCode the HTTP status code. Defaults to 400.
	 */
	public function abort(int $statusCode = 400)
	{
		while (ob_get_level()) {
			ob_end_clean();
		}

		if (array_key_exists($statusCode, self::$messages) && !headers_sent()) {
			$statusResponse = $statusCode . ' ' . self::$messages[$statusCode];
			header('HTTP/1.0 ' . $statusResponse);
		}

		$errorPage = $this->errorPagesDir . $statusCode
            . self::ERROR_FILE_EXTENSION;

        if (is_readable($errorPage)) {
            include $errorPage;
        }

        exit(1);
	}
}

// -- End of file
