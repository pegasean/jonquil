<?php

declare(strict_types=1);

namespace Jonquil\Foundation;

/**
 * Class Response
 * @package Jonquil\Foundation
 */
class Response
{
    /**
     * @var array Error messages
     */
    protected static $errors = [
        'media_types_not_found'  => 'No media types for ".%s" files are defined',
    ];

	/**
	 * @var array
	 */
    protected $mediaTypes;

    /**
     * Initializes the class properties.
     *
     * @param array $mediaTypes
     */
    public function __construct(array $mediaTypes)
    {
        $this->mediaTypes = $mediaTypes;
    }

    /**
     * Returns an array of media types corresponding to a file extension.
     *
     * @param string $extension A file extension
     * @return array
     */
    public function getMediaTypes(string $extension): array
    {
        return array_key_exists($extension, $this->mediaTypes)
            ? $this->mediaTypes[$extension] : [];
    }

    /**
     * Returns a content type corresponding to a file extension.
     *
     * @param string $extension A file extension
	 * @param string $default A default return value
	 * @return string
     */
    public function getContentType(
        string $extension,
        string $default = ''
    ): string {
		$mediaTypes = $this->getMediaTypes($extension);
		return empty($mediaTypes) ? $default : array_shift($mediaTypes);
    }

    /**
     * Sets a content type header.
     *
     * @param string $extension A file extension
     * @param string $charset The charset of the content (default: utf-8)
     */
    public function setContentType(string $extension, string $charset = 'utf-8')
    {
        if (headers_sent()) {
            return;
        }

        $contentType = $this->getContentType($extension);

        if (!empty($contentType)) {
            header('Content-Type: ' . $contentType . '; charset=' . $charset);
        }
        else {
            throw new \RuntimeException(
                sprintf(self::$errors['media_types_not_found'], $extension)
            );
        }
    }

	/**
	 * Sends a file to user.
	 *
	 * @param string $fileName A file name
	 * @param string $content Content to be send.
	 * @param string $contentType The media type of the content.
	 * @param string $charset The charset of the content. Default: utf-8.
	 * @param bool $terminate Whether to terminate the current application
	 */
	public function sendFile(
        string $fileName,
        string $content,
        string $contentType,
        string $charset = 'utf-8',
        bool $terminate = true
    ) {
        if (headers_sent()) {
            return;
        }

		header('Pragma: public');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Content-Type: ' . $contentType . '; charset=' . $charset);
		header('Content-Disposition: attachment; filename="' . $fileName . '"');
		header('Content-Transfer-Encoding: binary');

		if (ob_get_length() === false) {
			header('Content-Length: ' . mb_strlen($content, '8bit'));
        }

		echo $content;

		if ($terminate) {
			exit(0);
        }
	}
}

// -- End of file
