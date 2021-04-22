<?php

declare(strict_types=1);

namespace Jonquil\Foundation;

use InvalidArgumentException;

/**
 * Class View
 * @package Jonquil\Foundation
 */
class View
{
    const DEFAULT_FILE_ORDER = 0;
    const DEFAULT_SCRIPT_POSITION = 'body';
    const DEFAULT_SNIPPET_POSITION = 'end';

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'file_not_found'            => 'The file "%s" cannot be found',
        'file_not_accessible'       => 'The file "%s" is not accessible',
        'directory_not_accessible'  => 'The directory "%s" is not accessible',
    ];

    /**
     * @var array
     */
    protected $variables;

    /**
     * @var array
     */
    protected $snippets;

    /**
     * @var array
     */
    protected $files;

    /**
     * @var string
     */
    protected $templateDir;

    /**
     * Initializes the class properties.
     *
     * @param string $templateDir A directory for templates
     */
    public function __construct(string $templateDir = '')
    {
        if (!is_readable($templateDir)) {
            throw new InvalidArgumentException(
                sprintf(self::$errors['directory_not_accessible'], $templateDir)
            );
        }
        $this->variables = [];
        $this->snippets = [];
        $this->files = [
            'css' => [],
            'js' => [],
        ];
        $this->templateDir = $templateDir;
    }

	/**
	 * Renders a view template and returns its output.
	 *
	 * @param string $template A template file path
	 * @return string
	 */
    public function render(string $template): string
    {
        $template = $this->resolveFilePath($template);

		// Capture the view output
		ob_start();

		// Include template file
		include $template;

        // Return the output and close the buffer
		return ob_get_clean();
    }

    /**
     * Includes a view template.
     *
     * @param string $template
     */
    public function include(string $template)
    {
        $template = $this->resolveFilePath($template);

        // Include template file
        include $template;
    }

	/**
	 * Returns a variable from the view.
	 *
	 * @param string $key A variable key
     * @param mixed $default A default value
	 * @return mixed
	 */
    public function get(string $key, $default = null)
    {
        return $this->exists($key) ? $this->variables[$key] : $default;
    }

	/**
	 * Sets a variable for the view.
	 *
	 * @param string $key A variable key
	 * @param mixed $value A value to be assigned
	 */
    public function set(string $key, $value)
    {
        $this->variables[$key] = $value;
    }

	/**
	 * Removes a variable from the view.
	 *
	 * @param string $key A variable key
	 */
    public function remove(string $key)
    {
        if ($this->exists($key)) {
            unset($this->variables[$key]);
        }
    }

	/**
	 * Checks whether a view variable exists
	 *
	 * @param string $key A variable key
	 * @return bool
	 */
    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->variables);
    }

    /**
     * @param string $url
     * @param array $attributes
     */
    public function addStyleSheet(string $url, array $attributes = [])
    {
        $this->addResourceFile('css', $url, $attributes);
    }

    /**
     * @param array $urls
     * @param array $attributes
     */
    public function addStyleSheets(array $urls, array $attributes = [])
    {
        foreach ($urls as $url) {
            $this->addStyleSheet($url, $attributes);
        }
    }

    public function insertStyleSheets()
    {
        foreach ($this->files['css'] as $file) {
            $tag = 'href="' . $file['url'] . '"';
            if (isset($file['media'])) {
                $tag .= ' media="' . $file['media'] . '"';
            }
            echo '<link rel="stylesheet" ' . $tag . ' />' . PHP_EOL;
        }
    }

    /**
     * @param string $url
     * @param array $attributes
     */
    public function addScript(string $url, array $attributes = [])
    {
        if (!isset($attributes['position'])) {
            $attributes['position'] = self::DEFAULT_SCRIPT_POSITION;
        }
        $this->addResourceFile('js', $url, $attributes);
    }

    /**
     * @param array $urls
     * @param array $attributes
     */
    public function addScripts(array $urls, array $attributes = [])
    {
        foreach ($urls as $url) {
            $this->addScript($url, $attributes);
        }
    }

    /**
     * @param string $position
     */
    public function insertScripts(string $position)
    {
        foreach ($this->files['js'] as $file) {
            if ($file['position'] !== $position) {
                continue;
            }
            $tag = 'src="' . $file['url'] . '"';
            if (isset($file['async']) && ($file['async'] === true)) {
                $tag .= ' async';
            }
            if (isset($file['defer']) && ($file['defer'] === true)) {
                $tag .= ' defer';
            }
            echo '<script ' . $tag . '></script>' . PHP_EOL;
        }
    }

    /**
     * @param string $content
     * @param string $position
     */
    public function addSnippet(string $content, string $position = '')
    {
        $content = trim($content);
        if (empty($content)) {
            return;
        }
        if (empty($position)) {
            $position = self::DEFAULT_SNIPPET_POSITION;
        }
        $this->snippets[$position][] = $content;
    }

    /**
     * @param string $position
     */
    public function insertSnippets(string $position)
    {
        if (!isset($this->snippets[$position])) {
            return;
        }
        foreach ($this->snippets[$position] as $snippet) {
            echo PHP_EOL . $snippet . PHP_EOL;
        }
    }

    /**
     * Returns a variable from the view.
     *
     * @param string $key A variable key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * Sets a variable for the view.
     *
     * @param string $key A variable key
     * @param mixed $value A value to be assigned
     */
    public function __set(string $key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Removes a variable from the view.
     *
     * @param string $key A variable key
     */
    public function __unset(string $key)
    {
        $this->remove($key);
    }

    /**
     * Checks whether a view variable exists
     *
     * @param string $key A variable key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->exists($key);
    }

    /**
     * @param string $type
     * @param string $url
     * @param array $attributes
     */
    protected function addResourceFile(
        string $type,
        string $url,
        array $attributes = []
    ) {
        if (!isset($this->files[$type]) || empty($url)) {
            return;
        }
        $order = isset($attributes['order'])
            ? (int) $attributes['order'] : self::DEFAULT_FILE_ORDER;
        $attributes['order'] = $order;
        $attributes['url'] = $url;
        $fileCount = count($this->files[$type]);
        $index = $fileCount - 1;
        if (($fileCount === 0)
            || ($this->files[$type][$index]['order'] <= $order)) {
            $this->files[$type][] = $attributes;
        } else {
            while ($index > 0) {
                if ($this->files[$type][$index - 1]['order'] <= $order) {
                    break;
                }
                $index--;
            }
            array_splice($this->files[$type], $index, 0, [$attributes]);
        }
    }

    /**
     * Normalizes a template file path and checks whether the template file
     * exists and is accessible.
     *
     * @param string $file The template file path
     * @return string
     */
    protected function resolveFilePath(string $file): string
    {
        if (substr($file, 0, 1) !== '/') {
            $file = $this->templateDir . $file;
        }

        if (!is_file($file)) {
            throw new InvalidArgumentException(
                sprintf(self::$errors['file_not_found'], $file)
            );
        } elseif (!is_readable($file)) {
            throw new InvalidArgumentException(
                sprintf(self::$errors['file_not_accessible'], $file)
            );
        }

        return $file;
    }
}

// -- End of file
